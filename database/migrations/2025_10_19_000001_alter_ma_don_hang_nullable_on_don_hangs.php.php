<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nếu cột chưa tồn tại (trường hợp hiếm), tạo mới cho an toàn
        if (!Schema::hasColumn('don_hangs', 'ma_don_hang')) {
            Schema::table('don_hangs', function (Blueprint $table) {
                $table->string('ma_don_hang', 255)->nullable()->unique()->after('id');
            });
            return;
        }

        // Thử cách "eloquent" (cần doctrine/dbal). Nếu lỗi -> fallback SQL thô
        try {
            Schema::table('don_hangs', function (Blueprint $table) {
                $table->string('ma_don_hang', 255)->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Fallback: dùng SQL thô (MySQL)
            // Giữ nguyên UNIQUE index hiện có; chỉ đổi NULLABLE
            DB::statement("ALTER TABLE `don_hangs` MODIFY `ma_don_hang` VARCHAR(255) NULL");
        }

        // Đảm bảo có UNIQUE (trong vài cấu hình có thể bị mất do change())
        try {
            DB::statement("ALTER TABLE `don_hangs` ADD UNIQUE `don_hangs_ma_don_hang_unique` (`ma_don_hang`)");
        } catch (\Throwable $e) {
            // đã tồn tại -> bỏ qua
        }
    }

    public function down(): void
    {
        // Trong down, ta có thể set NOT NULL lại nếu muốn (không bắt buộc).
        // Để an toàn, set các NULL còn sót (nếu có) về mã dựa theo id trước khi NOT NULL.
        DB::statement("UPDATE `don_hangs` SET `ma_don_hang` = CONCAT('DH', LPAD(`id`, 5, '0')) WHERE `ma_don_hang` IS NULL");

        try {
            Schema::table('don_hangs', function (Blueprint $table) {
                $table->string('ma_don_hang', 255)->nullable(false)->change();
            });
        } catch (\Throwable $e) {
            DB::statement("ALTER TABLE `don_hangs` MODIFY `ma_don_hang` VARCHAR(255) NOT NULL");
        }
    }
};
