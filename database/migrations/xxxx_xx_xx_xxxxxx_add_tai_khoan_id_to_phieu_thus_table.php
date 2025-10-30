<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('phieu_thus', function (Blueprint $table) {
            if (!Schema::hasColumn('phieu_thus', 'tai_khoan_id')) {
                $table->unsignedBigInteger('tai_khoan_id')->nullable()->after('phuong_thuc_thanh_toan');
                $table->foreign('tai_khoan_id')
                      ->references('id')->on('tai_khoan_tiens')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('phieu_thus', function (Blueprint $table) {
            if (Schema::hasColumn('phieu_thus', 'tai_khoan_id')) {
                try { $table->dropForeign(['tai_khoan_id']); } catch (\Throwable $e) {}
                $table->dropColumn('tai_khoan_id');
            }
        });
    }
};
