<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Models\TaiKhoanAlias;
use App\Class\CustomResponse;
use Illuminate\Http\Request;

class CashAliasController extends Controller
{
    // GET /cash/aliases
    public function index(Request $request)
    {
        $q = TaiKhoanAlias::query()->with('taiKhoan:id,ten_tk,loai,ngan_hang,so_tai_khoan');

        if ($request->filled('tai_khoan_id')) {
            $q->where('tai_khoan_id', (int)$request->get('tai_khoan_id'));
        }
        if ($request->filled('active')) {
            $q->where('is_active', (int)$request->get('active') === 1);
        }

        $rows = $q->orderBy('tai_khoan_id')->orderByDesc('is_active')->get();

        return CustomResponse::success([
            'collection' => $rows,
            'total'      => $rows->count(),
        ]);
    }

    // POST /cash/aliases  → Tạo alias (an toàn)
    public function store(Request $request)
    {
        $data = $request->validate([
            'tai_khoan_id'   => 'required|integer|exists:tai_khoan_tiens,id',
            'pattern_bank'   => 'nullable|string|max:191',
            'pattern_account'=> 'nullable|string|max:191',
            'pattern_note'   => 'nullable|string|max:191',
            'is_active'      => 'nullable|boolean',
        ]);

        // Không cho tạo alias “trống cả 3 pattern”
        if (empty($data['pattern_bank']) && empty($data['pattern_account']) && empty($data['pattern_note'])) {
            return CustomResponse::error('Cần nhập ít nhất 1 trong 3: pattern_bank / pattern_account / pattern_note');
        }

        // Tránh trùng (same tài khoản + cùng 3 pattern)
        $exists = \DB::table('tai_khoan_aliases')->where([
            'tai_khoan_id'    => $data['tai_khoan_id'],
            'pattern_bank'    => $data['pattern_bank']   ?? null,
            'pattern_account' => $data['pattern_account']?? null,
            'pattern_note'    => $data['pattern_note']   ?? null,
        ])->exists();

        if ($exists) {
            return CustomResponse::error('Alias đã tồn tại cho tài khoản này');
        }

        $id = \DB::table('tai_khoan_aliases')->insertGetId([
            'tai_khoan_id'    => $data['tai_khoan_id'],
            'pattern_bank'    => $data['pattern_bank']   ?? null,
            'pattern_account' => $data['pattern_account']?? null,
            'pattern_note'    => $data['pattern_note']   ?? null,
            'is_active'       => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $row = \DB::table('tai_khoan_aliases')->where('id', $id)->first();
        return CustomResponse::success($row, 'Tạo alias thành công');
    }

    // PUT /cash/aliases/{id}  → Cập nhật alias
    public function update($id, Request $request)
    {
        $row = \DB::table('tai_khoan_aliases')->where('id', (int)$id)->first();
        if (! $row) return CustomResponse::error('Không tìm thấy alias');

        $data = $request->validate([
            'pattern_bank'    => 'nullable|string|max:191',
            'pattern_account' => 'nullable|string|max:191',
            'pattern_note'    => 'nullable|string|max:191',
            'is_active'       => 'nullable|boolean',
        ]);

        // Nếu sửa về trống cả 3 pattern → chặn
        $mergeBank   = array_key_exists('pattern_bank',    $data) ? $data['pattern_bank']    : $row->pattern_bank;
        $mergeAcc    = array_key_exists('pattern_account', $data) ? $data['pattern_account'] : $row->pattern_account;
        $mergeNote   = array_key_exists('pattern_note',    $data) ? $data['pattern_note']    : $row->pattern_note;

        if (empty($mergeBank) && empty($mergeAcc) && empty($mergeNote)) {
            return CustomResponse::error('Alias cần ít nhất 1 trong 3 pattern');
        }

        $payload = [
            'pattern_bank'    => $data['pattern_bank']    ?? $row->pattern_bank,
            'pattern_account' => $data['pattern_account'] ?? $row->pattern_account,
            'pattern_note'    => $data['pattern_note']    ?? $row->pattern_note,
            'is_active'       => array_key_exists('is_active', $data) ? (int)$data['is_active'] : (int)$row->is_active,
            'updated_at'      => now(),
        ];

        \DB::table('tai_khoan_aliases')->where('id', $row->id)->update($payload);
        $row = \DB::table('tai_khoan_aliases')->where('id', $row->id)->first();

        return CustomResponse::success($row, 'Cập nhật alias thành công');
    }

    // DELETE /cash/aliases/{id}  → Xóa alias
    public function destroy($id)
    {
        $row = \DB::table('tai_khoan_aliases')->where('id', (int)$id)->first();
        if (! $row) return CustomResponse::error('Không tìm thấy alias');

        \DB::table('tai_khoan_aliases')->where('id', $row->id)->delete();
        return CustomResponse::success([], 'Đã xóa alias');
    }



}
