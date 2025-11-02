<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zl_messages', function (Blueprint $table) {
            $table->bigIncrements('id');

            // FK tới hội thoại Zalo
            $table->unsignedBigInteger('conversation_id'); // fk -> zl_conversations.id

            // 'in' (khách -> hệ thống) | 'out' (nhân viên -> khách)
            $table->enum('direction', ['in', 'out'])->index();

            // ID message phía Zalo (nếu có)
            $table->string('provider_message_id')->nullable()->index();

            // Văn bản
            $table->longText('text_raw')->nullable();         // bản gốc
            $table->longText('text_translated')->nullable();  // bản dịch (en<->vi)
            $table->longText('text_polished')->nullable();    // bản “polish with AI” (nếu bật)

            // Ngôn ngữ nguồn → đích
            $table->string('src_lang', 8)->nullable();  // ví dụ 'en', 'vi'
            $table->string('dst_lang', 8)->nullable();

            // Đính kèm (ảnh/file/…): metadata JSON
            $table->json('attachments')->nullable();

            // Dấu mốc gửi/nhận
            $table->timestamp('delivered_at')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();

            $table->timestamps();

            // Truy vấn theo hội thoại theo thứ tự thời gian
            $table->index(['conversation_id', 'created_at']);

            // Ràng buộc FK
            $table->foreign('conversation_id')
                ->references('id')->on('zl_conversations')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zl_messages');
    }
};
