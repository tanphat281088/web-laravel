<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Thêm cột idempotent_key (nếu chưa có) + unique index để chống sinh trùng phiếu
        if (!Schema::hasColumn('phieu_thus', 'idempotent_key')) {
            Schema::table('phieu_thus', function (Blueprint $table) {
                // string 191 để tương thích index ở MySQL (InnoDB + utf8mb4)
                $table->string('idempotent_key', 191)
                      ->nullable()
                      ->after('ly_do_thu');

                // unique để đảm bảo tính idempotent (NULL không vi phạm unique)
                $table->unique('idempotent_key', 'phieu_thus_idempotent_key_unique');
            });
        }
    }

    public function down(): void
    {
        // Gỡ index + cột nếu tồn tại (rollback an toàn)
        if (Schema::hasColumn('phieu_thus', 'idempotent_key')) {
            Schema::table('phieu_thus', function (Blueprint $table) {
                $table->dropUnique('phieu_thus_idempotent_key_unique');
                $table->dropColumn('idempotent_key');
            });
        }
    }
};
