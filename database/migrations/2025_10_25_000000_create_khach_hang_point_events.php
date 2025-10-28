<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng sự kiện biến động điểm của khách hàng.
     * - Mỗi ĐƠN HÀNG chỉ tạo 1 sự kiện (UNIQUE don_hang_id) 👉 chống cộng trùng & gửi trùng.
     * - Lưu đủ before/after để audit: doanh thu/điểm cũ → mới, chênh lệch.
     * - Trạng thái gửi ZNS: pending | sent | failed (chỉ gửi 1 lần/biến động).
     */
    public function up(): void
    {
        Schema::create('khach_hang_point_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Khóa ngoại
            $table->unsignedBigInteger('khach_hang_id')->comment('FK -> khach_hangs.id');
            $table->unsignedBigInteger('don_hang_id')->comment('FK -> don_hangs.id');

            // Thông tin đơn tại thời điểm ghi sự kiện (cache để hiển thị nhanh)
            $table->string('order_code', 50)->comment('Mã đơn tại thời điểm sự kiện');
            $table->dateTime('order_date')->comment('Thời điểm thanh toán/hoàn tất dùng tính điểm');
            $table->unsignedBigInteger('price')->default(0)->comment('Giá trị dùng tính điểm (VND)');

            // Doanh thu và điểm trước/sau (điểm = floor(doanh_thu/1000))
            $table->unsignedBigInteger('old_revenue')->default(0)->comment('Doanh thu tích lũy trước khi cộng (VND)');
            $table->unsignedBigInteger('new_revenue')->default(0)->comment('Doanh thu tích lũy sau khi cộng (VND)');
            $table->bigInteger('delta_revenue')->default(0)->comment('Chênh lệch doanh thu (VND)');

            $table->unsignedBigInteger('old_points')->default(0)->comment('Điểm trước (floor(old_revenue/1000))');
            $table->unsignedBigInteger('new_points')->default(0)->comment('Điểm sau (floor(new_revenue/1000))');
            $table->bigInteger('delta_points')->default(0)->comment('Điểm cộng cho đơn này');

            // Ghi chú tùy chọn
            $table->string('note', 255)->nullable();

            // Trạng thái gửi ZNS cho SỰ KIỆN NÀY (chỉ gửi một lần)
            $table->enum('zns_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->dateTime('zns_sent_at')->nullable();
            $table->string('zns_template_id', 64)->nullable()->comment('Template đã dùng để gửi');
            $table->string('zns_error_code', 64)->nullable();
            $table->string('zns_error_message', 255)->nullable();

            // Dấu vết hệ thống
            $table->string('nguoi_tao', 191)->nullable();
            $table->string('nguoi_cap_nhat', 191)->nullable();

            $table->timestamps();

            // Ràng buộc
            $table->foreign('khach_hang_id')
                ->references('id')->on('khach_hangs')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('don_hang_id')
                ->references('id')->on('don_hangs')
                ->onUpdate('cascade')->onDelete('cascade');

            // Mỗi đơn hàng chỉ có 1 sự kiện biến động điểm
            $table->unique('don_hang_id', 'uq_point_event_donhang');

            // Tối ưu truy vấn theo thời gian & trạng thái gửi
            $table->index(['zns_status', 'created_at'], 'idx_point_event_status_created');
            $table->index('khach_hang_id', 'idx_point_event_kh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khach_hang_point_events');
    }
};
