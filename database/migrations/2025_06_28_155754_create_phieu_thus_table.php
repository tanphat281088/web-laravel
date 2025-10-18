<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('phieu_thus', function (Blueprint $table) {
      $table->id();
      $table->string('ma_phieu_thu')->unique();
      $table->date('ngay_thu');
      $table->tinyInteger('loai_phieu_thu')->comment('1: thu cho đơn hàng, 2: thu cho nhiều đơn hàng theo khách hàng, 3: thu công nợ khách hàng, 4: thu khác');
      $table->unsignedBigInteger('khach_hang_id')->nullable();
      $table->unsignedBigInteger('don_hang_id')->nullable();
      $table->integer('so_tien');
      $table->string('nguoi_tra')->nullable();
      $table->tinyInteger('phuong_thuc_thanh_toan')->comment('1: tiền mặt, 2: chuyển khoản');
      $table->string('so_tai_khoan')->nullable();
      $table->string('ngan_hang')->nullable();
      $table->text('ly_do_thu')->nullable();
      $table->text('ghi_chu')->nullable();

      $table->string('nguoi_tao');
      $table->string('nguoi_cap_nhat');
      $table->timestamps();

      // Thêm các foreign key constraints
      $table->foreign('khach_hang_id')->references('id')->on('khach_hangs')->onDelete('set null');
      $table->foreign('don_hang_id')->references('id')->on('don_hangs')->onDelete('set null');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('phieu_thus');
  }
};