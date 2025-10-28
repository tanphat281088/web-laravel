<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng master Danh mục chi (cha → con) phục vụ KQKD Mức A
     * - code: mã ngắn, unique (vd: COGS, BH, QLDN, TC, CHI_KHAC, HOA, PK, INAN, ...)
     * - name: tên hiển thị
     * - parent_id: quan hệ cha → con (nullable đối với nhóm CHA)
     * - statement_line: dòng KQKD để tổng hợp (02/05/06/07/10). Nullable cho nhóm CHA nếu muốn.
     * - sort_order: sắp xếp
     * - is_active: bật/tắt
     */
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique()->comment('Mã danh mục ổn định để mapping và import (vd: COGS, BH, QLDN, TC, CHI_KHAC, HOA, PK, INAN, ...)');
            $table->string('name')->comment('Tên hiển thị');

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('expense_categories')
                ->nullOnDelete()
                ->comment('NULL = nhóm CHA; khác NULL = nhóm CON');

            $table->tinyInteger('statement_line')
                ->nullable()
                ->comment('Dòng KQKD: 02=Giá vốn, 05=Chi phí tài chính, 06=Chi phí bán hàng, 07=Chi phí QLDN, 10=Chi phí khác');

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('parent_id');
            $table->index('statement_line');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
