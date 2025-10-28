<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm khóa ngoại category_id (nullable) vào phieu_chis
     * - Trỏ tới expense_categories.id
     * - Không phá dữ liệu cũ (cho phép NULL)
     * - Thêm index phục vụ báo cáo
     */
    public function up(): void
    {
        Schema::table('phieu_chis', function (Blueprint $table) {
            // Thêm cột sau 'loai_phieu_chi' cho dễ đọc cấu trúc
            $table->foreignId('category_id')
                ->nullable()
                ->after('loai_phieu_chi')
                ->constrained('expense_categories')
                ->nullOnDelete(); // Nếu xóa danh mục => set NULL cho an toàn báo cáo

            // Khuyến nghị thêm index ngày chi để tổng hợp nhanh theo kỳ
            $table->index('ngay_chi');
        });
    }

    public function down(): void
    {
        Schema::table('phieu_chis', function (Blueprint $table) {
            // Xóa FK + cột
            $table->dropConstrainedForeignId('category_id');
            // Xóa index nếu tồn tại
            $table->dropIndex(['ngay_chi']);
        });
    }
};
