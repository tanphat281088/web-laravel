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
    Schema::create('don_hangs', function (Blueprint $table) {
      $table->id();

      $table->string('ma_don_hang')->unique();
      $table->date('ngay_tao_don_hang');
      $table->tinyInteger('loai_khach_hang')->comment('0: khách hàng hệ thống, 1: khách vãng lai');
      $table->unsignedBigInteger('khach_hang_id')->nullable();
      $table->string('ten_khach_hang')->nullable();
      $table->text('dia_chi_giao_hang');
      $table->integer('tong_so_luong_san_pham');
      $table->integer('tong_tien_hang');
      $table->integer('giam_gia')->default(0);
      $table->integer('chi_phi')->default(0);
      $table->integer('tong_tien_can_thanh_toan');
      $table->tinyInteger('loai_thanh_toan')->default(0)->comment('0: chưa thanh toán, 1: thanh toán 1 phần - đặt cọc, 2: thanh toán toàn bộ');
      $table->integer('so_tien_da_thanh_toan')->default(0);
      $table->tinyInteger('trang_thai_thanh_toan')->default(0)->comment('0: chưa hoàn thành, 1: đã hoàn thành');
      $table->tinyInteger('trang_thai_xuat_kho')->default(0)->comment('0: chưa xử lý, 1: đã có xuất kho, 2: đã hoàn thành');
      $table->unsignedBigInteger('nguoi_duyet_id')->nullable();
      $table->dateTime('ngay_duyet')->nullable();
      $table->text('ghi_chu')->nullable();

      $table->string('nguoi_tao');
      $table->string('nguoi_cap_nhat');
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('don_hangs');
  }
};