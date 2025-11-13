<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zns_review_invites', function (Blueprint $t) {
            $t->id();

            // Liên kết nghiệp vụ (không FK cứng để an toàn deploy)
            $t->unsignedBigInteger('khach_hang_id');
            $t->unsignedBigInteger('don_hang_id')->unique('uniq_review_by_order'); // 1 đơn tối đa 1 invite

            // Snapshot dữ liệu tại thời điểm mời
            $t->string('customer_code', 30)->nullable();
            $t->string('customer_name', 100)->nullable();
            $t->string('order_code', 30)->nullable();
            $t->timestamp('order_date')->nullable();

            // Trạng thái gửi ZNS
            $t->enum('zns_status', ['pending','sent','failed','cancelled'])->default('pending');
            $t->timestamp('zns_sent_at')->nullable();
            $t->string('zns_template_id', 64)->nullable();
            $t->string('zns_error_code', 64)->nullable();
            $t->string('zns_error_message', 255)->nullable();

            // Audit
            $t->string('nguoi_tao', 100)->nullable();
            $t->string('nguoi_cap_nhat', 100)->nullable();

            $t->timestamps();
            $t->softDeletes();

            // Index phục vụ lọc nhanh
            $t->index(['khach_hang_id', 'zns_status'], 'idx_review_kh_status');
            $t->index(['order_date'], 'idx_review_order_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zns_review_invites');
    }
};
