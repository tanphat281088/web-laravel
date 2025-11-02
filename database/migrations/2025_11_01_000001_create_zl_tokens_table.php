<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zl_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ===== Token OAuth v4 (MÃ HÓA trước khi lưu) =====
            $table->longText('access_token_enc')->nullable();   // Crypt::encryptString(access_token)
            $table->longText('refresh_token_enc')->nullable();  // Crypt::encryptString(refresh_token)

            // TTL
            $table->timestamp('expires_at')->nullable()->index();            // access token TTL (~1h)
            $table->timestamp('refresh_expires_at')->nullable()->index();    // refresh token TTL (<=30d, đếm lùi)

            // App/Scope/Thiết lập
            $table->string('app_id')->nullable();                // ZL_APP_ID dùng để đổi token
            $table->json('scopes')->nullable();                  // nếu Zalo trả scope chi tiết
            $table->json('settings')->nullable();                // cấu hình kênh (provider dịch, tone, flags, ...)

            // DPoP: khóa dùng để ký JWT-DPoP cho CHUỖI token này
            $table->string('dpop_kid')->nullable()->index();     // fingerprint/key-id của keypair đang dùng

            // Quan trắc/Health
            $table->tinyInteger('status')->default(1)->index();  // 1=active, 0=inactive (khóa nhanh)
            $table->timestamp('last_health_check_at')->nullable()->index();

            $table->timestamps();

            // Chỉ số tổng hợp phục vụ worker24h theo dõi sớm token sắp hết hạn
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'refresh_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zl_tokens');
    }
};
