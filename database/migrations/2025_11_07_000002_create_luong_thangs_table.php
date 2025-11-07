<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('luong_thangs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Khóa ngoại tới users
            $table->foreignId('user_id')
                ->constrained('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Tháng tính lương theo dạng YYYY-MM (ví dụ: 2025-11)
            $table->char('thang', 7);

            // ====== Snapshot cấu hình tại thời điểm chốt/thống kê ======
            $table->integer('luong_co_ban')->default(0);     // VND
            $table->tinyInteger('cong_chuan')->default(26);  // công chuẩn/tháng
            $table->decimal('he_so', 5, 2)->default(1.00);   // hệ số áp dụng cho tháng

            // Tổng hợp từ bảng công (BangCongThang)
            $table->decimal('so_ngay_cong', 6, 2)->default(0); // có thể lẻ
            $table->integer('so_gio_cong')->default(0);        // phút/giờ quy đổi (tuỳ bạn dùng)

            // Các khoản cộng/trừ có thể chỉnh tay trước khi lock
            $table->integer('phu_cap')->default(0);        // + VND
            $table->integer('thuong')->default(0);         // + VND
            $table->integer('phat')->default(0);           // - VND

            // Lương theo công = (luong_co_ban * he_so) * (so_ngay_cong / cong_chuan)
            $table->integer('luong_theo_cong')->default(0);

            // Bảo hiểm (tính theo % cấu hình tại thời điểm snapshot)
            $table->integer('bhxh')->default(0);
            $table->integer('bhyt')->default(0);
            $table->integer('bhtn')->default(0);

            // Khấu trừ khác & tạm ứng
            $table->integer('khau_tru_khac')->default(0);
            $table->integer('tam_ung')->default(0);

            // Thực nhận = lương_theo_cong + phu_cap + thuong - phat - (bhxh+bhyt+bhtn) - khau_tru_khac - tam_ung
            $table->integer('thuc_nhan')->default(0);

            // Trạng thái chốt
            $table->boolean('locked')->default(false);

            // Thời điểm tính toán/ghi snapshot
            $table->timestamp('computed_at')->nullable();

            $table->text('ghi_chu')->nullable();

            $table->timestamps();

            // Mỗi user chỉ có 1 dòng cho 1 tháng
            $table->unique(['user_id', 'thang'], 'luong_thangs_user_thang_unique');

            // Tra cứu nhanh
            $table->index(['thang'], 'luong_thangs_thang_idx');
            $table->index(['locked'], 'luong_thangs_locked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('luong_thangs');
    }
};
