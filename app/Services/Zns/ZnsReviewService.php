<?php

namespace App\Services\Zns;

use App\Models\ZnsReviewInvite;
use App\Models\DonHang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ZnsReviewService
{
    /**
     * Tạo (hoặc lấy) lời mời review từ 1 đơn đã có khách hàng hệ thống.
     * - Idempotent theo don_hang_id (UNIQUE ở DB).
     * - Chụp snapshot dữ liệu tại thời điểm tạo invite.
     */
    public function upsertInviteFromOrder(int $donHangId): ZnsReviewInvite
    {
        /** @var DonHang|null $don */
        $don = DonHang::query()->with('khachHang')->findOrFail($donHangId);
        $kh  = $don->khachHang ?? throw new \RuntimeException('ORDER_WITHOUT_CUSTOMER');

        $orderCode = $don->ma_don_hang ?: ('DH'.str_pad($don->id, 5, '0', STR_PAD_LEFT));
        $orderDate = $don->nguoi_nhan_thoi_gian ?: ($don->updated_at ?? now());

        return ZnsReviewInvite::query()->firstOrCreate(
            ['don_hang_id' => $don->id],
            [
                'khach_hang_id' => $kh->id,
                'customer_code' => $kh->ma_kh ?: (string)$kh->id,
                'customer_name' => $kh->ten_khach_hang,
                'order_code'    => $orderCode,
                'order_date'    => $orderDate,
                'zns_status'    => 'pending',
                'nguoi_tao'     => auth()->user()->name ?? 'system',
                'nguoi_cap_nhat'=> auth()->user()->name ?? 'system',
            ]
        );
    }

    /**
     * Gửi ZNS cho 1 invite (chỉ khi đang pending).
     * - Build params khớp template review đã duyệt.
     * - Cập nhật trạng thái sent/failed + lưu lỗi provider (nếu có).
     */
    public function sendInvite(ZnsReviewInvite $invite, ?string $templateId = null): object
    {
   if (!in_array($invite->zns_status, ['pending','failed'], true)) {
    return (object)['success'=>false,'error_code'=>'ALREADY_PROCESSED','error_message'=>null];
}

            \Log::info('[REVIEW][SEND] start', [
        'invite_id' => $invite->id,
        'status'    => $invite->zns_status,
        'kh_id'     => $invite->khach_hang_id,
    ]);


        // Lấy số điện thoại khách hàng chuẩn E.164
        $kh = DB::table('khach_hangs')->where('id', $invite->khach_hang_id)->first();
        if (!$kh) return (object)['success'=>false,'error_code'=>'CUSTOMER_NOT_FOUND'];

        $phone = $this->normalizePhoneVN((string)($kh->so_dien_thoai ?? ''));
        if (!$phone) return (object)['success'=>false,'error_code'=>'PHONE_INVALID'];

        $templateId = $templateId ?: (string) env('ZNS_TEMPLATE_REVIEW_ID', '');
        if ($templateId === '') return (object)['success'=>false,'error_code'=>'TEMPLATE_MISSING'];

        $params = [
            'customer_name' => (string) ($invite->customer_name ?? ''),
            'customer_code' => (string) ($invite->customer_code ?? ''),
            'order_code'    => (string) ($invite->order_code ?? ''),
            'order_date'    => $this->formatDate($invite->order_date),
        ];
    \Log::info('[REVIEW][SEND] payload', [
        'invite_id'  => $invite->id,
        'templateId' => $templateId ?: (string) env('ZNS_TEMPLATE_REVIEW_ID', ''),
        'params'     => $params,
    ]);

        /** @var \App\Services\Zns\ZnsProvider $provider */
        $provider = app(\App\Services\Zns\ZnsProvider::class);

        $res = $provider->send($templateId, $phone, $params, [
            'event_id'   => 'review-'.$invite->id,
            'order_code' => $invite->order_code,
        ]);

        $invite->zns_status        = $res->success ? 'sent' : 'failed';
        $invite->zns_sent_at       = now();
        $invite->zns_template_id   = $templateId;
        $invite->zns_error_code    = $res->success ? null : ($res->error_code ?? null);
        $invite->zns_error_message = $res->success ? null : ($res->error_message ?? null);
        $invite->nguoi_cap_nhat    = auth()->user()->name ?? 'system';
        $invite->save();

        return $res;
    }

    /**
     * Gửi hàng loạt theo khoảng ngày (pending).
     * - Tôn trọng rate-limit trong ZnsProvider (ZNS_RATE_LIMIT_PER_SEC).
     * - Trả về thống kê ok/fail + danh sách chi tiết.
     */
    public function bulkSend(?string $from = null, ?string $to = null, int $limit = 200, ?string $templateId = null): array
    {
        $q = ZnsReviewInvite::query()->where('zns_status', 'pending');

        if ($from) $q->where('order_date', '>=', $from.' 00:00:00');
        if ($to)   $q->where('order_date', '<=', $to.' 23:59:59');

        $rows = $q->orderBy('order_date')->limit($limit)->get();

        $ok=0; $fail=0; $details=[];
        foreach ($rows as $inv) {
            $res = $this->sendInvite($inv, $templateId);
            if (!empty($res->success)) $ok++; else $fail++;
            $details[] = [
                'id'         => $inv->id,
                'order_code' => $inv->order_code,
                'ok'         => (bool)$res->success,
                'err'        => $res->error_code ?? null,
            ];
        }
        return compact('ok','fail','details');
    }

    /**
     * Backfill invites: đơn có KH + (đã giao hoặc đã thanh toán) + chưa có invite → tạo.
     */
    public function backfillInvites(?string $from = null, ?string $to = null, int $limit = 500): array
    {
        $q = \DB::table('don_hangs')
->select('id','ma_don_hang','khach_hang_id','trang_thai_don_hang','trang_thai_thanh_toan','loai_thanh_toan','nguoi_nhan_thoi_gian','updated_at')

            ->whereNotNull('khach_hang_id')
   // ĐÃ GIAO (trang_thai_don_hang = 2 OR có nguoi_nhan_thoi_gian)
->where(function($w){
    $w->where('trang_thai_don_hang', 2)
      ->orWhereNotNull('nguoi_nhan_thoi_gian');
})
// VÀ ĐÃ THANH TOÁN (trang_thai_thanh_toan = 1 OR loai_thanh_toan = 2)
->where(function($w){
    $w->where('trang_thai_thanh_toan', 1)
      ->orWhere('loai_thanh_toan', 2);
 });   //  ⬅️  PHẢI CÓ DẤU ; Ở ĐÂY


if ($from || $to) {
    $fromAt = $from ? ($from.' 00:00:00') : null;
    $toAt   = $to   ? ($to.' 23:59:59')   : null;

    $q->where(function($d) use ($fromAt, $toAt) {
        // Có ngày giao → lọc theo nguoi_nhan_thoi_gian
        $d->whereNotNull('nguoi_nhan_thoi_gian');
        if ($fromAt) $d->where('nguoi_nhan_thoi_gian', '>=', $fromAt);
        if ($toAt)   $d->where('nguoi_nhan_thoi_gian', '<=', $toAt);
    })->orWhere(function($d) use ($fromAt, $toAt) {
        // Chưa có ngày giao → fallback updated_at
        $d->whereNull('nguoi_nhan_thoi_gian');
        if ($fromAt) $d->where('updated_at', '>=', $fromAt);
        if ($toAt)   $d->where('updated_at', '<=', $toAt);
    });
}


        // Chỉ đơn chưa có invite
        $q->whereNotExists(function($sub){
            $sub->select(\DB::raw(1))
                ->from('zns_review_invites as r')
                ->whereColumn('r.don_hang_id', 'don_hangs.id');
        });

        $rows = $q->orderByDesc('updated_at')->limit($limit)->get();

        $scanned = 0; $created = 0; $skipped = 0;
        foreach ($rows as $row) {
            $scanned++;
            try {
                $this->upsertInviteFromOrder((int)$row->id);
                $created++;
            } catch (\Throwable $e) {
                $skipped++;
                \Log::warning('[REVIEW][Backfill] upsert failed', ['order_id'=>$row->id, 'err'=>$e->getMessage()]);
            }
        }
        return compact('scanned','created','skipped');
    }



    // ===== Helpers =====
    private function formatDate($dt): string
    {
        try {
            return Carbon::parse($dt, config('app.timezone','Asia/Ho_Chi_Minh'))->format('d/m/Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Chuẩn hoá số VN về 84xxxxxxxxx */
    private function normalizePhoneVN(string $raw): ?string
    {
        $p = preg_replace('/\D+/', '', $raw);
        if ($p === '') return null;
        $p = preg_replace('/^00/', '', $p);
        if (str_starts_with($p, '84')) return $p;
        if (str_starts_with($p, '0') && strlen($p) >= 10) return '84'.substr($p,1);
        if (str_starts_with($p, '9') && strlen($p) === 9)  return '84'.$p;
        return null;
    }
}
