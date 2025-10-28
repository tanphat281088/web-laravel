<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Remap dữ liệu cũ -> mới:
        //    1 (Đã giao cũ) -> 2 (Đã giao mới)
        //    2 (Đã hủy cũ)  -> 3 (Đã hủy mới)
        DB::statement("
            UPDATE don_hangs
            SET trang_thai_don_hang = CASE
                WHEN trang_thai_don_hang = 1 THEN 2
                WHEN trang_thai_don_hang = 2 THEN 3
                ELSE trang_thai_don_hang
            END
        ");

        // 2) Cập nhật comment cột.
        // Cách A: dùng Schema::table(...)->change() nếu đã cài doctrine/dbal
        try {
            Schema::table('don_hangs', function (Blueprint $table) {
                $table->tinyInteger('trang_thai_don_hang')
                      ->default(0)
                      ->comment('0=Chưa giao,1=Đang giao,2=Đã giao,3=Đã hủy')
                      ->change();
            });
        } catch (\Throwable $e) {
            // Cách B: fallback raw SQL (nếu chưa cài doctrine/dbal)
            DB::statement("
                ALTER TABLE don_hangs
                MODIFY COLUMN trang_thai_don_hang TINYINT NOT NULL DEFAULT 0
                COMMENT '0=Chưa giao,1=Đang giao,2=Đã giao,3=Đã hủy'
            ");
        }
    }

    public function down(): void
    {
        // Rollback về mapping cũ:
        // 3 (Đã hủy mới) -> 2 (Đã hủy cũ)
        // 2 (Đã giao mới) -> 1 (Đã giao cũ)
        DB::statement("
            UPDATE don_hangs
            SET trang_thai_don_hang = CASE
                WHEN trang_thai_don_hang = 3 THEN 2
                WHEN trang_thai_don_hang = 2 THEN 1
                ELSE trang_thai_don_hang
            END
        ");

        // Trả lại comment cũ
        try {
            Schema::table('don_hangs', function (Blueprint $table) {
                $table->tinyInteger('trang_thai_don_hang')
                      ->default(0)
                      ->comment('0=Chưa giao,1=Đã giao,2=Đã hủy')
                      ->change();
            });
        } catch (\Throwable $e) {
            DB::statement("
                ALTER TABLE don_hangs
                MODIFY COLUMN trang_thai_don_hang TINYINT NOT NULL DEFAULT 0
                COMMENT '0=Chưa giao,1=Đã giao,2=Đã hủy'
            ");
        }
    }
};
