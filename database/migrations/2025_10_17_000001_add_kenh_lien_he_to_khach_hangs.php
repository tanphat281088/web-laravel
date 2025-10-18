<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            // Thêm cột kênh liên hệ (nullable để không ảnh hưởng dữ liệu cũ)
            if (!Schema::hasColumn('khach_hangs', 'kenh_lien_he')) {
                $table->string('kenh_lien_he', 191)->nullable();
                // Nếu bạn muốn đặt vị trí sau một cột nào đó (VD: ghi_chu), có thể dùng:
                // $table->string('kenh_lien_he', 191)->nullable()->after('ghi_chu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('khach_hangs', 'kenh_lien_he')) {
                $table->dropColumn('kenh_lien_he');
            }
        });
    }
};
