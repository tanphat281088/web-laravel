<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('luong_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Mỗi user có 1 hồ sơ lương hiện hành (có thể cập nhật khi điều chỉnh)
            $table->foreignId('user_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Cấu hình lương cơ bản
            $table->integer('muc_luong_co_ban')->default(0);     // đơn vị: VND
            $table->tinyInteger('cong_chuan')->default(26);      // số công chuẩn/tháng
            $table->decimal('he_so', 5, 2)->default(1.00);        // hệ số lương

            // Phụ cấp mặc định (cộng vào mỗi kỳ, có thể chỉnh tay khi chốt lương tháng)
            $table->integer('phu_cap_mac_dinh')->default(0);

            // Tỷ lệ BH (đơn vị: %) — có thể cập nhật theo chính sách
            $table->decimal('pt_bhxh', 5, 2)->default(8.00);      // ví dụ 8%
            $table->decimal('pt_bhyt', 5, 2)->default(1.50);      // ví dụ 1.5%
            $table->decimal('pt_bhtn', 5, 2)->default(1.00);      // ví dụ 1%

            // Ngày hiệu lực của profile này (để biết từ khi nào áp dụng)
            $table->date('hieu_luc_tu')->nullable();

            $table->text('ghi_chu')->nullable();

            $table->timestamps();

            // Mỗi user chỉ có 1 profile đang dùng
            $table->unique(['user_id'], 'luong_profiles_user_unique');

            // Tra cứu nhanh
            $table->index(['hieu_luc_tu'], 'luong_profiles_hieuluc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('luong_profiles');
    }
};
