<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ChamCong;
use Carbon\Carbon;
use Throwable;

class ChamCongMeController extends BaseController
{
    /**
     * GET /nhan-su/cham-cong/me?from=YYYY-MM-DD&to=YYYY-MM-DD&page=1&per_page=20
     * - Trả về lịch sử checkin/checkout của chính user đang đăng nhập, theo khoảng thời gian.
     * - Mặc định: 30 ngày gần nhất.
     * - Sắp xếp: checked_at DESC.
     */
public function index(Request $request)
{
    $user = $request->user();
    $userId = $user?->id ?? auth()->id();
    if (!$userId) {
        return $this->respond(false, 'UNAUTHORIZED', null, 401);
    }

    $v = Validator::make($request->all(), [
        'from'     => ['nullable', 'date_format:Y-m-d'],
        'to'       => ['nullable', 'date_format:Y-m-d'],
        'page'     => ['nullable', 'integer', 'min:1'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
    ]);
    if ($v->fails()) {
        return $this->respond(false, 'VALIDATION_ERROR', $v->errors(), 422);
    }

    // Khoảng mặc định: 30 ngày gần nhất
    $to   = $request->input('to') ?: now()->toDateString();
    $from = $request->input('from') ?: \Carbon\Carbon::parse($to)->subDays(30)->toDateString();

    $perPage = (int) ($request->input('per_page', 20));
    $page    = (int) ($request->input('page', 1));

    try {
        $query = ChamCong::query()
            ->ofUser($userId)
            ->between($from . ' 00:00:00', $to . ' 23:59:59')
            ->orderByDesc('checked_at');

        // (Tuỳ nhu cầu) Nếu muốn show tên user ở client thì eager-load users,
        // nhưng CHỈ lấy các cột chắc chắn tồn tại để tránh lỗi:
        // $query->with(['user:id,name,email']);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (ChamCong $c) {
            return [
                'id'         => $c->id,
                'type'       => $c->type, // checkin|checkout
                'checked_at' => optional($c->checked_at)->toIso8601String(),
                'lat'        => $c->lat,
                'lng'        => $c->lng,
                'distance_m' => $c->distance_m,
                'within'     => (bool) $c->within_geofence,
                'accuracy_m' => $c->accuracy_m,
                'device_id'  => $c->device_id,
                'ip'         => $c->ip,
                'ghi_chu'    => $c->ghi_chu,
                'short_desc' => $c->shortDesc(),
                'ngay'       => $c->checked_at ? $c->checked_at->toDateString() : null,
                'gio_phut'   => $c->checked_at ? $c->checked_at->format('H:i') : null,
            ];
        });

        return $this->respond(true, 'MY_ATTENDANCE', [
            // ✅ Chuẩn hóa về "filter" để FE dùng chung
            'filter' => [
                'from' => $from,
                'to'   => $to,
            ],
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'has_more'     => $paginator->hasMorePages(),
            ],
            'items' => $items,
        ]);
    } catch (Throwable $e) {
        return $this->respond(false, 'SERVER_ERROR', config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 500);
    }
}


    /**
     * Chuẩn hoá response (tương thích CustomResponse nếu dự án có).
     */
    private function respond(bool $success, string $code, $data = null, int $status = 200)
    {
        if (class_exists(\App\Class\CustomResponse::class)) {
            if ($success) {
                return \App\Class\CustomResponse::success($data, $code)->setStatusCode($status);
            }
            return \App\Class\CustomResponse::failed($data, $code)->setStatusCode($status);
        }

        return response()->json([
            'success' => $success,
            'code'    => $code,
            'data'    => $data,
        ], $status);
    }
}
