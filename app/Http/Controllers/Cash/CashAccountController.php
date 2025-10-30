<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Models\TaiKhoanTien;
use App\Class\CustomResponse;
use Illuminate\Http\Request;

class CashAccountController extends Controller
{
    // GET /cash/accounts
    public function index(Request $request)
    {
        $q = TaiKhoanTien::query();

        // filter cơ bản: active, loai
        if ($request->filled('active')) {
            $active = (int)$request->get('active') === 1;
            $q->where('is_active', $active);
        }
        if ($request->filled('loai')) {
            $q->where('loai', $request->get('loai'));
        }

        // sắp xếp mặc định theo loai → tên
        $rows = $q->orderBy('loai')->orderBy('ten_tk')->get();

        return CustomResponse::success([
            'collection' => $rows,
            'total'      => $rows->count(),
        ]);
    }

    // GET /cash/accounts/options
    public function options(Request $request)
    {
        $rows = TaiKhoanTien::query()
            ->when($request->filled('active'), fn($qq) => $qq->where('is_active', (int)$request->get('active') === 1))
            ->orderBy('loai')->orderBy('ten_tk')
            ->get()
            ->map(function ($r) {
                $label = $r->ten_tk;
                if ($r->loai !== 'cash') {
                    $bank  = trim((string)($r->ngan_hang ?? ''));
                    $accno = trim((string)($r->so_tai_khoan ?? ''));
                    if ($bank || $accno) {
                        $label .= " ({$bank}" . ($accno ? (": {$accno}") : '') . ')';
                    }
                }

                return [
                    'value' => $r->id,
                    'label' => $label,
                    'extra' => [
                        'loai'        => $r->loai,
                        'ngan_hang'   => $r->ngan_hang,
                        'so_tai_khoan'=> $r->so_tai_khoan,
                        'is_default_cash' => (bool)$r->is_default_cash,
                    ],
                ];
            });

// Ẩn 'Tiền mặt' (loai = cash) và các dòng có chữ 'chưa gắn' trong nhãn
$rows = $rows->filter(function ($r) {
    $loai  = $r['extra']['loai'] ?? null;
    $label = strtolower($r['label'] ?? '');
    if ($loai === 'cash') return false;                 // ẩn Tiền mặt
    if (str_contains($label, 'chưa gắn')) return false; // ẩn 'Chưa gắn tài khoản'
    return true;
});


        return CustomResponse::success($rows->values());
    }

    // POST /cash/accounts  → Tạo tài khoản tiền (an toàn)
    public function store(Request $request)
    {
        $data = $request->validate([
            'ma_tk'           => 'required|string|max:64|unique:tai_khoan_tiens,ma_tk',
            'ten_tk'          => 'required|string|max:191',
            'loai'            => 'required|string|in:cash,bank,ewallet',
            'so_tai_khoan'    => 'nullable|string|max:191',
            'ngan_hang'       => 'nullable|string|max:191',
            'is_default_cash' => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'ghi_chu'         => 'nullable|string',
        ]);

        // Nếu là cash → xoá info bank
        if (($data['loai'] ?? '') === 'cash') {
            $data['so_tai_khoan'] = null;
            $data['ngan_hang']    = null;
        }

        // Nếu set is_default_cash = true → unset các TK cash khác
        if (!empty($data['is_default_cash'])) {
            DB::table('tai_khoan_tiens')->update(['is_default_cash' => 0]);
            $data['is_default_cash'] = 1;
        }

        $data['is_active']    = array_key_exists('is_active', $data) ? (int)$data['is_active'] : 1;
        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['created_at']   = now();
        $data['updated_at']   = now();

        $id = DB::table('tai_khoan_tiens')->insertGetId($data);
        $row = DB::table('tai_khoan_tiens')->where('id', $id)->first();

        return \App\Class\CustomResponse::success($row, 'Tạo tài khoản thành công');
    }

    // PUT /cash/accounts/{id}  → Cập nhật (an toàn, không phá dữ liệu)
    public function update($id, Request $request)
    {
        $row = DB::table('tai_khoan_tiens')->where('id', (int)$id)->first();
        if (! $row) return \App\Class\CustomResponse::error('Không tìm thấy tài khoản');

        $data = $request->validate([
            'ten_tk'          => 'sometimes|required|string|max:191',
            'loai'            => 'sometimes|required|string|in:cash,bank,ewallet',
            'so_tai_khoan'    => 'nullable|string|max:191',
            'ngan_hang'       => 'nullable|string|max:191',
            'is_default_cash' => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'opening_balance' => 'nullable|numeric',
            'opening_date'    => 'nullable|date',
            'ghi_chu'         => 'nullable|string',
        ]);

        if (($data['loai'] ?? $row->loai) === 'cash') {
            $data['so_tai_khoan'] = null;
            $data['ngan_hang']    = null;
        }

        // Nếu set is_default_cash = true → unset TK cash khác
        if (array_key_exists('is_default_cash', $data) && (int)$data['is_default_cash'] === 1) {
            DB::table('tai_khoan_tiens')->update(['is_default_cash' => 0]);
            $data['is_default_cash'] = 1;
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (int)$data['is_active'];
        }

        $data['updated_at'] = now();

        DB::table('tai_khoan_tiens')->where('id', $row->id)->update($data);
        $row = DB::table('tai_khoan_tiens')->where('id', $row->id)->first();

        return \App\Class\CustomResponse::success($row, 'Cập nhật tài khoản thành công');
    }

    // DELETE /cash/accounts/{id}  → Chỉ cho phép nếu chưa có phát sinh
    public function destroy($id)
    {
        $row = DB::table('tai_khoan_tiens')->where('id', (int)$id)->first();
        if (! $row) return \App\Class\CustomResponse::error('Không tìm thấy tài khoản');

        // An toàn: nếu có phát sinh ở sổ quỹ hoặc chuyển nội bộ thì không cho xóa
        $hasLedger = DB::table('so_quy_entries')->where('tai_khoan_id', $row->id)->exists();
        $hasTransfer = DB::table('phieu_chuyen_noi_bos')
            ->where(function($q) use ($row) {
                $q->where('tu_tai_khoan_id', $row->id)
                  ->orWhere('den_tai_khoan_id', $row->id);
            })->exists();

        if ($hasLedger || $hasTransfer) {
            return \App\Class\CustomResponse::error('Tài khoản đã có phát sinh, không thể xóa. Vui lòng chuyển "không hoạt động" (is_active = 0).');
        }

        DB::table('tai_khoan_tiens')->where('id', $row->id)->delete();
        return \App\Class\CustomResponse::success([], 'Đã xóa tài khoản (chưa phát sinh).');
    }


}
