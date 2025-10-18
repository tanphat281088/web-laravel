<?php

namespace App\Modules\SanXuat;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\ChiTietSanXuat;
use App\Models\SanPham;
use App\Models\SanXuat;
use Exception;

class SanXuatService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = SanXuat::query()
                ->withoutGlobalScope('withUserNames')
                ->leftJoin('san_phams', 'san_xuats.san_pham_id', '=', 'san_phams.id')
                ->leftJoin('don_vi_tinhs', 'san_xuats.don_vi_tinh_id', '=', 'don_vi_tinhs.id')
                ->leftJoin('users as nguoi_tao', 'san_xuats.nguoi_tao', '=', 'nguoi_tao.id')
                ->leftJoin('users as nguoi_cap_nhat', 'san_xuats.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['san_xuats.*', 'san_phams.ten_san_pham', 'don_vi_tinhs.ten_don_vi', 'nguoi_tao.name as ten_nguoi_tao', 'nguoi_cap_nhat.name as ten_nguoi_cap_nhat'] // Columns cần select
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
        $data = SanXuat::with('chiTietSanXuat.sanPham', 'chiTietSanXuat.donViTinh')->find($id);
        if (! $data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    /**
     * Tạo mới dữ liệu
     */
    public function create(array $data)
    {
        try {
            $giaCost = 0;

            foreach ($data['chi_tiet_cong_thucs'] as $key => $chiTiet) {

                $sanPham = SanPham::find($chiTiet['san_pham_id']);

                $giaCost += $sanPham->gia_nhap_mac_dinh * $chiTiet['so_luong'] * $data['so_luong'];
                $data['chi_tiet_cong_thucs'][$key]['so_luong_cong_thuc'] = $chiTiet['so_luong'];
                $data['chi_tiet_cong_thucs'][$key]['don_gia'] = $sanPham->gia_nhap_mac_dinh;
                $data['chi_tiet_cong_thucs'][$key]['so_luong_thuc_te'] = $chiTiet['so_luong'] * $data['so_luong'];
            }

            $giaThanhSanXuatChuaLamTron = ($giaCost + $data['chi_phi_khac']) / $data['so_luong'];
            $giaBanDeXuatChuaLamTron = $giaThanhSanXuatChuaLamTron * (1 + $data['loi_nhuan'] / 100);

            $data['gia_cost'] = $giaCost;
            $data['gia_thanh_san_xuat'] = ceil($giaThanhSanXuatChuaLamTron / 1000) * 1000;
            $data['gia_ban_de_xuat'] = ceil($giaBanDeXuatChuaLamTron / 1000) * 1000;

            $dataCreate = $data;
            unset($dataCreate['chi_tiet_cong_thucs']);
            $result = SanXuat::create($dataCreate);

            foreach ($data['chi_tiet_cong_thucs'] as $chiTiet) {
                $chiTiet['san_xuat_id'] = $result->id;
                unset($chiTiet['so_luong']);
                ChiTietSanXuat::create($chiTiet);
            }

            return $result;
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Cập nhật dữ liệu
     */
    public function update($id, array $data)
    {
        try {
            $sanXuat = $this->getById($id);

            if ($sanXuat->trang_thai_hoan_thanh !== 0) {
                return CustomResponse::error('Sản xuất đang được sản xuất hoặc đã hoàn thành không thể cập nhật');
            }

            if ($sanXuat->trang_thai_nhap_kho !== 0) {
                return CustomResponse::error('Sản xuất đã nhập kho không thể cập nhật');
            }

            if ($sanXuat->trang_thai_xuat_kho !== 0) {
                return CustomResponse::error('Sản xuất đã xuất kho nguyên liệu không thể cập nhật');
            }

            $giaCost = 0;

            foreach ($data['chi_tiet_cong_thucs'] as $key => $chiTiet) {

                $sanPham = SanPham::find($chiTiet['san_pham_id']);

                $giaCost += $sanPham->gia_nhap_mac_dinh * $chiTiet['so_luong'] * $data['so_luong'];
                $data['chi_tiet_cong_thucs'][$key]['so_luong_cong_thuc'] = $chiTiet['so_luong'];
                $data['chi_tiet_cong_thucs'][$key]['don_gia'] = $sanPham->gia_nhap_mac_dinh;
                $data['chi_tiet_cong_thucs'][$key]['so_luong_thuc_te'] = $chiTiet['so_luong'] * $data['so_luong'];
            }

            $giaThanhSanXuatChuaLamTron = ($giaCost + $data['chi_phi_khac']) / $data['so_luong'];
            $giaBanDeXuatChuaLamTron = $giaThanhSanXuatChuaLamTron * (1 + $data['loi_nhuan'] / 100);

            $data['gia_cost'] = $giaCost;
            $data['gia_thanh_san_xuat'] = ceil($giaThanhSanXuatChuaLamTron / 1000) * 1000;
            $data['gia_ban_de_xuat'] = ceil($giaBanDeXuatChuaLamTron / 1000) * 1000;

            $dataCreate = $data;
            unset($dataCreate['chi_tiet_cong_thucs']);
            $result = $sanXuat->update($dataCreate);

            ChiTietSanXuat::where('san_xuat_id', $sanXuat->id)->delete();

            foreach ($data['chi_tiet_cong_thucs'] as $chiTiet) {
                $chiTiet['san_xuat_id'] = $sanXuat->id;
                unset($chiTiet['so_luong']);
                ChiTietSanXuat::create($chiTiet);
            }

            return $result;
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
            $sanXuat = $this->getById($id);

            if ($sanXuat->trang_thai_hoan_thanh !== 0) {
                return CustomResponse::error('Sản xuất đang được sản xuất hoặc đã hoàn thành không thể xóa');
            }

            if ($sanXuat->trang_thai_nhap_kho !== 0) {
                return CustomResponse::error('Sản xuất đã nhập kho không thể xóa');
            }

            if ($sanXuat->trang_thai_xuat_kho !== 0) {
                return CustomResponse::error('Sản xuất đã xuất kho nguyên liệu không thể xóa');
            }

            ChiTietSanXuat::where('san_xuat_id', $sanXuat->id)->delete();

            return $sanXuat->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách SanXuat dạng option
     */
    public function getOptions(array $params = [])
    {
        $query = SanXuat::query();

        $result = FilterWithPagination::findWithPagination(
            $query,
            $params,
            ['san_xuats.id as value', 'san_xuats.ma_lo_san_xuat as label']
        );

        return $result['collection'];
    }
}
