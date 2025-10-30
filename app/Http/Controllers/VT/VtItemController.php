<?php

namespace App\Http\Controllers\VT;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\VtItem;
use Illuminate\Http\Request;

class VtItemController extends Controller
{
    /**
     * Sinh mã VT dạng VT-YYYYMMDD-HHMMSS-XXXX (uppercase), đảm bảo unique.
     */
    protected function generateMaVt(): string
    {
        $attempts = 0;
        do {
            $code = sprintf(
                'VT-%s-%04d',
                now()->format('YmdHis'),
                random_int(0, 9999)
            );
            $code = strtoupper($code);
            $exists = VtItem::where('ma_vt', $code)->exists();
            $attempts++;
        } while ($exists && $attempts < 5);

        // Fallback cực hiếm khi va chạm nhiều lần
        if ($exists) {
            $code = 'VT-'.now()->format('YmdHis').'-'.strtoupper(substr(uniqid('', true), -4));
        }

        return $code;
    }

    public function index(Request $request)
    {
        $q        = VtItem::query()->orderByDesc('id');
        $term     = trim((string) $request->get('q', ''));
        $loai     = $request->get('loai');             // ASSET|CONSUMABLE
        $danhMuc  = $request->get('danh_muc_vt');
        $nhom     = $request->get('nhom_vt');
        $active   = $request->boolean('active_only', false);
        $perPage  = (int)($request->get('per_page', 20));
        $page     = (int)($request->get('page', 1));

        if ($term !== '') {
            $like = '%'.$term.'%';
            $q->where(function($qq) use ($like) {
                $qq->where('ma_vt','like',$like)
                   ->orWhere('ten_vt','like',$like)
                   ->orWhere('danh_muc_vt','like',$like)
                   ->orWhere('nhom_vt','like',$like);
            });
        }
        if ($loai)    $q->where('loai', $loai);
        if ($danhMuc) $q->where('danh_muc_vt', $danhMuc);
        if ($nhom)    $q->where('nhom_vt', $nhom);
        if ($active)  $q->where('trang_thai', 1);

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
        // MÃ VT: cho phép nullable để BE tự sinh nếu client không gửi
        $data = $request->validate([
            'ma_vt'        => 'nullable|string|max:50|unique:vt_items,ma_vt',
            'ten_vt'       => 'required|string|max:255',
            'danh_muc_vt'  => 'nullable|string|max:255',
            'nhom_vt'      => 'nullable|string|max:255',
            'don_vi_tinh'  => 'nullable|string|max:50',
            'loai'         => 'required|in:ASSET,CONSUMABLE',
            'trang_thai'   => 'nullable|in:0,1',
            'ghi_chu'      => 'nullable|string',
        ]);

        // Tự sinh mã nếu trống
        $code = trim((string)($data['ma_vt'] ?? ''));
        if ($code === '') {
            $code = $this->generateMaVt();
        } else {
            $code = strtoupper(trim($code));
        }

        $item = VtItem::create([
            'ma_vt'         => $code,
            'ten_vt'        => $data['ten_vt'],
            'danh_muc_vt'   => $data['danh_muc_vt'] ?? null,
            'nhom_vt'       => $data['nhom_vt'] ?? null,
            'don_vi_tinh'   => $data['don_vi_tinh'] ?? null,
            'loai'          => $data['loai'],
            'trang_thai'    => $data['trang_thai'] ?? 1,
            'ghi_chu'       => $data['ghi_chu'] ?? null,
            'nguoi_tao'     => auth()->id(),
            'nguoi_cap_nhat'=> auth()->id(),
        ]);

        return CustomResponse::success($item, 'Tạo vật tư thành công');
    }

    public function show($id)
    {
        $item = VtItem::find($id);
        if (!$item) return CustomResponse::error('Không tìm thấy vật tư', 404);
        return CustomResponse::success($item);
    }

    public function update(Request $request, $id)
    {
        $item = VtItem::find($id);
        if (!$item) return CustomResponse::error('Không tìm thấy vật tư', 404);

        // KHÓA KHÔNG CHO ĐỔI MÃ VT: bỏ rule ma_vt, và sẽ ignore nếu client gửi vào
        $data = $request->validate([
            // 'ma_vt'      => 'sometimes|string|max:50|unique:vt_items,ma_vt,'.$item->id, // <-- bỏ
            'ten_vt'       => 'sometimes|string|max:255',
            'danh_muc_vt'  => 'nullable|string|max:255',
            'nhom_vt'      => 'nullable|string|max:255',
            'don_vi_tinh'  => 'nullable|string|max:50',
            'loai'         => 'sometimes|in:ASSET,CONSUMABLE',
            'trang_thai'   => 'nullable|in:0,1',
            'ghi_chu'      => 'nullable|string',
        ]);

        // Loại bỏ ma_vt nếu có trong payload
        $update = $data;
        unset($update['ma_vt']);

        $item->update([
            ...$update,
            'nguoi_cap_nhat' => auth()->id(),
        ]);

        return CustomResponse::success($item->fresh(), 'Cập nhật vật tư thành công');
    }

    public function destroy($id)
    {
        $item = VtItem::find($id);
        if (!$item) return CustomResponse::error('Không tìm thấy vật tư', 404);
        $item->delete();
        return CustomResponse::success([], 'Xóa vật tư thành công');
    }

    public function options(Request $request)
    {
        $q    = trim((string)$request->get('q',''));
        $loai = $request->get('loai'); // ASSET|CONSUMABLE

        $query = VtItem::query()->select('id','ma_vt','ten_vt','don_vi_tinh','loai')->where('trang_thai',1);

        if ($loai) $query->where('loai', $loai);
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function($qq) use ($like){
                $qq->where('ma_vt','like',$like)->orWhere('ten_vt','like',$like);
            });
        }

        $rows = $query->orderBy('ten_vt')->limit(200)->get()
            ->map(fn($r) => [
                'value' => $r->id,
                'label' => "{$r->ma_vt} - {$r->ten_vt}",
                'ma_vt' => $r->ma_vt,
                'don_vi_tinh' => $r->don_vi_tinh,
                'loai' => $r->loai,
            ]);

        return CustomResponse::success($rows);
    }

    /**
     * Import tồn đầu từ JSON rows (nhanh). Hỗ trợ Excel/CSV sẽ bổ sung sau.
     * rows: [{ma_vt, ten_vt, loai, danh_muc_vt, nhom_vt, don_vi_tinh, so_luong, don_gia?, ngay_ct?}, ...]
     */
    public function importOpening(Request $request)
    {
        $rows = $request->input('rows');
        if (!is_array($rows) || empty($rows)) {
            return CustomResponse::error('Thiếu rows (mảng dữ liệu import)');
        }

        // Ủy quyền cho LedgerService (File #8)
        $service = app(\App\Services\VT\VtLedgerService::class);
        $result  = $service->importOpening($rows);

        return CustomResponse::success($result, 'Import tồn đầu đã xử lý');
    }
}
