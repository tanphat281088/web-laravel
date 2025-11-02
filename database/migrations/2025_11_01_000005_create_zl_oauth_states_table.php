<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zl_oauth_states', function (Blueprint $table) {
            // PKCE / CSRF state store (TTL ngắn)
            $table->string('state', 128)->primary();       // random url-safe, duy nhất cho mỗi flow

            // Lưu code_verifier dạng MÃ HÓA để có thể dùng khi đổi token (authorization_code)
            $table->longText('code_verifier_enc')->nullable(); // Crypt::encryptString(code_verifier)

            // Thông tin phụ (ví dụ: who initiated, redirect hint, dpop_kid dự kiến…)
            $table->json('meta')->nullable();

            // Hạn sử dụng state (khuyến nghị 10–15 phút theo Zalo)
            $table->timestamp('expires_at')->index();

            $table->timestamps();

            // Quét dọn định kỳ nhanh theo thời gian tạo
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zl_oauth_states');
    }
};
