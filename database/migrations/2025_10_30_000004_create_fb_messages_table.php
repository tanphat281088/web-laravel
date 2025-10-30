<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_messages', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK tới hội thoại
            $table->unsignedBigInteger('conversation_id'); // fk -> fb_conversations.id

            // in (khách → hệ thống) | out (nhân viên → khách)
            $table->enum('direction', ['in', 'out'])->index();

            // Message ID từ Meta (nếu có)
            $table->string('mid')->nullable()->index();

            // Văn bản
            $table->longText('text_raw')->nullable();         // bản gốc
            $table->longText('text_translated')->nullable();  // bản dịch (en<->vi)
            $table->longText('text_polished')->nullable();    // bản “polish with AI” (nếu bật)

            // Ngôn ngữ nguồn → đích
            $table->string('src_lang', 8)->nullable();  // ví dụ 'en', 'vi'
            $table->string('dst_lang', 8)->nullable();

            // Đính kèm (ảnh/file/sticker...) — lưu metadata JSON
            $table->json('attachments')->nullable();

            // Dấu mốc gửi/nhận
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();

            $table->timestamps();

            // Truy vấn theo hội thoại theo thứ tự thời gian
            $table->index(['conversation_id', 'created_at']);

            // Ràng buộc FK
            $table->foreign('conversation_id')
                ->references('id')->on('fb_conversations')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_messages');
    }
};
