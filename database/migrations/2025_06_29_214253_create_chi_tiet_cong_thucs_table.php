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
    Schema::create('chi_tiet_cong_thucs', function (Blueprint $table) {
      $table->id();
      $table->foreignId('cong_thuc_san_xuat_id')->constrained('cong_thuc_san_xuats');
      $table->foreignId('san_pham_id')->constrained('san_phams');
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
      $table->integer('so_luong');
      $table->integer('lan_cap_nhat')->default(1);
      $table->dateTime('thoi_gian_cap_nhat')->nullable();


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
    Schema::dropIfExists('chi_tiet_cong_thucs');
  }
};