<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fb_glossaries', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Thuật ngữ cần giữ nguyên hoặc ưu tiên dịch theo cách riêng
            $table->string('term');                 // ví dụ: "PHG Floral", "Hoa Pastel", "Eucalyptus"

            // Nếu true: giữ nguyên 'term' (không dịch)
            $table->boolean('prefer_keep')->default(false)->index();

            // Nếu có: bản dịch ưu tiên của 'term'
            $table->string('prefer_translation')->nullable();

            // Ghi chú nội bộ
            $table->string('note')->nullable();

            $table->timestamps();

            // Tăng tốc tìm kiếm theo term
            $table->index(['term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fb_glossaries');
    }
};
