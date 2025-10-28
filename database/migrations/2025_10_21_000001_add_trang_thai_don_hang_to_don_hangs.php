<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột trạng thái đơn hàng và (nếu có) index cho nguoi_nhan_thoi_gian.
     *
     * Quy ước trạng thái:
     * 0 = Chưa giao, 1 = Đã giao, 2 = Đã hủy
     */
    public function up(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // Thêm cột trạng_thai nếu chưa có
            if (! Schema::hasColumn('don_hangs', 'trang_thai_don_hang')) {
                $table->tinyInteger('trang_thai_don_hang')
                    ->default(0)
                    ->comment('0=Chưa giao,1=Đã giao,2=Đã hủy');
            }

            // Đảm bảo có index cho nguoi_nhan_thoi_gian (nếu cột này tồn tại)
            if (Schema::hasColumn('don_hangs', 'nguoi_nhan_thoi_gian')) {
                // Đặt tên index cố định để tránh tạo trùng tên ngẫu nhiên.
                // Nếu index đã tồn tại, try/catch sẽ bỏ qua lỗi.
                try {
                    $table->index('nguoi_nhan_thoi_gian', 'don_hangs_nguoi_nhan_thoi_gian_index');
                } catch (\Throwable $e) {
                    // Bỏ qua nếu index đã tồn tại
                }
            }
        });
    }

    /**
     * Rollback: gỡ index (nếu có) và xóa cột trạng thái.
     */
    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // Gỡ index nếu tồn tại (dùng tên đã đặt ở up())
            try {
                $table->dropIndex('don_hangs_nguoi_nhan_thoi_gian_index');
            } catch (\Throwable $e) {
                // Bỏ qua nếu index không tồn tại
            }

            // Xóa cột trạng thái nếu có
            if (Schema::hasColumn('don_hangs', 'trang_thai_don_hang')) {
                $table->dropColumn('trang_thai_don_hang');
            }
        });
    }
};
