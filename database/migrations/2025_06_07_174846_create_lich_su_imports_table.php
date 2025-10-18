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
    Schema::create('lich_su_imports', function (Blueprint $table) {
      $table->id();
      $table->string('muc_import');
      $table->integer('tong_so_luong');
      $table->integer('so_luong_thanh_cong');
      $table->integer('so_luong_that_bai');
      $table->json('ket_qua_import')->nullable();
      $table->string('file_path')->nullable();

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
    Schema::dropIfExists('lich_su_imports');
  }
};