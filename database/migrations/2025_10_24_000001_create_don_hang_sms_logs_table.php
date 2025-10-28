<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng log SMS cho từng đơn hàng & mốc (đang giao/đã giao).
     * - Mỗi (don_hang_id, type) chỉ có 1 bản ghi → chặn gửi trùng ở mức DB.
     * - Cho phép cập nhật lại bản ghi khi retry (khi lần trước thất bại).
     */
    public function up(): void
    {
        Schema::create('don_hang_sms_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('don_hang_id')->comment('FK -> don_hangs.id');
            $table->enum('type', ['dang_giao', 'da_giao'])->comment('Mốc gửi SMS');

            $table->string('phone', 20)->nullable()->comment('Số điện thoại người nhận (đã chuẩn hóa)');
            $table->text('message')->nullable()->comment('Nội dung tin nhắn đã gửi/đã thử gửi');

            $table->dateTime('attempted_at')->comment('Thời điểm thử gửi gần nhất');
            $table->boolean('success')->default(false)->comment('1=thành công, 0=thất bại');

            $table->string('provider_msg_id', 100)->nullable()->comment('Mã tin nhắn phía nhà cung cấp');
            $table->string('error_code', 50)->nullable();
            $table->string('error_message', 255)->nullable();

            $table->unique(['don_hang_id', 'type'], 'uq_sms_order_type');

            $table->foreign('don_hang_id')
                ->references('id')->on('don_hangs')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('success', 'idx_sms_success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('don_hang_sms_logs');
    }
};
