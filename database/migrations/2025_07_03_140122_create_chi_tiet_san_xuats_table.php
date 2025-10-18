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
    Schema::create('chi_tiet_san_xuats', function (Blueprint $table) {
      $table->id();
      $table->foreignId('san_xuat_id')->constrained('san_xuats');
      $table->foreignId('san_pham_id')->constrained('san_phams')->comment('thuộc loại NGUYEN_LIEU');
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
      $table->integer('don_gia')->comment('giá nhập của nguyên liệu');
      $table->integer('so_luong_cong_thuc')->comment('số lượng nguyên liệu cần dùng theo công thức sản xuất');
      $table->integer('so_luong_thuc_te');
      $table->integer('so_luong_xuat_kho')->default(0);


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
    Schema::dropIfExists('chi_tiet_san_xuats');
  }
};