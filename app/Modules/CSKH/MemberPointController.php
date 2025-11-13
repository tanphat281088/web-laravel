<?php

namespace App\Modules\CSKH;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CSKH → Điểm thành viên
 * - Liệt kê "biến động điểm" phát sinh khi đơn chuyển "đã thanh toán"
 * - Gửi ZNS 1 lần cho từng biến động (pending → sent/failed)
 */
class MemberPointController extends Controller
{
    /**
     * GET /api/cskh/points/events
     * Query:
     *  - status: pending|sent|failed (optional)
     *  - date_from/date_to | from_date/to_date | start_date/end_date: YYYY-MM-DD (optional)
     *  - q: tìm theo mã KH / tên KH / sđt / mã đơn (optional)
     *  - tier_id: lọc theo hạng hiện tại (optional)
     *  - per_page: default 20
     */
   public function index(Request $request)
{
    // ---- Map tham số linh hoạt: chấp nhận nhiều tên để tránh lệch FE↔BE ----
    $from = $request->input('from_date') ?? $request->input('date_from') ?? $request->input('start_date');
    $to   = $request->input('to_date')   ?? $request->input('date_to')   ?? $request->input('end_date');

    // Merge về 1 tên chuẩn để validate
    $request->merge([
        'from_date' => $from,
        'to_date'   => $to,
    ]);

    // ---- Validate an toàn ----
    $validated = $request->validate([
        'status'       => ['nullable', \Illuminate\Validation\Rule::in(['pending','sent','failed'])],
        'from_date'    => ['nullable', 'date_format:Y-m-d'],
        'to_date'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        'q'            => ['nullable', 'string', 'max:100'],
        'tier_id'      => ['nullable', 'integer'],
        'per_page'     => ['nullable', 'integer', 'min:5', 'max:200'],
        'include_zero' => ['nullable', 'boolean'], // NEW: hiển thị cả +0
    ]);

    $perPage  = (int) ($validated['per_page'] ?? 20);
    $status   = $validated['status'] ?? null;
    $dateFrom = $validated['from_date'] ?? null;
    $dateTo   = $validated['to_date'] ?? null;
    $q        = trim((string) ($validated['q'] ?? ''));
    $tierId   = $validated['tier_id'] ?? null;
    $includeZero = filter_var($request->input('include_zero', false), FILTER_VALIDATE_BOOLEAN);

    $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');

    // ---- Query "biến động thật" từ bảng events ----
    $events = DB::table('khach_hang_point_events as e')
        ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'e.khach_hang_id')
        ->leftJoin('don_hangs as dh', 'dh.id', '=', 'e.don_hang_id')
        ->selectRaw("
            e.id, e.khach_hang_id, e.don_hang_id,
            kh.ten_khach_hang,
            kh.ma_kh as ma_kh,
            kh.so_dien_thoai, kh.loai_khach_hang_id,
            e.order_code, e.order_date, e.price,
            e.old_revenue, e.new_revenue, e.delta_revenue,
            e.old_points, e.new_points, e.delta_points,
            e.zns_status, e.zns_sent_at, e.zns_error_code, e.zns_error_message,
            e.created_at
        ");

    if ($status) {
        $events->where('e.zns_status', $status);
    }
    if ($dateFrom) {
        $events->where('e.order_date', '>=', \Carbon\Carbon::parse($dateFrom, $tz)->startOfDay());
    }
    if ($dateTo) {
        $events->where('e.order_date', '<=', \Carbon\Carbon::parse($dateTo, $tz)->endOfDay());
    }
    if ($tierId) {
        $events->where('kh.loai_khach_hang_id', (int) $tierId);
    }
    if ($q !== '') {
        $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
        $events->where(function ($w) use ($like) {
            $w->where('kh.ma_kh', 'like', $like)
              ->orWhere('kh.ten_khach_hang', 'like', $like)
              ->orWhere('kh.so_dien_thoai', 'like', $like)
              ->orWhere('e.order_code', 'like', $like);
        });
    }

    if (!$includeZero) {
        // Giữ hành vi cũ: chỉ trả "biến động thật"
        $rows = $events->orderByDesc('e.order_date')->paginate($perPage);
        return \App\Class\CustomResponse::success($rows);
    }

    // ==== include_zero = true → thêm "dòng tổng hợp theo ĐƠN" (kể cả delta = 0) ====

    $orderAgg = DB::table('don_hangs as dh')
        ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'dh.khach_hang_id')
        ->selectRaw("
            NULL              as id,
            kh.id             as khach_hang_id,
            dh.id             as don_hang_id,
            kh.ten_khach_hang,
            kh.ma_kh          as ma_kh,
            kh.so_dien_thoai,
            kh.loai_khach_hang_id,
                COALESCE(dh.ma_don_hang, CONCAT('DH', LPAD(dh.id,5,'0'))) as order_code,
            dh.ngay_tao_don_hang as order_date,


            (CASE
                WHEN COALESCE(dh.loai_thanh_toan,0)=2 THEN COALESCE(dh.tong_tien_can_thanh_toan,0)
                WHEN COALESCE(dh.loai_thanh_toan,0)=1 THEN COALESCE(dh.so_tien_da_thanh_toan,0)
                ELSE 0
             END) as price,

            0 as old_revenue,
            0 as delta_revenue,
            0 as old_points,
            0 as delta_points,

            -- Tổng điểm hiện tại của KH để hiển thị
            COALESCE( (SELECT SUM(e2.delta_points) FROM khach_hang_point_events e2 WHERE e2.khach_hang_id = kh.id), 0) as new_points,
            COALESCE( (SELECT SUM(e2.delta_revenue) FROM khach_hang_point_events e2 WHERE e2.khach_hang_id = kh.id), 0) as new_revenue,

            'na' as zns_status,
            NULL as zns_sent_at,
            NULL as zns_error_code,
            NULL as zns_error_message,

            COALESCE(dh.updated_at, dh.created_at) as created_at
        ")
        ->whereNotNull('dh.khach_hang_id'); // chỉ đơn có KH hệ thống

    // Lọc ngày theo order_date
    // Lọc ngày theo NGÀY MUA HÀNG (ngay_tao_don_hang)
    if ($dateFrom) {
        $orderAgg->whereDate('dh.ngay_tao_don_hang', '>=', $dateFrom);
    }
    if ($dateTo) {
        $orderAgg->whereDate('dh.ngay_tao_don_hang', '<=', $dateTo);
    }

    if ($tierId) {
        $orderAgg->where('kh.loai_khach_hang_id', (int) $tierId);
    }
    if ($q !== '') {
        $like = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
        $orderAgg->where(function ($w) use ($like) {
            $w->where('kh.ma_kh', 'like', $like)
              ->orWhere('kh.ten_khach_hang', 'like', $like)
              ->orWhere('kh.so_dien_thoai', 'like', $like)
              ->orWhere('dh.ma_don_hang', 'like', $like)
              ->orWhereRaw("CAST(dh.id AS CHAR) like ?", [$like]);
        });
    }

    // UNION 2 nguồn dữ liệu
    $union = $events->unionAll($orderAgg);

    // Phải bọc union trong subquery để paginate
    $rows = DB::query()
        ->fromSub($union, 'u')
        ->orderByDesc('order_date')
        ->paginate($perPage);

    return \App\Class\CustomResponse::success($rows);
}


    /**
     * GET /api/cskh/points/customers/{khachHangId}/events
     * Lịch sử biến động của 1 khách
     */
    public function byCustomer(Request $request, int $khachHangId)
    {
        $validated = $request->validate([
            'per_page' => ['nullable','integer','min:5','max:200'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $rows = DB::table('khach_hang_point_events as e')
            ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'e.khach_hang_id')
            ->leftJoin('don_hangs as dh', 'dh.id', '=', 'e.don_hang_id')
            ->selectRaw("
                e.id, e.khach_hang_id, e.don_hang_id,
                kh.ten_khach_hang,
                kh.ma_kh as ma_kh,
                kh.so_dien_thoai, kh.loai_khach_hang_id,
                e.order_code, e.order_date, e.price,
                e.old_revenue, e.new_revenue, e.delta_revenue,
                e.old_points, e.new_points, e.delta_points,
                e.zns_status, e.zns_sent_at, e.zns_error_code, e.zns_error_message,
                e.created_at
            ")
            ->where('e.khach_hang_id', $khachHangId)
            ->orderByDesc('e.order_date')
            ->paginate($perPage);

        return CustomResponse::success($rows);
    }

    /**
     * POST /api/cskh/points/events/{eventId}/send-zns
     * Gửi ZNS 1 lần cho "biến động điểm".
     */
    public function sendZns(Request $request, int $eventId)
    {
        $validated = $request->validate([
            'note'        => ['nullable','string','max:200'],
            'template_id' => ['nullable','string','max:64'],
        ]);

        \Log::info('[PTS][SEND] enter', ['eventId' => $eventId]);

        $event = DB::table('khach_hang_point_events')->where('id', $eventId)->first();
        if (!$event) {
            return CustomResponse::error('Không tìm thấy biến động.', 404);
        }

     if (!in_array($event->zns_status, ['pending','failed'], true)) {
    return CustomResponse::error('Biến động này đã được xử lý gửi ZNS trước đó.', 400);
}


        $kh = DB::table('khach_hangs')->where('id', $event->khach_hang_id)->first();
        if (!$kh) {
            return CustomResponse::error('Không tìm thấy khách hàng.', 404);
        }

        $dh = DB::table('don_hangs')->where('id', $event->don_hang_id)->first();
        if (!$dh) {
            return CustomResponse::error('Không tìm thấy đơn hàng.', 404);
        }

        $rawPhone = $kh->so_dien_thoai ?? null;
        $phone    = $this->normalizePhoneVN((string) $rawPhone);
        if (!$phone) {
            return CustomResponse::error('Thiếu hoặc sai số điện thoại khách hàng.', 422);
        }

        $templateId = $validated['template_id']
            ?? ($event->zns_template_id ?: env('ZNS_TEMPLATE_POINT_ID', ''));
        if (!$templateId) {
            return CustomResponse::error('Thiếu template_id để gửi ZNS (ENV ZNS_TEMPLATE_POINT_ID hoặc zns_template_id).', 422);
        }

        $note = $validated['note'] ?? $event->note ?? '';

        $params = [
            'customer_name' => (string) ($kh->ten_khach_hang ?? ''), 
        'customer_code' => (string) ($kh->ma_kh ?? $kh->id),

            'order_code'    => (string) ($event->order_code ?? $dh->ma_don_hang ?? $dh->id),
            'order_date'    => $this->formatZnsDate($event->order_date),
            'price'         => (string) ($event->price ?? 0),
            'point'         => (string) ($event->delta_points ?? 0),
            'total_point'   => (string) ($event->new_points ?? 0),
            'note'          => (string) $note,
        ];

\Log::info('[PTS][SEND] payload', [
    'eventId'    => $eventId,
    'phone'      => $phone,
    'templateId' => $templateId,
    'params'     => $params,
]);


        try {
            /** @var \App\Services\Zns\ZnsProvider $provider */
            $provider = app(\App\Services\Zns\ZnsProvider::class);
            $res = $provider->send($templateId, $phone, $params, [
                'event_id'       => $eventId,
                'khach_hang_id'  => $event->khach_hang_id,
                'don_hang_id'    => $event->don_hang_id,
                'order_code'     => $params['order_code'],
            ]);

            $now = Carbon::now();
            $affected = DB::table('khach_hang_point_events')
                ->where('id', $eventId)
           ->whereIn('zns_status', ['pending','failed'])

                ->update([
                    'zns_status'       => $res->success ? 'sent' : 'failed',
                    'zns_sent_at'      => $now,
                    'zns_template_id'  => $templateId,
                    'zns_error_code'   => $res->success ? null : ($res->error_code ?? null),
                    'zns_error_message'=> $res->success ? null : ($res->error_message ?? null),
                    'note'             => $note ?: $event->note,
                    'updated_at'       => $now,
                ]);

            if ($affected === 0) {
                return CustomResponse::error('Biến động đã được xử lý bởi tác vụ khác.', 409);
            }

            return CustomResponse::success([
                'event_id'      => $eventId,
                'success'       => (bool) $res->success,
                'provider_id'   => $res->provider_id ?? null,
                'error_code'    => $res->error_code ?? null,
                'error_message' => $res->error_message ?? null,
                'phone'         => $phone,
                'params'        => $params,
            ], $res->success ? 'Gửi ZNS thành công.' : 'Gửi ZNS thất bại.');

        } catch (\Throwable $e) {
            report($e);
            \Log::error('[PTS][SEND] exception', ['eventId' => $eventId, 'err' => $e->getMessage()]);

            $now = Carbon::now();
            DB::table('khach_hang_point_events')
                ->where('id', $eventId)
              ->whereIn('zns_status', ['pending','failed']) 
                ->update([
                    'zns_status'        => 'failed',
                    'zns_sent_at'       => $now,
                    'zns_template_id'   => $templateId,
                    'zns_error_code'    => 'EXCEPTION',
                    'zns_error_message' => substr($e->getMessage(), 0, 240),
                    'updated_at'        => $now,
                ]);

            return CustomResponse::error('Có lỗi khi gửi ZNS: ' . $e->getMessage(), 500);
        }
    }

    // ==========================
    // Helpers
    // ==========================

    /** Chuẩn hoá số VN về 84xxxxxxxxx */
    private function normalizePhoneVN(?string $raw): ?string
    {
        $p = preg_replace('/\D+/', '', (string) $raw);
        if ($p === '') return null;
        $p = preg_replace('/^00/', '', $p);
        if (str_starts_with($p, '84')) return $p;
        if (str_starts_with($p, '0') && strlen($p) >= 10) return '84' . substr($p, 1);
        if (str_starts_with($p, '9') && strlen($p) === 9)  return '84' . $p;
        return null;
    }

    /** Định dạng ngày theo template ZNS (dd/MM/yyyy HH:mm) */
/** Định dạng ngày theo template ZNS (dd/MM/yyyy) */
private function formatZnsDate($dt): string
{
    try {
        return Carbon::parse($dt, config('app.timezone', 'Asia/Ho_Chi_Minh'))
            ->format('d/m/Y'); // ⬅️ chỉ gửi ngày
    } catch (\Throwable $e) {
        return '';
    }
}

}
