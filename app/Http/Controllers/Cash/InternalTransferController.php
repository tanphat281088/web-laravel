<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Class\CustomResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Cash\CashLedgerService;

class InternalTransferController extends Controller
{
    // GET /cash/internal-transfers
    public function index(Request $request)
    {
        $q = DB::table('phieu_chuyen_noi_bos as t')
            ->selectRaw('t.*, tk1.ten_tk as tu_tk_ten, tk2.ten_tk as den_tk_ten')
            ->leftJoin('tai_khoan_tiens as tk1', 'tk1.id', '=', 't.tu_tai_khoan_id')
            ->leftJoin('tai_khoan_tiens as tk2', 'tk2.id', '=', 't.den_tai_khoan_id');

        if ($request->filled('from')) $q->where('t.ngay_ct', '>=', $request->get('from'));
        if ($request->filled('to'))   $q->where('t.ngay_ct', '<=', $request->get('to'));
        if ($request->filled('status')) $q->where('t.trang_thai', $request->get('status'));
        if ($kw = trim((string) $request->get('keyword', ''))) {
            $q->where(function($qq) use ($kw) {
                $qq->where('t.ma_phieu', 'LIKE', "%{$kw}%")
                   ->orWhere('t.noi_dung', 'LIKE', "%{$kw}%");
            });
        }

        $q->orderByDesc('t.ngay_ct')->orderByDesc('t.id');

        $per  = max(1, (int)($request->get('per_page', 25)));
        $page = max(1, (int)($request->get('page', 1)));
        $rows = $q->paginate($per, ['*'], 'page', $page);

        return CustomResponse::success([
            'collection' => $rows->items(),
            'total'      => $rows->total(),
            'pagination' => [
                'current_page'  => $rows->currentPage(),
                'last_page'     => $rows->lastPage(),
                'from'          => $rows->firstItem(),
                'to'            => $rows->lastItem(),
                'total_current' => count($rows->items()),
            ],
        ]);
    }

    // GET /cash/internal-transfers/{id}
    public function show($id)
    {
        $row = DB::table('phieu_chuyen_noi_bos')->where('id', (int)$id)->first();
        if (! $row) return CustomResponse::error('Không tìm thấy phiếu chuyển');
        return CustomResponse::success($row);
    }

    // POST /cash/internal-transfers  (tạo DRAFT)
    public function store(Request $request)
    {
        $data = $request->validate([
            'ma_phieu'         => 'nullable|string|max:64',
            'ngay_ct'          => 'required|date',
            'tu_tai_khoan_id'  => 'required|integer|different:den_tai_khoan_id',
            'den_tai_khoan_id' => 'required|integer',
            'so_tien'          => 'required|numeric|min:0.01',
            'phi_chuyen'       => 'nullable|numeric|min:0',
            'noi_dung'         => 'nullable|string|max:255',
        ]);

        // Sinh mã nếu chưa có
        if (empty($data['ma_phieu'])) {
            $data['ma_phieu'] = $this->generateCode();
        }
        $data['trang_thai']  = 'draft';
        $data['created_at']  = now();
        $data['updated_at']  = now();

        // An toàn: insert trong transaction
        $id = DB::transaction(function () use ($data) {
            return DB::table('phieu_chuyen_noi_bos')->insertGetId($data);
        });

        $row = DB::table('phieu_chuyen_noi_bos')->where('id', $id)->first();
        return CustomResponse::success($row, 'Tạo phiếu chuyển nội bộ (draft) thành công');
    }

    // POST /cash/internal-transfers/{id}/post  (ghi sổ)
    public function post($id, CashLedgerService $ledger)
    {
        $row = DB::table('phieu_chuyen_noi_bos')->where('id', (int)$id)->first();
        if (! $row) return CustomResponse::error('Không tìm thấy phiếu chuyển');

        if ($row->trang_thai === 'locked') {
            return CustomResponse::error('Phiếu đã khóa, không thể post');
        }
        if ($row->trang_thai === 'posted') {
            // Idempotent: coi như thành công
            return CustomResponse::success($row, 'Phiếu đã được post trước đó');
        }

        DB::transaction(function () use ($row, $ledger) {
            $ledger->postInternalTransfer((object) $row);

            DB::table('phieu_chuyen_noi_bos')
                ->where('id', $row->id)
                ->update([
                    'trang_thai' => 'posted',
                    'updated_at' => now(),
                ]);
        });

        $row = DB::table('phieu_chuyen_noi_bos')->where('id', $id)->first();
        return CustomResponse::success($row, 'Post thành công');
    }

    // POST /cash/internal-transfers/{id}/unpost  (gỡ sổ)
    public function unpost($id, CashLedgerService $ledger)
    {
        $row = DB::table('phieu_chuyen_noi_bos')->where('id', (int)$id)->first();
        if (! $row) return CustomResponse::error('Không tìm thấy phiếu chuyển');

        if ($row->trang_thai === 'locked') {
            return CustomResponse::error('Phiếu đã khóa, không thể unpost');
        }
        if ($row->trang_thai !== 'posted') {
            // Chưa post ⇒ coi như thành công
            return CustomResponse::success($row, 'Phiếu đang ở trạng thái draft');
        }

        DB::transaction(function () use ($row, $ledger) {
            $ledger->unpostInternalTransfer((object) $row);

            DB::table('phieu_chuyen_noi_bos')
                ->where('id', $row->id)
                ->update([
                    'trang_thai' => 'draft',
                    'updated_at' => now(),
                ]);
        });

        $row = DB::table('phieu_chuyen_noi_bos')->where('id', $id)->first();
        return CustomResponse::success($row, 'Unpost thành công (về draft)');
    }

    // DELETE /cash/internal-transfers/{id}  (chỉ được xoá khi draft)
    public function destroy($id)
    {
        $row = DB::table('phieu_chuyen_noi_bos')->where('id', (int)$id)->first();
        if (! $row) return CustomResponse::error('Không tìm thấy phiếu chuyển');

        if ($row->trang_thai !== 'draft') {
            return CustomResponse::error('Chỉ được xoá khi phiếu ở trạng thái draft');
        }

        DB::table('phieu_chuyen_noi_bos')->where('id', $row->id)->delete();
        return CustomResponse::success([], 'Xóa phiếu chuyển nội bộ (draft) thành công');
    }

    private function generateCode(): string
    {
        $prefix = 'TRF-' . now()->format('Ymd-His') . '-';
        for ($i = 0; $i < 20; $i++) {
            $code = $prefix . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $exists = DB::table('phieu_chuyen_noi_bos')->where('ma_phieu', $code)->exists();
            if (! $exists) return $code;
        }
        return $prefix . uniqid();
    }
}
