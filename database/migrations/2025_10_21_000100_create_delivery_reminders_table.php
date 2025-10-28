<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng log nhắc giao hàng để chống trùng & báo cáo.
     * - Mỗi bản ghi tương ứng 1 lần nhắc cho 1 đơn ở 1 thời điểm (vd: trước 60’).
     * - Unique (don_hang_id, scheduled_at, type) đảm bảo idempotent.
     */
    public function up(): void
    {
        Schema::create('delivery_reminders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('don_hang_id')->index();
            $table->dateTime('scheduled_at')->index();       // thời điểm nhắc
            $table->string('type', 32)->default('60min');    // ví dụ: 60min, 15min...
            $table->json('channels')->nullable();            // kênh đã gửi: ["inapp","push","tts"]
            $table->dateTime('sent_at')->nullable();         // thời điểm đã gửi nhắc (nếu có)

            $table->timestamps();

            $table->unique(['don_hang_id', 'scheduled_at', 'type'], 'uniq_reminder');
            $table->foreign('don_hang_id')
                  ->references('id')->on('don_hangs')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_reminders');
    }
};
