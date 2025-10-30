<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_pages', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Page ID bên Meta (unique để tránh trùng cấu hình nhiều lần)
            $table->string('page_id')->unique();

            // Tên page (nếu lấy được), có thể null
            $table->string('name')->nullable();

            // Token Page đã được MÃ HÓA (Encrypt trước khi lưu)
            $table->longText('token_enc')->nullable();

            // Trạng thái cấu hình: 1=active, 0=inactive (để vô hiệu hoá nhanh từng page)
            $table->tinyInteger('status')->default(1)->index();

            // Lần cuối health-check thành công (null nếu chưa từng)
            $table->timestamp('last_health_check_at')->nullable()->index();

            // Các thiết lập bổ sung (provider dịch mặc định, tone, cờ polish, v.v.)
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_pages');
    }
};
