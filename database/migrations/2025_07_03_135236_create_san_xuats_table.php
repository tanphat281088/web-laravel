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
    Schema::create('san_xuats', function (Blueprint $table) {
      $table->id();
      $table->string('ma_lo_san_xuat');
      $table->date('ngay_san_xuat')->nullable();
      $table->foreignId('san_pham_id')->constrained('san_phams');
      $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
      $table->integer('so_luong');
      $table->integer('loi_nhuan');
      $table->integer('gia_cost');
      $table->integer('chi_phi_khac');
      $table->integer('gia_thanh_san_xuat');
      $table->integer('gia_ban_de_xuat');
      $table->tinyInteger('trang_thai_hoan_thanh')->default(0)->comment('(0: chưa sản xuất, 1: đang sản xuất, 2: đã hoàn thành)  khi mới tạo sản xuất mặc định là 0, sau khi xuất kho đủ số lượng nguyên liệu sẽ cập nhật lại 1, khi xác nhận hoàn thành sẽ cập nhật lại 2');
      $table->tinyInteger('trang_thai_nhap_kho')->default(0)->comment('(0: chưa nhập kho, 1: đã nhập kho 1 phần, 2: đã nhập kho hoàn tất)');
      $table->integer('so_luong_nhap_kho')->default(0);
      $table->tinyInteger('trang_thai_xuat_kho')->default(0)->comment('(0: chưa xuất kho, 1: đã xuất kho 1 phần, 2: đã xuất kho đủ)');
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
    Schema::dropIfExists('san_xuats');
  }
};