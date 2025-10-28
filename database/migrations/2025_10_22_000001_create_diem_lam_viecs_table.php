<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diem_lam_viecs', function (Blueprint $table) {
            $table->id();
            $table->string('ten', 150);
            $table->string('dia_chi', 255)->nullable();
            // DECIMAL(10,7) ~ độ chính xác ~1.1cm, đủ tốt cho geofence đô thị
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            // Bán kính geofence (mét). Mặc định 120m để bao trùm khuôn viên + sai số GPS
            $table->unsignedSmallInteger('ban_kinh_m')->default(120);
            $table->tinyInteger('trang_thai')->default(1); // 1=active, 0=inactive
            $table->timestamps();

            $table->index(['trang_thai']);
            $table->index(['lat', 'lng']);
        });

        // Seed sẵn trụ sở PHG theo yêu cầu
        DB::table('diem_lam_viecs')->insert([
            'ten'        => 'Trụ sở PHG',
            'dia_chi'    => '100 Nguyễn Minh Hoàng, Phường Bảy Hiền, TP. Hồ Chí Minh',
            'lat'        => 10.8000318,
            'lng'        => 106.6511966,
            'ban_kinh_m' => 120,
            'trang_thai' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('diem_lam_viecs');
    }
};
