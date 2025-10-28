<?php

namespace App\Modules\CSKH;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberPointMaintenanceController extends Controller
{
    /**
     * POST /api/cskh/points/resync
     * Body (optional):
     *  - from_date/to_date: YYYY-MM-DD (lọc theo dh.updated_at)
     *  - limit: số đơn tối đa để chạy (mặc định 2000)
     *  - only_missing: true|false (mặc định true → chỉ đơn đang lệch)
     */
    public function resync(Request $req)
    {
        $validated = $req->validate([
            'from_date'    => ['nullable','date_format:Y-m-d'],
            'to_date'      => ['nullable','date_format:Y-m-d','after_or_equal:from_date'],
            'limit'        => ['nullable','integer','min:1','max:50000'],
            'only_missing' => ['nullable','boolean'],
        ]);

        $from   = $validated['from_date']    ?? null;
        $to     = $validated['to_date']      ?? null;
        $limit  = (int)($validated['limit']  ?? 2000);
        $only   = array_key_exists('only_missing', $validated) ? (bool)$validated['only_missing'] : true;

        // Chọn ứng viên: đơn có khách hàng hệ thống
        $q = DB::table('don_hangs as dh')
            ->select('dh.id', 'dh.ma_don_hang', 'dh.loai_thanh_toan', 'dh.so_tien_da_thanh_toan', 'dh.tong_tien_can_thanh_toan', 'dh.updated_at')
            ->whereNotNull('dh.khach_hang_id');

        if ($from) $q->where('dh.updated_at', '>=', $from . ' 00:00:00');
        if ($to)   $q->where('dh.updated_at', '<=', $to   . ' 23:59:59');

        if ($only) {
            // only_missing = true → chỉ lấy đơn đang lệch (target ≠ existing)
            $q->whereRaw("
                (
                  CASE 
                    WHEN COALESCE(dh.loai_thanh_toan,0)=2 THEN COALESCE(dh.tong_tien_can_thanh_toan,0)
                    WHEN COALESCE(dh.loai_thanh_toan,0)=1 THEN COALESCE(dh.so_tien_da_thanh_toan,0)
                    ELSE 0
                  END
                ) <> COALESCE((SELECT SUM(e.price) FROM khach_hang_point_events e WHERE e.don_hang_id = dh.id),0)
            ");
        }

        // Ưu tiên mới nhất
        $q->orderByDesc('dh.updated_at')->limit($limit);

        $orders = $q->get();

        $svc = app(\App\Services\MemberPointService::class);

        $scanned = 0; $synced = 0; $createdEvents = 0;
        $details = [];

        foreach ($orders as $row) {
            $scanned++;
            try {
                $res = $svc->syncByOrder((int)$row->id);
                if (!empty($res['ok']) && empty($res['idempotent'])) {
                    $synced++;
                    $createdEvents++;
                    $details[] = [
                        'order_id'    => $row->id,
                        'order_code'  => $row->ma_don_hang ?? ('DH'.str_pad((string)$row->id, 5, '0', STR_PAD_LEFT)),
                        'delta_vnd'   => $res['delta_revenue'] ?? null,
                        'delta_point' => $res['delta_points'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                report($e);
                $details[] = [
                    'order_id'   => $row->id,
                    'order_code' => $row->ma_don_hang ?? ('DH'.str_pad((string)$row->id, 5, '0', STR_PAD_LEFT)),
                    'error'      => substr($e->getMessage(), 0, 200),
                ];
            }
        }

        return \App\Class\CustomResponse::success([
            'scanned'        => $scanned,
            'synced'         => $synced,
            'created_events' => $createdEvents,
            'details'        => $details,
        ], 'Resync completed.');
    }

    /**
     * POST /api/cskh/points/resync-by-order/{id}
     * Đồng bộ 1 đơn (tiện test/thao tác tay)
     */
    public function resyncByOrder(int $id)
    {
        $svc = app(\App\Services\MemberPointService::class);
        try {
            $res = $svc->syncByOrder($id);
            return \App\Class\CustomResponse::success($res);
        } catch (\Throwable $e) {
            report($e);
            return \App\Class\CustomResponse::error($e->getMessage(), 500);
        }
    }
}
