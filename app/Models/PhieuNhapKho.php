<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class PhieuNhapKho extends Model
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

    // Relationship với ChiTietPhieuNhapKho
    public function chiTietPhieuNhapKhos()
    {
        return $this->hasMany(ChiTietPhieuNhapKho::class);
    }

    // Relationship với NhaCungCap
    public function nhaCungCap()
    {
        return $this->belongsTo(NhaCungCap::class);
    }

    public function phieuChi()
    {
        return $this->hasMany(PhieuChi::class);
    }

    public function chiTietPhieuChi()
    {
        return $this->hasMany(ChiTietPhieuChi::class);
    }
}
