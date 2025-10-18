<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    //

    use DateTimeFormatter, UserNameResolver, UserTrackable;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            unset($model->attributes['image']);
        });
    }

    // Kết nối sẵn với bảng images để lưu ảnh
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class);
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class);
    }

    public function nguoiTao()
    {
        return $this->belongsTo(User::class, 'nguoi_tao');
    }

    public function phieuThu()
    {
        return $this->hasMany(PhieuThu::class);
    }

    public function chiTietPhieuThu()
    {
        return $this->hasMany(ChiTietPhieuThu::class);
    }
}
