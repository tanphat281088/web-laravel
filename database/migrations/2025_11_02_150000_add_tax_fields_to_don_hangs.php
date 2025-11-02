<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            // ===== Thuế (tương thích ngược) =====
            // 0 = không thuế (giữ hành vi cũ), 1 = có VAT
            $table->tinyInteger('tax_mode')
                  ->default(0)
                  ->comment('0=none, 1=vat')
                  ->after('ghi_chu');

            // % VAT, chỉ dùng khi tax_mode=1
            $table->decimal('vat_rate', 5, 2)
                  ->nullable()
                  ->after('tax_mode');

            // Tạm tính sau giảm giá + chi phí (chưa VAT)
            $table->bigInteger('subtotal')
                  ->nullable()
                  ->after('vat_rate');

            // Tiền VAT (đồng)
            $table->bigInteger('vat_amount')
                  ->nullable()
                  ->after('subtotal');

            // Tổng thanh toán cuối cùng = subtotal + vat_amount (nếu có VAT)
            $table->bigInteger('grand_total')
                  ->nullable()
                  ->after('vat_amount');
        });
    }

    public function down(): void
    {
        Schema::table('don_hangs', function (Blueprint $table) {
            $table->dropColumn(['tax_mode', 'vat_rate', 'subtotal', 'vat_amount', 'grand_total']);
        });
    }
};
