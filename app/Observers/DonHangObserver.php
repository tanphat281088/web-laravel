<?php

namespace App\Observers;

use App\Models\DonHang;
use App\Modules\PhieuThu\AutoPhieuThuService;
use App\Services\MemberPointService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DonHangObserver
{
    public function created(DonHang $donHang): void
    {
        // 1) Cân phiếu thu tự động như hiện tại
        app(AutoPhieuThuService::class)->syncAutoReceiptForOrder($donHang);

        // 2) Đảm bảo lấy trạng thái/mã đơn MỚI nhất sau khi cân phiếu
        $donHang->refresh();

        // 3) Nếu đơn mới đã = 2 → ghi điểm dương (idempotent, giữ nguyên hành vi)
        try {
            $now = (int) ($donHang->trang_thai_thanh_toan ?? 0);
            if ($now === 2 && $donHang->khach_hang_id) {
                // Gọi trực tiếp để chắc chắn chạy (không phụ thuộc afterCommit)
                app(MemberPointService::class)->recordPaidOrder((int) $donHang->id);
            }
        } catch (\Throwable $e) {
            Log::error('[POINT_EVENT][created-ex] ' . $e->getMessage(), ['don_id' => $donHang->id]);
        }

        // 4) Luôn đồng bộ theo delta (±) để khớp tuyệt đối với loại/tiền hiện tại
        if ($donHang->khach_hang_id) {
            try {
                app(MemberPointService::class)->syncByOrder((int) $donHang->id);
            } catch (\Throwable $e) {
                Log::error('[POINT_SYNC][created] ' . $e->getMessage(), ['don_id' => $donHang->id]);
            }
        }
    }

    public function updated(DonHang $donHang): void
    {
        try {
            // 1) Snapshot TRƯỚC khi auto-sync có thể thay đổi trạng thái
            $was = (int) ($donHang->getOriginal('trang_thai_thanh_toan') ?? 0);

            // Theo dõi field thanh toán để biết khi nào cần sync-by-order
            $payFields = ['loai_thanh_toan', 'so_tien_da_thanh_toan', 'tong_tien_thanh_toan'];
            $changedPayFields = [];
            foreach ($payFields as $f) {
                if ($donHang->getOriginal($f) !== $donHang->{$f}) {
                    $changedPayFields[] = $f;
                }
            }

            // Nhận diện ca "vừa gán khách hệ thống" (vãng lai -> KH hệ thống)
            $khWas = $donHang->getOriginal('khach_hang_id');
            $khNow = $donHang->khach_hang_id;
            $justLinkedCustomer = (empty($khWas) && !empty($khNow));

            // 2) Cân phiếu thu tự động (giữ nguyên)
            app(AutoPhieuThuService::class)->syncAutoReceiptForOrder($donHang);

            // 3) Lấy trạng thái CUỐI SAU khi cân phiếu
            $now = (int) ($donHang->fresh()->trang_thai_thanh_toan ?? 0);

            // Nếu vẫn chưa có KH hệ thống VÀ cũng không phải ca vừa gán -> bỏ qua phần điểm
            if (!$donHang->khach_hang_id && !$justLinkedCustomer) {
                return;
            }

            // ⭐ NEW: Nếu vừa gán KH hệ thống thì gọi thẳng sync để bù điểm theo delta (không chờ afterCommit)
            if ($justLinkedCustomer) {
                try {
                    app(MemberPointService::class)->syncByOrder((int) $donHang->id);
                } catch (\Throwable $e) {
                    Log::error('[POINT_SYNC][link-customer] ' . $e->getMessage(), ['don_id' => $donHang->id]);
                }
                // Không return: vẫn cho phép các nhánh dưới xử lý nếu có thay đổi trạng thái/tiền
            }

            // 4) Đồng bộ sau commit dựa trên (was, now) + các thay đổi liên quan
            DB::afterCommit(function () use ($donHang, $was, $now, $changedPayFields, $justLinkedCustomer) {
                try {
                    /** @var MemberPointService $svc */
                    $svc = app(MemberPointService::class);

                    // Giữ nguyên hành vi khi qua/vượt mốc "đã thanh toán"
                    if ($was !== 2 && $now === 2) {
                        // Chuyển sang "đã thanh toán" → cộng điểm (idempotent)
                        $svc->recordPaidOrder((int) $donHang->id);
                        // Đồng bộ tiếp theo delta (nếu partial -> full có chênh)
                        $svc->syncByOrder((int) $donHang->id);
                        return;
                    }

                    if ($was === 2 && $now !== 2) {
                        // Rời khỏi "đã thanh toán" → trừ điểm (idempotent)
                        $svc->reversePaidOrder((int) $donHang->id);
                        // Đồng bộ tiếp theo delta (nếu còn chênh)
                        $svc->syncByOrder((int) $donHang->id);
                        return;
                    }

                    // Fallback dữ liệu cũ: nếu hiện không phải 2 nhưng từng cộng điểm mà chưa có reversal
                    if ($now !== 2) {
                        $hasPos = \DB::table('khach_hang_point_events')
                            ->where('don_hang_id', $donHang->id)
                            ->where('delta_points', '>', 0)
                            ->exists();

                        $hasNeg = \DB::table('khach_hang_point_events')
                            ->where('don_hang_id', $donHang->id)
                            ->where('delta_points', '<', 0)
                            ->exists();

                        if ($hasPos && !$hasNeg) {
                            $svc->reversePaidOrder((int) $donHang->id);
                        }
                    }

                    // Nếu có thay đổi ở loại/tiền nhưng không qua mốc 2 -> vẫn cần sync theo delta
                    if (!empty($changedPayFields)) {
                        $svc->syncByOrder((int) $donHang->id);
                    }
                } catch (\Throwable $e) {
                    Log::error('[POINT_EVENT][updated] ' . $e->getMessage(), [
                        'don_id' => $donHang->id, 'was' => $was, 'now' => $now,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('[POINT_EVENT][updated-ex] ' . $e->getMessage(), ['don_id' => $donHang->id]);
        }
    }

    // public function deleted(DonHang $donHang): void {}
}
