<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\KhachHang;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * MemberPointService
 *
 * Nhiệm vụ:
 *  - Khi đơn chuyển "đã thanh toán" (trang_thai_thanh_toan = 2), tạo 1 "biến động điểm"
 *    tương ứng với đơn đó (idempotent: 1 đơn -> 1 sự kiện).
 *  - Tăng doanh_thu_tich_luy cho khách theo số tiền dùng để tính điểm.
 *  - Quy đổi điểm: 1 điểm = 1.000 VND (có thể cấu hình).
 *  - KHÔNG gửi ZNS tại đây; chỉ ghi sự kiện để UI chủ động gửi sau.
 *
 * ENV cấu hình (tuỳ chọn):
 *  - POINT_VND_RATE=1000                    (mặc định 1000 VND / 1 điểm)
 *  - POINT_REVENUE_FIELD=so_tien_da_thanh_toan | tong_tien_can_thanh_toan (mặc định: so_tien_da_thanh_toan)
 *  - POINT_UPDATE_TIER=true|false           (mặc định true: tự cập nhật hạng theo nguong_doanh_thu)
 */
class MemberPointService
{
    /**
     * Ghi nhận "biến động điểm" cho đơn hàng đã thanh toán.
     *
     * @param  int  $donHangId
     * @return array  Kết quả tóm tắt để logging/hiển thị (không dùng cho FE trực tiếp)
     */
    public function recordPaidOrder(int $donHangId): array
    {
        // Đọc config
        $rateVnd        = (int) (env('POINT_VND_RATE', 1000)); // 1 điểm = 1000 VND
        $revenueField   = (string) (env('POINT_REVENUE_FIELD', 'so_tien_da_thanh_toan'));
        $updateTier     = filter_var(env('POINT_UPDATE_TIER', true), FILTER_VALIDATE_BOOLEAN);

        // Lấy đơn
        /** @var DonHang $don */
        $don = DonHang::query()->with('khachHang')->find($donHangId);
        if (!$don) {
            return ['ok' => false, 'reason' => 'ORDER_NOT_FOUND', 'don_hang_id' => $donHangId];
        }

        // Chỉ xử lý nếu đơn đã thanh toán (2)
        // Nếu dự án của anh dùng giá trị khác, đổi tại đây hoặc map về enum.
        $isPaid = ((int) ($don->trang_thai_thanh_toan ?? 0)) === 2;
        if (!$isPaid) {
            return ['ok' => false, 'reason' => 'ORDER_NOT_PAID', 'don_hang_id' => $donHangId];
        }

        // Lấy khách
        /** @var KhachHang $kh */
        $kh = $don->khachHang;
        if (!$kh) {
            return ['ok' => false, 'reason' => 'CUSTOMER_NOT_FOUND', 'don_hang_id' => $donHangId];
        }

        // Xác định "giá trị dùng tính điểm"
        $price = $this->extractRevenueForPoint($don, $revenueField);
        if ($price <= 0) {
            // Không cộng nếu giá trị tính điểm không dương
            return [
                'ok'          => false,
                'reason'      => 'PRICE_NOT_POSITIVE',
                'don_hang_id' => $donHangId,
                'price'       => $price,
            ];
        }

        // Idempotency cấp DB: 1 đơn -> 1 sự kiện (UNIQUE(don_hang_id))
        // Dùng transaction + khoá bản ghi KH để tránh race-condition.
        $result = DB::transaction(function () use ($don, $kh, $price, $rateVnd, $updateTier) {

            // Kiểm tra đã tồn tại sự kiện cho đơn này chưa
            $exists = DB::table('khach_hang_point_events')
                ->where('don_hang_id', $don->id)
                ->exists();

            if ($exists) {
                // Đã tạo trước đó -> idempotent
                return [
                    'ok'            => true,
                    'idempotent'    => true,
                    'don_hang_id'   => $don->id,
                    'order_code'    => (string) ($don->ma_don_hang ?? $don->id),
                    'skipped'       => 'EVENT_ALREADY_EXISTS',
                ];
            }

            // Khoá dòng khách hàng để cộng doanh thu an toàn
            $khLocked = KhachHang::query()->whereKey($kh->id)->lockForUpdate()->first();
            $oldRevenue = (int) ($khLocked->doanh_thu_tich_luy ?? 0);
            $newRevenue = $oldRevenue + (int) $price;
            $deltaRevenue = $newRevenue - $oldRevenue;

            // Quy đổi điểm theo tổng revenue (tránh trôi do round tại từng đơn)
            $oldPoints = (int) floor($oldRevenue / max(1, $rateVnd));
            $newPoints = (int) floor($newRevenue / max(1, $rateVnd));
            $deltaPoints = $newPoints - $oldPoints;

            // Cập nhật doanh thu tích luỹ cho khách
            KhachHang::query()
                ->whereKey($khLocked->id)
                ->update([
                    'doanh_thu_tich_luy' => $newRevenue,
                    'nguoi_cap_nhat'     => auth()->user()->name ?? 'system',
                    'updated_at'         => now(),
                ]);

            // (Tuỳ chọn) cập nhật hạng theo bảng loai_khach_hangs.nguong_doanh_thu
            $tierChangedTo = null;
            if ($updateTier) {
                $tierChangedTo = $this->updateCustomerTierIfNeeded($khLocked->id, $newRevenue);
            }

// Cache dữ liệu đơn tại thời điểm sự kiện
$donFresh   = DonHang::query()
    ->select('id','ma_don_hang','ngay_tao_don_hang','nguoi_nhan_thoi_gian','created_at','updated_at')
    ->find($don->id); // luôn đọc lại sau khi đã save/mã đã sinh

$orderCode  = $donFresh->ma_don_hang
    ?: ('DH' . str_pad((string)$donFresh->id, 5, '0', STR_PAD_LEFT)); // fallback an toàn

// Ngày mua hàng: ưu tiên ngay_tao_don_hang, fallback created_at/nguoi_nhan_thoi_gian/updated_at
$orderDate  = $donFresh->ngay_tao_don_hang
    ?? $donFresh->created_at
    ?? $donFresh->nguoi_nhan_thoi_gian
    ?? $donFresh->updated_at
    ?? Carbon::now();


            // Tạo sự kiện (pending để UI chủ động gửi ZNS)
            $eventId = DB::table('khach_hang_point_events')->insertGetId([
                'khach_hang_id'   => $khLocked->id,
                'don_hang_id'     => $don->id,
                'order_code'      => $orderCode,
                'order_date'      => $orderDate,
                'price'           => $price,

                'old_revenue'     => $oldRevenue,
                'new_revenue'     => $newRevenue,
                'delta_revenue'   => $deltaRevenue,

                'old_points'      => $oldPoints,
                'new_points'      => $newPoints,
                'delta_points'    => $deltaPoints,

                'note'            => null,

                'zns_status'      => 'pending',
                'zns_sent_at'     => null,
                'zns_template_id' => null,
                'zns_error_code'  => null,
                'zns_error_message' => null,

                'nguoi_tao'       => auth()->user()->name ?? 'system',
                'nguoi_cap_nhat'  => auth()->user()->name ?? 'system',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return [
                'ok'              => true,
                'idempotent'      => false,
                'event_id'        => $eventId,
                'don_hang_id'     => $don->id,
                'khach_hang_id'   => $khLocked->id,
                'order_code'      => $orderCode,
                'order_date'      => $orderDate->format('Y-m-d H:i:s'),
                'price'           => $price,
                'old_revenue'     => $oldRevenue,
                'new_revenue'     => $newRevenue,
                'delta_revenue'   => $deltaRevenue,
                'old_points'      => $oldPoints,
                'new_points'      => $newPoints,
                'delta_points'    => $deltaPoints,
                'tier_changed_to' => $tierChangedTo,
            ];
        });

        return $result;
    }

    /**
     * HOÀN (TRỪ) điểm khi đơn rơi khỏi trạng thái "đã thanh toán".
     * - Idempotent: nếu đã có event âm cho đơn -> bỏ qua.
     * - Nếu chưa có event dương trước đó -> trả về EVENT_NOT_FOUND.
     *
     * @param  int  $donHangId
     * @return array
     */
    public function reversePaidOrder(int $donHangId): array
    {
        /** @var DonHang $don */
        $don = DonHang::query()->with('khachHang')->find($donHangId);
        if (!$don) {
            return ['ok' => false, 'reason' => 'ORDER_NOT_FOUND', 'don_hang_id' => $donHangId];
        }

        /** @var KhachHang|null $kh */
        $kh = $don->khachHang;
        if (!$kh) {
            return ['ok' => false, 'reason' => 'CUSTOMER_NOT_FOUND', 'don_hang_id' => $donHangId];
        }

        // Tìm event dương gắn với đơn (ưu tiên mới nhất)
        $pos = DB::table('khach_hang_point_events')
            ->where('don_hang_id', $don->id)
            ->where('delta_points', '>', 0)
            ->orderByDesc('id')
            ->first();

        if (!$pos) {
            return ['ok' => false, 'reason' => 'EVENT_NOT_FOUND', 'don_hang_id' => $donHangId];
        }

        // Idempotent: đã có event âm cho đơn này chưa?
        $hasNeg = DB::table('khach_hang_point_events')
            ->where('don_hang_id', $don->id)
            ->where('delta_points', '<', 0)
            ->exists();

        if ($hasNeg) {
            return [
                'ok'          => true,
                'idempotent'  => true,
                'don_hang_id' => $don->id,
                'skipped'     => 'REVERSAL_ALREADY_EXISTS',
            ];
        }

        // Thực hiện hoàn điểm
        $rateVnd = (int) (env('POINT_VND_RATE', 1000)); // dùng cùng base với event dương

        $res = DB::transaction(function () use ($don, $kh, $pos, $rateVnd) {

            // Khóa KH để trừ doanh thu an toàn
            $khLocked   = KhachHang::query()->whereKey($kh->id)->lockForUpdate()->first();

            $pricePos   = (int) ($pos->price ?? 0);          // VND đã cộng ở event dương
            $pointsPos  = (int) ($pos->delta_points ?? 0);   // điểm đã cộng

            $oldRevenue = (int) ($khLocked->doanh_thu_tich_luy ?? 0);
            $newRevenue = max(0, $oldRevenue - $pricePos);
            $deltaRevenue = $newRevenue - $oldRevenue;       // âm

            // Recompute points from total revenue (giữ cùng quy tắc quy đổi)
            $oldPoints = (int) floor($oldRevenue / max(1, $rateVnd));
            $newPoints = (int) floor($newRevenue / max(1, $rateVnd));
            $deltaPoints = $newPoints - $oldPoints;          // âm

            // Nếu vì làm tròn mà |deltaPoints| != pointsPos, ta vẫn dùng deltaPoints nhất quán theo tổng doanh thu.
            // (Không ép cứng = -pointsPos để tránh lệch tích luỹ về sau.)

            // Trừ doanh thu tích luỹ của KH
            KhachHang::query()
                ->whereKey($khLocked->id)
                ->update([
                    'doanh_thu_tich_luy' => $newRevenue,
                    'nguoi_cap_nhat'     => auth()->user()->name ?? 'system',
                    'updated_at'         => now(),
                ]);

            // Ghi event âm (reversal)
// Ghi event âm (reversal)
$donFresh  = DonHang::query()
    ->select('id','ma_don_hang','nguoi_nhan_thoi_gian','updated_at')
    ->find($don->id);
$orderCode = $donFresh->ma_don_hang
    ?: ('DH' . str_pad((string)$donFresh->id, 5, '0', STR_PAD_LEFT));
$now = Carbon::now();

            $eventId = DB::table('khach_hang_point_events')->insertGetId([
                'khach_hang_id'   => $khLocked->id,
                'don_hang_id'     => $don->id,
                'order_code'      => $orderCode,
                'order_date'      => $now,                  // reversal tại thời điểm hoàn
                'price'           => -$pricePos,            // âm

                'old_revenue'     => $oldRevenue,
                'new_revenue'     => $newRevenue,
                'delta_revenue'   => $deltaRevenue,         // âm

                'old_points'      => $oldPoints,
                'new_points'      => $newPoints,
                'delta_points'    => $deltaPoints,          // âm

                'note'            => 'reversal for ' . $orderCode,

                'zns_status'      => 'pending',             // tuỳ chính sách: thường không gửi ZNS tự động
                'zns_sent_at'     => null,
                'zns_template_id' => null,
                'zns_error_code'  => null,
                'zns_error_message'=> null,

                'nguoi_tao'       => auth()->user()->name ?? 'system',
                'nguoi_cap_nhat'  => auth()->user()->name ?? 'system',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            return [
                'ok'              => true,
                'idempotent'      => false,
                'event_id'        => $eventId,
                'don_hang_id'     => $don->id,
                'khach_hang_id'   => $khLocked->id,
                'order_code'      => $orderCode,
                'order_date'      => $now->format('Y-m-d H:i:s'),
                'reversed_price'  => -$pricePos,
                'old_revenue'     => $oldRevenue,
                'new_revenue'     => $newRevenue,
                'delta_revenue'   => $deltaRevenue,
                'old_points'      => $oldPoints,
                'new_points'      => $newPoints,
                'delta_points'    => $deltaPoints,
            ];
        });

        return $res;
    }

    /**
     * ⭐ MỚI: Đồng bộ điểm theo trạng thái & số tiền hiện tại của ĐƠN.
     * - So sánh "doanh thu mục tiêu" (theo loai_thanh_toan) với "tổng price đã ghi" cho chính đơn.
     * - Nếu chênh ≠ 0: tạo 1 event bù (±), cập nhật doanh_thu_tich_luy + delta_points theo tổng tích luỹ.
     * - Không đụng các hàm cũ (recordPaidOrder/reversePaidOrder).
     */
    /**
     * ⭐ MỚI: Đồng bộ điểm theo trạng thái & số tiền hiện tại của ĐƠN.
     * - So sánh "doanh thu mục tiêu" (theo loai_thanh_toan + trạng_thái_thanh_toán) 
     *   với "tổng price đã ghi" cho chính đơn.
     * - Nếu chênh ≠ 0: tạo 1 event bù (±), cập nhật doanh_thu_tich_luy + delta_points theo tổng tích luỹ.
     * - Không đụng các hàm cũ (recordPaidOrder/reversePaidOrder).
     */
    public function syncByOrder(int $donHangId): array
    {
        $rateVnd    = (int) (env('POINT_VND_RATE', 1000));
        $updateTier = filter_var(env('POINT_UPDATE_TIER', true), FILTER_VALIDATE_BOOLEAN);

        /** @var DonHang|null $don */
        $don = DonHang::query()->with('khachHang')->find($donHangId);
        if (!$don || !$don->khachHang) {
            return ['ok' => false, 'reason' => 'ORDER_OR_CUSTOMER_NOT_FOUND', 'don_hang_id' => $donHangId];
        }

        // ✅ XÁC ĐỊNH ĐƠN ĐANG ĐƯỢC COI LÀ "ĐÃ THANH TOÁN" (paid)
        // - ĐÃ TT nếu: trang_thai_thanh_toan = 1 (hoàn thành) HOẶC loai_thanh_toan = 2 (full)
        // - Nếu KHÔNG paid: ta vẫn cho chạy, nhưng ép targetRevenue = 0 để TỰ ĐỘNG HOÀN điểm nếu trước đó đã cộng.
        $paid = ((int)($don->trang_thai_thanh_toan ?? 0) === 1)
             || ((int)($don->loai_thanh_toan ?? 0) === 2);

        return DB::transaction(function () use ($don, $rateVnd, $updateTier, $paid) {
            // 1) Tính "doanh thu mục tiêu" gốc theo LOẠI THANH TOÁN hiện tại
            //    2: toàn bộ  -> dùng tong_tien_thanh_toan (fallback: tong_tien_can_thanh_toan)
            //    1: một phần -> so_tien_da_thanh_toan
            //    0: default  -> 0
            $targetRevenue = match ((int) ($don->loai_thanh_toan ?? 0)) {
                2       => (int) ($don->tong_tien_thanh_toan ?? $don->tong_tien_can_thanh_toan ?? 0),
                1       => (int) ($don->so_tien_da_thanh_toan ?? 0),
                default => 0,
            };

            // Không cho targetRevenue âm
            $targetRevenue = max(0, $targetRevenue);

            // ❗ Nếu đơn HIỆN TẠI KHÔNG CÒN ĐƯỢC COI LÀ ĐÃ THANH TOÁN
            //    → ép targetRevenue = 0 để nó tự trừ hết phần đã cộng trước đó (nếu có)
            if (!$paid) {
                $targetRevenue = 0;
            }

            // 2) Tổng price đã ghi cho RIÊNG order này (có thể âm/dương)
            $existingRevenue = (int) DB::table('khach_hang_point_events')
                ->where('don_hang_id', $don->id)
                ->sum('price');

            $deltaRevenue = $targetRevenue - $existingRevenue;
            if ($deltaRevenue === 0) {
                return [
                    'ok'               => true,
                    'idempotent'       => true,
                    'skipped'          => 'NO_CHANGE',
                    'don_hang_id'      => $don->id,
                    'target_revenue'   => $targetRevenue,
                    'existing_revenue' => $existingRevenue,
                    'delta_revenue'    => 0,
                ];
            }

            // 3) Khóa KH và tính delta_points theo TỔNG doanh thu tích luỹ
            /** @var KhachHang $khLocked */
            $khLocked = KhachHang::query()->whereKey($don->khach_hang_id)->lockForUpdate()->first();

            $oldRevenueAll = (int) ($khLocked->doanh_thu_tich_luy ?? 0);
            $newRevenueAll = max(0, $oldRevenueAll + $deltaRevenue);

            $oldPointsAll = (int) floor($oldRevenueAll / max(1, $rateVnd));
            $newPointsAll = (int) floor($newRevenueAll / max(1, $rateVnd));
            $deltaPoints  = $newPointsAll - $oldPointsAll; // có thể âm/dương/0

            // 4) Cập nhật doanh thu tích luỹ KH
            KhachHang::query()
                ->whereKey($khLocked->id)
                ->update([
                    'doanh_thu_tich_luy' => $newRevenueAll,
                    'nguoi_cap_nhat'     => auth()->user()->name ?? 'system',
                    'updated_at'         => now(),
                ]);

            // (Tuỳ chọn) cập nhật hạng sau khi doanh thu thay đổi
            $tierChangedTo = null;
            if ($updateTier) {
                $tierChangedTo = $this->updateCustomerTierIfNeeded($khLocked->id, $newRevenueAll);
            }

            // 5) Ghi 1 event bù cho ORDER này
          $donFresh  = DonHang::query()
    ->select('id','ma_don_hang','ngay_tao_don_hang','nguoi_nhan_thoi_gian','created_at','updated_at')
    ->find($don->id);
$orderCode = $donFresh->ma_don_hang
    ?: ('DH' . str_pad((string)$donFresh->id, 5, '0', STR_PAD_LEFT));

$now = Carbon::now();
$eventId = DB::table('khach_hang_point_events')->insertGetId([
    'khach_hang_id'   => $khLocked->id,
    'don_hang_id'     => $don->id,
    'order_code'      => $orderCode,
    // Ngày mua hàng: ưu tiên ngay_tao_don_hang
    'order_date'      => $donFresh->ngay_tao_don_hang
                          ?? $donFresh->created_at
                          ?? $donFresh->nguoi_nhan_thoi_gian
                          ?? $donFresh->updated_at
                          ?? $now,

                'price'           => $deltaRevenue,          // bù doanh thu cho đơn (±)
                'old_revenue'     => $oldRevenueAll,
                'new_revenue'     => $newRevenueAll,
                'delta_revenue'   => $deltaRevenue,

                'old_points'      => $oldPointsAll,
                'new_points'      => $newPointsAll,
                'delta_points'    => $deltaPoints,           // có thể âm/dương

                'note'            => 'sync-by-order',

                'zns_status'      => 'pending',
                'zns_sent_at'     => null,
                'zns_template_id' => null,
                'zns_error_code'  => null,
                'zns_error_message'=> null,

                'nguoi_tao'       => auth()->user()->name ?? 'system',
                'nguoi_cap_nhat'  => auth()->user()->name ?? 'system',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);

            return [
                'ok'               => true,
                'idempotent'       => false,
                'event_id'         => $eventId,
                'don_hang_id'      => $don->id,
                'khach_hang_id'    => $khLocked->id,
                'target_revenue'   => $targetRevenue,
                'existing_revenue' => $existingRevenue,
                'delta_revenue'    => $deltaRevenue,
                'delta_points'     => $deltaPoints,
                'new_total_points' => $newPointsAll,
                'tier_changed_to'  => $tierChangedTo,
            ];
        });
    }


    /**
     * Lấy giá trị doanh thu dùng để tính điểm từ đơn hàng.
     * Ưu tiên theo cấu hình: so_tien_da_thanh_toan | tong_tien_can_thanh_toan
     */
    private function extractRevenueForPoint(DonHang $don, string $preferredField): int
    {
        $preferredField = $preferredField ?: 'so_tien_da_thanh_toan';

        $value = null;

        if ($preferredField === 'so_tien_da_thanh_toan') {
            $value = $don->so_tien_da_thanh_toan ?? null;
            if ($value === null) {
                // Fallback an toàn nếu dự án chưa set trường này
                $value = $don->tong_tien_can_thanh_toan ?? 0;
            }
        } else { // tong_tien_can_thanh_toan
            $value = $don->tong_tien_can_thanh_toan ?? null;
            if ($value === null) {
                $value = $don->so_tien_da_thanh_toan ?? 0;
            }
        }

        return (int) max(0, (int) $value);
    }

    /**
     * Cập nhật hạng khách hàng theo nguong_doanh_thu (nếu vượt ngưỡng).
     * Trả về tên hạng mới khi có thay đổi, hoặc null nếu giữ nguyên.
     */
    private function updateCustomerTierIfNeeded(int $khachHangId, int $newRevenue): ?string
    {
        // Tìm hạng phù hợp có nguong_doanh_thu <= newRevenue, ưu tiên ngưỡng cao nhất
        $tier = DB::table('loai_khach_hangs')
            ->select('id', 'ten_loai_khach_hang', 'nguong_doanh_thu')
            ->where('trang_thai', 1)
            ->where('nguong_doanh_thu', '<=', $newRevenue)
            ->orderByDesc('nguong_doanh_thu')
            ->first();

        if (!$tier) {
            // Không có hạng nào phù hợp (giữ nguyên)
            return null;
        }

        // Đọc hạng hiện tại của khách (nếu có)
        $currentTierId = DB::table('khach_hangs')
            ->where('id', $khachHangId)
            ->value('loai_khach_hang_id');

        if ((int) $currentTierId === (int) $tier->id) {
            // Không thay đổi
            return null;
        }

        // Cập nhật hạng cho khách
        DB::table('khach_hangs')
            ->where('id', $khachHangId)
            ->update([
                'loai_khach_hang_id' => $tier->id,
                'nguoi_cap_nhat'     => auth()->user()->name ?? 'system',
                'updated_at'         => now(),
            ]);

        return (string) $tier->ten_loai_khach_hang;
    }
}
