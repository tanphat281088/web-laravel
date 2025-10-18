<?php

namespace App\Modules\PhieuNhapKho;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Class\Helper;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\KhoTong;
use App\Models\PhieuNhapKho;
use App\Models\SanPham;
use App\Models\SanXuat;
use Exception;
use Illuminate\Support\Facades\DB;

class PhieuNhapKhoService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản với relationships thay vì JOIN
            $query = PhieuNhapKho::query()
                ->withoutGlobalScopes(['withUserNames'])
                ->leftJoin('nha_cung_caps', 'phieu_nhap_khos.nha_cung_cap_id', '=', 'nha_cung_caps.id')
                ->leftJoin('users as nguoi_tao', 'phieu_nhap_khos.nguoi_tao', '=', 'nguoi_tao.id')
                ->leftJoin('users as nguoi_cap_nhat', 'phieu_nhap_khos.nguoi_cap_nhat', '=', 'nguoi_cap_nhat.id');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                [
                    'phieu_nhap_khos.*',
                    'nha_cung_caps.ten_nha_cung_cap',
                    'nguoi_tao.name as ten_nguoi_tao',
                    'nguoi_cap_nhat.name as ten_nguoi_cap_nhat',
                ] // Columns cần select
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
        $data = PhieuNhapKho::with('images', 'chiTietPhieuNhapKhos.sanPham', 'chiTietPhieuNhapKhos.nhaCungCap', 'chiTietPhieuNhapKhos.donViTinh')->find($id);
        if (! $data) {
            return CustomResponse::error('Phiếu nhập kho không tồn tại');
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
                $phieuNhapKho = new PhieuNhapKho;

                return $this->xuLyChiTietPhieuNhap($phieuNhapKho, $data, 'create');
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
        $phieuNhapKho = PhieuNhapKho::find($id);

        if (! $phieuNhapKho) {
            return CustomResponse::error('Phiếu nhập kho không tồn tại');
        }

        if ($phieuNhapKho->phieuChi()->exists() || $phieuNhapKho->chiTietPhieuChi()->exists()) {
            throw new Exception('Phiếu nhập kho đã có phiếu chi, không thể cập nhật');
        }

        try {
            DB::transaction(function () use ($phieuNhapKho, &$data) {
                // Hoàn tác các thay đổi từ phiếu nhập kho cũ
                $this->hoanTacChiTietPhieuNhap($phieuNhapKho);

                // Xử lý và cập nhật với dữ liệu mới
                $this->xuLyChiTietPhieuNhap($phieuNhapKho, $data, 'update');
            });

            return $phieuNhapKho->fresh();
        } catch (Exception $e) {
            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        $phieuNhapKho = PhieuNhapKho::find($id);

        if ($phieuNhapKho->phieuChi()->exists() || $phieuNhapKho->chiTietPhieuChi()->exists()) {
            throw new Exception('Phiếu nhập kho đã có phiếu chi, không thể xóa');
        }

        if (! $phieuNhapKho) {
            return CustomResponse::error('Phiếu nhập kho không tồn tại');
        }

        try {
            DB::transaction(function () use ($phieuNhapKho) {
                $this->hoanTacChiTietPhieuNhap($phieuNhapKho);
                $phieuNhapKho->delete();
            });

            return $phieuNhapKho;
        } catch (Exception $e) {
            DB::rollBack();

            return CustomResponse::error($e->getMessage());
        }
    }

    private function xuLyChiTietPhieuNhap(PhieuNhapKho $phieuNhapKho, array &$data, string $mode)
    {
        // 1. Validate và chuẩn bị dữ liệu cho danh sách sản phẩm
        $this->xacThucVaChuanBiDuLieuChiTiet($data, $phieuNhapKho, $mode);

        // 2. Lưu thông tin phiếu nhập kho và các chi tiết của nó
        $this->luuPhieuNhapKhoVaChiTiet($phieuNhapKho, $data, $mode);

        // 3. Cập nhật số lượng tồn kho và các bảng liên quan
        $this->capNhatSoLieuSauKhiNhap($phieuNhapKho, $data);

        return $phieuNhapKho;
    }

    /**
     * Hoàn tác lại các thay đổi của phiếu nhập kho.
     * Được sử dụng khi cập nhật hoặc xóa phiếu nhập kho.
     */
    private function hoanTacChiTietPhieuNhap(PhieuNhapKho $phieuNhapKho): void
    {
        $chiTietPhieuNhapKho = $phieuNhapKho->chiTietPhieuNhapKhos;
        if ($chiTietPhieuNhapKho->isEmpty()) {
            return;
        }

        // Xóa các bản ghi tồn kho tương ứng
        $maLoSanPham = $chiTietPhieuNhapKho->pluck('ma_lo_san_pham')->all();
        KhoTong::whereIn('ma_lo_san_pham', $maLoSanPham)->delete();

        // Hoàn tác số lượng đã nhập kho từ sản xuất
        if ($phieuNhapKho->loai_phieu_nhap == 2 && $phieuNhapKho->san_xuat_id) {
            $sanXuat = SanXuat::find($phieuNhapKho->san_xuat_id);
            if ($sanXuat) {
                $soLuongNhap = $chiTietPhieuNhapKho->sum('so_luong_nhap');
                $sanXuat->decrement('so_luong_nhap_kho', $soLuongNhap);
            }
        }

        // Xóa chi tiết phiếu nhập kho
        ChiTietPhieuNhapKho::where('phieu_nhap_kho_id', $phieuNhapKho->id)->delete();
    }

    private function xacThucVaChuanBiDuLieuChiTiet(array &$data, PhieuNhapKho $phieuNhapKho, string $mode)
    {
        $danhSachSanPham = collect($data['danh_sach_san_pham']);
        $sanPhamIds = $danhSachSanPham->pluck('san_pham_id')->unique()->all();
        $sanPhams = SanPham::whereIn('id', $sanPhamIds)->get()->keyBy('id');
        $tongTienHang = 0;
        $tongChietKhau = 0;

        // Xử lý logic dựa trên loại phiếu nhập
        switch ($data['loai_phieu_nhap']) {
            case 1: // Nhập từ nhà cung cấp
                foreach ($data['danh_sach_san_pham'] as &$chiTiet) {
                    $sanPham = $sanPhams[$chiTiet['san_pham_id']] ?? null;
                    if (! $sanPham) {
                        throw new Exception('Sản phẩm với ID '.$chiTiet['san_pham_id'].' không tồn tại.');
                    }

                    $tongTienNhap = $chiTiet['gia_nhap'] * $chiTiet['so_luong_nhap'];
                    $tienChietKhau = $tongTienNhap * ($chiTiet['chiet_khau'] ?? 0) / 100;
                    $thanhTienSauChietKhau = $tongTienNhap - $tienChietKhau;
                    $giaVonDonVi = $chiTiet['so_luong_nhap'] > 0 ? $thanhTienSauChietKhau / $chiTiet['so_luong_nhap'] : 0;
                    $giaBanLeDonVi = $giaVonDonVi * (1 + ($sanPham->muc_loi_nhuan ?? 0) / 100);

                    $tongTienHang += $thanhTienSauChietKhau;
                    $tongChietKhau += $tienChietKhau;

                    $chiTiet['nha_cung_cap_id'] = $data['nha_cung_cap_id'];
                    $chiTiet['tong_tien_nhap'] = $tongTienNhap;
                    $chiTiet['gia_von_don_vi'] = $giaVonDonVi;
                    $chiTiet['gia_ban_le_don_vi'] = $giaBanLeDonVi;
                    $chiTiet['loi_nhuan_ban_le'] = $giaBanLeDonVi - $giaVonDonVi;
                }
                unset($chiTiet); // Hủy tham chiếu

                $tongTienTruocThueVAT = $tongTienHang + ($data['chi_phi_nhap_hang'] ?? 0) - ($data['giam_gia_nhap_hang'] ?? 0);
                $tongThueVat = $tongTienTruocThueVAT * ($data['thue_vat'] ?? 0) / 100;

                $data['tong_tien_hang'] = $tongTienHang;
                $data['tong_chiet_khau'] = $tongChietKhau;
                $data['tong_tien'] = $tongTienTruocThueVAT + $tongThueVat;
                break;

            case 2: // Nhập từ sản xuất
                $sanXuatId = $data['san_xuat_id'] ?? null;
                $sanXuat = $sanXuatId ? SanXuat::find($sanXuatId) : null;
                if (! $sanXuat) {
                    throw new Exception('Phiếu sản xuất không tồn tại.');
                }

                $soLuongNhapMoi = $danhSachSanPham->sum('so_luong_nhap');
                $soLuongDaNhap = ($mode === 'update') ? ($phieuNhapKho->san_xuat_id == $sanXuatId ? 0 : $sanXuat->so_luong_nhap_kho) : $sanXuat->so_luong_nhap_kho;

                if ($soLuongDaNhap + $soLuongNhapMoi > $sanXuat->so_luong) {
                    throw new Exception('Số lượng nhập kho vượt quá số lượng sản xuất.');
                }

                foreach ($data['danh_sach_san_pham'] as &$chiTiet) {
                    $sanPham = $sanPhams[$chiTiet['san_pham_id']] ?? null;
                    if (! $sanPham) {
                        throw new Exception('Sản phẩm với ID '.$chiTiet['san_pham_id'].' không tồn tại.');
                    }

                    $tongTienNhap = $chiTiet['gia_nhap'] * $chiTiet['so_luong_nhap'];
                    $giaVonDonVi = $chiTiet['gia_nhap'];
                    $giaBanLeDonVi = $giaVonDonVi * (1 + ($sanPham->muc_loi_nhuan ?? 0) / 100);

                    $tongTienHang += $tongTienNhap;

                    $chiTiet['tong_tien_nhap'] = $tongTienNhap;
                    $chiTiet['chiet_khau'] = 0;
                    $chiTiet['gia_von_don_vi'] = $giaVonDonVi;
                    $chiTiet['gia_ban_le_don_vi'] = $giaBanLeDonVi;
                    $chiTiet['loi_nhuan_ban_le'] = $giaBanLeDonVi - $giaVonDonVi;
                }
                unset($chiTiet); // Hủy tham chiếu

                $data['tong_tien_hang'] = $tongTienHang;
                $data['tong_chiet_khau'] = 0;
                $data['tong_tien'] = $tongTienHang;
                break;

            default:
                throw new Exception('Loại phiếu nhập không hợp lệ');
        }
    }

    private function luuPhieuNhapKhoVaChiTiet(PhieuNhapKho $phieuNhapKho, array $data, string $mode): void
    {
        $phieuNhapKhoData = $data;
        unset($phieuNhapKhoData['danh_sach_san_pham']);

        if ($mode === 'create') {
            $phieuNhapKho->fill($phieuNhapKhoData)->save();
        } else {
            $phieuNhapKho->update($phieuNhapKhoData);
        }
        $phieuNhapKho->refresh();

        $chiTietToInsert = [];
        foreach ($data['danh_sach_san_pham'] as $chiTiet) {
            $chiTiet['ma_lo_san_pham'] = Helper::generateMaLoSanPham();
            $chiTietToInsert[] = $chiTiet;
        }

        $phieuNhapKho->chiTietPhieuNhapKhos()->createMany($chiTietToInsert);
    }

    /**
     * Cập nhật số lượng tồn kho và số liệu các bảng liên quan sau khi nhập.
     */
    private function capNhatSoLieuSauKhiNhap(PhieuNhapKho $phieuNhapKho, array $data): void
    {
        $checkNgayNhapKho = ($data['ngay_nhap_kho'] ?? $phieuNhapKho->ngay_nhap_kho) <= date('Y-m-d');
        if (! $checkNgayNhapKho) {
            return;
        }

        // Lấy lại các chi tiết vừa tạo để có mã lô
        $chiTietPhieuNhapKhos = $phieuNhapKho->chiTietPhieuNhapKhos()->get();
        $sanPhamIds = $chiTietPhieuNhapKhos->pluck('san_pham_id')->unique()->all();
        $sanPhams = SanPham::whereIn('id', $sanPhamIds)->get()->keyBy('id');

        $khoTongToInsert = [];
        foreach ($chiTietPhieuNhapKhos as $chiTiet) {
            $sanPham = $sanPhams[$chiTiet->san_pham_id] ?? null;
            $khoTongToInsert[] = [
                'ma_lo_san_pham' => $chiTiet->ma_lo_san_pham,
                'san_pham_id' => $chiTiet->san_pham_id,
                'don_vi_tinh_id' => $chiTiet->don_vi_tinh_id,
                'so_luong_ton' => $chiTiet->so_luong_nhap,
                'trang_thai' => ($sanPham && $sanPham->so_luong_canh_bao > $chiTiet->so_luong_nhap) ? 1 : 2,
                'created_at' => now(),
                'updated_at' => now(),
                'nguoi_tao' => auth()->user()->id,
                'nguoi_cap_nhat' => auth()->user()->id,
            ];
        }
        KhoTong::insert($khoTongToInsert);

        if ($phieuNhapKho->loai_phieu_nhap == 2) {
            $sanXuat = SanXuat::find($phieuNhapKho->san_xuat_id);
            if ($sanXuat) {
                $soLuongNhap = $chiTietPhieuNhapKhos->sum('so_luong_nhap');
                $sanXuat->increment('so_luong_nhap_kho', $soLuongNhap);
            }
        }
    }

    /**
     * Lấy danh sách PhieuNhapKho dạng option
     */
    public function getOptions()
    {
        return PhieuNhapKho::select('id as value', 'ma_phieu_nhap_kho as label')->get();
    }

    /**
     * Lấy danh sách PhieuNhapKho dạng option
     */
    public function getOptionsByNhaCungCap($nhaCungCapId, $params = [])
    {
        $query = PhieuNhapKho::where('nha_cung_cap_id', $nhaCungCapId);
        if (isset($params['chua_hoan_thanh']) && $params['chua_hoan_thanh'] == 'true') {
            $query->whereRaw('da_thanh_toan < tong_tien');
        }

        return $query->select(
            'id as value',
            'id',
            DB::raw("CONCAT(ma_phieu_nhap_kho, ' (Công nợ: ', REPLACE(FORMAT(tong_tien - da_thanh_toan, 0), ',', '.'), ' đ)') as label"),
            'ma_phieu_nhap_kho',
            'tong_tien',
            'da_thanh_toan',
            DB::raw('(tong_tien - da_thanh_toan) as cong_no')
        )->get();
    }

    /**
     * Lấy danh sách PhieuNhapKho dạng option
     */
    public function getTongTienCanThanhToanTheoNhaCungCap($nhaCungCapId)
    {
        $model = PhieuNhapKho::where('nha_cung_cap_id', $nhaCungCapId)->get();
        $tongTien = 0;
        foreach ($model as $item) {
            $tongTien += $item->tong_tien - $item->da_thanh_toan;
        }

        return $tongTien;
    }

    /**
     * Lấy danh sách PhieuNhapKho dạng option
     */
    public function getTongTienCanThanhToanTheoNhieuPhieuNhapKho($phieuNhapKhoIds)
    {
        $phieuNhapKhoIds = explode(',', $phieuNhapKhoIds);
        $model = PhieuNhapKho::whereIn('id', $phieuNhapKhoIds)->get();
        $tongTien = 0;
        foreach ($model as $item) {
            $tongTien += $item->tong_tien - $item->da_thanh_toan;
        }

        return $tongTien;
    }
}
