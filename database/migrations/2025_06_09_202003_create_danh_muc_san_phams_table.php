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
    Schema::create('danh_muc_san_phams', function (Blueprint $table) {
      $table->id();
      $table->string('ma_danh_muc');
      $table->string('ten_danh_muc');
      $table->string('ghi_chu')->nullable();
      $table->tinyInteger('trang_thai')->default(1);


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
    Schema::dropIfExists('danh_muc_san_phams');
  }
};