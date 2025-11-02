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
use Carbon\Carbon;

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
            throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
        }
    }

    /**
     * Lấy dữ liệu theo ID
     */
    public function getById($id)
    {
        $data = DonHang::with('khachHang', 'chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')->find($id);
        if (!$data) {
            return CustomResponse::error('Dữ liệu không tồn tại');
        }

        return $data;
    }

    /**
     * Chuẩn hoá thanh toán theo loại thanh toán (an toàn dữ liệu)
     * - loai_thanh_toan: 0=Chưa TT, 1=TT một phần, 2=TT toàn bộ
     * - chỉ thiết lập so_tien_da_thanh_toan & trang_thai_thanh_toan
     * - KHÔNG ghi so_tien_con_lai vào DB (tránh yêu cầu thêm cột)
     */
    private function normalizePayments(array &$data, int $tongTienCanThanhToan): void
    {
        $loai = (int)($data['loai_thanh_toan'] ?? 0);
        $daTT = (int)($data['so_tien_da_thanh_toan'] ?? 0);

        if ($loai === 0) {
            // Chưa thanh toán
            $daTT = 0;
        } elseif ($loai === 2) {
            // Thanh toán toàn bộ
            $daTT = $tongTienCanThanhToan;
        } else {
            // Thanh toán một phần: kẹp 0..tổng
            if ($daTT < 0) $daTT = 0;
            if ($daTT > $tongTienCanThanhToan) $daTT = $tongTienCanThanhToan;
        }

        $data['so_tien_da_thanh_toan'] = $daTT;

        // Dẫn xuất "còn lại" để quyết định trạng thái (không lưu DB)
// Dẫn xuất "còn lại" để quyết định trạng thái (không lưu DB)
$conLai = max(0, $tongTienCanThanhToan - $daTT);
/**
 * Quy ước chuẩn:
 * 0 = chưa thanh toán
 * 1 = thanh toán một phần
 * 2 = đã thanh toán đủ
 */
if ($daTT <= 0) {
    $data['trang_thai_thanh_toan'] = 0;
} elseif ($conLai > 0) {
    $data['trang_thai_thanh_toan'] = 1;
} else {
    $data['trang_thai_thanh_toan'] = 2;
}


        // Phòng thủ: nếu phía FE gửi so_tien_con_lai, loại bỏ trước khi create/update
        unset($data['so_tien_con_lai']);
    }

    /**
     * Chuẩn hoá thông tin người nhận (Tên/SĐT/Ngày giờ nhận)
     * - Không bắt buộc 3 field này; chỉ xử lý khi có
     * - Ngày giờ nhận: nhận mọi định dạng hợp lệ và chuẩn hoá 'Y-m-d H:i:s'
     */
    private function normalizeRecipientFields(array &$data): void
    {
        if (array_key_exists('nguoi_nhan_ten', $data) && $data['nguoi_nhan_ten'] !== null) {
            $data['nguoi_nhan_ten'] = trim((string)$data['nguoi_nhan_ten']);
        }

        if (array_key_exists('nguoi_nhan_sdt', $data) && $data['nguoi_nhan_sdt'] !== null) {
            $data['nguoi_nhan_sdt'] = trim((string)$data['nguoi_nhan_sdt']);
        }

        if (array_key_exists('nguoi_nhan_thoi_gian', $data)) {
            $raw = $data['nguoi_nhan_thoi_gian'];

            if ($raw === null || $raw === '') {
                $data['nguoi_nhan_thoi_gian'] = null;
            } else {
                try {
                    // Hỗ trợ cả ISO string / timestamp / date string
                    $dt = $raw instanceof \DateTimeInterface ? Carbon::instance($raw) : Carbon::parse($raw);
                    $data['nguoi_nhan_thoi_gian'] = $dt->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    // Nếu parse lỗi, để null để không làm hỏng request
                    $data['nguoi_nhan_thoi_gian'] = null;
                }
            }
        }
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
                // ✅ Ghi lại loại giá (default 1 nếu thiếu từ FE)
                $data['danh_sach_san_pham'][$index]['loai_gia'] = $item['loai_gia'] ?? 1;

                $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'])
                    ->where('don_vi_tinh_id', $item['don_vi_tinh_id'])
                    ->orderBy('id', 'asc')
                    ->first();

                if ($loSanPham) {
                    $data['danh_sach_san_pham'][$index]['don_gia'] = (int)$loSanPham->gia_ban_le_don_vi;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * (int)$data['danh_sach_san_pham'][$index]['don_gia'];
                } else {
                    $sanPham = SanPham::find($item['san_pham_id']);
                    if ($sanPham) {
                        // 1 = Đặt ngay → gia_nhap_mac_dinh
                        // 2 = Đặt trước 3 ngày → gia_dat_truoc_3n
                        $base = (int)((isset($item['loai_gia']) && (int)$item['loai_gia'] === 2)
                            ? ($sanPham->gia_dat_truoc_3n ?? 0)
                            : ($sanPham->gia_nhap_mac_dinh ?? 0));

                        $data['danh_sach_san_pham'][$index]['don_gia']    = $base;
                        $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * $base;
                    } else {
                        throw new Exception('Sản phẩm ' . $item['san_pham_id'] . ' không tồn tại');
                    }
                }

                $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $giamGia = (int)($data['giam_gia'] ?? 0);
            $chiPhi  = (int)($data['chi_phi'] ?? 0);

// ===== VAT-AWARE TOTALS (TƯƠNG THÍCH NGƯỢC) =====
$taxMode = (int)($data['tax_mode'] ?? 0);
$vatRate = array_key_exists('vat_rate', $data) ? (float)$data['vat_rate'] : null;

// 1) Subtotal = tổng hàng - giảm giá + chi phí (kẹp >= 0)
$subtotal = max(0, (int)$tongTienHang - $giamGia + $chiPhi);

// 2) VAT (chỉ khi tax_mode=1 & có vat_rate)
if ($taxMode === 1 && $vatRate !== null) {
    $vatAmount  = (int) round($subtotal * $vatRate / 100, 0); // làm tròn đồng
    $grandTotal = $subtotal + $vatAmount;
} else {
    // giữ hành vi cũ: không thuế
    $taxMode   = 0;
    $vatRate   = null;
    $vatAmount = null;    // để NULL -> tương thích ngược
    $grandTotal = $subtotal;
}

// 3) legacy field vẫn dùng: gán tổng cần thanh toán = grand_total
$tongTienCanThanhToan = (int) $grandTotal;

// Chuẩn hoá thanh toán theo tổng mới
$this->normalizePayments($data, $tongTienCanThanhToan);

// 4) Ghi vào $data cho DonHang (NULL khi không thuế để không phá report cũ)
$data['tax_mode']   = $taxMode;
$data['vat_rate']   = $vatRate;
$data['subtotal']   = ($taxMode === 1) ? (int)$subtotal : null;
$data['vat_amount'] = ($taxMode === 1) ? (int)$vatAmount : null;
$data['grand_total']= ($taxMode === 1) ? (int)$grandTotal : null;


            // ✅ Chuẩn hoá thông tin người nhận (Tên/SĐT/Ngày giờ nhận)
            $this->normalizeRecipientFields($data);
// ===== NEW: Chuẩn hoá trạng thái đơn hàng (0=Chưa giao,1=Đang giao,2=Đã giao,3=Đã hủy) =====
if (!array_key_exists('trang_thai_don_hang', $data) || $data['trang_thai_don_hang'] === null || $data['trang_thai_don_hang'] === '') {
    $data['trang_thai_don_hang'] = DonHang::TRANG_THAI_CHUA_GIAO; // default = 0
} else {
    $v = (int)$data['trang_thai_don_hang'];
    $data['trang_thai_don_hang'] = in_array($v, [
        DonHang::TRANG_THAI_CHUA_GIAO,
        DonHang::TRANG_THAI_DANG_GIAO,
        DonHang::TRANG_THAI_DA_GIAO,
        DonHang::TRANG_THAI_DA_HUY,
    ], true) ? $v : DonHang::TRANG_THAI_CHUA_GIAO;
}

            $data['tong_tien_hang'] = (int)$tongTienHang;
            $data['tong_tien_can_thanh_toan'] = (int)$tongTienCanThanhToan;
            $data['tong_so_luong_san_pham'] = count($data['danh_sach_san_pham']);

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai'] = $khachHang->so_dien_thoai;
                }
            }

            // ⚠️ Quan trọng: KHÔNG nhận ma_don_hang từ request (BE tự sinh)
            $dataDonHang = $data;
            unset(
                $dataDonHang['danh_sach_san_pham'],
                $dataDonHang['so_tien_con_lai'],
                $dataDonHang['ma_don_hang'] // <- thêm phòng thủ
            );

            $donHang = DonHang::create($dataDonHang);

            // 🔒 Fallback an toàn: nếu hook created() chưa gán mã, tự gán tại đây
            if (empty($donHang->ma_don_hang)) {
                $donHang->ma_don_hang = 'DH' . str_pad((string)$donHang->id, 5, '0', STR_PAD_LEFT);
                $donHang->saveQuietly();
            }

            foreach ($data['danh_sach_san_pham'] as $item) {
                $item['don_hang_id'] = $donHang->id;
                ChiTietDonHang::create($item);
            }

            DB::commit();
            // refresh để đảm bảo có ma_don_hang (từ hook hoặc fallback)
            return $donHang->refresh();
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

        // ⛔️ GỠ CHẶN: cho phép cập nhật dù đã có phiếu thu.
        // Observer DonHang sẽ tự sinh PHIẾU HIỆU CHỈNH để cân đối → không cần chặn ở đây।

        try {
            $tongTienHang = 0;

            foreach ($data['danh_sach_san_pham'] as $index => $item) {
                // ✅ Ghi lại loại giá khi cập nhật (default 1 nếu thiếu)
                $data['danh_sach_san_pham'][$index]['loai_gia'] = $item['loai_gia'] ?? 1;

                $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'])
                    ->where('don_vi_tinh_id', $item['don_vi_tinh_id'])
                    ->orderBy('id', 'asc')
                    ->first();

                if ($loSanPham) {
                    $data['danh_sach_san_pham'][$index]['don_gia'] = (int)$loSanPham->gia_ban_le_don_vi;
                    $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * (int)$data['danh_sach_san_pham'][$index]['don_gia'];
                } else {
                    $sanPham = SanPham::find($item['san_pham_id']);
                    if ($sanPham) {
                        // 1 = Đặt ngay → gia_nhap_mac_dinh
                        // 2 = Đặt trước 3 ngày → gia_dat_truoc_3n
                        $base = (int)((isset($item['loai_gia']) && (int)$item['loai_gia'] === 2)
                            ? ($sanPham->gia_dat_truoc_3n ?? 0)
                            : ($sanPham->gia_nhap_mac_dinh ?? 0));

                        $data['danh_sach_san_pham'][$index]['don_gia']    = $base;
                        $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * $base;
                    } else {
                        throw new Exception('Sản phẩm ' . $item['san_pham_id'] . ' không tồn tại');
                    }
                }

                $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $giamGia = (int)($data['giam_gia'] ?? 0);
            $chiPhi  = (int)($data['chi_phi'] ?? 0);

// ===== VAT-AWARE TOTALS (TƯƠNG THÍCH NGƯỢC) =====
$taxMode = (int)($data['tax_mode'] ?? 0);
$vatRate = array_key_exists('vat_rate', $data) ? (float)$data['vat_rate'] : null;

// 1) Subtotal = tổng hàng - giảm giá + chi phí (kẹp >= 0)
$subtotal = max(0, (int)$tongTienHang - $giamGia + $chiPhi);

// 2) VAT (chỉ khi tax_mode=1 & có vat_rate)
if ($taxMode === 1 && $vatRate !== null) {
    $vatAmount  = (int) round($subtotal * $vatRate / 100, 0); // làm tròn đồng
    $grandTotal = $subtotal + $vatAmount;
} else {
    // giữ hành vi cũ: không thuế
    $taxMode   = 0;
    $vatRate   = null;
    $vatAmount = null;    // để NULL -> tương thích ngược
    $grandTotal = $subtotal;
}

// 3) legacy field vẫn dùng: gán tổng cần thanh toán = grand_total
$tongTienCanThanhToan = (int) $grandTotal;

// Chuẩn hoá thanh toán theo tổng mới
$this->normalizePayments($data, $tongTienCanThanhToan);

// 4) Ghi vào $data cho DonHang (NULL khi không thuế để không phá report cũ)
$data['tax_mode']   = $taxMode;
$data['vat_rate']   = $vatRate;
$data['subtotal']   = ($taxMode === 1) ? (int)$subtotal : null;
$data['vat_amount'] = ($taxMode === 1) ? (int)$vatAmount : null;
$data['grand_total']= ($taxMode === 1) ? (int)$grandTotal : null;


            // ✅ Chuẩn hoá thông tin người nhận (Tên/SĐT/Ngày giờ nhận)
            $this->normalizeRecipientFields($data);
// ===== NEW: Chuẩn hoá trạng thái đơn hàng (0=Chưa giao,1=Đang giao,2=Đã giao,3=Đã hủy) =====
if (array_key_exists('trang_thai_don_hang', $data)) {
    if ($data['trang_thai_don_hang'] === null || $data['trang_thai_don_hang'] === '') {
        // nếu FE cố ý gửi rỗng → đặt về default 0 để dữ liệu nhất quán
        $data['trang_thai_don_hang'] = DonHang::TRANG_THAI_CHUA_GIAO;
    } else {
        $v = (int)$data['trang_thai_don_hang'];
        $data['trang_thai_don_hang'] = in_array($v, [
            DonHang::TRANG_THAI_CHUA_GIAO,
            DonHang::TRANG_THAI_DANG_GIAO,
            DonHang::TRANG_THAI_DA_GIAO,
            DonHang::TRANG_THAI_DA_HUY,
        ], true) ? $v : DonHang::TRANG_THAI_CHUA_GIAO;
    }
}
// nếu FE không gửi field này thì giữ nguyên trạng thái hiện có của đơn

            $data['tong_tien_hang'] = (int)$tongTienHang;
            $data['tong_tien_can_thanh_toan'] = (int)$tongTienCanThanhToan;
            $data['tong_so_luong_san_pham'] = count($data['danh_sach_san_pham']);

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai'] = $khachHang->so_dien_thoai;
                }
            }

            // ⚠️ Quan trọng: KHÔNG cho update trực tiếp trường mã (đã sinh cố định)
            $dataDonHang = $data;
            unset(
                $dataDonHang['danh_sach_san_pham'],
                $dataDonHang['so_tien_con_lai'],
                $dataDonHang['ma_don_hang'] // <- phòng thủ
            );

            $donHang->update($dataDonHang);

            // Làm mới chi tiết
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
     * - Nếu không có lô: chọn theo loại giá định trước.
     */
    public function getGiaBanSanPham($sanPhamId, $donViTinhId, $loaiGia = 1)
    {
        // Ưu tiên giá theo lô (giữ nguyên hành vi cũ)
        $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $sanPhamId)
            ->where('don_vi_tinh_id', $donViTinhId)
            ->orderBy('id', 'asc')
            ->first();

        if ($loSanPham) {
            return (int)$loSanPham->gia_ban_le_don_vi;
        }

        // Không có lô → chọn giá theo loại (giữ nguyên tên cột như code cũ)
        $sanPham = SanPham::find($sanPhamId);
        if ($sanPham) {
            $base = (int)($loaiGia == 2
                ? ($sanPham->gia_dat_truoc_3n ?? 0)    // GIỮ tên cột cũ
                : ($sanPham->gia_nhap_mac_dinh ?? 0)); // GIỮ tên cột cũ

            // ❌ Không cộng/nhân thêm lợi nhuận ở đây (đơn giá đã chuẩn)
            return $base;
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

            if (!$donHang) {
                return CustomResponse::error('Đơn hàng không tồn tại');
            }

            return view('hoa-don.template', compact('donHang'));
        } catch (Exception $e) {
            return CustomResponse::error('Lỗi khi xem trước hóa đơn: ' . $e->getMessage());
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
        return (int)$donHang->tong_tien_can_thanh_toan - (int)$donHang->so_tien_da_thanh_toan;
        // Nếu bạn đã thêm accessor getSoTienConLaiAttribute() trong DonHang,
        // có thể trả về $donHang->so_tien_con_lai cho UI/in hoá đơn.
    }
}
