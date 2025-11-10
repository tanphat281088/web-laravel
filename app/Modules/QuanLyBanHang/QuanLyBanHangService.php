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
use App\Models\DonViTinhSanPham;


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

            // ✅ Sắp xếp DH00284 → DH00283 → … theo phần số sau “DH”
            // (để an toàn, tie-break thêm theo id desc)
            $query->orderByRaw('CAST(SUBSTRING(don_hangs.ma_don_hang, 3) AS UNSIGNED) DESC')
                  ->orderByDesc('don_hangs.id');

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
        $conLai = max(0, $tongTienCanThanhToan - $daTT);
        /**
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

        // Phòng thủ
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
                    $dt = $raw instanceof \DateTimeInterface ? Carbon::instance($raw) : Carbon::parse($raw);
                    $data['nguoi_nhan_thoi_gian'] = $dt->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
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

// ====== BEGIN: OVERRIDE PRICE (3 MÃ WHITELIST) + PREPARE SYNC ======
static $OVERRIDE_CODES = ['KG00001', 'KG00002', 'MO00001'];

// Lưu override để đồng bộ về san_phams sau khi lưu đơn
$priceSync = $priceSync ?? [];

$sanPham = SanPham::find($item['san_pham_id']);
if (!$sanPham) {
    throw new Exception('Sản phẩm ' . $item['san_pham_id'] . ' không tồn tại');
}

$code        = strtoupper((string)($sanPham->ma_san_pham ?? ''));
$canOverride = in_array($code, $OVERRIDE_CODES, true);
$userPrice   = isset($item['don_gia']) ? (int)$item['don_gia'] : null;


// Ưu tiên override khi thoả điều kiện
if ($canOverride && $userPrice !== null) {
    $usedPrice = max(0, $userPrice);

    // Ghi vào chi tiết
    $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
    $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * $usedPrice;

    // Gom để sync về san_phams và đảm bảo mapping DVT
    $priceSync[] = [
        'san_pham_id'    => (int)$item['san_pham_id'],
        'don_vi_tinh_id' => (int)$item['don_vi_tinh_id'],
        'loai_gia'       => (int)$data['danh_sach_san_pham'][$index]['loai_gia'],
        'price'          => $usedPrice,
        'override'       => true,
    ];
} else {
    // Hành vi cũ: ưu tiên giá theo lô; nếu không có lô → lấy từ SanPham
    $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'])
        ->where('don_vi_tinh_id', $item['don_vi_tinh_id'])
        ->orderBy('id', 'asc')
        ->first();

    if ($loSanPham) {
        $usedPrice = (int)$loSanPham->gia_ban_le_don_vi;
    } else {
        $usedPrice = (int)(((int)($data['danh_sach_san_pham'][$index]['loai_gia'] ?? 1) === 2)
            ? ($sanPham->gia_dat_truoc_3n ?? 0)
            : ($sanPham->gia_nhap_mac_dinh ?? 0));
    }

    $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
    $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * $usedPrice;

    // Vẫn push để đảm bảo mapping DVT ở bước sau
    $priceSync[] = [
        'san_pham_id'    => (int)$item['san_pham_id'],
        'don_vi_tinh_id' => (int)$item['don_vi_tinh_id'],
        'loai_gia'       => (int)$data['danh_sach_san_pham'][$index]['loai_gia'],
        'price'          => $usedPrice,
        'override'       => false,
    ];
}
// Cộng dồn tổng

// ====== END: OVERRIDE PRICE + PREPARE SYNC ======

                $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];
            }

            $giamGia = (int)($data['giam_gia'] ?? 0);
            $chiPhi  = (int)($data['chi_phi'] ?? 0);

            // ===== VAT-AWARE TOTALS (TƯƠNG THÍCH NGƯỢC) =====
            $taxMode = (int)($data['tax_mode'] ?? 0);
            $vatRate = array_key_exists('vat_rate', $data) ? (float)$data['vat_rate'] : null;

            // 1) Subtotal
            $subtotal = max(0, (int)$tongTienHang - $giamGia + $chiPhi);

            // 2) VAT
            if ($taxMode === 1 && $vatRate !== null) {
                $vatAmount  = (int) round($subtotal * $vatRate / 100, 0);
                $grandTotal = $subtotal + $vatAmount;
            } else {
                $taxMode   = 0;
                $vatRate   = null;
                $vatAmount = null;
                $grandTotal = $subtotal;
            }

            // 3) Tổng cần thanh toán
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
                $dataDonHang['ma_don_hang']
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

// ====== BEGIN: SYNC BACK TO san_phams + ENSURE DVT MAPPING ======
if (!empty($priceSync)) {
    foreach ($priceSync as $p) {
        // (1) Đảm bảo có mapping đơn vị tính cho SP/DVT
        if (!empty($p['san_pham_id']) && !empty($p['don_vi_tinh_id'])) {
            DonViTinhSanPham::firstOrCreate([
                'san_pham_id'    => (int)$p['san_pham_id'],
                'don_vi_tinh_id' => (int)$p['don_vi_tinh_id'],
            ]);
        }

        // (2) Chỉ sync giá khi thực sự override (3 mã whitelist)
        if (!empty($p['override'])) {
            /** @var \App\Models\SanPham|null $sp */
            $sp = SanPham::find((int)$p['san_pham_id']);
            if ($sp) {
                if ((int)$p['loai_gia'] === 2) {
                    // 2 = Đặt trước 3 ngày
                    $sp->gia_dat_truoc_3n = (int)$p['price'];
                } else {
                    // 1 = Đặt ngay
                    $sp->gia_nhap_mac_dinh = (int)$p['price'];
                }
                $sp->saveQuietly();
            }
        }
    }
}
// ====== END: SYNC BACK ======



            DB::commit();
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
        // Observer DonHang sẽ tự sinh PHIẾU HIỆU CHỈNH để cân đối → không cần chặn ở đây。

        // ===== RULE LOCKING: quyết định trường nào được phép sửa =====
        $isDelivered       = (int)$donHang->trang_thai_don_hang === DonHang::TRANG_THAI_DA_GIAO; // 2
        $isPaidFull        = (int)$donHang->trang_thai_thanh_toan === 2
                             || (int)$donHang->so_tien_da_thanh_toan >= (int)$donHang->tong_tien_can_thanh_toan;
        $isOlderThan10Days = Carbon::parse($donHang->ngay_tao_don_hang)->diffInDays(Carbon::now()) > 10;

        // Ưu tiên: (1) đã giao & đã thanh toán đủ → khoá toàn bộ
        //          (2) đã giao → chỉ cho sửa thanh toán + ghi chú
        //          (3) >10 ngày → chỉ cho sửa: trạng thái, giờ nhận, thanh toán, ghi chú,
        //                         và ĐỊA CHỈ khi CHƯA giao
        //          (4) còn lại → không giới hạn
        $allowed = null; // null = không giới hạn
        if ($isDelivered && $isPaidFull) {
            return CustomResponse::error('Đơn đã giao và đã thanh toán đủ — khoá toàn bộ chỉnh sửa.', 422);
        } elseif ($isDelivered) {
            $allowed = ['loai_thanh_toan', 'so_tien_da_thanh_toan', 'ghi_chu'];
        } elseif ($isOlderThan10Days) {
            $allowed = [
                'trang_thai_don_hang',
                'nguoi_nhan_thoi_gian',
                'loai_thanh_toan',
                'so_tien_da_thanh_toan',
                'ghi_chu',
            ];
            if (!$isDelivered) {
                $allowed[] = 'dia_chi_giao_hang';
            }
        }

        // Nếu có whitelist → chỉ giữ các key hợp lệ
        if (is_array($allowed)) {
            $data = array_intersect_key($data, array_flip($allowed));
        }

        try {
            // ===== Chỉ tái tính tiền/hàng nếu payload có các field liên quan =====
            $allowMoneyRecalc = array_key_exists('danh_sach_san_pham', $data)
                             || array_key_exists('giam_gia', $data)
                             || array_key_exists('chi_phi', $data)
                             || array_key_exists('tax_mode', $data)
                             || array_key_exists('vat_rate', $data);

            if ($allowMoneyRecalc) {
                $tongTienHang = 0;

                    $priceSync = $priceSync ?? [];


                foreach ($data['danh_sach_san_pham'] as $index => $item) {


                    // ✅ Ghi lại loại giá khi cập nhật (default 1 nếu thiếu)
                    $data['danh_sach_san_pham'][$index]['loai_gia'] = $item['loai_gia'] ?? 1;

// ====== BEGIN: OVERRIDE PRICE (3 MÃ WHITELIST) + PREPARE SYNC ======
static $OVERRIDE_CODES = ['KG00001', 'KG00002', 'MO00001'];

$sanPham = SanPham::find($item['san_pham_id']);
if (!$sanPham) {
    throw new Exception('Sản phẩm ' . $item['san_pham_id'] . ' không tồn tại');
}

$code        = strtoupper((string)($sanPham->ma_san_pham ?? ''));
$canOverride = in_array($code, $OVERRIDE_CODES, true);
$userPrice   = isset($item['don_gia']) ? (int)$item['don_gia'] : null;



if ($canOverride && $userPrice !== null) {
    // ✅ Tôn trọng giá nhập tay cho 3 mã whitelist
    $usedPrice = max(0, $userPrice);

    $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
    $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * $usedPrice;

    // Gom để sync về san_phams và đảm bảo mapping DVT
    $priceSync[] = [
        'san_pham_id'    => (int)$item['san_pham_id'],
        'don_vi_tinh_id' => (int)$item['don_vi_tinh_id'],
        'loai_gia'       => (int)$data['danh_sach_san_pham'][$index]['loai_gia'],
        'price'          => $usedPrice,
        'override'       => true,
    ];
} else {
    // Hành vi cũ: ưu tiên giá theo lô; nếu không có lô → lấy từ SanPham theo loai_gia
    $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $item['san_pham_id'])
        ->where('don_vi_tinh_id', $item['don_vi_tinh_id'])
        ->orderBy('id', 'asc')
        ->first();

    if ($loSanPham) {
        $usedPrice = (int)$loSanPham->gia_ban_le_don_vi;
    } else {
        $usedPrice = (int)(((int)($data['danh_sach_san_pham'][$index]['loai_gia'] ?? 1) === 2)
            ? ($sanPham->gia_dat_truoc_3n ?? 0)
            : ($sanPham->gia_nhap_mac_dinh ?? 0));
    }

    $data['danh_sach_san_pham'][$index]['don_gia']    = $usedPrice;
    $data['danh_sach_san_pham'][$index]['thanh_tien'] = (int)$item['so_luong'] * $usedPrice;

    // Vẫn push để đảm bảo mapping DVT ở bước SYNC
    $priceSync[] = [
        'san_pham_id'    => (int)$item['san_pham_id'],
        'don_vi_tinh_id' => (int)$item['don_vi_tinh_id'],
        'loai_gia'       => (int)$data['danh_sach_san_pham'][$index]['loai_gia'],
        'price'          => $usedPrice,
        'override'       => false,
    ];
}
// ====== END: OVERRIDE PRICE + PREPARE SYNC ======

                    $tongTienHang += (int)$data['danh_sach_san_pham'][$index]['thanh_tien'];
                }

                $giamGia = (int)($data['giam_gia'] ?? 0);
                $chiPhi  = (int)($data['chi_phi'] ?? 0);

                // ===== VAT-AWARE TOTALS (TƯƠNG THÍCH NGƯỢC) =====
                $taxMode = (int)($data['tax_mode'] ?? 0);
                $vatRate = array_key_exists('vat_rate', $data) ? (float)$data['vat_rate'] : null;

                // 1) Subtotal
                $subtotal = max(0, (int)$tongTienHang - $giamGia + $chiPhi);

                // 2) VAT
                if ($taxMode === 1 && $vatRate !== null) {
                    $vatAmount  = (int) round($subtotal * $vatRate / 100, 0);
                    $grandTotal = $subtotal + $vatAmount;
                } else {
                    $taxMode   = 0;
                    $vatRate   = null;
                    $vatAmount = null;
                    $grandTotal = $subtotal;
                }

                // 3) Tổng cần thanh toán
                $tongTienCanThanhToan = (int) $grandTotal;

                // Chuẩn hoá thanh toán theo tổng mới
                $this->normalizePayments($data, $tongTienCanThanhToan);

                // Tổng hợp trường tổng
                $data['tong_tien_hang']             = (int)$tongTienHang;
                $data['tong_tien_can_thanh_toan']   = (int)$tongTienCanThanhToan;
                $data['tong_so_luong_san_pham']     = isset($data['danh_sach_san_pham']) ? count($data['danh_sach_san_pham']) : $donHang->tong_so_luong_san_pham;

                // 4) Ghi vào $data cho DonHang (NULL khi không thuế để không phá report cũ)
                $data['tax_mode']   = $taxMode;
                $data['vat_rate']   = $vatRate;
                $data['subtotal']   = ($taxMode === 1) ? (int)$subtotal : null;
                $data['vat_amount'] = ($taxMode === 1) ? (int)$vatAmount : null;
                $data['grand_total']= ($taxMode === 1) ? (int)$grandTotal : null;

            } else {
                // KHÔNG tái tính tiền hàng khi không được phép chỉnh tiền/hàng
                // Chỉ chuẩn hoá thanh toán dựa trên tổng hiện tại trong DB
                $this->normalizePayments($data, (int) $donHang->tong_tien_can_thanh_toan);
            }

            // ✅ Chuẩn hoá thông tin người nhận (Tên/SĐT/Ngày giờ nhận)
            $this->normalizeRecipientFields($data);

            // ===== NEW: Chuẩn hoá trạng thái đơn hàng (0=Chưa giao,1=Đang giao,2=Đã giao,3=Đã hủy) =====
            if (array_key_exists('trang_thai_don_hang', $data)) {
                if ($data['trang_thai_don_hang'] === null || $data['trang_thai_don_hang'] === '') {
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

            if (isset($data['khach_hang_id']) && $data['khach_hang_id'] != null) {
                $khachHang = KhachHang::find($data['khach_hang_id']);
                if ($khachHang) {
                    $data['ten_khach_hang'] = $khachHang->ten_khach_hang;
                    $data['so_dien_thoai']  = $khachHang->so_dien_thoai;
                }
            }

            // ⚠️ KHÔNG cho update trực tiếp mã
            $dataDonHang = $data;
            unset(
                $dataDonHang['danh_sach_san_pham'],
                $dataDonHang['so_tien_con_lai'],
                $dataDonHang['ma_don_hang']
            );

            $donHang->update($dataDonHang);

            // Làm mới chi tiết — chỉ khi có gửi danh_sach_san_pham
            if (isset($data['danh_sach_san_pham']) && is_array($data['danh_sach_san_pham'])) {
                $donHang->chiTietDonHangs()->delete();
                foreach ($data['danh_sach_san_pham'] as $item) {
                    $item['don_hang_id'] = $donHang->id;
                    ChiTietDonHang::create($item);
                }
            }

// ====== BEGIN: SYNC BACK TO san_phams + ENSURE DVT MAPPING ======
if (!empty($priceSync)) {
    foreach ($priceSync as $p) {
        // (1) Đảm bảo có mapping đơn vị tính cho SP/DVT
        if (!empty($p['san_pham_id']) && !empty($p['don_vi_tinh_id'])) {
            \App\Models\DonViTinhSanPham::firstOrCreate([
                'san_pham_id'    => (int)$p['san_pham_id'],
                'don_vi_tinh_id' => (int)$p['don_vi_tinh_id'],
            ]);
        }

        // (2) Chỉ sync giá khi thực sự override (3 mã whitelist)
        if (!empty($p['override'])) {
            /** @var \App\Models\SanPham|null $sp */
            $sp = \App\Models\SanPham::find((int)$p['san_pham_id']);
            if ($sp) {
                if ((int)$p['loai_gia'] === 2) {
                    // 2 = Đặt trước 3 ngày
                    $sp->gia_dat_truoc_3n = (int)$p['price'];
                } else {
                    // 1 = Đặt ngay
                    $sp->gia_nhap_mac_dinh = (int)$p['price'];
                }
                $sp->saveQuietly();
            }
        }
    }
}
// ====== END: SYNC BACK ======


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
     */
    public function getGiaBanSanPham($sanPhamId, $donViTinhId, $loaiGia = 1)
    {
        // Ưu tiên giá theo lô
        $loSanPham = ChiTietPhieuNhapKho::where('san_pham_id', $sanPhamId)
            ->where('don_vi_tinh_id', $donViTinhId)
            ->orderBy('id', 'asc')
            ->first();

        if ($loSanPham) {
            return (int)$loSanPham->gia_ban_le_don_vi;
        }

        // Không có lô → chọn giá theo loại
        $sanPham = SanPham::find($sanPhamId);
        if ($sanPham) {
            $base = (int)($loaiGia == 2
                ? ($sanPham->gia_dat_truoc_3n ?? 0)
                : ($sanPham->gia_nhap_mac_dinh ?? 0));

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
        return DonHang::with('chiTietDonHangs.sanPham', 'chiTietDonHangs.donViTinh')
            ->where('id', $donHangId)
            ->first();
    }

    public function getDonHangByKhachHangId($khachHangId)
    {
        return DonHang::with('khachHang')
            ->where('khach_hang_id', $khachHangId)
            ->where('trang_thai_thanh_toan', 0)
            ->get();
    }

    public function getSoTienCanThanhToan($donHangId)
    {
        $donHang = $this->getById($donHangId);
        return (int)$donHang->tong_tien_can_thanh_toan - (int)$donHang->so_tien_da_thanh_toan;
    }
}
