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
    Schema::create('chi_tiet_don_hangs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('don_hang_id')->constrained('don_hangs');
      $table->foreignId('san_pham_id')->constrained('san_phams');
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
      $table->integer('so_luong');
      $table->integer('don_gia');
      $table->integer('thanh_tien');
      $table->integer('so_luong_da_xuat_kho')->default(0);



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
    Schema::dropIfExists('chi_tiet_don_hangs');
  }
};