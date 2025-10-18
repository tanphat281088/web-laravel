<?php

namespace App\Modules\QuanLyBanHang;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use Exception;
use Illuminate\Support\Facades\DB;

class QuanLyBanHangService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = DonHang::query()->with('images');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['don_hangs.*'] // Columns cần select
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
        $data = DonHang::with('khachHang', 'chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')->find($id);
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
        DB::beginTransaction();
        try {
            $tongTienHang = 0;

            foreach ($data['danh_sach_san_pham'] as $index => $item) {
                $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'])->where('don_vi_tinh_id', $item['don_vi_tinh_id'])->orderBy('id', 'asc')->first();

                if ($loSanPham) {
                    $data['danh_sach_san_pham'][$index]['don_gia'] = $loSanPham->gia_ban_le_don_vi;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = $item['so_luong'] * $data['danh_sach_san_pham'][$index]['don_gia'];
                } else {
                    $sanPham = SanPham::find($item['san_pham_id']);

                    if ($sanPham) {
                        $data['danh_sach_san_pham'][$index]['don_gia'] = $sanPham->gia_nhap_mac_dinh + ($sanPham->gia_nhap_mac_dinh * $sanPham->muc_loi_nhuan / 100);
                        $data['danh_sach_san_pham'][$index]['thanh_tien'] = $item['so_luong'] * $data['danh_sach_san_pham'][$index]['don_gia'];
                    } else {
                        throw new Exception('Sản phẩm '.$item['san_pham_id'].' không tồn tại');
                    }
                }

                $tongTienHang += $data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $tongTienCanThanhToan = $tongTienHang - $data['giam_gia'] + $data['chi_phi'];

            if ($data['so_tien_da_thanh_toan'] > $tongTienCanThanhToan) {
                throw new Exception('Số tiền đã thanh toán không được lớn hơn tổng tiền cần thanh toán');
            }

            $data['tong_tien_hang'] = $tongTienHang;
            $data['tong_tien_can_thanh_toan'] = $tongTienCanThanhToan;
            $data['tong_so_luong_san_pham'] = count($data['danh_sach_san_pham']);
            $data['trang_thai_thanh_toan'] = $data['so_tien_da_thanh_toan'] == $tongTienCanThanhToan ? 1 : 0;

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);

                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai'] = $khachHang->so_dien_thoai;
                }
            }

            $dataDonHang = $data;
            unset($dataDonHang['danh_sach_san_pham']);
            $donHang = DonHang::create($dataDonHang);

            foreach ($data['danh_sach_san_pham'] as $item) {
                $item['don_hang_id'] = $donHang->id;

                ChiTietDonHang::create($item);
            }

            DB::commit();

            return $donHang;
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
        DB::beginTransaction();
        $donHang = $this->getById($id);

        if ($donHang->phieuThu()->exists() || $donHang->chiTietPhieuThu()->exists()) {
            throw new Exception('Đơn hàng đã có phiếu thu, không thể cập nhật');
        }

        try {
            $tongTienHang = 0;

            foreach ($data['danh_sach_san_pham'] as $index => $item) {
                $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'])->where('don_vi_tinh_id', $item['don_vi_tinh_id'])->orderBy('id', 'asc')->first();

                if ($loSanPham) {
                    $data['danh_sach_san_pham'][$index]['don_gia'] = $loSanPham->gia_ban_le_don_vi;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = $item['so_luong'] * $data['danh_sach_san_pham'][$index]['don_gia'];
                } else {
                    $sanPham = SanPham::find($item['san_pham_id']);

                    if ($sanPham) {
                        $data['danh_sach_san_pham'][$index]['don_gia'] = $sanPham->gia_nhap_mac_dinh + ($sanPham->gia_nhap_mac_dinh * $sanPham->muc_loi_nhuan / 100);
                        $data['danh_sach_san_pham'][$index]['thanh_tien'] = $item['so_luong'] * $data['danh_sach_san_pham'][$index]['don_gia'];
                    } else {
                        throw new Exception('Sản phẩm '.$item['san_pham_id'].' không tồn tại');
                    }
                }

                $tongTienHang += $data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $tongTienCanThanhToan = $tongTienHang - $data['giam_gia'] + $data['chi_phi'];

            if ($data['so_tien_da_thanh_toan'] > $tongTienCanThanhToan) {
                throw new Exception('Số tiền đã thanh toán không được lớn hơn tổng tiền cần thanh toán');
            }

            $data['tong_tien_hang'] = $tongTienHang;
            $data['tong_tien_can_thanh_toan'] = $tongTienCanThanhToan;
            $data['tong_so_luong_san_pham'] = count($data['danh_sach_san_pham']);
            $data['trang_thai_thanh_toan'] = $data['so_tien_da_thanh_toan'] == $tongTienCanThanhToan ? 1 : 0;

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);

                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai'] = $khachHang->so_dien_thoai;
                }
            }

            $dataDonHang = $data;
            unset($dataDonHang['danh_sach_san_pham']);
            $donHang->update($dataDonHang);

            $donHang->chiTietDonHangs()->delete();

            foreach ($data['danh_sach_san_pham'] as $item) {
                $item['don_hang_id'] = $donHang->id;

                ChiTietDonHang::create($item);
            }

            DB::commit();

            return $donHang->refresh();
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
            $donHang = $this->getById($id);

            if ($donHang->phieuThu()->exists() || $donHang->chiTietPhieuThu()->exists()) {
                throw new Exception('Đơn hàng đã có phiếu thu, không thể xóa');
            }

            $donHang->chiTietDonHangs()->delete();

            return $donHang->delete();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách QuanLyBanHang dạng option
     */
    public function getOptions(array $params = [])
    {
        $query = DonHang::query();

        $result = FilterWithPagination::findWithPagination(
            $query,
            $params,
            ['don_hangs.id as value', 'don_hangs.ma_don_hang as label']
        );

        return $result['collection'];
    }

    /**
     * Lấy giá bán sản phẩm
     * Giữ tương thích cũ, thêm tham số $loaiGia (1 = Đặt ngay, 2 = Đặt trước 3 ngày).
     * - Nếu có lô nhập kho: ưu tiên trả giá lẻ theo lô (logic cũ).
     * - Nếu không có lô: tính theo loại giá đã chọn + cộng mức lợi nhuận.
     */
    public function getGiaBanSanPham($sanPhamId, $donViTinhId, $loaiGia = 1)
    {
        // Ưu tiên giá theo lô (giữ nguyên hành vi cũ)
        $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $sanPhamId)
            ->where('don_vi_tinh_id', $donViTinhId)
            ->orderBy('id', 'asc')
            ->first();

        if ($loSanPham) {
            return $loSanPham->gia_ban_le_don_vi;
        }

        // Không có lô → chọn giá theo loại
        $sanPham = SanPham::find($sanPhamId);
        if ($sanPham) {
            // base = giá đặt ngay hoặc giá đặt trước 3 ngày
            $base = (int) ($loaiGia == 2
                ? ($sanPham->gia_dat_truoc_3n ?? 0)
                : ($sanPham->gia_nhap_mac_dinh ?? 0));

            $mucLoiNhuan = (float) ($sanPham->muc_loi_nhuan ?? 0);

            return (int) round($base + ($base * $mucLoiNhuan / 100));
        }

        return null;
    }

    /**
     * Xem trước hóa đơn (HTML)
     */
    public function xemTruocHoaDon($id)
    {
        try {
            $donHang = $this->getById($id);

            if (! $donHang) {
                return CustomResponse::error('Đơn hàng không tồn tại');
            }

            return view('hoa-don.template', compact('donHang'));
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xem trước hóa đơn: '.$e->getMessage());
        }
    }

    public function getSanPhamByDonHangId($donHangId)
    {
        return DonHang::with('chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')->where('id', $donHangId)->first();
    }

    public function getDonHangByKhachHangId($khachHangId)
    {
        return DonHang::with('khachHang')->where('khach_hang_id', $khachHangId)->where('trang_thai_thanh_toan', 0)->get();
    }

    public function getSoTienCanThanhToan($donHangId)
    {
        $donHang = $this->getById($donHangId);

        return $donHang->tong_tien_can_thanh_toan - $donHang->so_tien_da_thanh_toan;
    }
}
