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
    Schema::create('khach_hangs', function (Blueprint $table) {
      $table->id();
      $table->string('ten_khach_hang');
      $table->string('email');
      $table->string('so_dien_thoai');
      $table->string('dia_chi');
      $table->foreignId('loai_khach_hang_id')->nullable()->constrained('loai_khach_hangs');
      $table->integer('cong_no')->default(0);
      $table->integer('doanh_thu_tich_luy')->default(0);
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
    Schema::dropIfExists('khach_hangs');
  }
};