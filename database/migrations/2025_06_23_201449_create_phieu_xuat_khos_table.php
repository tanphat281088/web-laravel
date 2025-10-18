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
    Schema::create('phieu_xuat_khos', function (Blueprint $table) {
      $table->id();

      $table->string('ma_phieu_xuat_kho')->unique();
      $table->tinyInteger('loai_phieu_xuat')->comment('1: xuất bán theo đơn hàng, 2: xuất huỷ, 3: xuất nguyên liệu sản xuất');
      $table->date('ngay_xuat_kho');
      $table->string('nguoi_nhan_hang')->nullable();
      $table->string('so_dien_thoai_nguoi_nhan_hang')->nullable();
      $table->foreignId('don_hang_id')->nullable();
      $table->foreignId('san_xuat_id')->nullable();
      $table->text('ly_do_huy')->nullable();
      $table->integer('tong_tien');
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
    Schema::dropIfExists('phieu_xuat_khos');
  }
};