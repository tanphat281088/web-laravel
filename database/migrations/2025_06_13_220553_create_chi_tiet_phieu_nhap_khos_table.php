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
        Schema::create('chi_tiet_phieu_nhap_khos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phieu_nhap_kho_id')->constrained('phieu_nhap_khos');
            $table->string('ma_lo_san_pham')->unique();
            $table->foreignId('san_pham_id')->constrained('san_phams');
            $table->foreignId('nha_cung_cap_id')->constrained('nha_cung_caps')->nullable();
            $table->foreignId('don_vi_tinh_id')->constrained('don_vi_tinhs');
            $table->date('ngay_san_xuat');
            $table->date('ngay_het_han');
            $table->integer('gia_nhap');
            $table->integer('so_luong_nhap');
            $table->integer('tong_tien_nhap');
            $table->integer('chiet_khau')->default(0);
            $table->integer('gia_von_don_vi')->default(0);
            $table->integer('gia_ban_le_don_vi')->default(0);
            $table->integer('loi_nhuan_ban_le')->default(0);
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
        Schema::dropIfExists('chi_tiet_phieu_nhap_khos');
    }
};
