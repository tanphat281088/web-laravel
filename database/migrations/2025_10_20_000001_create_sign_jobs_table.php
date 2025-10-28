<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sign_jobs', function (Blueprint $t) {
            $t->id();

            // Người thực hiện (nullable để không ràng buộc)
            $t->unsignedBigInteger('user_id')->nullable()->index();

            // Nội dung text đầu vào
            $t->text('input_text');

            // Có thể sinh nhiều template/size trong một job
            // Lưu danh sách code template đã chọn
            $t->json('template_codes'); // ["OVAL_S","RECT_M",...]

            // Loại xuất: pdf/png/zip
            $t->enum('export_type', ['pdf','png','zip'])->default('pdf');

            // Tùy chọn runtime (font, màu, icon, curve%, n-up, cmyk/rgb...)
            $t->json('options')->nullable();

            // Trạng thái job
            $t->enum('status', ['queued','processing','done','failed'])->default('queued')->index();

            // Kết quả: danh sách đường dẫn file hoặc object {pdf:"...", png:["..."]}
            $t->json('result_paths')->nullable();

            // Ghi thời điểm để theo dõi hiệu năng
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();

            // Lỗi (nếu có)
            $t->text('error_message')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_jobs');
    }
};
