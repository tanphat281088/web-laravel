<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zl_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Liên kết người dùng Zalo (theo app) và người phụ trách nội bộ
            $table->unsignedBigInteger('zl_user_id');                 // fk -> zl_users.id
            $table->unsignedBigInteger('assigned_user_id')->nullable(); // fk -> users.id

            // 1 = open, 0 = closed
            $table->tinyInteger('status')->default(1)->index();

            // Ngôn ngữ chính ước lượng ('en','vi',...)
            $table->string('lang_primary', 8)->nullable();

            // Thời điểm hệ thống CHO PHÉP gửi tiếp (quota/policy) — có thể null
            $table->timestamp('can_send_until_at')->nullable()->index();

            // Nhãn tuỳ chỉnh + mốc tin nhắn cuối để sort
            $table->json('tags')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // Index tổng hợp
            $table->index(['zl_user_id', 'last_message_at']);
            $table->index(['assigned_user_id']);

            // Ràng buộc FK
            $table->foreign('zl_user_id')
                ->references('id')->on('zl_users')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->foreign('assigned_user_id')
                ->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zl_conversations');
    }
};
