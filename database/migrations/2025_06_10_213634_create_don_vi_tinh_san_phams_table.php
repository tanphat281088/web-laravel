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
    Schema::create('don_vi_tinh_san_phams', function (Blueprint $table) {
      $table->id();
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
      $table->foreignId('san_pham_id')->constrained('san_phams');

      $table->unique(['don_vi_tinh_id', 'san_pham_id']);

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
    Schema::dropIfExists('don_vi_tinh_san_phams');
  }
};