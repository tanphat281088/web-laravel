<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng: bang_cong_thangs
     * Lưu tổng hợp công theo THÁNG cho từng nhân viên.
     *
     * Quy ước:
     *  - thang: định dạng 'YYYY-MM' (varchar(7)) để filter nhanh theo prefix
     *  - locked: 0=chưa khoá; 1=đã khoá (không cho ghi đè khi đã khoá)
     */
    public function up(): void
    {
        if (Schema::hasTable('bang_cong_thangs')) {
            return;
        }

        Schema::create('bang_cong_thangs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');

            // Kỳ công: 'YYYY-MM' (ví dụ '2025-10')
            $table->string('thang', 7);

            // Các chỉ số tổng hợp (tuỳ chính sách công ty)
            $table->unsignedSmallInteger('so_ngay_cong')->default(0);     // số NGÀY công chuẩn (làm tròn theo rule)
            $table->unsignedInteger('so_gio_cong')->default(0);           // tổng GIỜ công (nếu cần)
            $table->unsignedInteger('di_tre_phut')->default(0);           // tổng phút đi trễ
            $table->unsignedInteger('ve_som_phut')->default(0);           // tổng phút về sớm

            $table->unsignedSmallInteger('nghi_phep_ngay')->default(0);   // tổng ngày nghỉ phép
            $table->unsignedInteger('nghi_phep_gio')->default(0);         // tổng giờ nghỉ phép (nếu nghỉ theo giờ)
            $table->unsignedSmallInteger('nghi_khong_luong_ngay')->default(0);
            $table->unsignedInteger('nghi_khong_luong_gio')->default(0);

            $table->unsignedInteger('lam_them_gio')->default(0);          // tổng giờ OT
            $table->json('ghi_chu')->nullable();                           // ghi chú/metadata (json)

            $table->boolean('locked')->default(false);                     // khoá bảng công tháng
            $table->timestamp('computed_at')->nullable();                  // thời điểm tổng hợp gần nhất

            $table->timestamps();

            // Index phục vụ tra cứu nhanh
            $table->unique(['user_id', 'thang']);      // 1 user chỉ 1 dòng mỗi tháng
            $table->index('thang');
            $table->index(['thang', 'locked']);

            // FK an toàn
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bang_cong_thangs');
    }
};
