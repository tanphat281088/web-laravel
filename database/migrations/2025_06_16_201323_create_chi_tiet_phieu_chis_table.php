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
    Schema::create('chi_tiet_phieu_chis', function (Blueprint $table) {
      $table->id();
      $table->foreignId('phieu_chi_id')->constrained('phieu_chis');
      $table->foreignId('phieu_nhap_kho_id')->constrained('phieu_nhap_khos');
      $table->integer('so_tien');


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
    Schema::dropIfExists('chi_tiet_phieu_chis');
  }
};