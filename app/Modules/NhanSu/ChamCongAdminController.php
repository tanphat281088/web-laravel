<?php

namespace App\Modules\NhanSu;

use App\Http\Controllers\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\ChamCong;
use Carbon\Carbon;
use Throwable;

class ChamCongAdminController extends BaseController
{
    /**
     * GET /nhan-su/cham-cong?user_id=&from=YYYY-MM-DD&to=YYYY-MM-DD&page=1&per_page=20
     * - Xem chấm công ALL nhân viên (yêu cầu middleware permission xử lý ở routes).
     * - Mặc định: 30 ngày gần nhất, sắp xếp checked_at DESC.
     * - Bộ lọc: user_id (tuỳ chọn), from/to (tuỳ chọn).
     */
public function index(Request $request)
{
    $v = Validator::make($request->all(), [
        'user_id'  => ['nullable', 'integer', 'min:1'],
        'from'     => ['nullable', 'date_format:Y-m-d'],
        'to'       => ['nullable', 'date_format:Y-m-d'],
        'page'     => ['nullable', 'integer', 'min:1'],
        'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
    ]);
    if ($v->fails()) {
        return $this->respond(false, 'VALIDATION_ERROR', $v->errors(), 422);
    }

    // Range mặc định: 30 ngày gần nhất
    $to   = $request->input('to')   ?: now()->toDateString();
    $from = $request->input('from') ?: \Carbon\Carbon::parse($to)->subDays(30)->toDateString();

    $perPage = (int) ($request->input('per_page', 20));
    $page    = (int) ($request->input('page', 1));
    $userId  = $request->input('user_id');

    try {
        $query = ChamCong::query()
            ->between($from . ' 00:00:00', $to . ' 23:59:59');

        if ($userId) {
            $query->ofUser((int) $userId);
        }

        // 🔧 Chỉ lấy các cột chắc chắn có trên users
        $query->with(['user' => function ($q) {
            $q->select('id', 'name', 'email');
        }]);

        $query->orderByDesc('checked_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function (ChamCong $c) {
            $displayName = null;
            if ($c->relationLoaded('user') && $c->user) {
                $displayName = $c->user->name ?? $c->user->email ?? ('#' . $c->user->id);
            }

            return [
                'id'         => $c->id,
                'user_id'    => $c->user_id,
                'user_name'  => $displayName,
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

        return $this->respond(true, 'ADMIN_ATTENDANCE', [
            'filter' => ['user_id' => $userId ? (int) $userId : null, 'from' => $from, 'to' => $to],
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'items' => $items,
        ]);
    } catch (Throwable $e) {
        return $this->respond(false, 'SERVER_ERROR', config('app.debug') ? $e->getMessage() : 'Lỗi hệ thống.', 500);
    }
}


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
