<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('nha_cung_caps', function (Blueprint $table) {
      $table->id();
      $table->string('ma_nha_cung_cap')->unique();
      $table->string('ten_nha_cung_cap');
      $table->string('so_dien_thoai')->unique();
      $table->string('email')->unique();
      $table->string('dia_chi')->nullable();
      $table->string('ma_so_thue')->unique();
      $table->string('ngan_hang')->nullable();
      $table->string('so_tai_khoan')->nullable();
      $table->text('ghi_chu')->nullable();
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
    Schema::dropIfExists('nha_cung_caps');
  }
};