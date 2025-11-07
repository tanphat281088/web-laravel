<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\BangCongThang;
use App\Services\Timesheet\BangCongService;
use App\Services\Payroll\BangLuongService; // NEW: để gọi recompute Payroll sau khi tính công

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BangCongController extends BaseController
{
    /**
     * GET /nhan-su/bang-cong/my?thang=YYYY-MM
     */
    public function myIndex(Request $request)
    {
        $uid = $request->user()?->id ?? auth()->id();
        if (!$uid) return $this->failed([], 'UNAUTHORIZED', 401);

        $v = Validator::make($request->all(), [
            'thang' => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        // Mặc định: kỳ hiện tại 6→5
        $thang = $request->input('thang') ?: BangCongService::cycleLabelForDate(now());

        $row = BangCongThang::query()
            ->with(['user:id,name,email'])
            ->ofUser((int) $uid)
            ->month($thang)
            ->first();

        // Lazy recompute nếu chưa có
        if (!$row) {
            try {
                /** @var \App\Services\Timesheet\BangCongService $svc */
                $svc = app(BangCongService::class);
                $svc->computeMonth($thang, (int) $uid);

                $row = BangCongThang::query()
                    ->with(['user:id,name,email'])
                    ->ofUser((int) $uid)
                    ->month($thang)
                    ->first();

                \Log::info('Timesheet lazy recompute MY done', ['uid' => $uid, 'thang' => $thang, 'found' => (bool)$row]);
            } catch (\Throwable $e) {
                \Log::error('Timesheet lazy recompute MY failed', [
                    'uid' => $uid, 'thang' => $thang, 'err' => $e->getMessage()
                ]);
            }
        }

// NEW: nếu client yêu cầu thì sau khi tính bảng công, chạy luôn Payroll cho user/tháng này
if ($request->boolean('also_payroll', false)) {
    try {
        /** @var BangLuongService $payroll */
        $payroll = app(BangLuongService::class);
        $payroll->computeMonth($thang, (int) $uid);
        \Log::info('Payroll recompute triggered from myIndex', ['uid' => $uid, 'thang' => $thang]);
    } catch (\Throwable $e) {
        \Log::warning('Payroll recompute (myIndex) failed', ['uid' => $uid, 'thang' => $thang, 'err' => $e->getMessage()]);
    }
}


        $payload = [
            'thang' => $thang,
            'item'  => $row ? $this->toApi($row) : null,
        ];

        if (config('app.debug')) {
            $payload['debug'] = ['lazy_recomputed' => (bool)$row];
        }

        return $this->success($payload, 'MY_TIMESHEET');
    }

    /**
     * GET /nhan-su/bang-cong?user_id=&thang=YYYY-MM
     */
    public function adminIndex(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $userId = (int) $request->input('user_id');
        $thang  = $request->input('thang') ?: BangCongService::cycleLabelForDate(now());

        $row = BangCongThang::query()
            ->with(['user:id,name,email'])
            ->ofUser($userId)
            ->month($thang)
            ->first();

        if (!$row) {
            try {
                /** @var \App\Services\Timesheet\BangCongService $svc */
                $svc = app(BangCongService::class);
                $svc->computeMonth($thang, (int) $userId);

                $row = BangCongThang::query()
                    ->with(['user:id,name,email'])
                    ->ofUser($userId)
                    ->month($thang)
                    ->first();

                \Log::info('Timesheet lazy recompute ADMIN done', ['uid' => $userId, 'thang' => $thang, 'found' => (bool)$row]);
            } catch (\Throwable $e) {
                \Log::error('Timesheet lazy recompute ADMIN failed', [
                    'uid' => $userId, 'thang' => $thang, 'err' => $e->getMessage()
                ]);
            }
        }

// NEW: nếu yêu cầu, sau khi tính bảng công cho user này thì chạy luôn Payroll cùng kỳ
if ($request->boolean('also_payroll', false)) {
    try {
        /** @var BangLuongService $payroll */
        $payroll = app(BangLuongService::class);
        $payroll->computeMonth($thang, (int) $userId);
        \Log::info('Payroll recompute triggered from adminIndex', ['uid' => $userId, 'thang' => $thang]);
    } catch (\Throwable $e) {
        \Log::warning('Payroll recompute (adminIndex) failed', ['uid' => $userId, 'thang' => $thang, 'err' => $e->getMessage()]);
    }
}


        $payload = [
            'user_id' => $userId,
            'thang'   => $thang,
            'item'    => $row ? $this->toApi($row) : null,
        ];

        if (config('app.debug')) {
            $payload['debug'] = ['lazy_recomputed' => (bool)$row];
        }

        return $this->success($payload, 'ADMIN_TIMESHEET');
    }

    /**
     * POST /nhan-su/bang-cong/recompute?thang=YYYY-MM&user_id=
     * Gọi service tổng hợp lại bảng công theo kỳ 6→5 (1 user hoặc tất cả).
     * Quyền: nhan-su.update | nhan-su.create | nhan-su.store (tuỳ hệ thống phân quyền).
     */
    public function recompute(Request $request, BangCongService $svc)
    {
$v = Validator::make($request->all(), [
    'thang'         => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
    'user_id'       => ['nullable', 'integer', 'min:1'],
    'also_payroll'  => ['nullable', 'boolean'], // NEW: cho phép chạy luôn Payroll
]);

        if ($v->fails()) {
            return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);
        }

        $thang  = $request->input('thang') ?: now()->format('Y-m');
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;
        $alsoPayroll = (bool) $request->boolean('also_payroll', false); // NEW


        try {
            // Chạy tổng hợp theo kỳ 6→5
// Chạy tổng hợp theo kỳ 6→5
$svc->computeMonth($thang, $userId);

// NEW: nếu được yêu cầu thì gọi luôn Payroll (bảng lương) cho cùng kỳ
if ($alsoPayroll) {
    try {
        /** @var BangLuongService $payroll */
        $payroll = app(BangLuongService::class);
        $payroll->computeMonth($thang, $userId);
        \Log::info('Payroll recompute triggered from Timesheet.recompute', ['uid' => $userId, 'thang' => $thang]);
    } catch (\Throwable $e) {
        \Log::warning('Payroll recompute (Timesheet.recompute) failed', ['uid' => $userId, 'thang' => $thang, 'err' => $e->getMessage()]);
    }
}

return $this->success([
    'thang'         => $thang,
    'user_id'       => $userId,
    'also_payroll'  => $alsoPayroll ? true : false, // NEW: để FE biết đã gọi Payroll chưa
    'notice'        => 'Đã tổng hợp bảng công.',
], 'RECOMPUTED_OK');

        } catch (\Throwable $e) {
            // Ghi log chi tiết để tra lỗi nhanh
            \Log::error('Timesheet recompute failed', [
                'thang'   => $thang,
                'user_id' => $userId,
                'err'     => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            // Trả lỗi rõ ràng cho FE
            $msg = config('app.debug') ? $e->getMessage() : 'Không thể tổng hợp bảng công.';
            return $this->failed(['message' => $msg], 'RECOMPUTE_FAILED', 500);
        }
    }

    // ===== Helpers =====

    private function toApi(BangCongThang $r): array
    {
        return [
            'id'                        => $r->id,
            'user_id'                   => $r->user_id,
            'user_name'                 => $r->relationLoaded('user') && $r->user
                                            ? ($r->user->name ?? $r->user->email)
                                            : null,
            'thang'                     => $r->thang,
            'so_ngay_cong'              => $r->so_ngay_cong,
            'so_gio_cong'               => $r->so_gio_cong,
            'di_tre_phut'               => $r->di_tre_phut,
            've_som_phut'               => $r->ve_som_phut,
            'nghi_phep_ngay'            => $r->nghi_phep_ngay,
            'nghi_phep_gio'             => $r->nghi_phep_gio,
            'nghi_khong_luong_ngay'     => $r->nghi_khong_luong_ngay,
            'nghi_khong_luong_gio'      => $r->nghi_khong_luong_gio,
            'lam_them_gio'              => $r->lam_them_gio,
            'locked'                    => (bool) $r->locked,
            'computed_at'               => $r->computed_at?->toDateTimeString(),
            'ghi_chu'                   => $r->ghi_chu,
            'created_at'                => $r->created_at?->toDateTimeString(),
            'updated_at'                => $r->updated_at?->toDateTimeString(),
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
