<?php

use App\Class\CustomResponse;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CauHinhChungController;
use App\Http\Controllers\Api\LichSuImportController;
use App\Http\Controllers\Api\ThoiGianLamViecController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VaiTroController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB; // MỚI: dùng cho route loai-san-pham/options
use App\Modules\KhachHangVangLai\KhachHangVangLaiController; // MỚI: controller KH vãng lai
use App\Modules\SanPham\SanPhamController; // ĐƯA LÊN ĐẦU FILE: tránh lỗi PHP use giữa file
use App\Modules\ThuChi\BaoCaoThuChiController; // ĐƯA LÊN ĐẦU FILE: tránh lỗi PHP use giữa file
use App\Modules\GiaoHang\GiaoHangController; // MỚI: controller Quản lý giao hàng
use App\Modules\NhanSu\ChamCongController;
use App\Modules\NhanSu\ChamCongCheckoutController;
use App\Modules\NhanSu\ChamCongMeController;
use App\Modules\NhanSu\ChamCongAdminController;
use App\Modules\NhanSu\DonTuController;
use App\Modules\NhanSu\BangCongController;
use App\Modules\NhanSu\BangCongAdminOpsController;
use App\Modules\NhanSu\HolidayController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\BaoCaoQuanTriController;
use App\Http\Controllers\SignMakerController;
use App\Modules\CSKH\MemberPointController; // CSKH → Điểm thành viên
// +++ CSKH · Điểm thành viên — Resync +++
use App\Modules\CSKH\MemberPointMaintenanceController;

use App\Modules\Utilities\Facebook\FbInboxController;
use App\Modules\Utilities\Facebook\MessengerWebhookController;


Route::prefix('cskh/points')->group(function () {
    Route::post('/resync', [MemberPointMaintenanceController::class, 'resync']); // rà soát & đồng bộ theo delta
    Route::post('/resync-by-order/{id}', [MemberPointMaintenanceController::class, 'resyncByOrder']); // đồng bộ 1 đơn (tùy chọn)
});



// Auth
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
Route::match(['GET','POST'], '/auth/refresh', [AuthController::class, 'refresh'])->name('refresh');

Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOTP'])->name('verify-otp');

// Route công khai không cần xác thực
Route::get('/quan-ly-ban-hang/xem-truoc-hoa-don/{id}', [\App\Modules\QuanLyBanHang\QuanLyBanHangController::class, 'xemTruocHoaDon']);

// ================== (KHUNG) Facebook Messenger Webhook — PUBLIC (no auth) ==================
Route::prefix('fb')->group(function () {
    Route::get('/webhook',  [MessengerWebhookController::class, 'verify']);
    Route::post('/webhook', [MessengerWebhookController::class, 'receive']);
});
// =================================================================================================



Route::get('lich-su-import/download-file/{id}', [LichSuImportController::class, 'downloadFile']);

Route::prefix('dashboard')->group(function () {
    Route::get('/statistics', [DashboardController::class, 'getStatistics']);
    Route::get('/activities', [DashboardController::class, 'getRecentActivities']);
});

// 👉 PUBLIC: combobox tìm sản phẩm theo mã/tên (không cần token)
Route::get('san-pham/options', [SanPhamController::class, 'getOptions']);

Route::get('expense-categories/parents', [ExpenseCategoryController::class, 'parents']);
Route::get('expense-categories/options', [ExpenseCategoryController::class, 'options']);
Route::get('expense-categories/tree',    [ExpenseCategoryController::class, 'tree']);

// 👉 Auth-only (bỏ permission): dropdown "Tham chiếu" cho VT
Route::middleware(['jwt'])->get('vt/references', [\App\Http\Controllers\VT\VtReferenceController::class, 'index']);


Route::group([
  'middleware' => ['jwt', 'permission'],
], function ($router) {

  // Authenticated
  Route::group(['prefix' => 'auth'], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('me', [AuthController::class, 'me']);
        Route::match(['GET','POST'], 'me', [AuthController::class, 'me']);

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

  // ====== MỚI: Khách hàng vãng lai ======
  Route::prefix('khach-hang-vang-lai')->group(function () {
    Route::get('/', [KhachHangVangLaiController::class, 'index']);
    Route::post('/convert', [KhachHangVangLaiController::class, 'convert']);
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


// ================== (KHUNG) QUẢN LÝ TIỆN ÍCH → TƯ VẤN FACEBOOK ==================
Route::prefix('utilities')->group(function () {
    Route::prefix('fb')->group(function () {
        Route::get   ('/health',                    [FbInboxController::class, 'health']);
        Route::get   ('/conversations',             [FbInboxController::class, 'conversations']);
        Route::get   ('/conversations/{id}',        [FbInboxController::class, 'show'])->whereNumber('id');
        Route::post  ('/conversations/{id}/reply',  [FbInboxController::class, 'reply'])->whereNumber('id');
        Route::post  ('/conversations/{id}/assign', [FbInboxController::class, 'assign'])->whereNumber('id');
        Route::patch ('/conversations/{id}/status', [FbInboxController::class, 'status'])->whereNumber('id');
    });
});
// ==================================================================================


  // ===== Báo cáo quản trị =====
  Route::prefix('bao-cao-quan-tri')->group(function () {
      Route::get('/kqkd',        [BaoCaoQuanTriController::class, 'kqkd']);
      Route::get('/kqkd-detail', [BaoCaoQuanTriController::class, 'kqkdDetail']);  // nếu bạn đã thêm method detail
      Route::get('/kqkd-export', [BaoCaoQuanTriController::class, 'kqkdExport']);  // nếu bạn đã thêm export
  });

  // ================== Quản lý giao hàng ==================
  // 3 tab: Đơn hôm nay, Lịch giao hôm nay, Lịch giao tổng
  Route::prefix('giao-hang')->group(function () {
      // Danh sách đơn có lịch giao TRONG NGÀY HÔM NAY (bảng "Đơn hôm nay")
      Route::get('/hom-nay', [GiaoHangController::class, 'donHomNay']);

      // Lịch giao hôm nay dạng nhóm theo khung giờ (timeline)
      Route::get('/lich-hom-nay', [GiaoHangController::class, 'lichGiaoHomNay']);

      // Lịch giao tổng (calendar) với filter from/to/status
      // Ví dụ: /api/giao-hang/lich-tong?from=2025-10-21&to=2025-10-28&status=0
      Route::get('/lich-tong', [GiaoHangController::class, 'lichGiaoTong']);

      // Cập nhật trạng thái đơn hàng: 0=Chưa giao, 1=Đã giao, 2=Đã hủy
      Route::patch('/{id}/trang-thai', [GiaoHangController::class, 'capNhatTrangThai']);
      // Gửi SMS (1 lần/mốc) + đổi trạng thái (luôn đổi, dù SMS lỗi vẫn cảnh báo)
      Route::post('/{id}/notify-and-set-status', [GiaoHangController::class, 'notifyAndSetStatus']);

  });
  // =======================================================

  // ================== CSKH → Điểm thành viên ==================
  Route::prefix('cskh')->group(function () {
      Route::prefix('points')->group(function () {
          // Danh sách biến động toàn hệ thống (filter & phân trang)
          Route::get('/events', [MemberPointController::class, 'index']);

          // Lịch sử biến động của 1 khách
          Route::get('/customers/{khachHangId}/events', [MemberPointController::class, 'byCustomer'])
               ->whereNumber('khachHangId');

          // Gửi ZNS 1 lần cho một "biến động điểm"
          Route::post('/events/{eventId}/send-zns', [MemberPointController::class, 'sendZns'])
               ->whereNumber('eventId');
      });
  });
  // ============================================================
  // ================== CSKH → Điểm thành viên (ALIAS cho permission) ==================
  // Mục đích: để middleware permission match module 'cskh-points' (có action index)
  Route::prefix('cskh-points')->group(function () {
      Route::get('/events', [MemberPointController::class, 'index']);
      Route::get('/customers/{khachHangId}/events', [MemberPointController::class, 'byCustomer'])
           ->whereNumber('khachHangId');
      Route::post('/events/{eventId}/send-zns', [MemberPointController::class, 'sendZns'])
           ->whereNumber('eventId');
  });
  // ====================================================================================



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
    // MỚI: danh sách LOẠI phiếu thu (bổ sung TAI_CHINH)
    Route::get('/loai-options', [\App\Modules\PhieuThu\PhieuThuController::class, 'loaiOptions']); // <-- thêm route này
    Route::get('/download-template-excel', [\App\Modules\PhieuThu\PhieuThuController::class, 'downloadTemplateExcel']);
    Route::post('/', [\App\Modules\PhieuThu\PhieuThuController::class, 'store']);
    Route::get('/{id}', [\App\Modules\PhieuThu\PhieuThuController::class, 'show']);
    Route::put('/{id}', [\App\Modules\PhieuThu\PhieuThuController::class, 'update']);
    Route::delete('/{id}', [\App\Modules\PhieuThu\PhieuThuController::class, 'destroy']);
    Route::post('/import-excel', [\App\Modules\PhieuThu\PhieuThuController::class, 'importExcel']);
  });

// ================== Quản lý dòng tiền (READ-ONLY, an toàn) ==================
Route::prefix('cash')->group(function () {

    // Danh mục tài khoản tiền
    Route::get('/accounts',          [\App\Http\Controllers\Cash\CashAccountController::class, 'index']);
    Route::get('/accounts/options',  [\App\Http\Controllers\Cash\CashAccountController::class, 'options']);

  // WRITE: Tài khoản tiền
  Route::post('/accounts',                 [\App\Http\Controllers\Cash\CashAccountController::class, 'store']);
  Route::put ('/accounts/{id}',            [\App\Http\Controllers\Cash\CashAccountController::class, 'update'])->whereNumber('id');
  Route::delete('/accounts/{id}',          [\App\Http\Controllers\Cash\CashAccountController::class, 'destroy'])->whereNumber('id');



    // Alias tài khoản (phục vụ auto-map) - chỉ đọc
    Route::get('/aliases',           [\App\Http\Controllers\Cash\CashAliasController::class, 'index']);

  // WRITE: Alias tài khoản
  Route::post('/aliases',                 [\App\Http\Controllers\Cash\CashAliasController::class, 'store']);
  Route::put ('/aliases/{id}',            [\App\Http\Controllers\Cash\CashAliasController::class, 'update'])->whereNumber('id');
  Route::delete('/aliases/{id}',          [\App\Http\Controllers\Cash\CashAliasController::class, 'destroy'])->whereNumber('id');



    // Sổ quỹ & số dư (đọc-only)
    Route::get('/ledger',            [\App\Http\Controllers\Cash\CashLedgerController::class,  'ledger']);
    Route::get('/balances',          [\App\Http\Controllers\Cash\CashLedgerController::class,  'balances']);
    Route::get('/balances/summary',  [\App\Http\Controllers\Cash\CashLedgerController::class,  'summary']);
      // Internal transfers (create draft, post/unpost, list)
  Route::prefix('internal-transfers')->group(function () {
      Route::get('/',              [\App\Http\Controllers\Cash\InternalTransferController::class, 'index']);
      Route::post('/',             [\App\Http\Controllers\Cash\InternalTransferController::class, 'store']);
      Route::get('/{id}',          [\App\Http\Controllers\Cash\InternalTransferController::class, 'show'])->whereNumber('id');
      Route::post('/{id}/post',    [\App\Http\Controllers\Cash\InternalTransferController::class, 'post'])->whereNumber('id');
      Route::post('/{id}/unpost',  [\App\Http\Controllers\Cash\InternalTransferController::class, 'unpost'])->whereNumber('id');
      Route::delete('/{id}',       [\App\Http\Controllers\Cash\InternalTransferController::class, 'destroy'])->whereNumber('id');
  });

});


// ================== Quản lý vật tư (VT) ==================
Route::prefix('vt')->group(function () {
    // Danh mục VT
    Route::prefix('items')->group(function () {
        Route::get('/',        [\App\Http\Controllers\VT\VtItemController::class, 'index']);
        Route::post('/',       [\App\Http\Controllers\VT\VtItemController::class, 'store']);
        Route::get('/{id}',    [\App\Http\Controllers\VT\VtItemController::class, 'show'])->whereNumber('id');
        Route::put('/{id}',    [\App\Http\Controllers\VT\VtItemController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [\App\Http\Controllers\VT\VtItemController::class, 'destroy'])->whereNumber('id');

        // Options + Import tồn đầu
        Route::get('/options',         [\App\Http\Controllers\VT\VtItemController::class, 'options']);
        Route::post('/import-opening', [\App\Http\Controllers\VT\VtItemController::class, 'importOpening']);
    });

    // Phiếu nhập VT
    Route::prefix('receipts')->group(function () {
        Route::get('/',        [\App\Http\Controllers\VT\VtReceiptController::class, 'index']);
        Route::post('/',       [\App\Http\Controllers\VT\VtReceiptController::class, 'store']);
        Route::get('/{id}',    [\App\Http\Controllers\VT\VtReceiptController::class, 'show'])->whereNumber('id');
        Route::put('/{id}',    [\App\Http\Controllers\VT\VtReceiptController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [\App\Http\Controllers\VT\VtReceiptController::class, 'destroy'])->whereNumber('id');
    });

    // Phiếu xuất VT
    Route::prefix('issues')->group(function () {
        Route::get('/',        [\App\Http\Controllers\VT\VtIssueController::class, 'index']);
        Route::post('/',       [\App\Http\Controllers\VT\VtIssueController::class, 'store']);
        Route::get('/{id}',    [\App\Http\Controllers\VT\VtIssueController::class, 'show'])->whereNumber('id');
        Route::put('/{id}',    [\App\Http\Controllers\VT\VtIssueController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [\App\Http\Controllers\VT\VtIssueController::class, 'destroy'])->whereNumber('id');
    });

    // Tồn & Sổ kho
    Route::get('stocks', [\App\Http\Controllers\VT\VtStockController::class, 'index']);
    Route::get('ledger', [\App\Http\Controllers\VT\VtLedgerController::class, 'index']);

    // === NEW: dropdown masters ===
    Route::get('categories/options', [\App\Http\Controllers\VT\VtMasterController::class, 'categoryOptions']);
    Route::get('groups/options',     [\App\Http\Controllers\VT\VtMasterController::class, 'groupOptions']);
    Route::get('units/options',      [\App\Http\Controllers\VT\VtMasterController::class, 'unitOptions']);


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

  // ===== Alias cho FE: /attendance/* → dùng chung controller Nhân Sự (KHÔNG thay thế route cũ)
  Route::post('/attendance/checkin',  [ChamCongController::class,         'checkin']);
  Route::post('/attendance/checkout', [ChamCongCheckoutController::class, 'checkout']);
  Route::get ('/attendance/my',       [ChamCongMeController::class,       'index']);
  Route::get ('/attendance/admin',    [ChamCongAdminController::class,    'index']);
});

// ...

Route::get('thu-chi/bao-cao/tong-hop', [BaoCaoThuChiController::class, 'tongHop']);

Route::prefix('sign-maker')->middleware([])->group(function () {
    Route::get('/templates', [SignMakerController::class, 'templates']);
    Route::post('/preview',  [SignMakerController::class, 'preview']);
    Route::post('/export-pdf', [SignMakerController::class, 'exportPdf']);
    Route::get('/download/{path}', [SignMakerController::class, 'download'])
         ->where('path', '.*')
         ->name('sign-maker.download');
});

Route::middleware(['jwt', 'permission'])
    ->prefix('nhan-su')
    ->name('nhan-su.')
    ->group(function () {
        // ===== Chấm công =====
        Route::post('cham-cong/checkin',  [ChamCongController::class,         'checkin'])->name('cham-cong.checkin');
        Route::post('cham-cong/checkout', [ChamCongCheckoutController::class, 'checkout'])->name('cham-cong.checkout');
        Route::get ('cham-cong/me',       [ChamCongMeController::class,       'index'])->name('cham-cong.me');
        Route::get ('cham-cong',          [ChamCongAdminController::class,    'index'])->name('cham-cong.index');

        // ===== Đơn từ (xin nghỉ phép) =====
        Route::prefix('don-tu')->group(function () {
            Route::post('/',              [DonTuController::class, 'store'])->name('don-tu.store');
            Route::get('/my',             [DonTuController::class, 'myIndex'])->name('don-tu.my');
            Route::get('/',               [DonTuController::class, 'adminIndex'])->name('don-tu.index');
            Route::patch('/{id}/approve', [DonTuController::class, 'approve'])->name('don-tu.approve');
            Route::patch('/{id}/reject',  [DonTuController::class, 'reject'])->name('don-tu.reject');
            Route::patch('/{id}/cancel',  [DonTuController::class, 'cancel'])->name('don-tu.cancel');
        });

        // ===== Bảng công tháng =====
        Route::prefix('bang-cong')->group(function () {
            Route::get('/my',            [BangCongController::class,        'myIndex'])->name('bang-cong.my');
            Route::get('/',              [BangCongController::class,        'adminIndex'])->name('bang-cong.index');
            Route::post('/recompute',    [BangCongController::class,        'recompute'])->name('bang-cong.recompute');
            Route::patch('/lock',        [BangCongAdminOpsController::class,'lock'])->name('bang-cong.lock');
            Route::patch('/unlock',      [BangCongAdminOpsController::class,'unlock'])->name('bang-cong.unlock');
            Route::post('/recompute-all',[BangCongAdminOpsController::class,'recomputeAll'])->name('bang-cong.recompute_all');
        });

        // ===== Ngày lễ (Holiday) =====
        Route::prefix('holiday')->group(function () {
            Route::get('/',       [HolidayController::class, 'index'])->name('holiday.index');     // nhan-su.index | list
            Route::post('/',      [HolidayController::class, 'store'])->name('holiday.store');     // nhan-su.create | store
            Route::patch('/{id}', [HolidayController::class, 'update'])->name('holiday.update');   // nhan-su.update
            Route::delete('/{id}',[HolidayController::class, 'destroy'])->name('holiday.destroy'); // nhan-su.delete
        });
    });
