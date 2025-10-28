<?php

use Illuminate\Support\Facades\Route;
use App\Modules\QuanLyBanHang\QuanLyBanHangController;
use App\Http\Controllers\ThuChi\AutoThuController; // <- chỉ import 1 lần

Route::get('/', function () {
    return view('welcome');
});

// === Xem trước hóa đơn (WEB ROUTE, trả về Blade view) ===
// URL: http://127.0.0.1:8000/quan-ly-ban-hang/xem-truoc-hoa-don/{id}
Route::prefix('quan-ly-ban-hang')->group(function () {
    Route::get('xem-truoc-hoa-don/{id}', [QuanLyBanHangController::class, 'xemTruocHoaDon'])
        ->name('quan-ly-ban-hang.xem-truoc-hoa-don');
});

// Re-sync phiếu thu theo ID số
Route::get('/admin/thu-chi/re-sync/{donHangId}', [AutoThuController::class, 'reSync'])
     ->name('thu-chi.reSync');

// Re-sync phiếu thu theo mã đơn (ma_don_hang), ví dụ: DH-20251019-160623
Route::get('/admin/thu-chi/re-sync-by-code/{maDonHang}', [AutoThuController::class, 'reSyncByCode'])
     ->name('thu-chi.reSyncByCode');
