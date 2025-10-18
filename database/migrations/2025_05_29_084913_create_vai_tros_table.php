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
    Schema::create('vai_tros', function (Blueprint $table) {
      $table->id();
      $table->string('ma_vai_tro');
      $table->string('ten_vai_tro');
      $table->boolean('trang_thai')->default(true);
      $table->longText('phan_quyen');

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
    Schema::dropIfExists('vai_tros');
  }
};