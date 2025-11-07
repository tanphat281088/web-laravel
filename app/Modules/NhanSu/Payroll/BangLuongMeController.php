<?php

namespace App\Modules\NhanSu\Payroll;

use App\Http\Controllers\Controller as BaseController;
use App\Models\LuongThang;
use App\Services\Payroll\BangLuongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BangLuongMeController extends BaseController
{
    /**
     * GET /nhan-su/bang-luong/my?thang=YYYY-MM
     * - Trả về bảng lương của chính user đang đăng nhập cho kỳ (6→5).
     * - Nếu chưa có snapshot -> lazy compute rồi trả.
     */
    public function myIndex(Request $request)
    {
        $uid = $request->user()?->id ?? auth()->id();
        if (!$uid) return $this->failed([], 'UNAUTHORIZED', 401);

        $v = Validator::make($request->all(), [
            'thang' => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        // Kỳ mặc định: theo service bảng công (6→5)
        $thang = $request->input('thang') ?: \App\Services\Timesheet\BangCongService::cycleLabelForDate(now());

        $row = LuongThang::query()
            ->with(['user:id,name,email'])
            ->ofUser((int) $uid)
            ->month($thang)
            ->first();

        // Lazy compute nếu chưa có
        if (!$row) {
            try {
                /** @var BangLuongService $svc */
                $svc = app(BangLuongService::class);
                // Tổng hợp BẢNG CÔNG trước để Payroll đọc (an toàn)
                /** @var \App\Services\Timesheet\BangCongService $ts */
                $ts = app(\App\Services\Timesheet\BangCongService::class);
                $ts->computeMonth($thang, (int) $uid);

                $svc->computeMonth($thang, (int) $uid);

                $row = LuongThang::query()
                    ->with(['user:id,name,email'])
                    ->ofUser((int) $uid)
                    ->month($thang)
                    ->first();

                \Log::info('Payroll lazy recompute MY done', ['uid' => $uid, 'thang' => $thang, 'found' => (bool)$row]);
            } catch (\Throwable $e) {
                \Log::error('Payroll lazy recompute MY failed', [
                    'uid' => $uid, 'thang' => $thang, 'err' => $e->getMessage()
                ]);
            }
        }

        return $this->success([
            'thang' => $thang,
            'item'  => $row ? $this->toApi($row) : null,
        ], 'MY_PAYROLL');
    }

    // ===== Helpers =====

private function toApi(LuongThang $r): array
{
    // Giải mã ghi_chu (có thể là JSON string)
    $note = null;
    if (!empty($r->ghi_chu)) {
        if (is_string($r->ghi_chu)) {
            try { $note = json_decode($r->ghi_chu, true, 512, JSON_THROW_ON_ERROR); }
            catch (\Throwable $e) { $note = null; }
        } elseif (is_array($r->ghi_chu) || $r->ghi_chu instanceof \JsonSerializable) {
            $note = (array) $r->ghi_chu;
        }
    }

    $mode       = $note['mode']        ?? null;
    $base       = isset($note['base']) ? (int)$note['base'] : null;
    $daily_rate = isset($note['daily_rate']) ? (int)$note['daily_rate'] : null;
    $cong_eff   = isset($note['cong_chuan']) ? (int)$note['cong_chuan'] : (int)$r->cong_chuan;
    $bh_base    = isset($note['bh_base']) ? (int)$note['bh_base'] : null;

    // P/Q/R/T/U: U = P − Q − R − T (khớp Excel)
    $P_gross = (int)$r->luong_theo_cong + (int)$r->phu_cap + (int)$r->thuong - (int)$r->phat; // P
    $Q_ins   = (int)$r->bhxh + (int)$r->bhyt + (int)$r->bhtn;                                   // Q
    $R_ded   = (int)$r->khau_tru_khac;                                                          // R
    $T_adv   = (int)$r->tam_ung;                                                                // T
    $U_net   = (int)$r->thuc_nhan;                                                              // U (đã tính)

    return [
        'id'              => (int)$r->id,
        'user_id'         => (int)$r->user_id,
        'user_name'       => $r->relationLoaded('user') && $r->user
                              ? ($r->user->name ?? $r->user->email)
                              : null,
        'thang'           => (string)$r->thang,

        'luong_co_ban'    => (int)$r->luong_co_ban,
        'cong_chuan'      => $cong_eff,
        'he_so'           => (float)$r->he_so,

        'so_ngay_cong'    => (float)$r->so_ngay_cong,
        'so_gio_cong'     => (int)$r->so_gio_cong,

        'phu_cap'         => (int)$r->phu_cap,
        'thuong'          => (int)$r->thuong,
        'phat'            => (int)$r->phat,

        'luong_theo_cong' => (int)$r->luong_theo_cong,
        'bhxh'            => (int)$r->bhxh,
        'bhyt'            => (int)$r->bhyt,
        'bhtn'            => (int)$r->bhtn,
        'khau_tru_khac'   => $R_ded,
        'tam_ung'         => $T_adv,

        // ===== P/Q/R/T/U =====
        'P_gross'         => $P_gross,
        'Q_insurance'     => $Q_ins,
        'R_deduct_other'  => $R_ded,
        'T_advance'       => $T_adv,
        'U_net'           => $U_net,

        // metrics phụ để FE hiển thị chi tiết
        'metrics' => [
            'mode'       => $mode,
            'base'       => $base,
            'daily_rate' => $daily_rate,
            'bh_base'    => $bh_base,
        ],

        'locked'          => (bool)$r->locked,
        'computed_at'     => $r->computed_at?->toDateTimeString(),
        'created_at'      => $r->created_at?->toDateTimeString(),
        'updated_at'      => $r->updated_at?->toDateTimeString(),
        // giữ nguyên ghi_chu (raw) để debug nếu cần
        'ghi_chu'         => $r->ghi_chu,
    ];
}


    // --- Response helpers ---
    private function success($data = [], string $code = 'OK', int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::success($data, $code, $status);
        }
        return response()->json(['success' => true, 'code' => $code, 'data' => $data], $status);
    }

    private function failed($data = [], string $code = 'ERROR', int $status = 400)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            return \App\Class\CustomResponse::failed($data, $code, $status);
        }
        return response()->json(['success' => false, 'code' => $code, 'data' => $data], $status);
    }
}
