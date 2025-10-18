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
    Schema::create('nha_cung_cap_san_phams', function (Blueprint $table) {
      $table->id();
      $table->foreignId('nha_cung_cap_id')->constrained('nha_cung_caps');
      $table->foreignId('san_pham_id')->constrained('san_phams');

      $table->unique(['nha_cung_cap_id', 'san_pham_id']);


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
    Schema::dropIfExists('nha_cung_cap_san_phams');
  }
};