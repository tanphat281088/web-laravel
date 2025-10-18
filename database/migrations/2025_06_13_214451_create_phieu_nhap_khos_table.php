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
        Schema::create('phieu_nhap_khos', function (Blueprint $table) {
            $table->id();
            $table->string('ma_phieu_nhap_kho')->unique();
            $table->tinyInteger('loai_phieu_nhap')->comment('1: nhập kho từ nhà cung cấp, 2: nhập kho từ sản xuất');
            $table->date('ngay_nhap_kho');
            $table->integer('nha_cung_cap_id')->nullable();
            $table->integer('san_xuat_id')->nullable();
            $table->string('so_hoa_don_nha_cung_cap')->nullable();
            $table->string('nguoi_giao_hang')->nullable();
            $table->string('so_dien_thoai_nguoi_giao_hang')->nullable();
            $table->integer('tong_tien_hang');
            $table->integer('tong_chiet_khau');
            $table->integer('thue_vat');
            $table->integer('chi_phi_nhap_hang')->default(0);
            $table->integer('giam_gia_nhap_hang')->default(0);
            $table->integer('tong_tien');
            $table->integer('da_thanh_toan')->default(0);
            $table->tinyInteger('trang_thai')->default(0)->comment('0: chưa có thanh toán, 1: đã có thanh toán');
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
        Schema::dropIfExists('phieu_nhap_khos');
    }
};
