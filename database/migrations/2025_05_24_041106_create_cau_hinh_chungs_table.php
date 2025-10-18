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
    Schema::create('cau_hinh_chungs', function (Blueprint $table) {
      $table->id();
      $table->string('ten_cau_hinh');
      $table->string('gia_tri');
      $table->string('mo_ta')->nullable();

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
    Schema::dropIfExists('cau_hinh_chungs');
  }
};