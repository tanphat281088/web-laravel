<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NhaCungCap extends Model
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

  public function sanPhams(): BelongsToMany
  {
    return $this->belongsToMany(SanPham::class, 'nha_cung_cap_san_phams', 'nha_cung_cap_id', 'san_pham_id')->withTimestamps();
  }

  public function nhaCungCapSanPhams(): HasMany
  {
    return $this->hasMany(NhaCungCapSanPham::class);
  }


  // Kết nối sẵn với bảng images để lưu ảnh
  public function images()
  {
    return $this->morphMany(Image::class, 'imageable');
  }
}