<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\VtReceipt;
use App\Services\VT\VtLedgerService;
use Illuminate\Http\Request;

class VtReceiptController extends Controller
{
    public function __construct(private VtLedgerService $ledger) {}

    public function index(Request $request)
    {
        $q = VtReceipt::query()
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
        // KHÔNG nhận so_ct từ client: BE tự sinh PNVT-...
        $payload = $request->validate([
            // 'so_ct'           => 'sometimes|string|max:50', // <— bỏ, luôn auto
            'ngay_ct'          => 'sometimes|date',
            'nha_cung_cap_id'  => 'sometimes|nullable|integer|exists:nha_cung_caps,id',
            'tham_chieu'       => 'sometimes|nullable|string|max:191',
            'ghi_chu'          => 'sometimes|nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.vt_item_id' => 'required|integer|exists:vt_items,id',
            'items.*.so_luong'   => 'required|integer|min:1',
            'items.*.don_gia'    => 'nullable|numeric|min:0',
            'items.*.ghi_chu'    => 'nullable|string',
        ]);

        // Dù client cố gửi so_ct theo raw payload thì cũng bỏ:
        unset($payload['so_ct']);

        $receipt = $this->ledger->createReceipt($payload);
        return CustomResponse::success($receipt, 'Tạo phiếu nhập VT thành công');
    }

    public function show($id)
    {
        $rec = VtReceipt::with(['items.item:id,ma_vt,ten_vt,don_vi_tinh,loai','nhaCungCap:id,ten_nha_cung_cap'])->find($id);
        if (!$rec) return CustomResponse::error('Không tìm thấy phiếu nhập', 404);
        return CustomResponse::success($rec);
    }

    public function update(Request $request, $id)
    {
        // KHÔNG cho đổi so_ct khi cập nhật: bỏ khỏi rules & sẽ giữ số cũ trong service
        $payload = $request->validate([
            // 'so_ct'           => 'sometimes|string|max:50', // <— bỏ
            'ngay_ct'          => 'sometimes|date',
            'nha_cung_cap_id'  => 'sometimes|nullable|integer|exists:nha_cung_caps,id',
            'tham_chieu'       => 'sometimes|nullable|string|max:191',
            'ghi_chu'          => 'sometimes|nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.vt_item_id' => 'required|integer|exists:vt_items,id',
            'items.*.so_luong'   => 'required|integer|min:1',
            'items.*.don_gia'    => 'nullable|numeric|min:0',
            'items.*.ghi_chu'    => 'nullable|string',
        ]);

        unset($payload['so_ct']); // nếu có gửi lên cũng bỏ

        $updated = $this->ledger->updateReceipt((int)$id, $payload);
        return CustomResponse::success($updated, 'Cập nhật phiếu nhập VT thành công');
    }
public function destroy(int $id)
{
    try {
        \DB::beginTransaction();

        // ✅ Dùng service để hoàn tồn + xoá ledger + xoá chi tiết + xoá header
        $this->ledger->deleteReceipt($id);

        \DB::commit();
        return response()->json([
            'success' => true,
            'message' => 'Xóa phiếu nhập VT thành công',
            'data'    => [],
        ], 200);
    } catch (\Illuminate\Database\QueryException $e) {
        \DB::rollBack();
        // Nếu có ràng buộc khác phát sinh, trả về 409 cho FE hiển thị rõ
        return response()->json([
            'success' => false,
            'message' => 'Không thể xóa phiếu nhập: ' . ($e->getMessage() ?: 'Lỗi cơ sở dữ liệu'),
            'errors'  => 409,
        ], 409);
    } catch (\Throwable $e) {
        \DB::rollBack();
        // Các lỗi khác (không tìm thấy, logic…) để bubble lên 500 cho log
        throw $e;
    }
}


}
