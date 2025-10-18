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
    Schema::create('chi_tiet_phieu_xuat_khos', function (Blueprint $table) {
      $table->id();

      $table->foreignId('phieu_xuat_kho_id')->constrained('phieu_xuat_khos');
      $table->foreignId('san_pham_id')->constrained('san_phams');
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
      $table->integer('don_gia');
      $table->string('ma_lo_san_pham');
      $table->integer('so_luong');
      $table->integer('tong_tien');

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
    Schema::dropIfExists('chi_tiet_phieu_xuat_khos');
  }
};