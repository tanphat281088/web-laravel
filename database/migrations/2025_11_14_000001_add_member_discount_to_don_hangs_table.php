<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột giảm giá thành viên vào bảng don_hangs.
     *
     * - member_discount_percent: % ưu đãi (snapshot tại thời điểm tạo đơn)
     * - member_discount_amount:  số tiền giảm tương ứng (VNĐ)
     *
     * Đơn cũ: hai cột này đều = 0 → hành vi giữ nguyên.
     */
    public function up(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // % giảm giá thành viên (0–100), đặt ngay sau cột giam_gia
            if (!Schema::hasColumn('don_hangs', 'member_discount_percent')) {
                $table->unsignedInteger('member_discount_percent')
                    ->default(0)
                    ->after('giam_gia')
                    ->comment('% giảm giá thành viên tại thời điểm tạo đơn');
            }

            // Số tiền giảm do thành viên (VNĐ), đặt ngay sau percent
            if (!Schema::hasColumn('don_hangs', 'member_discount_amount')) {
                $table->bigInteger('member_discount_amount')
                    ->default(0)
                    ->after('member_discount_percent')
                    ->comment('Số tiền giảm giá thành viên (VNĐ)');
            }
        });
    }

    /**
     * Rollback: xoá 2 cột mới.
     */
    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('don_hangs', 'member_discount_amount')) {
                $table->dropColumn('member_discount_amount');
            }
            if (Schema::hasColumn('don_hangs', 'member_discount_percent')) {
                $table->dropColumn('member_discount_percent');
            }
        });
    }
};
