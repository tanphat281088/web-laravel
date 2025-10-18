<?php

namespace App\Modules\CongThucSanXuat;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\ChiTietCongThuc;
use App\Models\CongThucSanXuat;
use Exception;
use Illuminate\Support\Facades\DB;

class CongThucSanXuatService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = CongThucSanXuat::query()
                ->withoutGlobalScope('withUserNames')
                ->leftJoin('san_phams', 'cong_thuc_san_xuats.san_pham_id', '=', 'san_phams.id')
                ->leftJoin('don_vi_tinhs', 'cong_thuc_san_xuats.don_vi_tinh_id', '=', 'don_vi_tinhs.id')
                ->leftJoin('users as nguoi_tao', 'cong_thuc_san_xuats.nguoi_tao', '=', 'nguoi_tao.id')
                ->leftJoin('users as nguoi_cap_nhat', 'cong_thuc_san_xuats.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['cong_thuc_san_xuats.*', 'san_phams.ten_san_pham', 'don_vi_tinhs.ten_don_vi', 'nguoi_tao.name as ten_nguoi_tao', 'nguoi_cap_nhat.name as ten_nguoi_cap_nhat'] // Columns cần select
            );

            return [
                'data' => $result['collection'],
                'total' => $result['total'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'from' => $result['from'],
                    'to' => $result['to'],
                    'total_current' => $result['total_current'],
                ],
            ];
        } catch (Exception $e) {
            throw new Exception('Lỗi khi lấy danh sách: '.$e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = CongThucSanXuat::with([
            'sanPham',
            'donViTinh',
            'chiTietCongThucs' => function ($query) use ($id) {
                // Lấy lan_cap_nhat mới nhất
                $maxLanCapNhat = ChiTietCongThuc::where('cong_thuc_san_xuat_id', $id)->max('lan_cap_nhat');
                // Chỉ lấy các chi tiết có lan_cap_nhat = max
                $query->where('lan_cap_nhat', $maxLanCapNhat)
                    ->with(['sanPham', 'donViTinh']);
            },
        ])->find($id);

        if (! $data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    public function getBySanPhamIdAndDonViTinhId($sanPhamId, $donViTinhId)
    {
        $congThucSanXuat = CongThucSanXuat::where('san_pham_id', $sanPhamId)->where('don_vi_tinh_id', $donViTinhId)->first();

        if (! $congThucSanXuat) {
            return CustomResponse::error('Công thức sản xuất của sản phẩm với đơn vị tính này không tồn tại');
        }

        $data = CongThucSanXuat::with([
            'sanPham',
            'donViTinh',
            'chiTietCongThucs' => function ($query) use ($congThucSanXuat) {
                // Lấy lan_cap_nhat mới nhất
                $maxLanCapNhat = ChiTietCongThuc::where('cong_thuc_san_xuat_id', $congThucSanXuat->id)
                    ->max('lan_cap_nhat');
                // Chỉ lấy các chi tiết có lan_cap_nhat = max
                $query->where('lan_cap_nhat', $maxLanCapNhat)
                    ->with(['sanPham', 'donViTinh']);
            },
        ])->find($congThucSanXuat->id);

        if (! $data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    public function getLichSuCapNhat($id)
    {
        $this->getById($id);

        $data = ChiTietCongThuc::where('cong_thuc_san_xuat_id', $id)
            ->withoutGlobalScope('withUserNames')
            ->leftJoin('san_phams', 'chi_tiet_cong_thucs.san_pham_id', '=', 'san_phams.id')
            ->leftJoin('don_vi_tinhs', 'chi_tiet_cong_thucs.don_vi_tinh_id', '=', 'don_vi_tinhs.id')
            ->leftJoin('users as nguoi_tao', 'chi_tiet_cong_thucs.nguoi_tao', '=', 'nguoi_tao.id')
            ->leftJoin('users as nguoi_cap_nhat', 'chi_tiet_cong_thucs.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id')
            ->select('chi_tiet_cong_thucs.*', 'san_phams.ten_san_pham', 'don_vi_tinhs.ten_don_vi', 'nguoi_tao.name as ten_nguoi_tao', 'nguoi_cap_nhat.name as ten_nguoi_cap_nhat')
            ->orderBy('thoi_gian_cap_nhat', 'desc')
            ->get();

        // Group theo thoi_gian_cap_nhat
        $groupedData = $data->groupBy('thoi_gian_cap_nhat')->map(function ($items) {
            return $items->values(); // Chuyển collection thành array đơn giản
        });

        return $groupedData;
    }

    /**
     * Tạo mới dữ liệu
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $congThucSanXuat = CongThucSanXuat::where([
                'san_pham_id' => $data['san_pham_id'],
                'don_vi_tinh_id' => $data['don_vi_tinh_id'],
            ])->first();

            if ($congThucSanXuat) {
                throw new Exception('Công thức sản xuất của sản phẩm với đơn vị tính này đã tồn tại');
            }

            $dataCreate = $data;
            unset($dataCreate['chi_tiet_cong_thucs']);
            $result = CongThucSanXuat::create($dataCreate);

            $thoiGianCapNhat = now();
            $lanCapNhatGanNhat = ChiTietCongThuc::where('cong_thuc_san_xuat_id', $result->id)->max('lan_cap_nhat') ?? 0;

            foreach ($data['chi_tiet_cong_thucs'] as $chi_tiet_cong_thuc) {
                $chi_tiet_cong_thuc['cong_thuc_san_xuat_id'] = $result->id;
                $chi_tiet_cong_thuc['lan_cap_nhat'] = $lanCapNhatGanNhat + 1;
                $chi_tiet_cong_thuc['thoi_gian_cap_nhat'] = $thoiGianCapNhat;

                ChiTietCongThuc::create($chi_tiet_cong_thuc);
            }

            DB::commit();

            return $result;
        } catch (Exception $e) {
            DB::rollBack();

            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     */
    public function update($id, array $data)
    {
        try {
            DB::beginTransaction();

            $congThucSanXuat = $this->getById($id);

            $dataCreate = $data;
            unset($dataCreate['chi_tiet_cong_thucs']);
            $result = $congThucSanXuat->update($dataCreate);

            $thoiGianCapNhat = now();
            $lanCapNhatGanNhat = ChiTietCongThuc::where('cong_thuc_san_xuat_id', $id)->max('lan_cap_nhat');

            foreach ($data['chi_tiet_cong_thucs'] as $chi_tiet_cong_thuc) {
                $chi_tiet_cong_thuc['cong_thuc_san_xuat_id'] = $id;
                $chi_tiet_cong_thuc['lan_cap_nhat'] = $lanCapNhatGanNhat + 1;
                $chi_tiet_cong_thuc['thoi_gian_cap_nhat'] = $thoiGianCapNhat;

                ChiTietCongThuc::create($chi_tiet_cong_thuc);
            }

            DB::commit();

            return $result;
        } catch (Exception $e) {
            DB::rollBack();

            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            DB::beginTransaction();
            $congThucSanXuat = $this->getById($id);

            $congThucSanXuat->chiTietCongThucs()->delete();

            $congThucSanXuat->delete();

            DB::commit();

            return CustomResponse::success('Xóa dữ liệu thành công');
        } catch (Exception $e) {
            DB::rollBack();

            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách CongThucSanXuat dạng option
     */
    public function getOptions(array $params = [])
    {
        $query = CongThucSanXuat::query();

        $result = FilterWithPagination::findWithPagination(
            $query,
            $params,
            ['cong_thuc_san_xuats.id as value', 'cong_thuc_san_xuats.ten_cong_thuc_san_xuat as label']
        );

        return $result['collection'];
    }
}
