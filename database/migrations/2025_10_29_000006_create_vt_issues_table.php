<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_issues', function (Blueprint $table) {
            $table->id();

            // Số chứng từ & ngày chứng từ
            $table->string('so_ct', 50)->unique();
            $table->date('ngay_ct')->index();

            // Lý do xuất: BAN | HUY | CHUYEN | KHAC (giữ linh hoạt)
            $table->enum('ly_do', ['BAN', 'HUY', 'CHUYEN', 'KHAC'])->default('KHAC')->index();

            // Tổng hợp nhanh
            $table->integer('tong_so_luong')->default(0);
            $table->decimal('tong_gia_tri', 18, 2)->nullable(); // nếu cần tính giá trị xuất với ASSET (bình quân)

            // Tham chiếu, ghi chú
            $table->string('tham_chieu', 191)->nullable();
            $table->text('ghi_chu')->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // FK
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vt_issues', function (Blueprint $table) {
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_issues');
    }
};
