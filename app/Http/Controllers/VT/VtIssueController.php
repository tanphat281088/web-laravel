<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\VtIssue;
use App\Services\VT\VtLedgerService;
use Illuminate\Http\Request;

class VtIssueController extends Controller
{
    public function __construct(private VtLedgerService $ledger) {}

    public function index(Request $request)
    {
        $q = VtIssue::query()
            ->with(['items.item:id,ma_vt,ten_vt,don_vi_tinh,loai'])
            ->orderByDesc('ngay_ct')
            ->orderByDesc('id');

        if ($kw = trim((string)$request->get('q',''))) {
            $like = '%'.$kw.'%';
            $q->where(function($qq) use ($like){
                $qq->where('so_ct','like',$like)
                   ->orWhere('tham_chieu','like',$like)
                   ->orWhere('ghi_chu','like',$like);
            });
        }
        if ($from = $request->get('from')) $q->whereDate('ngay_ct','>=',$from);
        if ($to   = $request->get('to'))   $q->whereDate('ngay_ct','<=',$to);
        if ($lyDo = $request->get('ly_do')) $q->where('ly_do', $lyDo);

        $perPage = (int)($request->get('per_page', 20));
        $page    = (int)($request->get('page', 1));
        $p = $q->paginate($perPage, ['*'], 'page', $page);

        return CustomResponse::success([
            'collection' => $p->items(),
            'total'      => $p->total(),
            'pagination' => [
                'current_page' => $p->currentPage(),
                'last_page'    => $p->lastPage(),
                'per_page'     => $p->perPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        // KHÔNG nhận so_ct từ client: BE tự sinh PXVT-...
        $payload = $request->validate([
            // 'so_ct'      => 'sometimes|string|max:50', // <— bỏ, luôn auto
            'ngay_ct'      => 'sometimes|date',
            'ly_do'        => 'sometimes|in:BAN,HUY,CHUYEN,KHAC',
            'tham_chieu'   => 'sometimes|nullable|string|max:191',
            'ghi_chu'      => 'sometimes|nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.vt_item_id' => 'required|integer|exists:vt_items,id',
            'items.*.so_luong'   => 'required|integer|min:1',
            'items.*.ghi_chu'    => 'nullable|string',
        ]);

        unset($payload['so_ct']); // dù có gửi lên cũng bỏ

        $issue = $this->ledger->createIssue($payload);
        return CustomResponse::success($issue, 'Tạo phiếu xuất VT thành công');
    }

    public function show($id)
    {
        $iss = VtIssue::with(['items.item:id,ma_vt,ten_vt,don_vi_tinh,loai'])->find($id);
        if (!$iss) return CustomResponse::error('Không tìm thấy phiếu xuất', 404);
        return CustomResponse::success($iss);
    }

    public function update(Request $request, $id)
    {
        // KHÔNG cho đổi so_ct khi cập nhật: bỏ khỏi rules & sẽ giữ số cũ trong service
        $payload = $request->validate([
            // 'so_ct'      => 'sometimes|string|max:50', // <— bỏ
            'ngay_ct'      => 'sometimes|date',
            'ly_do'        => 'sometimes|in:BAN,HUY,CHUYEN,KHAC',
            'tham_chieu'   => 'sometimes|nullable|string|max:191',
            'ghi_chu'      => 'sometimes|nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.vt_item_id' => 'required|integer|exists:vt_items,id',
            'items.*.so_luong'   => 'required|integer|min:1',
            'items.*.ghi_chu'    => 'nullable|string',
        ]);

        unset($payload['so_ct']); // nếu có gửi lên cũng bỏ

        $updated = $this->ledger->updateIssue((int)$id, $payload);
        return CustomResponse::success($updated, 'Cập nhật phiếu xuất VT thành công');
    }

    public function destroy($id)
    {
        $this->ledger->deleteIssue((int)$id);
        return CustomResponse::success([], 'Xóa phiếu xuất VT thành công');
    }
}
