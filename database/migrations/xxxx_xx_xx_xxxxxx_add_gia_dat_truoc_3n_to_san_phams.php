<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('san_phams', function (Blueprint $table) {
            // Thêm cột giá đặt trước 3 ngày, để ngay sau giá nhập mặc định
            if (!Schema::hasColumn('san_phams', 'gia_dat_truoc_3n')) {
                $table->unsignedInteger('gia_dat_truoc_3n')
                      ->default(0)
                      ->after('gia_nhap_mac_dinh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('san_phams', function (Blueprint $table) {
            if (Schema::hasColumn('san_phams', 'gia_dat_truoc_3n')) {
                $table->dropColumn('gia_dat_truoc_3n');
            }
        });
    }
};
