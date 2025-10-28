<?php

namespace App\Modules\PhieuThu;

use App\Models\DonHang;
use App\Models\PhieuThu;
use App\Models\ChiTietPhieuThu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutoPhieuThuService
{
    public function syncAutoReceiptForOrder(DonHang $donHang): void
    {
        DB::transaction(function () use ($donHang) {
            $donHang->refresh();

            // 1) Tổng CẦN THU (theo đơn)
            $mustCollect = (float) ($donHang->tong_tien_can_thanh_toan ?? 0);

            // 2) SỐ TIỀN MUỐN THU (TARGET) theo loại thanh toán trên đơn
            $targetPaid = $this->computeTargetPaid($donHang, $mustCollect);

            // 3) Tổng ĐÃ THU (từ phiếu thu gắn với đơn)
            $already = (float) PhieuThu::query()
                ->where('don_hang_id', $donHang->id)
                ->sum('so_tien');

            // 4) Chênh lệch cần điều chỉnh để ĐÃ THU = TARGET
            $delta = round($targetPaid - $already, 2);
            if ($delta == 0.0) {
                $this->updateOrderPaidState($donHang, $already, $mustCollect);
                return;
            }

            $mode = config('thu_chi.auto_receipt_mode', 'adjustment');
            if ($mode === 'adjustment') {
                $this->createAdjustmentReceipt($donHang, $delta);
            } else {
                $this->updateLatestAutoReceipt($donHang, $delta);
            }

            // 5) Sau khi điều chỉnh, cập nhật lại trạng thái thanh toán của đơn
            $newAlready = (float) PhieuThu::query()
                ->where('don_hang_id', $donHang->id)
                ->sum('so_tien');

            $this->updateOrderPaidState($donHang, $newAlready, $mustCollect);
        });
    }

    /**
     * Tính "số tiền muốn thu" từ đơn theo loại thanh toán:
     * 0 = chưa thu gì; 1 = thu một phần (theo so_tien_da_thanh_toan, kẹp <= mustCollect);
     * 2 = thu toàn bộ (mustCollect).
     */
    protected function computeTargetPaid(DonHang $donHang, float $mustCollect): float
    {
        $loai = (int) ($donHang->loai_thanh_toan ?? 0);
        $partial = (float) ($donHang->so_tien_da_thanh_toan ?? 0);

        if ($loai === 0) {
            return 0.0;
        }
        if ($loai === 2) {
            return $mustCollect;
        }
        // loai === 1 → thu một phần
        if ($partial < 0) $partial = 0.0;
        if ($partial > $mustCollect) $partial = $mustCollect;
        return $partial;
    }

    protected function createAdjustmentReceipt(DonHang $donHang, float $delta): void
    {
        $reason = config('thu_chi.adjustment_reason', 'Hiệu chỉnh theo thay đổi đơn hàng');

        $epoch = optional($donHang->updated_at)->timestamp ?? now()->timestamp;
        $idempotentKey = implode(':', [
            'adj', (string) $donHang->id, (string) $epoch, number_format($delta, 2, '.', '')
        ]);

        if (PhieuThu::query()->where('idempotent_key', $idempotentKey)->exists()) {
            return;
        }

        // Đổi số này nếu hệ thống quy ước khác (1=tiền mặt, 2=CK…)
        $defaultPaymentMethod = 0;
        $maPhieuThu = $this->generateReceiptCode();

        $phieu = PhieuThu::create([
            'ma_phieu_thu'           => $maPhieuThu,
            'khach_hang_id'          => $donHang->khach_hang_id ?? null,
            'don_hang_id'            => $donHang->id,
            'so_tien'                => $delta,                 // (+) thu thêm, (-) hoàn/giảm
            'loai_phieu_thu'         => 1,                      // 1: thu theo đơn hàng
            'phuong_thuc_thanh_toan' => $defaultPaymentMethod,
            'ly_do_thu'              => $reason . ' #' . ($donHang->ma_don_hang ?? $donHang->id),
            'idempotent_key'         => $idempotentKey,
            'nguoi_tao'              => Auth::id() ?? 1,
            'nguoi_cap_nhat'         => Auth::id() ?? 1,
            'ngay_thu'               => now(),
        ]);

        // Bảng chi tiết không có cột ghi_chu → chỉ ghi cần thiết
        ChiTietPhieuThu::create([
            'phieu_thu_id' => $phieu->id,
            'don_hang_id'  => $donHang->id,
            'so_tien'      => $delta,
        ]);
    }

    protected function updateLatestAutoReceipt(DonHang $donHang, float $delta): void
    {
        $latest = PhieuThu::query()
            ->where('don_hang_id', $donHang->id)
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            $this->createAdjustmentReceipt($donHang, $delta);
            return;
        }

        $latest->update([
            'so_tien'   => (float) $latest->so_tien + $delta,
            'ly_do_thu' => trim(($latest->ly_do_thu ?? '') . ' | auto update'),
        ]);
    }

    protected function updateOrderPaidState(DonHang $donHang, float $already, float $mustCollect): void
    {
$donHang->so_tien_da_thanh_toan = $already; // đồng bộ từ phiếu thu

$eps = 0.00001; // tránh lỗi làm tròn số thực
if ($already <= 0) {
    $donHang->trang_thai_thanh_toan = 0; // chưa thanh toán
} elseif ($already + $eps < $mustCollect) {
    $donHang->trang_thai_thanh_toan = 1; // thanh toán một phần
} else {
    $donHang->trang_thai_thanh_toan = 2; // đã thanh toán đủ
}

$donHang->saveQuietly();

    }

    protected function generateReceiptCode(): string
    {
        $prefix = 'PT-' . now()->format('Ymd-His') . '-';
        for ($i = 0; $i < 20; $i++) {
            $code = $prefix . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            if (!PhieuThu::query()->where('ma_phieu_thu', $code)->exists()) {
                return $code;
            }
        }
        return $prefix . uniqid();
    }
}
