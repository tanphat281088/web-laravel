<?php

namespace App\Modules\PhieuXuatKho;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\ChiTietPhieuXuatKho;
use App\Models\ChiTietSanXuat;
use App\Models\DonHang;
use App\Models\KhoTong;
use App\Models\PhieuXuatKho;
use App\Models\SanXuat;
use Exception;
use Illuminate\Support\Facades\DB;

class PhieuXuatKhoService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = PhieuXuatKho::query()
                ->withoutGlobalScope('withUserNames')
                ->leftJoin('don_hangs', 'phieu_xuat_khos.don_hang_id', '=', 'don_hangs.id')
                ->leftJoin('users', 'phieu_xuat_khos.nguoi_tao', '=', 'users.id')
                ->leftJoin('users as users_update', 'phieu_xuat_khos.nguoi_cap_nhat', '=', 'users_update.id');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['phieu_xuat_khos.*', 'don_hangs.ma_don_hang', 'users.name as ten_nguoi_tao', 'users_update.name as ten_nguoi_cap_nhat'] // Columns cần select
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
        $data = PhieuXuatKho::with('images', 'chiTietPhieuXuatKhos')->find($id);
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
            $result = DB::transaction(function () use (&$data) {
                if (! in_array($data['loai_phieu_xuat'], [1, 2, 3])) {
                    throw new Exception('Loại phiếu xuất kho không hợp lệ');
                }
                $phieuXuatKho = new PhieuXuatKho;

                return $this->processChiTietXuatKho($phieuXuatKho, $data, 'create');
            });

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
        $phieuXuatKho = PhieuXuatKho::with('donHang', 'sanXuat')->find($id);

        if (! $phieuXuatKho) {
            return CustomResponse::error('Phiếu xuất kho không tồn tại');
        }

        try {
            DB::transaction(function () use ($phieuXuatKho, &$data) {
                $this->revertChiTietXuatKho($phieuXuatKho);
                ChiTietPhieuXuatKho::where('phieu_xuat_kho_id', $phieuXuatKho->id)->delete();
                $this->processChiTietXuatKho($phieuXuatKho, $data, 'update');
            });

            return $phieuXuatKho;
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        $phieuXuatKho = PhieuXuatKho::with('donHang', 'sanXuat')->find($id);

        if (! $phieuXuatKho) {
            return CustomResponse::error('Phiếu xuất kho không tồn tại');
        }

        try {
            DB::transaction(function () use ($phieuXuatKho) {
                $this->revertChiTietXuatKho($phieuXuatKho);
                ChiTietPhieuXuatKho::where('phieu_xuat_kho_id', $phieuXuatKho->id)->delete();
                $phieuXuatKho->delete();
            });

            return $phieuXuatKho;
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách PhieuXuatKho dạng option
     */
    public function getOptions()
    {
        return PhieuXuatKho::select('id as value', 'ma_phieu_xuat_kho as label')->get();
    }

    /**
     * Xử lý tạo hoặc cập nhật chi tiết phiếu xuất kho và các dữ liệu liên quan.
     *
     * @param  array  $data  Dữ liệu đầu vào
     * @param  string  $mode  'create' hoặc 'update'
     * @return PhieuXuatKho
     *
     * @throws Exception
     */
    private function processChiTietXuatKho(PhieuXuatKho $phieuXuatKho, array &$data, string $mode)
    {
        $danhSachSanPham = collect($data['danh_sach_san_pham']);
        $loaiPhieuXuat = $mode === 'create' ? $data['loai_phieu_xuat'] : $phieuXuatKho->loai_phieu_xuat;
        $donHangId = ($data['loai_phieu_xuat'] ?? $phieuXuatKho->loai_phieu_xuat) == 1 ? ($data['don_hang_id'] ?? $phieuXuatKho->don_hang_id) : null;
        $sanXuatId = ($data['loai_phieu_xuat'] ?? $phieuXuatKho->loai_phieu_xuat) == 3 ? ($data['san_xuat_id'] ?? $phieuXuatKho->san_xuat_id) : null;

        // Lấy tất cả dữ liệu liên quan từ database để xử lý
        $duLieuLienQuan = $this->layDuLieuLienQuan($danhSachSanPham, $loaiPhieuXuat, $donHangId, $sanXuatId);

        // Kiểm tra và xác thực dữ liệu của từng sản phẩm trong phiếu xuất
        $this->xacThucDanhSachSanPham($danhSachSanPham, $duLieuLienQuan, $loaiPhieuXuat, $donHangId, $sanXuatId);

        // Chuẩn bị dữ liệu để lưu chi tiết phiếu xuất và tính tổng tiền
        $processedData = $this->chuanBiChiTietVaTinhTongTien($danhSachSanPham, $duLieuLienQuan['chiTietNhapKhoList'], $loaiPhieuXuat);

        // Lưu thông tin phiếu xuất kho và các chi tiết của nó
        $this->luuPhieuXuatKhoVaChiTiet($phieuXuatKho, $data, $processedData, $mode);

        // Cập nhật số lượng tồn kho và số lượng đã xuất trong các bảng liên quan
        $this->capNhatSoLieuSauKhiXuat($danhSachSanPham, $phieuXuatKho);

        // Cập nhật trạng thái cuối cùng cho đơn hàng hoặc phiếu sản xuất
        $this->capNhatTrangThaiLienQuan($phieuXuatKho);

        return $phieuXuatKho;
    }

    private function revertChiTietXuatKho(PhieuXuatKho $phieuXuatKho)
    {
        $chiTietPhieuXuatKhos = $phieuXuatKho->chiTietPhieuXuatKhos;
        if ($chiTietPhieuXuatKhos->isEmpty()) {
            return;
        }

        // Hoàn tác lại việc cập nhật số lượng trong kho và các bảng liên quan
        $this->hoanTacCapNhatSoLieu($phieuXuatKho, $chiTietPhieuXuatKhos);

        // Cập nhật lại trạng thái của đơn hàng hoặc phiếu sản xuất
        if (($phieuXuatKho->loai_phieu_xuat == 1 && $phieuXuatKho->donHang) || ($phieuXuatKho->loai_phieu_xuat == 3 && $phieuXuatKho->sanXuat)) {
            $this->capNhatTrangThaiLienQuan($phieuXuatKho);
        }
    }

    private function updateTrangThaiDonHang(DonHang $donHang)
    {
        $donHang->refresh();
        $soLuongConLai = $donHang->chiTietDonHangs()->whereRaw('so_luong > so_luong_da_xuat_kho')->count();
        $trangThai = ($soLuongConLai === 0) ? 2 : 1;
        $donHang->update(['trang_thai_xuat_kho' => $trangThai]);
    }

    private function updateTrangThaiSanXuat(SanXuat $sanXuat)
    {
        $sanXuat->refresh();
        $soLuongConLai = $sanXuat->chiTietSanXuat()->whereRaw('so_luong_thuc_te > so_luong_xuat_kho')->count();
        $trangThai = ($soLuongConLai === 0) ? 2 : 1;
        $sanXuat->update(['trang_thai_xuat_kho' => $trangThai]);
    }

    private function layDuLieuLienQuan(\Illuminate\Support\Collection $danhSachSanPham, int $loaiPhieuXuat, ?int $donHangId, ?int $sanXuatId): array
    {
        $maLoSanPhamList = $danhSachSanPham->pluck('ma_lo_san_pham')->unique()->all();
        $sanPhamIds = $danhSachSanPham->pluck('san_pham_id')->unique()->all();
        $donViTinhIds = $danhSachSanPham->pluck('don_vi_tinh_id')->unique()->all();

        $chiTietNhapKhoList = ChiTietPhieuNhapKho::whereIn('ma_lo_san_pham', $maLoSanPhamList)
            ->whereIn('san_pham_id', $sanPhamIds)
            ->whereIn('don_vi_tinh_id', $donViTinhIds)
            ->get()
            ->keyBy(fn ($item) => $item->ma_lo_san_pham.'-'.$item->san_pham_id.'-'.$item->don_vi_tinh_id);

        $khoTongList = KhoTong::whereIn('ma_lo_san_pham', $maLoSanPhamList)
            ->whereIn('san_pham_id', $sanPhamIds)
            ->whereIn('don_vi_tinh_id', $donViTinhIds)
            ->get()
            ->keyBy(fn ($item) => $item->ma_lo_san_pham.'-'.$item->san_pham_id.'-'.$item->don_vi_tinh_id);

        $chiTietDonHangList = collect([]);
        if ($loaiPhieuXuat == 1 && $donHangId) {
            $chiTietDonHangList = ChiTietDonHang::where('don_hang_id', $donHangId)
                ->whereIn('san_pham_id', $sanPhamIds)
                ->whereIn('don_vi_tinh_id', $donViTinhIds)
                ->get()
                ->keyBy(fn ($item) => $donHangId.'-'.$item->san_pham_id.'-'.$item->don_vi_tinh_id);
        }

        $chiTietSanXuatList = collect([]);
        if ($loaiPhieuXuat == 3 && $sanXuatId) {
            $chiTietSanXuatList = ChiTietSanXuat::where('san_xuat_id', $sanXuatId)
                ->whereIn('san_pham_id', $sanPhamIds)
                ->whereIn('don_vi_tinh_id', $donViTinhIds)
                ->get()
                ->keyBy(fn ($item) => $sanXuatId.'-'.$item->san_pham_id.'-'.$item->don_vi_tinh_id);
        }

        return compact('chiTietNhapKhoList', 'khoTongList', 'chiTietDonHangList', 'chiTietSanXuatList');
    }

    private function xacThucDanhSachSanPham(\Illuminate\Support\Collection $danhSachSanPham, array $duLieuLienQuan, int $loaiPhieuXuat, ?int $donHangId, ?int $sanXuatId): void
    {
        extract($duLieuLienQuan); // Lấy các biến từ mảng $duLieuLienQuan
        // gồm chiTietNhapKhoList, khoTongList, chiTietDonHangList, chiTietSanXuatList
        // VD: Thay vì dùng $duLieuLienQuan['khoTongList'] thì dùng $khoTongList

        foreach ($danhSachSanPham as $sanPham) {
            $key = $sanPham['ma_lo_san_pham'].'-'.$sanPham['san_pham_id'].'-'.$sanPham['don_vi_tinh_id'];

            if (! isset($chiTietNhapKhoList[$key])) {
                throw new Exception('Lô sản phẩm/nguyên liệu '.$sanPham['ma_lo_san_pham'].' không tồn tại.');
            }

            if (! isset($khoTongList[$key]) || $sanPham['so_luong'] > $khoTongList[$key]->so_luong_ton) {
                throw new Exception('Số lượng xuất kho của sản phẩm với lô '.$sanPham['ma_lo_san_pham'].' không được lớn hơn số lượng tồn trong kho.');
            }

            if ($loaiPhieuXuat == 1) {
                $keyChiTiet = $donHangId.'-'.$sanPham['san_pham_id'].'-'.$sanPham['don_vi_tinh_id'];
                if (! isset($chiTietDonHangList[$keyChiTiet]) || $sanPham['so_luong'] > $chiTietDonHangList[$keyChiTiet]->so_luong_con_lai_xuat_kho) {
                    throw new Exception('Số lượng xuất kho lớn hơn số lượng cần xuất còn lại trong đơn hàng.');
                }
            } elseif ($loaiPhieuXuat == 3) {
                $keyChiTiet = $sanXuatId.'-'.$sanPham['san_pham_id'].'-'.$sanPham['don_vi_tinh_id'];
                if (! isset($chiTietSanXuatList[$keyChiTiet]) || $sanPham['so_luong'] > $chiTietSanXuatList[$keyChiTiet]->so_luong_con_lai_xuat_kho) {
                    throw new Exception('Số lượng xuất kho lớn hơn số lượng cần xuất còn lại trong sản xuất.');
                }
            }
        }
    }

    private function chuanBiChiTietVaTinhTongTien(\Illuminate\Support\Collection $danhSachSanPham, \Illuminate\Support\Collection $chiTietNhapKhoList, int $loaiPhieuXuat): array
    {
        $tongTien = 0;
        $chiTietToInsert = [];

        foreach ($danhSachSanPham as $sanPham) {
            $key = $sanPham['ma_lo_san_pham'].'-'.$sanPham['san_pham_id'].'-'.$sanPham['don_vi_tinh_id'];
            $loSanPham = $chiTietNhapKhoList[$key];

            $donGia = $loaiPhieuXuat == 2 ? $loSanPham->gia_nhap : $loSanPham->gia_ban_le_don_vi;
            $thanhTien = $sanPham['so_luong'] * $donGia;
            $tongTien += $thanhTien;

            $chiTietToInsert[] = [
                'san_pham_id' => $sanPham['san_pham_id'],
                'don_vi_tinh_id' => $sanPham['don_vi_tinh_id'],
                'so_luong' => $sanPham['so_luong'],
                'don_gia' => $donGia,
                'ma_lo_san_pham' => $sanPham['ma_lo_san_pham'],
                'tong_tien' => $thanhTien,
            ];
        }

        return ['chiTietToInsert' => $chiTietToInsert, 'tongTien' => $tongTien];
    }

    private function luuPhieuXuatKhoVaChiTiet(PhieuXuatKho $phieuXuatKho, array $data, array $processedData, string $mode): void
    {
        $phieuXuatKhoData = $data;
        $phieuXuatKhoData['tong_tien'] = $processedData['tongTien'];
        unset($phieuXuatKhoData['danh_sach_san_pham']);

        if ($mode === 'create') {
            $phieuXuatKho->fill($phieuXuatKhoData)->save();
        } else {
            $phieuXuatKho->update($phieuXuatKhoData);
        }
        $phieuXuatKho->refresh();

        $phieuXuatKho->chiTietPhieuXuatKhos()->createMany($processedData['chiTietToInsert']);
    }

    /**
     * Cập nhật số lượng tồn kho và số lượng đã xuất trong các bảng liên quan.
     */
    private function capNhatSoLieuSauKhiXuat(\Illuminate\Support\Collection $danhSachSanPham, PhieuXuatKho $phieuXuatKho): void
    {
        $loaiPhieuXuat = $phieuXuatKho->loai_phieu_xuat;
        foreach ($danhSachSanPham as $sanPham) {
            $keyUpdate = [
                'san_pham_id' => $sanPham['san_pham_id'],
                'don_vi_tinh_id' => $sanPham['don_vi_tinh_id'],
            ];
            KhoTong::where('ma_lo_san_pham', $sanPham['ma_lo_san_pham'])->where($keyUpdate)->decrement('so_luong_ton', $sanPham['so_luong']);

            if ($loaiPhieuXuat == 1) {
                ChiTietDonHang::where('don_hang_id', $phieuXuatKho->don_hang_id)->where($keyUpdate)->increment('so_luong_da_xuat_kho', $sanPham['so_luong']);
            } elseif ($loaiPhieuXuat == 3) {
                ChiTietSanXuat::where('san_xuat_id', $phieuXuatKho->san_xuat_id)->where($keyUpdate)->increment('so_luong_xuat_kho', $sanPham['so_luong']);
            }
        }
    }

    /**
     * Hoàn tác cập nhật số liệu (tăng lại tồn kho, giảm số lượng đã xuất).
     */
    private function hoanTacCapNhatSoLieu(PhieuXuatKho $phieuXuatKho, \Illuminate\Support\Collection $chiTietPhieuXuatKhos): void
    {
        foreach ($chiTietPhieuXuatKhos as $chiTiet) {
            KhoTong::where('ma_lo_san_pham', $chiTiet->ma_lo_san_pham)
                ->where('san_pham_id', $chiTiet->san_pham_id)
                ->where('don_vi_tinh_id', $chiTiet->don_vi_tinh_id)
                ->increment('so_luong_ton', $chiTiet->so_luong);

            $keyUpdate = [
                'san_pham_id' => $chiTiet->san_pham_id,
                'don_vi_tinh_id' => $chiTiet->don_vi_tinh_id,
            ];

            if ($phieuXuatKho->loai_phieu_xuat == 1 && $phieuXuatKho->donHang) {
                ChiTietDonHang::where('don_hang_id', $phieuXuatKho->don_hang_id)->where($keyUpdate)
                    ->decrement('so_luong_da_xuat_kho', $chiTiet->so_luong);
            } elseif ($phieuXuatKho->loai_phieu_xuat == 3 && $phieuXuatKho->sanXuat) {
                ChiTietSanXuat::where('san_xuat_id', $phieuXuatKho->san_xuat_id)->where($keyUpdate)
                    ->decrement('so_luong_xuat_kho', $chiTiet->so_luong);
            }
        }
    }

    /**
     * Cập nhật trạng thái cuối cùng cho đơn hàng hoặc phiếu sản xuất liên quan.
     */
    private function capNhatTrangThaiLienQuan(PhieuXuatKho $phieuXuatKho): void
    {
        if ($phieuXuatKho->loai_phieu_xuat == 1 && $phieuXuatKho->donHang) {
            $this->updateTrangThaiDonHang($phieuXuatKho->donHang);
        } elseif ($phieuXuatKho->loai_phieu_xuat == 3 && $phieuXuatKho->sanXuat) {
            $this->updateTrangThaiSanXuat($phieuXuatKho->sanXuat);
        }
    }
}
