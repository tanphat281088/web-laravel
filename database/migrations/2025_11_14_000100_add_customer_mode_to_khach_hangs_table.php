<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột customer_mode vào bảng khach_hangs.
     *
     * 0 = Khách hàng hệ thống (retail/normal)
     * 1 = Khách hàng Pass đơn & CTV
     *
     * Tất cả bản ghi cũ mặc định = 0 → hành vi giữ nguyên.
     */
    public function up(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            if (!Schema::hasColumn('khach_hangs', 'customer_mode')) {
                $table->unsignedTinyInteger('customer_mode')
                    ->default(0)
                    ->after('loai_khach_hang_id')
                    ->comment('0=normal,1=pass/CTV');
            }
        });
    }

    /**
     * Rollback: xoá cột customer_mode (nếu tồn tại).
     */
    public function down(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('khach_hangs', 'customer_mode')) {
                $table->dropColumn('customer_mode');
            }
        });
    }
};
