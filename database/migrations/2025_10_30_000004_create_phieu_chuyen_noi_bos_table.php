<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('phieu_chuyen_noi_bos')) {
            Schema::create('phieu_chuyen_noi_bos', function (Blueprint $table) {
                $table->id();

                // Mã phiếu chuyển (để tra cứu nhanh / in chứng từ)
                $table->string('ma_phieu', 64)->unique();

                // Ngày chứng từ (dùng DATETIME nếu bạn muốn giờ/phút)
                $table->date('ngay_ct');

                // Tài khoản nguồn & đích
                $table->unsignedBigInteger('tu_tai_khoan_id');
                $table->unsignedBigInteger('den_tai_khoan_id');

                // Số tiền chuyển > 0 (phí có thể = 0)
                $table->decimal('so_tien', 18, 2);
                $table->decimal('phi_chuyen', 18, 2)->default(0);

                // Nội dung, ghi chú
                $table->string('noi_dung', 255)->nullable();

                // Trạng thái: draft | posted | locked
                $table->string('trang_thai', 16)->default('draft');

                // Người tạo/cập nhật
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->timestamps();

                // ===== Ràng buộc & index =====
                $table->foreign('tu_tai_khoan_id')
                      ->references('id')->on('tai_khoan_tiens')
                      ->onDelete('restrict');

                $table->foreign('den_tai_khoan_id')
                      ->references('id')->on('tai_khoan_tiens')
                      ->onDelete('restrict');

                // Không cho chuyển cùng 1 tài khoản (sẽ enforce ở tầng service/validation)
                $table->index(['ngay_ct', 'trang_thai'], 'trf_ngay_status_idx');
                $table->index(['tu_tai_khoan_id', 'den_tai_khoan_id'], 'trf_from_to_idx');
                $table->index('created_at', 'trf_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('phieu_chuyen_noi_bos');
    }
};
