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
    Schema::create('phieu_chis', function (Blueprint $table) {
      $table->id();
      $table->string('ma_phieu_chi')->unique();
      $table->date('ngay_chi');
      $table->tinyInteger('loai_phieu_chi')->comment('1: chi thanh toán cho phiếu nhập kho,2: chi thanh toán nhiều phiếu nhập kho theo nhà cung cấp, 3: thanh toán công nợ, 4: chi khác');
      $table->integer('nha_cung_cap_id')->nullable();
      $table->integer('phieu_nhap_kho_id')->nullable();
      $table->integer('so_tien');
      $table->string('nguoi_nhan')->nullable();
      $table->tinyInteger('phuong_thuc_thanh_toan')->comment('1: tiền mặt, 2: chuyển khoản');
      $table->string('so_tai_khoan')->nullable();
      $table->string('ngan_hang')->nullable();
      $table->text('ly_do_chi')->nullable();
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
    Schema::dropIfExists('phieu_chis');
  }
};