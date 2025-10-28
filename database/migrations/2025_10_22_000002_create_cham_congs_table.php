<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cham_congs', function (Blueprint $table) {
            $table->id();

            // Nhân sự chấm công
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete();

            // Loại mốc thời gian: checkin | checkout
            $table->enum('type', ['checkin', 'checkout']);

            // Toạ độ GPS tại thời điểm chấm
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            // Sai số & trạng thái vùng địa lý
            $table->unsignedSmallInteger('accuracy_m')->nullable(); // nếu FE truyền được
            $table->unsignedInteger('distance_m');                 // khoảng cách tới tâm geofence (m)
            $table->tinyInteger('within_geofence')->default(0);    // 1=đúng vùng | 0=ngoài vùng

            // Thông tin thiết bị/nghiệp vụ
            $table->string('device_id', 100)->nullable();
            $table->string('ip', 45)->nullable();                   // IPv4/IPv6
            $table->dateTime('checked_at');                         // thời điểm thực tế chấm công (server lưu)

            // Cột ngày phát sinh (generated) để unique theo ngày
            // MySQL (InnoDB) hỗ trợ generated column dùng cho chỉ mục duy nhất
            $table->date('ngay')->storedAs('DATE(`checked_at`)');

            $table->string('ghi_chu', 255)->nullable();

            $table->timestamps();

            // Ràng buộc: mỗi user chỉ 1 checkin + 1 checkout / ngày
            $table->unique(['user_id', 'type', 'ngay'], 'uniq_user_type_ngay');

            // Chỉ mục phục vụ truy vấn dải thời gian & lọc người dùng
            $table->index(['user_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cham_congs');
    }
};
