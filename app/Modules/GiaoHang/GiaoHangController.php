<?php

namespace App\Modules\GiaoHang;

use App\Class\CustomResponse;
use App\Models\DonHang;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

// ⬇️ Model log sẽ được tạo bởi migration ở bước trước
// use App\Models\DonHangSmsLog;
// ⬇️ Service gửi SMS qua PA Việt Nam
// use App\Services\Sms\PaVnSmsService;

class GiaoHangController extends BaseController
{
    /**
     * GET /api/giao-hang/hom-nay
     * Danh sách "Đơn hôm nay": lọc theo nguoi_nhan_thoi_gian trong [00:00–23:59] hôm nay (theo app.timezone).
     * Query params:
     *  - status: 0|1|2|3 (optional)  // 0=Chưa giao, 1=Đang giao, 2=Đã giao, 3=Đã hủy
     *  - per_page: số bản ghi/trang (mặc định 20)
     */
    public function donHomNay(Request $request)
    {
        $status  = $request->filled('status') ? (int) $request->input('status') : null;
        $perPage = (int) ($request->input('per_page', 20));
        $page    = (int) ($request->input('page', 1));

        $tz    = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now   = Carbon::now($tz);
        $start = $now->copy()->startOfDay();
        $end   = $now->copy()->endOfDay();

        $query = DonHang::query()
->select([
    'id',
    'ma_don_hang',
    'ten_khach_hang',
    'dia_chi_giao_hang',
    'nguoi_nhan_ten',
    'nguoi_nhan_sdt',
    'nguoi_nhan_thoi_gian',
    'trang_thai_don_hang',

    // ---- Flags SMS (đã gửi / lỗi) ----
    DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l 
              WHERE l.don_hang_id = don_hangs.id 
                AND l.type = 'dang_giao' 
                AND l.success = 1) AS sms_dang_giao_sent"),
    DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l 
              WHERE l.don_hang_id = don_hangs.id 
                AND l.type = 'da_giao'   
                AND l.success = 1) AS sms_da_giao_sent"),
    DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l 
              WHERE l.don_hang_id = don_hangs.id 
                AND l.type = 'dang_giao' 
                AND l.success = 0) AS sms_dang_giao_failed"),
    DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l 
              WHERE l.don_hang_id = don_hangs.id 
                AND l.type = 'da_giao'   
                AND l.success = 0) AS sms_da_giao_failed"),

    // ---- Lý do lỗi (bản ghi gần nhất mỗi mốc) ----
    DB::raw("(SELECT l.error_code 
              FROM don_hang_sms_logs l
              WHERE l.don_hang_id = don_hangs.id AND l.type = 'dang_giao'
              ORDER BY l.attempted_at DESC LIMIT 1) AS sms_dang_giao_error_code"),
    DB::raw("(SELECT l.error_message 
              FROM don_hang_sms_logs l
              WHERE l.don_hang_id = don_hangs.id AND l.type = 'dang_giao'
              ORDER BY l.attempted_at DESC LIMIT 1) AS sms_dang_giao_error_msg"),
    DB::raw("(SELECT l.error_code 
              FROM don_hang_sms_logs l
              WHERE l.don_hang_id = don_hangs.id AND l.type = 'da_giao'
              ORDER BY l.attempted_at DESC LIMIT 1) AS sms_da_giao_error_code"),
    DB::raw("(SELECT l.error_message 
              FROM don_hang_sms_logs l
              WHERE l.don_hang_id = don_hangs.id AND l.type = 'da_giao'
              ORDER BY l.attempted_at DESC LIMIT 1) AS sms_da_giao_error_msg"),
])

            ->whereNotNull('nguoi_nhan_thoi_gian')
            ->whereBetween('nguoi_nhan_thoi_gian', [$start, $end]);

        if ($status !== null) {
            $query->where('trang_thai_don_hang', $status);
        }

        $rows = $query->orderBy('nguoi_nhan_thoi_gian', 'asc')->paginate($perPage, ['*'], 'page', $page);

        return CustomResponse::success($rows);
    }

    /**
     * GET /api/giao-hang/lich-hom-nay
     * "Lịch giao hôm nay" dạng timeline: group theo khung giờ.
     * Query params:
     *  - status: 0|1|2|3 (optional)
     *  - bucket_minutes: kích thước khung giờ (mặc định 60, min 15, max 120)
     */
    public function lichGiaoHomNay(Request $request)
    {
        $status       = $request->filled('status') ? (int) $request->input('status') : null;
        $bucketMinute = (int) $request->input('bucket_minutes', 60);
        $bucketMinute = max(15, min(120, $bucketMinute));

        $tz    = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $now   = Carbon::now($tz);
        $start = $now->copy()->startOfDay();
        $end   = $now->copy()->endOfDay();

        $rows = DonHang::query()
            ->select([
                'id',
                'ma_don_hang',
                'ten_khach_hang',
                'dia_chi_giao_hang',
                'nguoi_nhan_ten',
                'nguoi_nhan_sdt',
                'nguoi_nhan_thoi_gian',
                'trang_thai_don_hang',
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'dang_giao' AND l.success = 1) AS sms_dang_giao_sent"),
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'da_giao'   AND l.success = 1) AS sms_da_giao_sent"),
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'dang_giao' AND l.success = 0) AS sms_dang_giao_failed"),
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'da_giao'   AND l.success = 0) AS sms_da_giao_failed"),
            ])
            ->whereNotNull('nguoi_nhan_thoi_gian')
            ->whereBetween('nguoi_nhan_thoi_gian', [$start, $end])
            ->when($status !== null, fn ($q) => $q->where('trang_thai_don_hang', $status))
            ->orderBy('nguoi_nhan_thoi_gian', 'asc')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $dt = Carbon::parse($row->nguoi_nhan_thoi_gian, $tz);
            $minuteBucket = intdiv((int) $dt->minute, $bucketMinute) * $bucketMinute;
            $slotStart = $dt->copy()->minute($minuteBucket)->second(0);
            $slotEnd   = $slotStart->copy()->addMinutes($bucketMinute)->subSecond();

            $label = $slotStart->format('H:i') . '–' . $slotEnd->format('H:i');
            $key   = $slotStart->format('H:i');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'slot'  => $label,
                    'start' => $slotStart->toIso8601String(),
                    'end'   => $slotEnd->toIso8601String(),
                    'items' => [],
                ];
            }

            $groups[$key]['items'][] = [
                'id'                   => $row->id,
                'ma_don_hang'          => $row->ma_don_hang,
                'ten_khach_hang'       => $row->ten_khach_hang,
                'dia_chi_giao_hang'    => $row->dia_chi_giao_hang,
                'nguoi_nhan_ten'       => $row->nguoi_nhan_ten,
                'nguoi_nhan_sdt'       => $row->nguoi_nhan_sdt,
                'nguoi_nhan_thoi_gian' => Carbon::parse($row->nguoi_nhan_thoi_gian, $tz)->toIso8601String(),
                'trang_thai_don_hang'  => (int) $row->trang_thai_don_hang,
                'sms_dang_giao_sent'   => (bool) $row->sms_dang_giao_sent,
                'sms_da_giao_sent'     => (bool) $row->sms_da_giao_sent,
                'sms_dang_giao_failed' => (bool) $row->sms_dang_giao_failed,
                'sms_da_giao_failed'   => (bool) $row->sms_da_giao_failed,
            ];
        }

        ksort($groups);

        return CustomResponse::success([
            'date'           => $now->toDateString(),
            'bucket_minutes' => $bucketMinute,
            'groups'         => array_values($groups),
        ]);
    }

    /**
     * GET /api/giao-hang/lich-tong
     */
    public function lichGiaoTong(Request $request)
    {
        $from    = $request->input('from'); // YYYY-MM-DD
        $to      = $request->input('to');   // YYYY-MM-DD
        $status  = $request->filled('status') ? (int) $request->input('status') : null;
        $perPage = (int) ($request->input('per_page', 50));
        $page    = (int) ($request->input('page', 1));

        $tz     = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $fromDt = $from ? Carbon::parse($from, $tz)->startOfDay() : null;
        $toDt   = $to   ? Carbon::parse($to,   $tz)->endOfDay()   : null;

        $query = DonHang::query()
            ->select([
                'id',
                'ma_don_hang',
                'ten_khach_hang',
                'dia_chi_giao_hang',
                'nguoi_nhan_ten',
                'nguoi_nhan_sdt',
                'nguoi_nhan_thoi_gian',
                'trang_thai_don_hang',
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'dang_giao' AND l.success = 1) AS sms_dang_giao_sent"),
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'da_giao'   AND l.success = 1) AS sms_da_giao_sent"),
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'dang_giao' AND l.success = 0) AS sms_dang_giao_failed"),
                DB::raw("EXISTS(SELECT 1 FROM don_hang_sms_logs l WHERE l.don_hang_id = don_hangs.id AND l.type = 'da_giao'   AND l.success = 0) AS sms_da_giao_failed"),
            ])
            ->whereNotNull('nguoi_nhan_thoi_gian')
            ->when($fromDt, fn ($q) => $q->where('nguoi_nhan_thoi_gian', '>=', $fromDt))
            ->when($toDt,   fn ($q) => $q->where('nguoi_nhan_thoi_gian', '<=', $toDt))
            ->when($status !== null, fn ($q) => $q->where('trang_thai_don_hang', $status))
            ->orderBy('nguoi_nhan_thoi_gian', 'asc');

        $rows = $query->paginate($perPage, ['*'], 'page', $page);

        return CustomResponse::success($rows);
    }

    /**
     * PATCH /api/giao-hang/{id}/trang-thai
     */
    public function capNhatTrangThai(Request $request, $id)
    {
        $data = $request->validate([
            'trang_thai_don_hang' => [
                'required',
                'integer',
                Rule::in([
                    DonHang::TRANG_THAI_CHUA_GIAO,  // 0
                    DonHang::TRANG_THAI_DANG_GIAO,  // 1
                    DonHang::TRANG_THAI_DA_GIAO,    // 2
                    DonHang::TRANG_THAI_DA_HUY,     // 3
                ]),
            ],
        ]);

        $donHang = DonHang::findOrFail($id);
        $donHang->trang_thai_don_hang = (int) $data['trang_thai_don_hang'];
        $donHang->save();

        return CustomResponse::success($donHang, 'Cập nhật trạng thái đơn hàng thành công.');
    }

    /**
     * POST /api/giao-hang/{id}/notify-and-set-status
     * Đổi trạng thái (1=Đang giao | 2=Đã giao), đồng thời GỬI SMS 1 LẦN/MỐC.
     * Nếu SMS thất bại → vẫn đổi trạng thái, trả sms_success=false để FE cảnh báo.
     */
    public function notifyAndSetStatus(Request $request, $id)
    {
        $data = $request->validate([
            'target_status' => ['required', 'integer', Rule::in([1, 2])],
            'message'       => ['nullable', 'string'],
            'force_retry'   => ['sometimes', 'boolean'],
        ]);

        $targetStatus = (int) $data['target_status'];
        $forceRetry   = (bool) ($data['force_retry'] ?? false);

        $donHang = DonHang::findOrFail($id);

        // 1) Luôn đổi trạng thái theo yêu cầu
        $donHang->trang_thai_don_hang = $targetStatus;
        $donHang->save();

        // 2) Mốc SMS
        $smsType = $this->statusToSmsType($targetStatus); // 'dang_giao' | 'da_giao' | null
        if ($smsType === null) {
            return CustomResponse::success([
                'ok'                => true,
                'order_id'          => $donHang->id,
                'new_status'        => $targetStatus,
                'sms_attempted'     => false,
                'sms_success'       => false,
                'already_attempted' => false,
                'can_retry'         => false,
                'sms_flags'         => $this->buildSmsFlags($donHang->id),
            ], 'Cập nhật trạng thái thành công (không áp dụng SMS cho mốc này).');
        }

        // 3) Chống gửi trùng
        $log = DB::table('don_hang_sms_logs')
            ->where('don_hang_id', $donHang->id)
            ->where('type', $smsType)
            ->first();

        if ($log) {
            $alreadyAttempted = true;
            $canRetry = false;

            if ((int) $log->success === 0) {
                $canRetry = Gate::check('giao-hang.sms-retry')
                    || (method_exists(auth()->user(), 'isAdmin') && auth()->user()->isAdmin());

                if (!($forceRetry && $canRetry)) {
                    return CustomResponse::success([
                        'ok'                => true,
                        'order_id'          => $donHang->id,
                        'new_status'        => $targetStatus,
                        'sms_attempted'     => false,
                        'sms_success'       => (bool) $log->success,
                        'already_attempted' => true,
                        'can_retry'         => $canRetry,
                        'sms_flags'         => $this->buildSmsFlags($donHang->id),
                    ], 'Đã cập nhật trạng thái. SMS trước đó đã được thử và thất bại; không gửi lại.');
                }
                // nếu force_retry & có quyền → cho phép gửi lại (update log)
            } else {
                // đã gửi thành công trước đó → không gửi lại
                return CustomResponse::success([
                    'ok'                => true,
                    'order_id'          => $donHang->id,
                    'new_status'        => $targetStatus,
                    'sms_attempted'     => false,
                    'sms_success'       => true,
                    'already_attempted' => true,
                    'can_retry'         => false,
                    'sms_flags'         => $this->buildSmsFlags($donHang->id),
                ], 'Đã cập nhật trạng thái. SMS mốc này trước đó đã gửi thành công; không gửi lại.');
            }
        } else {
            $alreadyAttempted = false;
            $canRetry = false;
        }

        // 4) Chuẩn bị nội dung SMS
        $phone = $donHang->nguoi_nhan_sdt ?: $donHang->so_dien_thoai ?? null;
        if (!$phone) {
            $this->upsertSmsLog($donHang->id, $smsType, [
                'phone'           => null,
                'message'         => $data['message'] ?: $this->defaultSmsMessage($smsType),
                'success'         => 0,
                'error_code'      => 'NO_PHONE',
                'error_message'   => 'Không có số điện thoại người nhận',
                'provider_msg_id' => null,
            ]);

            return CustomResponse::success([
                'ok'                => true,
                'order_id'          => $donHang->id,
                'new_status'        => $targetStatus,
                'sms_attempted'     => true,
                'sms_success'       => false,
                'already_attempted' => $alreadyAttempted,
                'can_retry'         => true,
                'sms_flags'         => $this->buildSmsFlags($donHang->id),
            ], 'Đã cập nhật trạng thái nhưng không thể gửi SMS (thiếu số điện thoại).');
        }

        $message = $data['message'] ?: $this->defaultSmsMessage($smsType);

        // 5) Gửi SMS
        try {
            $service = app(\App\Services\Sms\PaVnSmsService::class);
            $result  = $service->send(
                $phone,
                $message,
                'PHG Don ' . ($donHang->ma_don_hang ?? $donHang->id),
                '' // rỗng = gửi ngay; muốn hẹn giờ thì truyền "dd-mm-YYYY HH:ii"
            );

            // ghi/ cập nhật log; nếu blacklist → coi như thất bại để FE cảnh báo
            $this->upsertSmsLog($donHang->id, $smsType, [
                'phone'           => $phone,
                'message'         => $message,
                'success'         => (!empty($result->blacklisted) ? 0 : ($result->success ? 1 : 0)),
                'provider_msg_id' => $result->provider_id ?? null,
                'error_code'      => !empty($result->blacklisted) ? 'BLACKLISTED' : ($result->error_code ?? null),
                'error_message'   => !empty($result->blacklisted) ? 'Số thuộc danh sách từ chối (blacklist)' : ($result->error_message ?? null),
            ]);

            $smsSuccess = empty($result->blacklisted) && !empty($result->success);

            return CustomResponse::success([
                'ok'                => true,
                'order_id'          => $donHang->id,
                'new_status'        => $targetStatus,
                'sms_attempted'     => true,
                'sms_success'       => $smsSuccess,
                'already_attempted' => $alreadyAttempted,
                'can_retry'         => !$smsSuccess, // cho retry khi thất bại (nếu có quyền)
                'sms_flags'         => $this->buildSmsFlags($donHang->id),
            ], $smsSuccess
                ? 'Đã cập nhật trạng thái và gửi SMS thành công.'
                : 'Đã cập nhật trạng thái nhưng SMS chưa gửi được (có thể do blacklist hoặc lỗi nhà cung cấp).');

        } catch (\Throwable $e) {
            // lỗi runtime: vẫn đổi trạng thái, ghi log fail
            $this->upsertSmsLog($donHang->id, $smsType, [
                'phone'           => $phone,
                'message'         => $message,
                'success'         => 0,
                'provider_msg_id' => null,
                'error_code'      => 'EXCEPTION',
                'error_message'   => substr($e->getMessage(), 0, 240),
            ]);

            report($e);

            return CustomResponse::success([
                'ok'                => true,
                'order_id'          => $donHang->id,
                'new_status'        => $targetStatus,
                'sms_attempted'     => true,
                'sms_success'       => false,
                'already_attempted' => $alreadyAttempted,
                'can_retry'         => true,
                'sms_flags'         => $this->buildSmsFlags($donHang->id),
            ], 'Đã cập nhật trạng thái nhưng gửi SMS thất bại.');
        }
    }   // <= END notifyAndSetStatus()

    // -----------------------
    // Helpers nội bộ
    // -----------------------

    /**
     * Map trạng thái → loại SMS ('dang_giao' | 'da_giao' | null)
     */
    private function statusToSmsType(int $status): ?string
    {
        return match ($status) {
            1 => 'dang_giao',
            2 => 'da_giao',
            default => null,
        };
    }

    /**
     * Tin nhắn mặc định theo mốc.
     */
    private function defaultSmsMessage(string $smsType): string
    {
        if ($smsType === 'dang_giao') {
            return 'PHG Floral: Don hang cua Quy khach da hoan thien va dang duoc giao. Vui long giu lien lac de nhan hoa. LH 0949404344.';
        }
        if ($smsType === 'da_giao') {
            return 'PHG Floral: Don hang cua Quy khach da giao thanh cong. Cam on Quy khach da tin tuong PHG Floral! LH 0949404344.';
        }
        return '';
    }

    /**
     * Ghi/ cập nhật log (đảm bảo 1 bản ghi duy nhất cho mỗi (don_hang_id, type)).
     * - Nếu đã tồn tại → cập nhật kết quả mới (phục vụ retry khi fail).
     * - Nếu chưa tồn tại → tạo mới.
     */
    private function upsertSmsLog(int $donHangId, string $type, array $payload): void
    {
        $now = Carbon::now();

        $existing = DB::table('don_hang_sms_logs')
            ->where('don_hang_id', $donHangId)
            ->where('type', $type)
            ->first();

        $data = [
            'don_hang_id'     => $donHangId,
            'type'            => $type,
            'phone'           => $payload['phone'] ?? null,
            'message'         => $payload['message'] ?? null,
            'attempted_at'    => $now,
            'success'         => (int) ($payload['success'] ?? 0),
            'provider_msg_id' => $payload['provider_msg_id'] ?? null,
            'error_code'      => $payload['error_code'] ?? null,
            'error_message'   => $payload['error_message'] ?? null,
        ];

        if ($existing) {
            DB::table('don_hang_sms_logs')->where('id', $existing->id)->update($data);
        } else {
            DB::table('don_hang_sms_logs')->insert($data);
        }
    }

    /**
     * Xây cờ tổng hợp cho FE (đã gửi/đã fail theo từng mốc).
     */
    private function buildSmsFlags(int $donHangId): array
    {
        $sentDangGiao = DB::table('don_hang_sms_logs')
            ->where('don_hang_id', $donHangId)
            ->where('type', 'dang_giao')
            ->where('success', 1)
            ->exists();

        $sentDaGiao = DB::table('don_hang_sms_logs')
            ->where('don_hang_id', $donHangId)
            ->where('type', 'da_giao')
            ->where('success', 1)
            ->exists();

        return [
            'dang_giao_sent' => (bool) $sentDangGiao,
            'da_giao_sent'   => (bool) $sentDaGiao,
        ];
    }
}
