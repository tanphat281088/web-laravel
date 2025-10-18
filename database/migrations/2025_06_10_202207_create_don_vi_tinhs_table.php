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
    Schema::create('don_vi_tinhs', function (Blueprint $table) {
      $table->id();
      $table->string('ten_don_vi')->unique();
      $table->string('ky_hieu')->nullable()->unique();
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
    Schema::dropIfExists('don_vi_tinhs');
  }
};