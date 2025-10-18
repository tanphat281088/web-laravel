<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhieuXuatKho extends Model
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

  // Quan hệ với đơn hàng
  public function donHang()
  {
    return $this->belongsTo(DonHang::class, 'don_hang_id');
  }

  public function sanXuat()
  {
    return $this->belongsTo(SanXuat::class, 'san_xuat_id');
  }

  // Quan hệ với chi tiết phiếu xuất kho
  public function chiTietPhieuXuatKhos(): HasMany
  {
    return $this->hasMany(ChiTietPhieuXuatKho::class);
  }
}