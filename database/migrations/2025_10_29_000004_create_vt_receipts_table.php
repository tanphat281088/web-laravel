<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_receipts', function (Blueprint $table) {
            $table->id();

            // Số chứng từ & ngày chứng từ
            $table->string('so_ct', 50)->unique();
            $table->date('ngay_ct')->index();

            // (Tuỳ chọn) Nhà cung cấp nếu bạn muốn lưu tham chiếu
            $table->unsignedBigInteger('nha_cung_cap_id')->nullable()->index();

            // Tổng hợp nhanh (không bắt buộc, tiện báo cáo)
            $table->integer('tong_so_luong')->default(0);
            $table->decimal('tong_gia_tri', 18, 2)->nullable(); // chỉ có ý nghĩa khi nhập ASSET có đơn giá

            // Tham chiếu, ghi chú
            $table->string('tham_chieu', 191)->nullable();
            $table->text('ghi_chu')->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // FK (tùy vào bảng hiện có)
            $table->foreign('nha_cung_cap_id')->references('id')->on('nha_cung_caps')->nullOnDelete();
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vt_receipts', function (Blueprint $table) {
            $table->dropForeign(['nha_cung_cap_id']);
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_receipts');
    }
};
