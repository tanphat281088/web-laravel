<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class LoaiKhachHang extends Model
{
    use UserTrackable, UserNameResolver, DateTimeFormatter;

    // Cho phép fill toàn bộ field (đã dùng từ trước)
    protected $guarded = [];

    /**
     * Boot model
     * - Giữ nguyên logic unset('image')
     * - THÊM logic tự động tính nguong_diem = floor(nguong_doanh_thu / 1000)
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Giữ logic cũ
            unset($model->attributes['image']);

            // 🔹 Logic mới: tự tính ngưỡng điểm từ ngưỡng doanh thu
            $doanhThu = (int) ($model->nguong_doanh_thu ?? 0);
            $model->nguong_diem = (int) floor($doanhThu / 1000);
        });
    }

    // Kết nối sẵn với bảng images để lưu ảnh
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}
