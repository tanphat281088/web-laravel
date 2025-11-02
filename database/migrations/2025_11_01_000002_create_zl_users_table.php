<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zl_users', function (Blueprint $table) {
            $table->bigIncrements('id');

            // User ID theo từng ứng dụng (unique trong phạm vi app)
            $table->string('zalo_user_id')->unique();

            // Thông tin hiển thị (nếu lấy được từ /me)
            $table->string('name')->nullable();
            $table->string('avatar_url')->nullable();

            // Ngôn ngữ/locale nếu Zalo trả (không bắt buộc)
            $table->string('locale', 16)->nullable()->index();

            // Nhóm nhạy cảm (tài liệu /me: is_sensitive)
            $table->boolean('is_sensitive')->default(false)->index();

            // Lần đầu “thấy” user (khi phát sinh tương tác)
            $table->timestamp('first_seen_at')->nullable();

            $table->timestamps();

            // Các chỉ mục phụ trợ
            $table->index(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zl_users');
    }
};
