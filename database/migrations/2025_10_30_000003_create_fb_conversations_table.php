<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Liên kết Page và User (PSID)
            $table->unsignedBigInteger('page_id');      // fk -> fb_pages.id
            $table->unsignedBigInteger('fb_user_id');   // fk -> fb_users.id

            // Gán cho nhân viên nội bộ (users.id) - có thể null
            $table->unsignedBigInteger('assigned_user_id')->nullable();

            // 1 = open, 0 = closed (đơn giản, dễ filter)
            $table->tinyInteger('status')->default(1)->index();

            // Ngôn ngữ chính ước lượng của hội thoại (ví dụ: 'en', 'vi')
            $table->string('lang_primary', 8)->nullable();

            // Mốc hết hạn 24h kể từ lần tương tác cuối (Messenger policy)
            $table->timestamp('within_24h_until_at')->nullable()->index();

            // Nhãn/tags tuỳ chỉnh (VIP, khiếu nại, v.v.)
            $table->json('tags')->nullable();

            // Thời điểm tin nhắn cuối, để sort danh sách hội thoại
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // ===== Index tổng hợp thường dùng =====
            $table->index(['page_id', 'last_message_at']);
            $table->index(['assigned_user_id']);

            // ===== Ràng buộc khoá ngoại =====
            $table->foreign('page_id')
                ->references('id')->on('fb_pages')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('fb_user_id')
                ->references('id')->on('fb_users')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('assigned_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_conversations');
    }
};
