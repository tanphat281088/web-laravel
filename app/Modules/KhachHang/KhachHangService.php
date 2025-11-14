<?php

namespace App\Modules\KhachHang;

use App\Models\KhachHang;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class KhachHangService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            $query = KhachHang::query()->with('images', 'loaiKhachHang:id,ten_loai_khach_hang');

            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['khach_hangs.*'] // gồm cả ma_kh, kenh_lien_he
            );

            return [
                'data'       => $result['collection'],
                'total'      => $result['total'],
                'pagination' => [
                    'current_page'  => $result['current_page'],
                    'last_page'     => $result['last_page'],
                    'from'          => $result['from'],
                    'to'            => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = KhachHang::with('images')->find($id);
        if (!$data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }
        return $data;
    }

    /**
     * Tạo mới dữ liệu
     * - Nếu request KHÔNG truyền ma_kh -> tự cấp theo rule KH + 5 số (nhìn MAX đúng pattern rồi +1)
     * - Dùng transaction + lock để tránh trùng khi 2 request chạy song song
     */
    public function create(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                /** @var \App\Models\KhachHang $model */
                $model = KhachHang::create($data);

                // chỉ cấp khi không truyền ma_kh
                if (empty($model->ma_kh)) {
                    $model->ma_kh = $this->nextIncrementalCode();
                    $model->save();
                }

                return $model;
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     * - Không đổi ma_kh khi update (đảm bảo tính nhất quán).
     * - Nếu vì lý do nào đó ma_kh còn trống -> tự backfill theo rule mới.
     */
    public function update($id, array $data)
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $model = KhachHang::findOrFail($id);
                $model->update($data);

                if (empty($model->ma_kh)) {
                    $model->ma_kh = $this->nextIncrementalCode();
                    $model->save();
                }

                return $model->fresh();
            });
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            $model = KhachHang::findOrFail($id);
            return $model->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách option hiển thị cả mã KH
     */
    public function getOptions(array $params = [])
    {
        // nhận keyword từ nhiều khóa để tương thích FE
        $kw    = trim($params['keyword'] ?? $params['q'] ?? $params['search'] ?? $params['term'] ?? '');
        $limit = (int)($params['limit'] ?? 30);

        $query = KhachHang::query()
            ->select(
                'id as value',
                DB::raw('CONCAT(ma_kh, " - ", ten_khach_hang, " - ", COALESCE(so_dien_thoai, "")) as label')
            )
            ->orderBy('ma_kh');

        if ($kw !== '') {
            $query->where(function ($q) use ($kw) {
                $q->where('ma_kh', 'like', "%{$kw}%")
                  ->orWhere('ten_khach_hang', 'like', "%{$kw}%")
                  ->orWhere('so_dien_thoai', 'like', "%{$kw}%");
            });
        }

        return $query->limit($limit)->get();
    }

    /**
     * Sinh mã KH theo đúng pattern 'KH' + 5 số:
     * - Chỉ xét các mã hợp lệ (REGEXP '^KH[0-9]{5}$'), bỏ qua mã rác nếu có
     * - Lấy MAX rồi +1
     * - Dùng lockForUpdate để tránh race-condition
     */
    private function nextIncrementalCode(): string
    {
        $row = DB::table('khach_hangs')
            ->whereRaw("ma_kh REGEXP '^KH[0-9]{5}$'")
            ->selectRaw('MAX(CAST(SUBSTRING(ma_kh, 3) AS UNSIGNED)) AS max_num')
            ->lockForUpdate()
            ->first();

        $next = (int)($row->max_num ?? 0) + 1;
        return 'KH' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
    }
}
