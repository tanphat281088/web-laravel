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
    Schema::create('cong_thuc_san_xuats', function (Blueprint $table) {
      $table->id();
      $table->foreignId('san_pham_id')->constrained('san_phams')->onDelete('cascade');
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs')->onDelete('cascade');
      $table->integer('so_luong');
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
    Schema::dropIfExists('cong_thuc_san_xuats');
  }
};