<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng: don_tu_nghi_pheps
     * Mục đích: lưu đơn từ (xin nghỉ phép/đi trễ/về sớm/khác)
     *
     * Quy ước:
     *  - trang_thai: 0=pending | 1=approved | 2=rejected | 3=canceled
     *  - loai (gợi ý): 'nghi_phep','khong_luong','di_tre','ve_som','lam_viec_tu_xa','khac'
     */
    public function up(): void
    {
        // An toàn: nếu đã tồn tại thì bỏ qua
        if (Schema::hasTable('don_tu_nghi_pheps')) {
            return;
        }

        Schema::create('don_tu_nghi_pheps', function (Blueprint $table) {
            $table->id();

            // Người tạo đơn
            $table->unsignedBigInteger('user_id');

            // Khoảng thời gian xin nghỉ (ngày) - có thể dùng so_gio cho đơn theo giờ
            $table->date('tu_ngay')->nullable();
            $table->date('den_ngay')->nullable();

            // Số giờ xin nghỉ (nếu là đơn nghỉ theo giờ/đi trễ/về sớm) - đơn vị: giờ
            $table->unsignedSmallInteger('so_gio')->nullable();

            // Loại đơn & lý do
            $table->string('loai', 50);        // vd: nghi_phep, khong_luong, di_tre, ve_som, lam_viec_tu_xa, khac
            $table->text('ly_do')->nullable();

            // Trạng thái duyệt
            $table->tinyInteger('trang_thai')->default(0); // 0=pending,1=approved,2=rejected,3=canceled

            // Thông tin duyệt
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();

            // File đính kèm (mảng JSON các đường dẫn / id file)
            $table->json('attachments')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index phục vụ tra cứu nhanh
            $table->index(['user_id', 'tu_ngay', 'den_ngay']);
            $table->index('trang_thai');

            // FK an toàn (không xóa cascade để tránh mất lịch sử)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('don_tu_nghi_pheps');
    }
};
