<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vt_ledger', function (Blueprint $table) {
            $table->id();

            // Liên kết vật tư
            $table->unsignedBigInteger('vt_item_id')->index();

            // Ngày chứng từ & loại chứng từ
            // OPENING: tồn đầu kỳ, RECEIPT: nhập, ISSUE: xuất, ADJUST: điều chỉnh (+/-)
            $table->date('ngay_ct')->index();
            $table->enum('loai_ct', ['OPENING', 'RECEIPT', 'ISSUE', 'ADJUST'])->index();

            // Số lượng vào/ra
            $table->integer('so_luong_in')->default(0);
            $table->integer('so_luong_out')->default(0);

            // Đơn giá (chỉ bắt buộc cho ASSET ở OPENING/RECEIPT nếu bạn muốn theo dõi trị giá ngay)
            // CONSUMABLE có thể để null
            $table->decimal('don_gia', 18, 2)->nullable();

            // Tham chiếu số CT/ghi chú
            $table->string('tham_chieu', 191)->nullable();
            $table->text('ghi_chu')->nullable();

            // Audit
            $table->unsignedBigInteger('nguoi_tao')->nullable()->index();
            $table->unsignedBigInteger('nguoi_cap_nhat')->nullable()->index();

            $table->timestamps();

            // FK
            $table->foreign('vt_item_id')->references('id')->on('vt_items')->cascadeOnDelete();
            $table->foreign('nguoi_tao')->references('id')->on('users')->nullOnDelete();
            $table->foreign('nguoi_cap_nhat')->references('id')->on('users')->nullOnDelete();

            // Chỉ mục tổng hợp cho truy vấn sổ kho theo VT & thời gian
            $table->index(['vt_item_id', 'ngay_ct', 'loai_ct']);
        });
    }

    public function down(): void
    {
        Schema::table('vt_ledger', function (Blueprint $table) {
            $table->dropForeign(['vt_item_id']);
            $table->dropForeign(['nguoi_tao']);
            $table->dropForeign(['nguoi_cap_nhat']);
        });
        Schema::dropIfExists('vt_ledger');
    }
};
