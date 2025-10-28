<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sign_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique(); // ví dụ: OVAL_S, RECT_M, CLOUD_L
            $t->string('name');           // Tên hiển thị: "Oval nhỏ", "Rectangle vừa"...
            // Kiểu dáng cơ bản (có thể mở rộng sau này)
            $t->enum('shape', ['oval','rect','roundrect','cloud','heart','ribbon'])->default('oval');

            // Kích thước in (mm) & bleed
            $t->unsignedSmallInteger('width_mm');      // ví dụ 160
            $t->unsignedSmallInteger('height_mm');     // ví dụ 80
            $t->unsignedSmallInteger('bleed_mm')->default(3);

            // Style JSON cho nền/viền/chữ/padding/bo góc/độ cong chữ...
            // ví dụ:
            // {
            //   "bg_color":"#FFFFFF",
            //   "font_family":"Montserrat",
            //   "font_color":"#111111",
            //   "stroke_color":"#D32F2F",
            //   "stroke_width_mm":1,
            //   "corner_radius_mm":8,
            //   "text_align":"center",
            //   "curve_percent":15,
            //   "padding_mm":6
            // }
            $t->json('style')->nullable();

            // Tùy chọn layout (N-up, khoảng cách, crop marks…) cho xuất PDF
            $t->json('export_prefs')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_templates');
    }
};
