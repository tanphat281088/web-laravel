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
    Schema::create('thoi_gian_lam_viecs', function (Blueprint $table) {
      $table->id();
      $table->string('thu');
      $table->string('gio_bat_dau');
      $table->string('gio_ket_thuc');
      $table->text('ghi_chu')->nullable();


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
    Schema::dropIfExists('thoi_gian_lam_viecs');
  }
};