<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_issue_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('vt_issue_id')->index();
            $table->unsignedBigInteger('vt_item_id')->index();

            // Xuất giảm số lượng
            $table->integer('so_luong')->default(0);

            // Ghi chú dòng
            $table->text('ghi_chu')->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // FK
            $table->foreign('vt_issue_id')->references('id')->on('vt_issues')->cascadeOnDelete();
            $table->foreign('vt_item_id')->references('id')->on('vt_items')->restrictOnDelete();
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vt_issue_items', function (Blueprint $table) {
            $table->dropForeign(['vt_issue_id']);
            $table->dropForeign(['vt_item_id']);
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_issue_items');
    }
};
