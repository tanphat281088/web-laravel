<?php

use App\Class\CustomResponse;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\CauHinhChungController;
use App\Http\Controllers\api\LichSuImportController;
use App\Http\Controllers\api\ThoiGianLamViecController;
use App\Http\Controllers\api\UploadController;
use App\Http\Controllers\api\VaiTroController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB; // MỚI: dùng cho route loai-san-pham/options

// Auth
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/refresh', [AuthController::class, 'refresh'])->name('refresh');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOTP'])->name('verify-otp');

// Route công khai không cần xác thực
Route::get('/quan-ly-ban-hang/xem-truoc-hoa-don/{id}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'xemTruocHoaDon']);
Route::get('lich-su-import/download-file/{id}', [LichSuImportController::class, 'downloadFile']);

Route::prefix('dashboard')->group(function () {
    Route::get('/statistics', [DashboardController::class, 'getStatistics']);
    Route::get('/activities', [DashboardController::class, 'getRecentActivities']);
});

use App\Modules\SanPham\SanPhamController; // đảm bảo có use ở đầu file

// 👉 PUBLIC: combobox tìm sản phẩm theo mã/tên (không cần token)
Route::get('san-pham/options', [SanPhamController::class, 'getOptions']);


Route::group([
  'middleware' => ['jwt', 'permission'],
], function ($router) {

  // Authenticated
  Route::group(['prefix' => 'auth'], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('me', [AuthController::class, 'me']);
    Route::post('profile', [AuthController::class, 'updateProfile']);
  });

  // Lấy danh sách phân quyền
  Route::get('danh-sach-phan-quyen', function () {
    return CustomResponse::success(config('permission'));
  });

  // ================== LOẠI SẢN PHẨM (MASTER) – DROPDOWN OPTIONS ==================
  // MỚI: Endpoint trả về danh sách options cho dropdown "Loại sản phẩm"
  // value = code (ổn định để lưu vào san_phams.loai_san_pham), label = tên hiển thị
  Route::get('loai-san-pham/options', function () {
    $rows = DB::table('loai_san_pham_masters')
      ->select('code as value', 'ten_hien_thi as label')
      ->orderBy('ten_hien_thi')
      ->get();

    return response()->json([
      'success' => true,
      'data'    => $rows,
    ]);
  });
  // ===============================================================================

  // Vai trò
  Route::prefix('vai-tro')->group(function () {
    Route::get('/', [VaiTroController::class, 'index']);
    Route::get('/options', [VaiTroController::class, 'options']);
    Route::post('/', [VaiTroController::class, 'store']);
    Route::get('/{id}', [VaiTroController::class, 'show']);
    Route::put('/{id}', [VaiTroController::class, 'update']);
    Route::delete('/{id}', [VaiTroController::class, 'destroy']);
  });

  // Upload
  Route::post('upload/single', [UploadController::class, 'uploadSingle']);
  Route::post('upload/multiple', [UploadController::class, 'uploadMultiple']);

  // Cấu hình chung
  Route::get('cau-hinh-chung', [CauHinhChungController::class, 'index']);
  Route::post('cau-hinh-chung', [CauHinhChungController::class, 'create']);

  // Thời gian làm việc
  Route::get('thoi-gian-lam-viec', [ThoiGianLamViecController::class, 'index']);
  Route::patch('thoi-gian-lam-viec/{id}', [ThoiGianLamViecController::class, 'update']);

  // Lịch sử import
  Route::get('lich-su-import', [LichSuImportController::class, 'index']);

  // NguoiDung
  Route::prefix('nguoi-dung')->group(function () {
    Route::get('/', [\App\Modules\NguoiDung\NguoiDungController::class, 'index']);
    Route::post('/', [\App\Modules\NguoiDung\NguoiDungController::class, 'store']);
    Route::get('/{id}', [\App\Modules\NguoiDung\NguoiDungController::class, 'show']);
    Route::put('/{id}', [\App\Modules\NguoiDung\NguoiDungController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\NguoiDung\NguoiDungController::class, 'destroy']);
    Route::patch('/ngoai-gio/{id}', [\App\Modules\NguoiDung\NguoiDungController::class, 'changeStatusNgoaiGio']);
  });

  // LoaiKhachHang
  Route::prefix('loai-khach-hang')->group(function () {
    Route::get('/', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'index']);
    Route::get('/options', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'store']);
    Route::get('/{id}', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'show']);
    Route::put('/{id}', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\LoaiKhachHang\LoaiKhachHangController::class, 'importExcel']);
  });

  // KhachHang
  Route::prefix('khach-hang')->group(function () {
    Route::get('/', [\App\Modules\KhachHang\KhachHangController::class, 'index']);
    Route::get('/options', [\App\Modules\KhachHang\KhachHangController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\KhachHang\KhachHangController::class, 'downloadTemplateExcelWithLoaiKhachHang']);
    Route::post('/', [\App\Modules\KhachHang\KhachHangController::class, 'store']);
    Route::get('/{id}', [\App\Modules\KhachHang\KhachHangController::class, 'show']);
    Route::put('/{id}', [\App\Modules\KhachHang\KhachHangController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\KhachHang\KhachHangController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\KhachHang\KhachHangController::class, 'importExcel']);
  });

  // NhaCungCap
  Route::prefix('nha-cung-cap')->group(function () {
    Route::get('/', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'index']);
    Route::get('/options', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'store']);
    Route::get('/{id}', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'show']);
    Route::put('/{id}', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\NhaCungCap\NhaCungCapController::class, 'importExcel']);
  });

  // DanhMucSanPham
  Route::prefix('danh-muc-san-pham')->group(function () {
    Route::get('/', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'index']);
    Route::get('/options', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'store']);
    Route::get('/{id}', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'show']);
    Route::put('/{id}', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\DanhMucSanPham\DanhMucSanPhamController::class, 'importExcel']);
  });

  // DonViTinh
  Route::prefix('don-vi-tinh')->group(function () {
    Route::get('/', [\App\Modules\DonViTinh\DonViTinhController::class, 'index']);
    Route::get('/options', [\App\Modules\DonViTinh\DonViTinhController::class, 'getOptions']);
    Route::get('/options-by-san-pham/{sanPhamId}', [\App\Modules\DonViTinh\DonViTinhController::class, 'getOptionsBySanPham']);
    Route::get('/download-template-excel', [\App\Modules\DonViTinh\DonViTinhController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\DonViTinh\DonViTinhController::class, 'store']);
    Route::get('/{id}', [\App\Modules\DonViTinh\DonViTinhController::class, 'show']);
    Route::put('/{id}', [\App\Modules\DonViTinh\DonViTinhController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\DonViTinh\DonViTinhController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\DonViTinh\DonViTinhController::class, 'importExcel']);
  });

  // SanPham
  Route::prefix('san-pham')->group(function () {
    Route::get('/', [\App\Modules\SanPham\SanPhamController::class, 'index']);
    
    Route::get('/options-by-nha-cung-cap/{nhaCungCapId}', [\App\Modules\SanPham\SanPhamController::class, 'getOptionsByNhaCungCap']);
    Route::get('/options-lo-san-pham-by-san-pham/{sanPhamId}/{donViTinhId}', [\App\Modules\SanPham\SanPhamController::class, 'getOptionsLoSanPhamBySanPhamIdAndDonViTinhId']);
    Route::get('/download-template-excel', [\App\Modules\SanPham\SanPhamController::class, 'downloadTemplateExcelWithRelations']);
    Route::post('/', [\App\Modules\SanPham\SanPhamController::class, 'store']);
    Route::get('/{id}', [\App\Modules\SanPham\SanPhamController::class, 'show']);
    Route::put('/{id}', [\App\Modules\SanPham\SanPhamController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\SanPham\SanPhamController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\SanPham\SanPhamController::class, 'importExcel']);
  });

  // QuanLyCongNo
  Route::prefix('quan-ly-cong-no')->group(function () {
    Route::get('/', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'index']);
    Route::get('/options', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'store']);
    Route::get('/{id}', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'show']);
    Route::put('/{id}', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\QuanLyCongNo\QuanLyCongNoController::class, 'importExcel']);
  });

  // PhieuNhapKho
  Route::prefix('phieu-nhap-kho')->group(function () {
    Route::get('/', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'index']);
    Route::get('/tong-tien-can-thanh-toan-theo-nha-cung-cap/{nhaCungCapId}', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'getTongTienCanThanhToanTheoNhaCungCap']);
    Route::get('/options', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'getOptions']);
    Route::get('/options-by-nha-cung-cap/{nhaCungCapId}', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'getOptionsByNhaCungCap']);
    Route::get('/tong-tien-can-thanh-toan-theo-nhieu-phieu-nhap-kho', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'getTongTienCanThanhToanTheoNhieuPhieuNhapKho']);
    Route::get('/download-template-excel', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'store']);
    Route::get('/{id}', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'show']);
    Route::put('/{id}', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\PhieuNhapKho\PhieuNhapKhoController::class, 'importExcel']);
  });

  // QuanLyTonKho
  Route::prefix('quan-ly-ton-kho')->group(function () {
    Route::get('/', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'index']);
    Route::get('/options', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'store']);
    Route::get('/{id}', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'show']);
    Route::put('/{id}', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\QuanLyTonKho\QuanLyTonKhoController::class, 'importExcel']);
  });

  // PhieuChi
  Route::prefix('phieu-chi')->group(function () {
    Route::get('/', [\App\Modules\PhieuChi\PhieuChiController::class, 'index']);
    Route::get('/options', [\App\Modules\PhieuChi\PhieuChiController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\PhieuChi\PhieuChiController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\PhieuChi\PhieuChiController::class, 'store']);
    Route::get('/{id}', [\App\Modules\PhieuChi\PhieuChiController::class, 'show']);
    Route::put('/{id}', [\App\Modules\PhieuChi\PhieuChiController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\PhieuChi\PhieuChiController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\PhieuChi\PhieuChiController::class, 'importExcel']);
  });

  // QuanLyBanHang
  Route::prefix('quan-ly-ban-hang')->group(function () {
    Route::get('/', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'index']);
    Route::get('/get-gia-ban-san-pham', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'getGiaBanSanPham']);
    Route::get('/options', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'getOptions']);
    Route::get('/get-san-pham-by-don-hang-id/{donHangId}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'getSanPhamByDonHangId']);
    Route::get('/get-so-tien-can-thanh-toan/{donHangId}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'getSoTienCanThanhToan']);
    Route::get('/get-don-hang-by-khach-hang-id/{khachHangId}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'getDonHangByKhachHangId']);
    Route::get('/download-template-excel', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'downloadTemplateExcel']);
    // Route xem trước hóa đơn đã được đặt bên ngoài middleware JWT
    Route::post('/', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'store']);
    Route::get('/{id}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'show']);
    Route::put('/{id}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'importExcel']);
  });

  // PhieuXuatKho
  Route::prefix('phieu-xuat-kho')->group(function () {
    Route::get('/', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'index']);
    Route::get('/options', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'store']);
    Route::get('/{id}', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'show']);
    Route::put('/{id}', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\PhieuXuatKho\PhieuXuatKhoController::class, 'importExcel']);
  });

  // PhieuThu
  Route::prefix('phieu-thu')->group(function () {
    Route::get('/', [\App\Modules\PhieuThu\PhieuThuController::class, 'index']);
    Route::get('/options', [\App\Modules\PhieuThu\PhieuThuController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\PhieuThu\PhieuThuController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\PhieuThu\PhieuThuController::class, 'store']);
    Route::get('/{id}', [\App\Modules\PhieuThu\PhieuThuController::class, 'show']);
    Route::put('/{id}', [\App\Modules\PhieuThu\PhieuThuController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\PhieuThu\PhieuThuController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\PhieuThu\PhieuThuController::class, 'importExcel']);
  });

  // CongThucSanXuat
  Route::prefix('cong-thuc-san-xuat')->group(function () {
    Route::get('/', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'index']);
    Route::get('/options', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'getOptions']);
    Route::get('/lich-su-cap-nhat/{id}', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'getLichSuCapNhat']);
    Route::get('/get-by-san-pham-id-and-don-vi-tinh-id', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'getBySanPhamIdAndDonViTinhId']);
    Route::get('/download-template-excel', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'store']);
    Route::get('/{id}', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'show']);
    Route::put('/{id}', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\CongThucSanXuat\CongThucSanXuatController::class, 'importExcel']);
  });

  // SanXuat
  Route::prefix('san-xuat')->group(function () {
    Route::get('/', [\App\Modules\SanXuat\SanXuatController::class, 'index']);
    Route::get('/options', [\App\Modules\SanXuat\SanXuatController::class, 'getOptions']);
    Route::get('/download-template-excel', [\App\Modules\SanXuat\SanXuatController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\SanXuat\SanXuatController::class, 'store']);
    Route::get('/{id}', [\App\Modules\SanXuat\SanXuatController::class, 'show']);
    Route::put('/{id}', [\App\Modules\SanXuat\SanXuatController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\SanXuat\SanXuatController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\SanXuat\SanXuatController::class, 'importExcel']);
  });
});
