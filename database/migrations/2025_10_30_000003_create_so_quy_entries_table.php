<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('so_quy_entries')) {
            Schema::create('so_quy_entries', function (Blueprint $table) {
                $table->id();

                // === Tài khoản tiền (bắt buộc) ===
                $table->unsignedBigInteger('tai_khoan_id');

                // === Thời điểm ghi sổ ===
                // Dùng DATETIME để có thể sắp xếp theo giờ/phút (linh hoạt hơn DATE)
                $table->dateTime('ngay_ct');

                // === Số tiền: dương = vào, âm = ra ===
                $table->decimal('amount', 18, 2);

                // === Nguồn phát sinh (tham chiếu mềm) ===
                // phieu_thu | phieu_chi | chuyen_noi_bo | phi_chuyen | dieuchinh (tùy mở rộng)
                $table->string('ref_type', 32);
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->string('ref_code', 191)->nullable(); // ví dụ: PT-*, PC-*, TRF-*

                // Mô tả/ghi chú (tùy chọn)
                $table->text('mo_ta')->nullable();

                // Người tạo (nếu cần truy vết)
                $table->unsignedBigInteger('created_by')->nullable();

                // Đối soát (giai đoạn 2)
                $table->dateTime('reconciled_at')->nullable();

                $table->timestamps();

                // === Ràng buộc & index ===
                $table->foreign('tai_khoan_id')
                      ->references('id')->on('tai_khoan_tiens')
                      ->onDelete('cascade');

                // Tra cứu nhanh theo tài khoản & thời điểm
                $table->index(['tai_khoan_id', 'ngay_ct'], 'soquy_tk_ngay_idx');

                // Tra cứu nhanh theo nguồn phát sinh
                $table->index(['ref_type', 'ref_id'], 'soquy_ref_idx');

                // Lọc nhanh các bản ghi đã/Chưa đối soát
                $table->index('reconciled_at', 'soquy_reconciled_idx');

                // Truy vấn theo thời gian tạo (dashboard)
                $table->index('created_at', 'soquy_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('so_quy_entries');
    }
};
