<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loai_khach_hangs', function (Blueprint $table) {
            // 🔹 THÊM NGAY SAU nguong_doanh_thu
            $table->integer('gia_tri_uu_dai')->default(0)->after('nguong_doanh_thu');
            $table->integer('nguong_diem')->default(0)->after('gia_tri_uu_dai');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loai_khach_hangs', function (Blueprint $table) {
            $table->dropColumn(['gia_tri_uu_dai', 'nguong_diem']);
        });
    }
};
