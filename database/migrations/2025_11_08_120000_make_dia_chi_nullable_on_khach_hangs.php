<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            // Nếu cột hiện đang kiểu VARCHAR
            $table->string('dia_chi', 255)->nullable()->change();

            // Nếu dự án của bạn dùng kiểu TEXT cho dia_chi, dùng dòng dưới thay cho dòng trên:
            // $table->text('dia_chi')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            // Khôi phục về NOT NULL (giữ nguyên kiểu như up())
            $table->string('dia_chi', 255)->nullable(false)->change();

            // Nếu ở up() bạn dùng text(), thì down() dùng:
            // $table->text('dia_chi')->nullable(false)->change();
        });
    }
};
