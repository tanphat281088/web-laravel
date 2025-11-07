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
        return [
            'id'              => $r->id,
            'user_id'         => $r->user_id,
            'user_name'       => $r->relationLoaded('user') && $r->user
                                  ? ($r->user->name ?? $r->user->email)
                                  : null,
            'thang'           => $r->thang,

            'luong_co_ban'    => $r->luong_co_ban,
            'cong_chuan'      => $r->cong_chuan,
            'he_so'           => (float) $r->he_so,

            'so_ngay_cong'    => (float) $r->so_ngay_cong,
            'so_gio_cong'     => $r->so_gio_cong,

            'phu_cap'         => $r->phu_cap,
            'thuong'          => $r->thuong,
            'phat'            => $r->phat,

            'luong_theo_cong' => $r->luong_theo_cong,
            'bhxh'            => $r->bhxh,
            'bhyt'            => $r->bhyt,
            'bhtn'            => $r->bhtn,
            'khau_tru_khac'   => $r->khau_tru_khac,
            'tam_ung'         => $r->tam_ung,
            'thuc_nhan'       => $r->thuc_nhan,

            'locked'          => (bool) $r->locked,
            'computed_at'     => $r->computed_at?->toDateTimeString(),
            'ghi_chu'         => $r->ghi_chu,
            'created_at'      => $r->created_at?->toDateTimeString(),
            'updated_at'      => $r->updated_at?->toDateTimeString(),
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
