<?php

namespace App\Modules\NhanSu\Payroll;

use App\Http\Controllers\Controller as BaseController;
use App\Models\LuongThang;
use App\Services\Payroll\BangLuongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BangLuongAdminController extends BaseController
{
    /**
     * GET /nhan-su/bang-luong?user_id=&thang=YYYY-MM
     * - Xem bảng lương 1 người (quyền quản lý).
     */
    public function adminShow(Request $request)
    {
        $v = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'min:1'],
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $userId = (int) $request->input('user_id');
        $thang  = $request->input('thang') ?: \App\Services\Timesheet\BangCongService::cycleLabelForDate(now());

        $row = LuongThang::query()
            ->with(['user:id,name,email'])
            ->ofUser($userId)
            ->month($thang)
            ->first();

        if (!$row) {
            try {
                // ✅ Tổng hợp BẢNG CÔNG trước cho đúng kỳ, để Payroll có dữ liệu đọc
                /** @var \App\Services\Timesheet\BangCongService $ts */
                $ts = app(\App\Services\Timesheet\BangCongService::class);
                $ts->computeMonth($thang, $userId);

                /** @var BangLuongService $svc */
                $svc = app(BangLuongService::class);
                $svc->computeMonth($thang, $userId);

                $row = LuongThang::query()
                    ->with(['user:id,name,email'])
                    ->ofUser($userId)
                    ->month($thang)
                    ->first();

                \Log::info('Payroll lazy recompute ADMIN done', ['uid' => $userId, 'thang' => $thang, 'found' => (bool)$row]);
            } catch (\Throwable $e) {
                \Log::error('Payroll lazy recompute ADMIN failed', [
                    'uid' => $userId, 'thang' => $thang, 'err' => $e->getMessage()
                ]);
            }
        }

        return $this->success([
            'user_id' => $userId,
            'thang'   => $thang,
            'item'    => $row ? $this->toApi($row) : null,
        ], 'ADMIN_PAYROLL');
    }

    /**
     * GET /nhan-su/bang-luong/list?thang=YYYY-MM&page=1&per_page=50
     * - Danh sách lương toàn công ty trong 1 tháng (quyền quản lý).
     */
    public function adminList(Request $request)
    {
        $v = Validator::make($request->all(), [
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
            'page'    => ['nullable', 'integer', 'min:1'],
            'per_page'=> ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang   = $request->input('thang') ?: now()->format('Y-m');
        $perPage = (int) ($request->input('per_page', 50));
        $page    = (int) ($request->input('page', 1));

        // ✅ 1) Lazy compute BẢNG CÔNG trước (để Payroll có dữ liệu đọc)
        try {
            /** @var \App\Services\Timesheet\BangCongService $ts */
            $ts = app(\App\Services\Timesheet\BangCongService::class);
            \Log::info('adminList: Timesheet lazy compute start', ['thang' => $thang]);
            $ts->computeMonth($thang, null);
        } catch (\Throwable $e) {
            \Log::warning('adminList: Timesheet lazy compute error', ['thang' => $thang, 'err' => $e->getMessage()]);
        }

        // ✅ 2) Nếu tháng chưa có snapshot lương → lazy compute Payroll
        if (!\App\Models\LuongThang::query()->where('thang', $thang)->exists()) {
            try {
                /** @var BangLuongService $svc */
                $svc = app(BangLuongService::class);
                \Log::info('adminList: Payroll lazy compute start', ['thang' => $thang]);
                $svc->computeMonth($thang, null);
            } catch (\Throwable $e) {
                \Log::warning('adminList: Payroll lazy compute error', ['thang' => $thang, 'err' => $e->getMessage()]);
            }
        }

        $q = LuongThang::query()
            ->with(['user:id,name,email'])
            ->where('thang', $thang)
            ->orderByDesc('user_id');

        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        // HOTFIX: map an toàn để không nổ 500
        $raw = $paginator->items();
        $items = array_map(function ($r) {
            $name = null;
            if (isset($r->user) && $r->user) {
                $name = $r->user->name ?? $r->user->email ?? null;
            }
            return [
                'id'              => (int) $r->id,
                'user_id'         => (int) $r->user_id,
                'user_name'       => $name,
                'thang'           => (string) $r->thang,
                'luong_co_ban'    => (int) $r->luong_co_ban,
                'cong_chuan'      => (int) $r->cong_chuan,
                'he_so'           => (float) $r->he_so,
                'so_ngay_cong'    => (float) $r->so_ngay_cong,
                'so_gio_cong'     => (int) $r->so_gio_cong,
                'phu_cap'         => (int) $r->phu_cap,
                'thuong'          => (int) $r->thuong,
                'phat'            => (int) $r->phat,
                'luong_theo_cong' => (int) $r->luong_theo_cong,
                'bhxh'            => (int) $r->bhxh,
                'bhyt'            => (int) $r->bhyt,
                'bhtn'            => (int) $r->bhtn,
                'khau_tru_khac'   => (int) $r->khau_tru_khac,
                'tam_ung'         => (int) $r->tam_ung,
                'thuc_nhan'       => (int) $r->thuc_nhan,
                'locked'          => (bool) $r->locked,
                'computed_at'     => $r->computed_at ? $r->computed_at->toDateTimeString() : null,
                'ghi_chu'         => $r->ghi_chu,
                'created_at'      => $r->created_at ? $r->created_at->toDateTimeString() : null,
                'updated_at'      => $r->updated_at ? $r->updated_at->toDateTimeString() : null,
            ];
        }, $raw);

        return $this->success([
            'thang'      => $thang,
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'items' => $items,
        ], 'ADMIN_PAYROLL_LIST');
    }

    /**
     * POST /nhan-su/bang-luong/recompute?thang=YYYY-MM&user_id=
     * - Tính lại 1 người hoặc toàn bộ (bỏ qua dòng locked).
     */
    public function recompute(Request $request, BangLuongService $svc)
    {
        $v = Validator::make($request->all(), [
            'thang'   => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang  = $request->input('thang') ?: now()->format('Y-m');
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

        try {
            // ✅ Recompute bảng công trước
            /** @var \App\Services\Timesheet\BangCongService $ts */
            $ts = app(\App\Services\Timesheet\BangCongService::class);
            $ts->computeMonth($thang, $userId);

            // ✅ Sau đó Payroll
            $svc->computeMonth($thang, $userId);

            return $this->success([
                'thang'   => $thang,
                'user_id' => $userId,
                'notice'  => 'Đã tổng hợp bảng lương.',
            ], 'RECOMPUTED_PAYROLL_OK');
        } catch (\Throwable $e) {
            \Log::error('Payroll recompute failed', [
                'thang'   => $thang, 'user_id' => $userId,
                'err'     => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            $msg = config('app.debug') ? $e->getMessage() : 'Không thể tổng hợp bảng lương.';
            return $this->failed(['message' => $msg], 'RECOMPUTE_PAYROLL_FAILED', 500);
        }
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
