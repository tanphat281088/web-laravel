<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use App\Models\BangCongThang;
use App\Services\Timesheet\BangCongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BangCongAdminOpsController extends BaseController
{
    /**
     * PATCH /nhan-su/bang-cong/lock
     * Query|Body: thang=YYYY-MM&user_id=?
     * - Nếu có user_id: khoá 1 người; nếu không: khoá tất cả dòng của tháng.
     * Quyền: nhan-su.update
     */
    public function lock(Request $request)
    {
        $v = Validator::make($request->all(), [
            'thang'   => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang  = (string) $request->input('thang');
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

        try {
            $affected = DB::transaction(function () use ($thang, $userId) {
                $q = BangCongThang::query()->where('thang', $thang);
                if ($userId) $q->where('user_id', $userId);
                return $q->update(['locked' => true, 'updated_at' => now()]);
            });

            // Thống kê sau cập nhật
            $stats = $this->statsOfMonth($thang);

            return $this->success([
                'thang'    => $thang,
                'user_id'  => $userId,
                'affected' => $affected,
                'stats'    => $stats,
            ], 'LOCKED_OK');
        } catch (Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 'SERVER_ERROR', 500);
        }
    }

    /**
     * PATCH /nhan-su/bang-cong/unlock
     * Query|Body: thang=YYYY-MM&user_id=?
     * Quyền: nhan-su.update
     */
    public function unlock(Request $request)
    {
        $v = Validator::make($request->all(), [
            'thang'   => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang  = (string) $request->input('thang');
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

        try {
            $affected = DB::transaction(function () use ($thang, $userId) {
                $q = BangCongThang::query()->where('thang', $thang);
                if ($userId) $q->where('user_id', $userId);
                return $q->update(['locked' => false, 'updated_at' => now()]);
            });

            $stats = $this->statsOfMonth($thang);

            return $this->success([
                'thang'    => $thang,
                'user_id'  => $userId,
                'affected' => $affected,
                'stats'    => $stats,
            ], 'UNLOCKED_OK');
        } catch (Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 'SERVER_ERROR', 500);
        }
    }

    /**
     * POST /nhan-su/bang-cong/recompute-all
     * Query|Body: thang=YYYY-MM
     * - Gọi service compute cho toàn bộ users (khuyến nghị: service bỏ qua các dòng đã khoá).
     * Quyền: nhan-su.update | nhan-su.create
     */
    public function recomputeAll(Request $request, BangCongService $svc)
    {
 $v = Validator::make($request->all(), [
    'thang'         => ['required', 'regex:/^\d{4}\-\d{2}$/'],
    'also_payroll'  => ['nullable', 'boolean'],   // ✅ NEW: cho phép gọi Payroll sau khi tính công
]);

        if ($v->fails()) return $this->failed($v->errors(), 'VALIDATION_ERROR', 422);

        $thang = (string) $request->input('thang');
        $alsoPayroll = (bool) $request->boolean('also_payroll', false);  // ✅ NEW


        try {
            // Giao cho service xử lý chi tiết (tính & upsert). Khuyến nghị logic service:
            // - Chỉ upsert các user chưa có dòng hoặc dòng chưa locked
            // - Tôn trọng locked=true (bỏ qua)
      // Giao cho service xử lý chi tiết (tính & upsert). Khuyến nghị logic service:
// - Chỉ upsert các user chưa có dòng hoặc dòng chưa locked
// - Tôn trọng locked=true (bỏ qua)
$svc->computeMonth($thang, null);

// ✅ NEW: Nếu được yêu cầu, chạy luôn Payroll cho tháng này (bỏ qua locked theo logic Payroll)
if ($alsoPayroll) {
    /** @var \App\Services\Payroll\BangLuongService $payroll */
    $payroll = app(\App\Services\Payroll\BangLuongService::class);
    $payroll->computeMonth($thang, null);
}

$stats = $this->statsOfMonth($thang);

return $this->success([
    'thang'         => $thang,
    'notice'        => 'Đã tổng hợp lại cho toàn bộ nhân viên (bỏ qua các dòng đã khoá).',
    'also_payroll'  => $alsoPayroll ? true : false,                 // ✅ NEW
    'stats'         => $stats,
], 'RECOMPUTED_ALL_OK');

        } catch (Throwable $e) {
            return $this->failed(config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 'SERVER_ERROR', 500);
        }
    }

    // ===== Helpers =====

    /**
     * Lấy thống kê nhanh cho 1 tháng (tổng dòng, số locked/unlocked).
     */
    private function statsOfMonth(string $thang): array
    {
        $total    = BangCongThang::query()->where('thang', $thang)->count();
        $locked   = BangCongThang::query()->where('thang', $thang)->where('locked', true)->count();
        $unlocked = $total - $locked;

        return compact('total', 'locked', 'unlocked');
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
