<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('vt_receipt_id')->index();
            $table->unsignedBigInteger('vt_item_id')->index();

            $table->integer('so_luong')->default(0);

            // Đơn giá:
            // - ASSET: nên có (để tính bình quân/giá trị ngay từ khi nhập)
            // - CONSUMABLE: có thể để null
            $table->decimal('don_gia', 18, 2)->nullable();

            $table->text('ghi_chu')->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // FK
            $table->foreign('vt_receipt_id')->references('id')->on('vt_receipts')->cascadeOnDelete();
            $table->foreign('vt_item_id')->references('id')->on('vt_items')->restrictOnDelete();
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vt_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['vt_receipt_id']);
            $table->dropForeign(['vt_item_id']);
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_receipt_items');
    }
};
