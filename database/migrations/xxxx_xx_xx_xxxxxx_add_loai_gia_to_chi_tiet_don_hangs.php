<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            // 1 = Đặt ngay (giá hiện có), 2 = Đặt trước 3 ngày
            if (!Schema::hasColumn('chi_tiet_don_hangs', 'loai_gia')) {
                $table->unsignedTinyInteger('loai_gia')
                      ->default(1)
                      ->after('don_gia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chi_tiet_don_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('chi_tiet_don_hangs', 'loai_gia')) {
                $table->dropColumn('loai_gia');
            }
        });
    }
};

