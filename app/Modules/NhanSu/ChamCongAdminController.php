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
    // ===== NEW filters =====
    'type'     => ['nullable', 'in:checkin,checkout'],
    'within'   => ['nullable', 'in:0,1'],
    'q'        => ['nullable', 'string', 'max:255'],
    'order'    => ['nullable', 'in:asc,desc'],
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
    $type  = $request->input('type');                             // 'checkin' | 'checkout' | null
$within= $request->has('within') ? (int)$request->input('within') : null; // 0|1|null
$q     = trim((string)$request->input('q', ''));             // free-text search
$order = strtolower((string)$request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';


    try {
        $query = ChamCong::query()
            ->between($from . ' 00:00:00', $to . ' 23:59:59');

        if ($userId) {
            $query->ofUser((int) $userId);
            // ====== EXTRA FILTERS (type/within/q) ======
if ($type) {
    // 'checkin' hoặc 'checkout' -> gọi scope tương ứng
    $query->{$type}();
}

if ($within !== null) {
    // 1 = trong vùng geofence, 0 = ngoài vùng
    $query->where('within_geofence', (bool) $within);
}

if ($q !== '') {
    // Tìm theo IP / device_id / ghi_chu hoặc tên/email user
    $query->where(function ($w) use ($q) {
        $w->where('ip', 'like', '%' . $q . '%')
          ->orWhere('device_id', 'like', '%' . $q . '%')
          ->orWhere('ghi_chu', 'like', '%' . $q . '%');
    })->orWhereHas('user', function ($u) use ($q) {
        $u->where('name', 'like', '%' . $q . '%')
          ->orWhere('email', 'like', '%' . $q . '%');
    });
}
// ===========================================

        }

        // 🔧 Chỉ lấy các cột chắc chắn có trên users
        $query->with(['user' => function ($q) {
            $q->select('id', 'name', 'email');
        }]);

   

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
               
                  'weekday'    => $c->checked_at ? $c->checked_at->locale('vi')->isoFormat('ddd') : null, // T2..CN
        'source'     => $c->device_id ? 'device' : ($c->ip ? 'ip' : null),
            ];
        });

        return $this->respond(true, 'ADMIN_ATTENDANCE', [
            'filter' => [
    'user_id' => $userId ? (int) $userId : null,
    'from'    => $from,
    'to'      => $to,
    'type'    => $type,
    'within'  => $within,
    'q'       => $q !== '' ? $q : null,
    'order'   => $order,
],
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
