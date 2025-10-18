<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class SanXuat extends Model
{
  //

  use UserTrackable, UserNameResolver, DateTimeFormatter;

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

  public function sanPham()
  {
    return $this->belongsTo(SanPham::class);
  }

  public function donViTinh()
  {
    return $this->belongsTo(DonViTinh::class);
  }

  public function chiTietSanXuat()
  {
    return $this->hasMany(ChiTietSanXuat::class);
  }
}