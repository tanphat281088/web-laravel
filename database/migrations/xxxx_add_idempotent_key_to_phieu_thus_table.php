<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('phieu_thus', function (Blueprint $table) {
            if (!Schema::hasColumn('phieu_thus', 'idempotent_key')) {
                $table->string('idempotent_key', 191)->nullable()->after('ghi_chu');
                $table->index('idempotent_key', 'phieu_thus_idempotent_key_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('phieu_thus', function (Blueprint $table) {
            if (Schema::hasColumn('phieu_thus', 'idempotent_key')) {
                try { $table->dropIndex('phieu_thus_idempotent_key_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('idempotent_key');
            }
        });
    }
};
