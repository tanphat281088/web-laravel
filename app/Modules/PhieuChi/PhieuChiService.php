<?php

namespace App\Modules\PhieuChi;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Class\Helper;
use App\Models\ChiTietPhieuChi;
use App\Models\PhieuChi;
use App\Models\PhieuNhapKho;
use Exception;
use Illuminate\Support\Facades\DB;

class PhieuChiService
{
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // Tạo query cơ bản
            $query = PhieuChi::query()->with('images');

            // Sử dụng FilterWithPagination để xử lý filter và pagination
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                ['phieu_chis.*'] // Columns cần select
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
        $phieuChi = PhieuChi::find($id);

        if ($phieuChi->loai_phieu_chi == 2 || $phieuChi->loai_phieu_chi == 4) {
            $query = "
      SELECT
        phieu_nhap_khos.ma_phieu_nhap_kho,
        phieu_nhap_khos.tong_tien - COALESCE((SELECT SUM(so_tien) FROM chi_tiet_phieu_chis WHERE phieu_nhap_kho_id = phieu_nhap_khos.id AND phieu_chi_id < $id), 0) as tong_tien_can_thanh_toan,
        chi_tiet_phieu_chis.so_tien as tong_tien_da_thanh_toan,
        (phieu_nhap_khos.tong_tien - COALESCE((SELECT SUM(so_tien) FROM chi_tiet_phieu_chis WHERE phieu_nhap_kho_id = phieu_nhap_khos.id AND phieu_chi_id < $id), 0) - chi_tiet_phieu_chis.so_tien) as so_tien_con_lai
      FROM phieu_chis
      LEFT JOIN chi_tiet_phieu_chis ON phieu_chis.id = chi_tiet_phieu_chis.phieu_chi_id
      LEFT JOIN phieu_nhap_khos ON chi_tiet_phieu_chis.phieu_nhap_kho_id = phieu_nhap_khos.id
      WHERE phieu_chis.id = $id";

            $data = DB::select($query);

            $phieuChi->chi_tiet_phieu_chi = $data;
        }

        if (! $phieuChi) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $phieuChi;
    }

    /**
     * Tạo mới dữ liệu
     */
    public function create(array $data)
    {
        try {
            DB::beginTransaction();

            $result = match ($data['loai_phieu_chi']) {
                1 => $this->xuLyChiThanhToanPhieuNhapKho($data),
                2 => $this->xuLyChiThanhToanCongNo($data),
                3 => $this->xuLyChiKhac($data),
                4 => $this->xuLyChiThanhToanNhieuPhieuNhapKho($data),
                default => throw new Exception('Loại phiếu chi không hợp lệ')
            };

            if ($result instanceof \App\Class\CustomResponse) {
                DB::rollBack();

                return $result;
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
        return CustomResponse::error('Không thể cập nhật phiếu chi');
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            $model = PhieuChi::findOrFail($id);

            if (! Helper::checkIsToday($model->created_at)) {
                throw new Exception('Chỉ được xóa phiếu chi trong ngày hôm nay');
            }

            DB::beginTransaction();

            $result = match ($model->loai_phieu_chi) {
                1 => $this->xuLyXoaPhieuChiPhieuNhapKho($model),
                2 => $this->xuLyXoaPhieuChiCongNo($model),
                3 => $this->xuLyXoaChiKhac($model),
                4 => $this->xuLyXoaPhieuChiNhieuPhieuNhapKho($model),
                default => throw new Exception('Loại phiếu chi không hợp lệ')
            };

            if ($result instanceof \App\Class\CustomResponse) {
                DB::rollBack();

                return $result;
            }

            $model->delete();
            DB::commit();

            return $model;
        } catch (Exception $e) {
            DB::rollBack();

            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách PhieuChi dạng option
     */
    public function getOptions()
    {
        return PhieuChi::select('id as value', 'ten_phieu_chi as label')->get();
    }

    /**
     * Tính trạng thái thanh toán dựa trên số tiền đã thanh toán và tổng tiền
     */
    private function getTrangThaiThanhToan($daThanhToan, $tongTien)
    {
        if ($daThanhToan < $tongTien) {
            return 1; // Chưa thanh toán đủ
        } elseif ($daThanhToan == $tongTien) {
            return 2; // Đã thanh toán đủ
        } else {
            return 0; // Thanh toán thừa (lỗi)
        }
    }

    /**
     * Xử lý chi thanh toán cho phiếu nhập kho
     */
    private function xuLyChiThanhToanPhieuNhapKho(array $data)
    {
        $phieuNhapKho = $this->kiemTraPhieuNhapKhoTonTai($data['phieu_nhap_kho_id']);
        if (! $phieuNhapKho) {
            throw new Exception('Phiếu nhập kho không tồn tại');
        }

        $kiemTra = $this->kiemTraSoTienChiHopLe($phieuNhapKho, $data['so_tien']);
        if ($kiemTra !== true) {
            return $kiemTra;
        }

        $this->capNhatTrangThaiThanhToanPhieuNhapKho($phieuNhapKho, $data['so_tien']);

        return PhieuChi::create($data);
    }

    /**
     * Xử lý chi thanh toán công nợ
     */
    private function xuLyChiThanhToanCongNo(array $data)
    {
        if (empty($data['nha_cung_cap_id'])) {
            throw new Exception('Nhà cung cấp không được để trống');
        }

        $phieuNhapKhos = $this->layPhieuNhapKhoChuaThanhToanDayDu($data['nha_cung_cap_id']);
        if ($phieuNhapKhos->isEmpty()) {
            throw new Exception('Không tìm thấy công nợ của nhà cung cấp này');
        }

        $tongTienCanThanhToan = $this->tinhTongTienCanThanhToanPhieuNhapKho($phieuNhapKhos);
        if ($tongTienCanThanhToan < $data['so_tien']) {
            throw new Exception('Số tiền thanh toán nhiều hơn số tiền cần thanh toán nhà cung cấp');
        }

        $phieuChi = PhieuChi::create($data);
        $this->xuLyPhanBoTienChiThanhToan($phieuChi->id, $phieuNhapKhos, $data['so_tien']);

        return $phieuChi;
    }

    /**
     * Xử lý chi khác
     */
    private function xuLyChiKhac(array $data)
    {
        return PhieuChi::create($data);
    }

    /**
     * Xử lý chi thanh toán cho nhiều phiếu nhập kho chỉ định
     */
    private function xuLyChiThanhToanNhieuPhieuNhapKho(array $data)
    {
        if (empty($data['phieu_nhap_kho_ids'])) {
            throw new Exception('Danh sách phiếu nhập kho không được để trống');
        }

        $phieuNhapKhoIds = $this->layDanhSachIdPhieuNhapKho($data['phieu_nhap_kho_ids']);
        $phieuNhapKhos = $this->layPhieuNhapKhoTheoIds($phieuNhapKhoIds);

        if ($phieuNhapKhos->isEmpty()) {
            throw new Exception('Không tìm thấy công nợ của nhà cung cấp này');
        }

        $phieuNhapKhoList = $data['phieu_nhap_kho_ids'];
        unset($data['phieu_nhap_kho_ids']);
        $phieuChi = PhieuChi::create($data);

        $this->xuLyPhanBoPhieuNhapKho($phieuChi->id, $phieuNhapKhos, $phieuNhapKhoList);

        return $phieuChi;
    }

    /**
     * Xử lý xóa phiếu chi phiếu nhập kho
     */
    private function xuLyXoaPhieuChiPhieuNhapKho($model)
    {
        $phieuNhapKho = PhieuNhapKho::find($model->phieu_nhap_kho_id);
        $this->capNhatTrangThaiThanhToanPhieuNhapKho($phieuNhapKho, $model->so_tien, true);

        return true;
    }

    /**
     * Xử lý xóa phiếu chi công nợ
     */
    private function xuLyXoaPhieuChiCongNo($model)
    {
        $this->hoantTraTienTuChiTietPhieuChi($model->id);
        $this->xoaChiTietPhieuChi($model->id);

        return true;
    }

    /**
     * Xử lý xóa chi khác
     */
    private function xuLyXoaChiKhac($model)
    {
        return true;
    }

    /**
     * Xử lý xóa phiếu chi nhiều phiếu nhập kho
     */
    private function xuLyXoaPhieuChiNhieuPhieuNhapKho($model)
    {
        $this->hoantTraTienTuChiTietPhieuChi($model->id);
        $this->xoaChiTietPhieuChi($model->id);

        return true;
    }

    /**
     * Cập nhật trạng thái thanh toán phiếu nhập kho
     */
    private function capNhatTrangThaiThanhToanPhieuNhapKho($phieuNhapKho, $soTien, $isGiam = false)
    {
        $soTienMoi = $isGiam
          ? $phieuNhapKho->da_thanh_toan - $soTien
          : $phieuNhapKho->da_thanh_toan + $soTien;

        $phieuNhapKho->update([
            'da_thanh_toan' => $soTienMoi,
            'trang_thai' => $this->getTrangThaiThanhToan($soTienMoi, $phieuNhapKho->tong_tien),
        ]);
    }

    /**
     * Kiểm tra phiếu nhập kho tồn tại
     */
    private function kiemTraPhieuNhapKhoTonTai($phieuNhapKhoId)
    {
        return PhieuNhapKho::find($phieuNhapKhoId);
    }

    /**
     * Kiểm tra số tiền chi hợp lệ
     */
    private function kiemTraSoTienChiHopLe($phieuNhapKho, $soTienChi)
    {
        $soTienCanThanhToan = $phieuNhapKho->tong_tien - $phieuNhapKho->da_thanh_toan;

        if ($soTienChi > $soTienCanThanhToan) {
            throw new Exception('Số tiền thanh toán nhiều hơn số tiền cần thanh toán nhà cung cấp');
        }

        return true;
    }

    /**
     * Lấy phiếu nhập kho chưa thanh toán đầy đủ
     */
    private function layPhieuNhapKhoChuaThanhToanDayDu($nhaCungCapId)
    {
        return PhieuNhapKho::where('nha_cung_cap_id', $nhaCungCapId)
            ->whereRaw('da_thanh_toan < tong_tien')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Tính tổng tiền cần thanh toán phiếu nhập kho
     */
    private function tinhTongTienCanThanhToanPhieuNhapKho($phieuNhapKhos)
    {
        return $phieuNhapKhos->sum(function ($phieu) {
            return $phieu->tong_tien - $phieu->da_thanh_toan;
        });
    }

    /**
     * Lấy danh sách ID phiếu nhập kho
     */
    private function layDanhSachIdPhieuNhapKho($phieuNhapKhoIds)
    {
        return collect($phieuNhapKhoIds)->map(function ($item) {
            return (int) $item['id'];
        })->toArray();
    }

    /**
     * Lấy phiếu nhập kho theo IDs
     */
    private function layPhieuNhapKhoTheoIds($phieuNhapKhoIds)
    {
        return PhieuNhapKho::whereIn('id', $phieuNhapKhoIds)
            ->whereRaw('da_thanh_toan < tong_tien')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Xử lý phân bổ tiền chi thanh toán
     */
    private function xuLyPhanBoTienChiThanhToan($phieuChiId, $phieuNhapKhos, $soTienThanhToan)
    {
        foreach ($phieuNhapKhos as $phieu) {
            if ($soTienThanhToan <= 0) {
                break;
            }

            $soTienCanThanhToan = $phieu->tong_tien - $phieu->da_thanh_toan;
            $soTienThanhToanPhieu = min($soTienCanThanhToan, $soTienThanhToan);

            $this->capNhatTrangThaiThanhToanPhieuNhapKho($phieu, $soTienThanhToanPhieu);
            $this->taoChiTietPhieuChi($phieuChiId, $phieu->id, $soTienThanhToanPhieu);

            $soTienThanhToan -= $soTienThanhToanPhieu;
        }
    }

    /**
     * Xử lý phân bổ phiếu nhập kho
     */
    private function xuLyPhanBoPhieuNhapKho($phieuChiId, $phieuNhapKhos, $phieuNhapKhoList)
    {
        foreach ($phieuNhapKhos as $phieu) {
            $soTienThanhToan = $this->laySoTienThanhToanTuDanhSachPhieuNhapKho($phieu->id, $phieuNhapKhoList);

            if ($soTienThanhToan <= 0) {
                continue;
            }

            $kiemTra = $this->kiemTraSoTienChiHopLe($phieu, $soTienThanhToan);
            if ($kiemTra !== true) {
                throw new Exception('Phiếu nhập kho '.$phieu->ma_phieu_nhap_kho.' có số tiền thanh toán nhiều hơn số tiền công nợ');
            }

            $this->capNhatTrangThaiThanhToanPhieuNhapKho($phieu, $soTienThanhToan);
            $this->taoChiTietPhieuChi($phieuChiId, $phieu->id, $soTienThanhToan);
        }
    }

    /**
     * Lấy số tiền thanh toán từ danh sách phiếu nhập kho
     */
    private function laySoTienThanhToanTuDanhSachPhieuNhapKho($phieuNhapKhoId, $phieuNhapKhoList)
    {
        foreach ($phieuNhapKhoList as $item) {
            if ((int) $item['id'] === $phieuNhapKhoId && isset($item['so_tien_thanh_toan'])) {
                return $item['so_tien_thanh_toan'];
            }
        }

        return 0;
    }

    /**
     * Tạo chi tiết phiếu chi
     */
    private function taoChiTietPhieuChi($phieuChiId, $phieuNhapKhoId, $soTien)
    {
        return ChiTietPhieuChi::create([
            'phieu_chi_id' => $phieuChiId,
            'phieu_nhap_kho_id' => $phieuNhapKhoId,
            'so_tien' => $soTien,
        ]);
    }

    /**
     * Hoàn trả tiền từ chi tiết phiếu chi
     */
    private function hoantTraTienTuChiTietPhieuChi($phieuChiId)
    {
        $chiTietPhieuChi = ChiTietPhieuChi::where('phieu_chi_id', $phieuChiId)->get();

        foreach ($chiTietPhieuChi as $chiTiet) {
            $phieuNhapKho = PhieuNhapKho::find($chiTiet->phieu_nhap_kho_id);
            $this->capNhatTrangThaiThanhToanPhieuNhapKho($phieuNhapKho, $chiTiet->so_tien, true);
        }
    }

    /**
     * Xóa chi tiết phiếu chi
     */
    private function xoaChiTietPhieuChi($phieuChiId)
    {
        ChiTietPhieuChi::where('phieu_chi_id', $phieuChiId)->delete();
    }
}
