<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Danh mục tài khoản tiền
        if (! Schema::hasTable('tai_khoan_tiens')) {
            Schema::create('tai_khoan_tiens', function (Blueprint $table) {
                $table->id();

                // Mã & tên hiển thị
                $table->string('ma_tk', 64)->unique();          // ví dụ: CASH, COMPANY, ZLP, PHAT, ANH_TUYET, HONG_TUYET
                $table->string('ten_tk', 191);                  // ví dụ: "Tiền mặt", "TK Công ty", "ZaloPay", ...

                // Loại tài khoản: cash | bank | ewallet
                $table->string('loai', 16)->default('bank');    // đảm bảo backward-compat

                // Thông tin ngân hàng/ewallet (nullable cho 'cash')
                $table->string('so_tai_khoan', 191)->nullable();
                $table->string('ngan_hang', 191)->nullable();

                // Trạng thái & cờ đặc biệt
                $table->boolean('is_default_cash')->default(false); // flag đánh dấu tài khoản "Tiền mặt"
                $table->boolean('is_active')->default(true);

                // Số dư đầu kỳ (tùy chọn)
                $table->decimal('opening_balance', 18, 2)->default(0);
                $table->date('opening_date')->nullable();

                $table->text('ghi_chu')->nullable();

                $table->timestamps();

                // Index phục vụ tra cứu nhanh
                $table->index(['loai', 'is_active'], 'tai_khoan_tiens_loai_active_idx');
                $table->index(['ngan_hang', 'so_tai_khoan'], 'tai_khoan_tiens_bank_acc_idx');
            });
        }

        // 2) Alias nhận diện tài khoản
        if (! Schema::hasTable('tai_khoan_aliases')) {
            Schema::create('tai_khoan_aliases', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tai_khoan_id');

                // Mẫu nhận diện (regex/substring) — nullable để linh hoạt
                $table->string('pattern_bank', 191)->nullable();    // ví dụ: 'VCB|Vietcombank|MB|MBBank|ZaloPay'
                $table->string('pattern_account', 191)->nullable(); // ví dụ: '0123|1903|ZLP'
                $table->string('pattern_note', 191)->nullable();    // dò theo ghi chú/ly_do

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tai_khoan_id')
                      ->references('id')->on('tai_khoan_tiens')
                      ->onDelete('cascade');

                // Index giúp map nhanh theo từng tiêu chí
                $table->index(['tai_khoan_id', 'is_active'], 'tai_khoan_aliases_fk_active_idx');
                $table->index('pattern_bank', 'tai_khoan_aliases_bank_idx');
                $table->index('pattern_account', 'tai_khoan_aliases_acc_idx');
                $table->index('pattern_note', 'tai_khoan_aliases_note_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop theo thứ tự FK
        if (Schema::hasTable('tai_khoan_aliases')) {
            Schema::dropIfExists('tai_khoan_aliases');
        }
        if (Schema::hasTable('tai_khoan_tiens')) {
            Schema::dropIfExists('tai_khoan_tiens');
        }
    }
};
