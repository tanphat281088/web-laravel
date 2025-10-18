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
    Schema::create('cong_nos', function (Blueprint $table) {
      $table->id();
      $table->integer('nha_cung_cap_id')->nullable();
      $table->integer('khach_hang_id')->nullable();
      $table->integer('so_tien');
      $table->tinyInteger('phan_loai')->comment('1: nợ nhà cung cấp, 2: thanh toán nhà cung cấp 3: khách hàng nợ, 4: khách hàng thanh toán');

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
    Schema::dropIfExists('cong_nos');
  }
};