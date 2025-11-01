<?php

namespace App\Modules\PhieuThu;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Class\Helper;
use App\Models\ChiTietPhieuThu;
use App\Models\DonHang;
use App\Models\PhieuThu;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Services\Cash\CashLedgerService;


class PhieuThuService
{
    /**
     * Lấy tất cả dữ liệu
     */
    /**
     * Lấy tất cả dữ liệu
     */
    /**
     * Lấy tất cả dữ liệu
     */
    /**
     * Lấy tất cả dữ liệu
     */
    public function getAll(array $params = [])
    {
        try {
            // JOIN don_hangs qua derived-table để tránh cột 'nguoi_tao' bị trùng
            $dhSub = DB::raw('(SELECT id, ma_don_hang, ten_khach_hang, so_dien_thoai FROM don_hangs) as dh');

            $query = PhieuThu::query()
                ->leftJoin($dhSub, 'dh.id', '=', 'phieu_thus.don_hang_id')
                ->with('images');

            // Để FilterWithPagination hoạt động, liệt kê rõ các cột cần lấy
            $result = FilterWithPagination::findWithPagination(
                $query,
                $params,
                [
                    'phieu_thus.*',
                    'dh.ma_don_hang',
                    'dh.ten_khach_hang',
                    'dh.so_dien_thoai',
                ]
            );

            // Tạo chuỗi mô tả ở PHP (tránh alias SQL gây lỗi)
            $collection = $result['collection'];
            foreach ($collection as $item) {
                $loaiText = match ((int)($item->loai_phieu_thu ?? 0)) {
                    1 => 'Thu cho đơn hàng',
                    2 => 'Thu cho nhiều đơn hàng theo khách hàng',
                    3 => 'Thu công nợ khách hàng',
                    5 => 'Thu hoạt động tài chính', // MỚI: loại 5
                    default => 'Thu khác',
                };

                $maDonHang   = $item->ma_don_hang ?? 'N/A';
                $tenKhach    = $item->ten_khach_hang ?? 'N/A';
                $soDienThoai = $item->so_dien_thoai ?? 'N/A';

                $item->mo_ta_phieu_thu = "{$loaiText} - {$maDonHang} - {$tenKhach} - {$soDienThoai}";
            }

            return [
                'data' => $collection,
                'total' => $result['total'],
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
        $phieuThu = PhieuThu::find($id);

        if (!$phieuThu) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        if ($phieuThu->loai_phieu_thu == 2 || $phieuThu->loai_phieu_thu == 3) {
            $query = "
                SELECT
                  don_hangs.ma_don_hang,
                  don_hangs.tong_tien_can_thanh_toan,
                  chi_tiet_phieu_thus.so_tien as so_tien_da_thanh_toan
                FROM phieu_thus
                LEFT JOIN chi_tiet_phieu_thus ON phieu_thus.id = chi_tiet_phieu_thus.phieu_thu_id
                LEFT JOIN don_hangs ON chi_tiet_phieu_thus.don_hang_id = don_hangs.id
                WHERE phieu_thus.id = $id
            ";

            $data = DB::select($query);
            $phieuThu->chi_tiet_phieu_thu = $data;
        }

        return $phieuThu;
    }

    /**
     * Tạo mới dữ liệu
     */
public function create(array $data)
{
    try {
        DB::beginTransaction();

        // MỚI: chuẩn hoá loại để chấp nhận cả chuỗi 'TAI_CHINH'
        if (isset($data['loai_phieu_thu'])) {
            $data['loai_phieu_thu'] = $this->normalizeLoai($data['loai_phieu_thu']);
        }

        /* ======================= HYDRATE CK THEO TÀI KHOẢN ======================= 
           Nếu là chuyển khoản (pt=2) và có tai_khoan_id, tự điền ngân_hàng/số_tk
           từ bảng tai_khoan_tiens khi 2 field đang rỗng. Không đè dữ liệu FE.   */
        $data['phuong_thuc_thanh_toan'] = (int)($data['phuong_thuc_thanh_toan'] ?? 0);
        if ($data['phuong_thuc_thanh_toan'] === 2) { // 2 = Chuyển khoản
            $tkId = (int)($data['tai_khoan_id'] ?? 0);
            if ($tkId > 0) {
                $tk = DB::table('tai_khoan_tiens')
                        ->select('ngan_hang','so_tai_khoan')
                        ->where('id', $tkId)
                        ->first();
                if ($tk) {
                    $data['ngan_hang']    = $data['ngan_hang']    ?? (string)($tk->ngan_hang ?? '');
                    $data['so_tai_khoan'] = $data['so_tai_khoan'] ?? (string)($tk->so_tai_khoan ?? '');
                }
            }
        }
        /* ===================== HẾT KHỐI HYDRATE CK THEO TÀI KHOẢN ===================== */

        $result = match ($data['loai_phieu_thu']) {
            1 => $this->xuLyThanhToanDonHang($data),
            2 => $this->xuLyThanhToanNhieuDonHang($data),
            3 => $this->xuLyThanhToanCongNoKhachHang($data),
            4 => $this->xuLyThuKhac($data),
            5 => $this->xuLyThuTaiChinh($data), // MỚI: loại tài chính
            default => throw new Exception('Loại phiếu thu không hợp lệ')
        };

        if ($result instanceof \App\Class\CustomResponse) {
            DB::rollBack();
            return $result;
        }

        // Mirror vào sổ quỹ (an toàn, idempotent)
        if ($result instanceof \App\Models\PhieuThu) {
            app(\App\Services\Cash\CashLedgerService::class)->recordReceipt($result);
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
        return CustomResponse::error('Không thể cập nhật phiếu thu');
    }

    /**
     * Xóa dữ liệu
     */
    public function delete($id)
    {
        try {
            $model = PhieuThu::findOrFail($id);

            if (! Helper::checkIsToday($model->created_at)) {
                return CustomResponse::error('Chỉ được xóa phiếu thu trong ngày hôm nay');
            }

            DB::beginTransaction();

            $result = match ($model->loai_phieu_thu) {
                1 => $this->xuLyXoaPhieuThuDonHang($model),
                2 => $this->xuLyXoaPhieuThuNhieuDonHang($model),
                3 => $this->xuLyXoaPhieuThuCongNoKhachHang($model),
                4 => $this->xuLyXoaThuKhac($model),
                5 => $this->xuLyXoaThuTaiChinh($model), // MỚI
                default => throw new Exception('Loại phiếu thu không hợp lệ')
            };

            if ($result instanceof \App\Class\CustomResponse) {
                DB::rollBack();

                return $result;
            }

// Gỡ bút toán sổ quỹ nếu đã ghi
app(\App\Services\Cash\CashLedgerService::class)->removeReceipt($model);


            $model->delete();
            DB::commit();

            return $model;
        } catch (Exception $e) {
            DB::rollBack();

            return CustomResponse::error($e->getMessage());
        }
    }

    /**
     * Lấy danh sách PhieuThu dạng option
     */
    public function getOptions()
    {
        return PhieuThu::select('id as value', 'ten_phieu_thu as label')->get();
    }

    /**
     * Xử lý thanh toán cho đơn hàng
     */
    private function xuLyThanhToanDonHang(array $data)
    {
        $donHang = $this->kiemTraDonHangTonTai($data['don_hang_id']);
        if (! $donHang) {
            throw new Exception('Đơn hàng không tồn tại');
        }

        $kiemTra = $this->kiemTraSoTienThanhToanHopLe($donHang, $data['so_tien']);
        if ($kiemTra !== true) {
            return $kiemTra;
        }

        $this->capNhatTrangThaiThanhToanDonHang($donHang, $data['so_tien']);

        return PhieuThu::create($data);
    }

    /**
     * Xử lý thanh toán cho nhiều đơn hàng theo khách hàng
     */
    private function xuLyThanhToanNhieuDonHang(array $data)
    {
        if (empty($data['don_hang_ids'])) {
            throw new Exception('Danh sách đơn hàng không được để trống');
        }

        $donHangIds = $this->layDanhSachIdDonHang($data['don_hang_ids']);
        $donHangs = $this->layDonHangTheoIds($donHangIds, $data['khach_hang_id']);

        if ($donHangs->isEmpty()) {
            throw new Exception('Không tìm thấy công nợ của khách hàng này');
        }

        $donHangList = $data['don_hang_ids'];
        unset($data['don_hang_ids']);
        $phieuThu = PhieuThu::create($data);

        $this->xuLyPhanBoDonHang($phieuThu->id, $donHangs, $donHangList);

        return $phieuThu;
    }

    /**
     * Xử lý thanh toán công nợ khách hàng
     */
    private function xuLyThanhToanCongNoKhachHang(array $data)
    {
        if (empty($data['khach_hang_id'])) {
            throw new Exception('Khách hàng không được để trống');
        }

        $donHangs = $this->layDonHangChuaThanhToanDayDu($data['khach_hang_id']);
        if ($donHangs->isEmpty()) {
            throw new Exception('Không tìm thấy công nợ của khách hàng này');
        }

        $tongTienCanThanhToan = $this->tinhTongTienCanThanhToan($donHangs);
        if ($tongTienCanThanhToan < $data['so_tien']) {
            throw new Exception('Số tiền thanh toán nhiều hơn số tiền cần thanh toán khách hàng');
        }

        $phieuThu = PhieuThu::create($data);
        $this->xuLyPhanBoTienThanhToan($phieuThu->id, $donHangs, $data['so_tien']);

        return $phieuThu;
    }

    /**
     * Xử lý thu khác
     */
    private function xuLyThuKhac(array $data)
    {
        return PhieuThu::create($data);
    }

    /**
     * MỚI: Xử lý thu hoạt động tài chính (loại 5)
     */
    private function xuLyThuTaiChinh(array $data)
    {
        // Không gắn đơn/khách — chỉ tạo bản ghi
        return PhieuThu::create($data);
    }

    /**
     * Xử lý xóa phiếu thu đơn hàng
     */
    private function xuLyXoaPhieuThuDonHang($model)
    {
        $donHang = DonHang::find($model->don_hang_id);
        $this->capNhatTrangThaiThanhToanDonHang($donHang, $model->so_tien, true);

        return true;
    }

    /**
     * Xử lý xóa phiếu thu nhiều đơn hàng
     */
    private function xuLyXoaPhieuThuNhieuDonHang($model)
    {
        $this->hoantTraTienTuChiTietPhieuThu($model->id);
        $this->xoaChiTietPhieuThu($model->id);

        return true;
    }

    /**
     * Xử lý xóa phiếu thu công nợ khách hàng
     */
    private function xuLyXoaPhieuThuCongNoKhachHang($model)
    {
        $this->hoantTraTienTuChiTietPhieuThu($model->id);
        $this->xoaChiTietPhieuThu($model->id);

        return true;
    }

    /**
     * Xử lý xóa thu khác
     */
    private function xuLyXoaThuKhac($model)
    {
        return true;
    }

    /**
     * MỚI: Xử lý xóa thu hoạt động tài chính (loại 5)
     */
    private function xuLyXoaThuTaiChinh($model)
    {
        // Không cần hoàn/điều chỉnh đơn hàng
        return true;
    }

    /**
     * Cập nhật trạng thái thanh toán đơn hàng
     */
    private function capNhatTrangThaiThanhToanDonHang($donHang, $soTien, $isGiam = false)
    {
        $soTienMoi = $isGiam
          ? $donHang->so_tien_da_thanh_toan - $soTien
          : $donHang->so_tien_da_thanh_toan + $soTien;

        $donHang->update([
            'so_tien_da_thanh_toan' => $soTienMoi,
            'trang_thai_thanh_toan' => $soTienMoi >= $donHang->tong_tien_can_thanh_toan ? 1 : 0,
        ]);
    }

    /**
     * Kiểm tra đơn hàng tồn tại
     */
    private function kiemTraDonHangTonTai($donHangId)
    {
        return DonHang::find($donHangId);
    }

    /**
     * Kiểm tra số tiền thanh toán hợp lệ
     */
    private function kiemTraSoTienThanhToanHopLe($donHang, $soTienThanhToan)
    {
        $soTienCanThanhToan = $donHang->tong_tien_can_thanh_toan - $donHang->so_tien_da_thanh_toan;

        if ($soTienThanhToan > $soTienCanThanhToan) {
            throw new Exception('Số tiền thanh toán nhiều hơn số tiền cần thanh toán');
        }

        return true;
    }

    /**
     * Lấy danh sách ID đơn hàng
     */
    private function layDanhSachIdDonHang($donHangIds)
    {
        return collect($donHangIds)->map(function ($item) {
            return (int) $item['id'];
        })->toArray();
    }

    /**
     * Lấy đơn hàng theo IDs và khách hàng
     */
    private function layDonHangTheoIds($donHangIds, $khachHangId)
    {
        return DonHang::whereIn('id', $donHangIds)
            ->where('khach_hang_id', $khachHangId)
            ->whereRaw('so_tien_da_thanh_toan < tong_tien_can_thanh_toan')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Lấy đơn hàng chưa thanh toán đầy đủ
     */
    private function layDonHangChuaThanhToanDayDu($khachHangId)
    {
        return DonHang::where('khach_hang_id', $khachHangId)
            ->whereRaw('so_tien_da_thanh_toan < tong_tien_can_thanh_toan')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Tính tổng tiền cần thanh toán
     */
    private function tinhTongTienCanThanhToan($donHangs)
    {
        return $donHangs->sum(function ($donHang) {
            return $donHang->tong_tien_can_thanh_toan - $donHang->so_tien_da_thanh_toan;
        });
    }

    /**
     * Xử lý phân bổ đơn hàng
     */
    private function xuLyPhanBoDonHang($phieuThuId, $donHangs, $donHangList)
    {
        foreach ($donHangs as $donHang) {
            $soTienThanhToan = $this->laySoTienThanhToanTuDanhSach($donHang->id, $donHangList);

            if ($soTienThanhToan <= 0) {
                continue;
            }

            $kiemTra = $this->kiemTraSoTienThanhToanHopLe($donHang, $soTienThanhToan);
            if ($kiemTra !== true) {
                throw new Exception('Đơn hàng '.$donHang->ma_don_hang.' có số tiền thanh toán nhiều hơn số tiền công nợ');
            }

            $this->capNhatTrangThaiThanhToanDonHang($donHang, $soTienThanhToan);
            $this->taoChiTietPhieuThu($phieuThuId, $donHang->id, $soTienThanhToan);
        }
    }

    /**
     * Xử lý phân bổ tiền thanh toán
     */
    private function xuLyPhanBoTienThanhToan($phieuThuId, $donHangs, $soTienThanhToan)
    {
        foreach ($donHangs as $donHang) {
            if ($soTienThanhToan <= 0) {
                break;
            }

            $soTienCanThanhToan = $donHang->tong_tien_can_thanh_toan - $donHang->so_tien_da_thanh_toan;
            $soTienThanhToanPhieu = min($soTienCanThanhToan, $soTienThanhToan);

            $this->capNhatTrangThaiThanhToanDonHang($donHang, $soTienThanhToanPhieu);
            $this->taoChiTietPhieuThu($phieuThuId, $donHang->id, $soTienThanhToanPhieu);

            $soTienThanhToan -= $soTienThanhToanPhieu;
        }
    }

    /**
     * Lấy số tiền thanh toán từ danh sách
     */
    private function laySoTienThanhToanTuDanhSach($donHangId, $donHangList)
    {
        foreach ($donHangList as $item) {
            if ((int) $item['id'] === $donHangId && isset($item['so_tien_thanh_toan'])) {
                return $item['so_tien_thanh_toan'];
            }
        }

        return 0;
    }

    /**
     * Tạo chi tiết phiếu thu
     */
    private function taoChiTietPhieuThu($phieuThuId, $donHangId, $soTien)
    {
        return ChiTietPhieuThu::create([
            'phieu_thu_id' => $phieuThuId,
            'don_hang_id' => $donHangId,
            'so_tien' => $soTien,
        ]);
    }

    /**
     * Hoàn trả tiền từ chi tiết phiếu thu
     */
    private function hoantTraTienTuChiTietPhieuThu($phieuThuId)
    {
        $chiTietPhieuThu = ChiTietPhieuThu::where('phieu_thu_id', $phieuThuId)->get();

        foreach ($chiTietPhieuThu as $chiTiet) {
            $donHang = DonHang::find($chiTiet->don_hang_id);
            $this->capNhatTrangThaiThanhToanDonHang($donHang, $chiTiet->so_tien, true);
        }
    }

    /**
     * Xóa chi tiết phiếu thu
     */
    private function xoaChiTietPhieuThu($phieuThuId)
    {
        ChiTietPhieuThu::where('phieu_thu_id', $phieuThuId)->delete();
    }

    /**
     * MỚI: Chuẩn hoá "loại" nhận từ FE/BE
     * - Cho phép 'TAI_CHINH' -> 5
     * - Nếu là chuỗi số -> ép về số
     */
    private function normalizeLoai($loai): int
    {
        if (is_string($loai)) {
            $u = strtoupper(trim($loai));
            if ($u === 'TAI_CHINH') {
                return 5;
            }
            if (is_numeric($loai)) {
                return (int) $loai;
            }
        }
        return (int) $loai;
    }
}
