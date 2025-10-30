<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_items', function (Blueprint $table) {
            $table->id();

            // Mã VT (ví dụ VT00001), tên VT
            $table->string('ma_vt', 50)->unique();
            $table->string('ten_vt', 255);

            // Phân loại hiển thị từ file bạn gửi
            $table->string('danh_muc_vt', 255)->nullable(); // ví dụ: "Máy tính & văn phòng", "Vật tư tiêu hao"
            $table->string('nhom_vt', 255)->nullable();      // ví dụ: "Máy in", "Giấy gói", "Ruy băng"
            $table->string('don_vi_tinh', 50)->nullable();   // ví dụ: "cái", "cuộn", "túi", "bình", "bộ"

            // Loại vật tư: ASSET (Tài sản) | CONSUMABLE (Tiêu hao)
            $table->enum('loai', ['ASSET', 'CONSUMABLE'])->default('CONSUMABLE')->index();

            // Trạng thái sử dụng
            $table->tinyInteger('trang_thai')->default(1)->comment('1 = đang dùng, 0 = ngưng');

            // Ghi chú thêm nếu cần
            $table->text('ghi_chu')->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // (Tùy chọn) Ràng buộc FK users nếu bảng users đang dùng id BIGINT
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vt_items', function (Blueprint $table) {
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_items');
    }
};
