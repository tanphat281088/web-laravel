<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phieu_thus', function (Blueprint $table) {
            // Thêm cột idempotent_key dạng nullable để không ảnh hưởng dữ liệu cũ
            if (! Schema::hasColumn('phieu_thus', 'idempotent_key')) {
                $table->string('idempotent_key', 191)->nullable()->after('ghi_chu');
            }

            // Index giúp tra cứu nhanh; UNIQUE là tùy chọn — để an toàn, dùng index thường.
            // Nếu bạn muốn chống trùng tuyệt đối, có thể đổi thành unique() sau khi confirm dữ liệu.
            $table->index('idempotent_key', 'phieu_thus_idempotent_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('phieu_thus', function (Blueprint $table) {
            if (Schema::hasColumn('phieu_thus', 'idempotent_key')) {
                // Xóa index trước khi drop column
                try {
                    $table->dropIndex('phieu_thus_idempotent_key_idx');
                } catch (\Throwable $e) {
                    // bỏ qua nếu index chưa tồn tại
                }

                $table->dropColumn('idempotent_key');
            }
        });
    }
};
