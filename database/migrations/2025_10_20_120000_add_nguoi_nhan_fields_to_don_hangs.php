<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // Thông tin người nhận (bổ sung)
            $table->string('nguoi_nhan_ten', 191)->nullable()->after('dia_chi_giao_hang');
            $table->string('nguoi_nhan_sdt', 20)->nullable()->after('nguoi_nhan_ten');
            $table->dateTime('nguoi_nhan_thoi_gian')->nullable()->after('nguoi_nhan_sdt');

            // Index nhẹ cho tra cứu theo SĐT người nhận
            $table->index('nguoi_nhan_sdt', 'idx_don_hangs_nguoi_nhan_sdt');
        });
    }

    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            $table->dropIndex('idx_don_hangs_nguoi_nhan_sdt');
            $table->dropColumn(['nguoi_nhan_ten', 'nguoi_nhan_sdt', 'nguoi_nhan_thoi_gian']);
        });
    }
};
