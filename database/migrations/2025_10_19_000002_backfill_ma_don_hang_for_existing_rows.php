<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Chỉ fill những bản ghi chưa có mã
        DB::statement("
            UPDATE `don_hangs`
            SET `ma_don_hang` = CONCAT('DH', LPAD(`id`, 5, '0'))
            WHERE `ma_don_hang` IS NULL
        ");
    }

    public function down(): void
    {
        // Không nên xoá mã đã backfill; để trống down cho an toàn.
    }
};
