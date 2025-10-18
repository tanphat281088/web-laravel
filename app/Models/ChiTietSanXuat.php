<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ChiTietSanXuat extends Model
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

  /**
   * Tính số lượng nguyên liệu còn lại có thể xuất kho cho sản xuất.
   *
   * @return \Illuminate\Database\Eloquent\Casts\Attribute
   */
  protected function soLuongConLaiXuatKho(): Attribute
  {
    return Attribute::make(
      get: fn() => $this->so_luong_thuc_te - $this->so_luong_xuat_kho,
    );
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
}