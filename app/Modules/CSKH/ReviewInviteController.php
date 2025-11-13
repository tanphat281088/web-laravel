<?php

namespace App\Modules\CSKH;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\ZnsReviewInvite;
use App\Services\Zns\ZnsReviewService;
use Illuminate\Http\Request;

class ReviewInviteController extends Controller
{
    /** GET /api/cskh/reviews/invites
     * Query: status=pending|sent|failed|cancelled, from=YYYY-MM-DD, to=YYYY-MM-DD, q, per_page
     */
    public function index(Request $req)
    {
        $v = $req->validate([
            'status'   => ['nullable','in:pending,sent,failed,cancelled'],
            'from'     => ['nullable','date_format:Y-m-d'],
            'to'       => ['nullable','date_format:Y-m-d','after_or_equal:from'],
            'q'        => ['nullable','string','max:100'],
            'per_page' => ['nullable','integer','min:5','max:200'],
        ]);
        $per = (int) ($v['per_page'] ?? 20);

        $rows = ZnsReviewInvite::query()
            ->leftJoin('khach_hangs as kh','kh.id','=','zns_review_invites.khach_hang_id')
            ->leftJoin('don_hangs  as dh','dh.id','=','zns_review_invites.don_hang_id')
            ->when(($v['status'] ?? null), fn($q,$s)=>$q->where('zns_status',$s))
            ->when(($v['from'] ?? null), fn($q,$f)=>$q->where('order_date','>=',$f.' 00:00:00'))
            ->when(($v['to']   ?? null), fn($q,$t)=>$q->where('order_date','<=',$t.' 23:59:59'))
            ->when(($v['q'] ?? '') !== '', function($q) use ($v) {
                $like = '%'.str_replace(['%','_'],['\%','\_'],$v['q']).'%';
                $q->where(function($w) use ($like){
                    $w->where('kh.ma_kh','like',$like)
                      ->orWhere('kh.ten_khach_hang','like',$like)
                      ->orWhere('kh.so_dien_thoai','like',$like)
                      ->orWhere('zns_review_invites.order_code','like',$like);
                });
            })
->select([
    'zns_review_invites.*',
    'kh.ten_khach_hang','kh.ma_kh','kh.so_dien_thoai',

    // từ don_hangs
    'dh.trang_thai_don_hang   as dh_status',
    'dh.trang_thai_thanh_toan as pay_status',
    'dh.loai_thanh_toan       as pay_type',
    'dh.tong_tien_can_thanh_toan as order_total',
    'dh.so_tien_da_thanh_toan    as paid_amount',

    // danh sách tên sản phẩm (chính xác theo migrations chi_tiet_don_hangs/san_phams)
    \DB::raw("(SELECT GROUP_CONCAT(DISTINCT sp.ten_san_pham SEPARATOR ', ')
              FROM chi_tiet_don_hangs c
              JOIN san_phams sp ON sp.id = c.san_pham_id
              WHERE c.don_hang_id = zns_review_invites.don_hang_id) AS product_names"),
])

            ->orderByDesc('order_date')
            ->paginate($per);

        return CustomResponse::success($rows);
    }

    /** POST /api/cskh/reviews/invites/from-order/{donHangId} */
    public function createFromOrder(int $donHangId, ZnsReviewService $svc)
    {
        try {
            $invite = $svc->upsertInviteFromOrder($donHangId);
            return CustomResponse::success($invite, 'Invite ready.');
        } catch (\Throwable $e) {
            return CustomResponse::error($e->getMessage(), 422);
        }
    }

    /** POST /api/cskh/reviews/invites/{id}/send  (only pending) */
    public function send(int $id, Request $req, ZnsReviewService $svc)
    {
        $v = $req->validate(['template_id'=>['nullable','string','max:64']]);
            \Log::info('[REVIEW][SEND] enter', ['invite_id' => $id]);


        $invite = ZnsReviewInvite::query()->find($id);
        if (!$invite) return CustomResponse::error('Không tìm thấy invite.', 404);

        $res = $svc->sendInvite($invite, $v['template_id'] ?? null);


            \Log::info('[REVIEW][SEND] result', [
        'invite_id' => $invite->id,
        'success'   => (bool)($res->success ?? false),
        'err'       => $res->error_code  ?? null,
        'msg'       => $res->error_message ?? null,
    ]);

        return CustomResponse::success([
            'invite_id'   => $invite->id,
            'success'     => (bool) ($res->success ?? false),
            'provider_id' => $res->provider_id ?? null,
            'error_code'  => $res->error_code  ?? null,
            'error_msg'   => $res->error_message ?? null,
        ], !empty($res->success) ? 'Gửi ZNS thành công.' : 'Gửi ZNS thất bại.');
    }

    /** POST /api/cskh/reviews/bulk-send  (pending within date range) */
    public function bulkSend(Request $req, ZnsReviewService $svc)
    {
        $v = $req->validate([
            'from'        => ['nullable','date_format:Y-m-d'],
            'to'          => ['nullable','date_format:Y-m-d','after_or_equal:from'],
            'limit'       => ['nullable','integer','min:1','max:2000'],
            'template_id' => ['nullable','string','max:64'],
        ]);
        $limit = (int) ($v['limit'] ?? 200);

        $stat = $svc->bulkSend($v['from'] ?? null, $v['to'] ?? null, $limit, $v['template_id'] ?? null);
        return CustomResponse::success($stat, 'Bulk sent.');
    }

    /** PATCH /api/cskh/reviews/invites/{id}/cancel  (cancel pending) */
    public function cancel(int $id)
    {
        $aff = ZnsReviewInvite::query()
            ->where('id',$id)->where('zns_status','pending')
            ->update([
                'zns_status'    => 'cancelled',
                'nguoi_cap_nhat'=> auth()->user()->name ?? 'system',
            ]);

        return $aff
            ? CustomResponse::success(null, 'Đã hủy invite.')
            : CustomResponse::error('Invite đã xử lý hoặc không tồn tại.', 409);
    }

        /** POST /api/cskh/reviews/backfill  (manual refresh by date range) */
    public function backfill(\Illuminate\Http\Request $req, \App\Services\Zns\ZnsReviewService $svc)
    {
        $v = $req->validate([
            'from'  => ['nullable','date_format:Y-m-d'],
            'to'    => ['nullable','date_format:Y-m-d','after_or_equal:from'],
            'limit' => ['nullable','integer','min:1','max:5000'],
        ]);
        $stat = $svc->backfillInvites($v['from'] ?? null, $v['to'] ?? null, (int)($v['limit'] ?? 500));
        return \App\Class\CustomResponse::success($stat, 'Backfill done.');
    }

}
