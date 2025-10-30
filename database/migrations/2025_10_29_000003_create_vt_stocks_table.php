<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_stocks', function (Blueprint $table) {
            $table->id();

            // Mỗi VT một dòng tồn tức thời
            $table->unsignedBigInteger('vt_item_id')->unique();

            // Tồn số lượng (bắt buộc)
            $table->integer('so_luong_ton')->default(0);

            // (Tuỳ chọn) Giá trị tồn - chỉ có ý nghĩa với ASSET nếu bạn muốn xem tổng giá trị nhanh
            $table->decimal('gia_tri_ton', 18, 2)->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // FK
            $table->foreign('vt_item_id')->references('id')->on('vt_items')->cascadeOnDelete();
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vt_stocks', function (Blueprint $table) {
            $table->dropForeign(['vt_item_id']);
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_stocks');
    }
};
