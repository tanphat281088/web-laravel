<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_users', function (Blueprint $table) {
            $table->bigIncrements('id');

            // PSID của user trên Messenger (duy nhất theo Page)
            $table->string('psid')->unique();

            // Thông tin hiển thị (nếu lấy được)
            $table->string('name')->nullable();
            $table->string('locale')->nullable();    // ví dụ: en_US, vi_VN
            $table->string('timezone')->nullable();  // có thể là offset hoặc tên vùng
            $table->string('avatar')->nullable();    // URL ảnh đại diện (nếu có)

            // Thời điểm lần đầu mình “thấy” user này (optional)
            $table->timestamp('first_seen_at')->nullable();

            $table->timestamps();

            // Chỉ mục phụ trợ (tùy trường hợp tra cứu)
            $table->index(['locale']);
            $table->index(['timezone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_users');
    }
};
