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
    Schema::create('san_phams', function (Blueprint $table) {
      $table->id();
      $table->string('ma_san_pham')->unique();
      $table->string('ten_san_pham');
      $table->foreignId('danh_muc_id')->constrained('danh_muc_san_phams');
      $table->integer('gia_nhap_mac_dinh')->default(0);
      $table->decimal('ty_le_chiet_khau', 5, 2)->default(0);
      $table->decimal('muc_loi_nhuan', 5, 2)->default(0);
      $table->integer('tong_so_luong_nhap')->default(0);
      $table->integer('tong_so_luong_thuc_te')->default(0);
      $table->integer('so_luong_canh_bao')->default(0);
      $table->string('loai_san_pham')->comment('SP_NHA_CUNG_CAP, SP_SAN_XUAT, NGUYEN_LIEU');
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
    Schema::dropIfExists('san_phams');
  }
};