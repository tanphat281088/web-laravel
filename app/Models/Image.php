<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\ImageUpload;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
  use UserTrackable, UserNameResolver, DateTimeFormatter, ImageUpload;

  protected $guarded = [];

  protected $visible = ['id', 'path'];

  // Boot model để đăng ký các events
  protected static function boot()
  {
    parent::boot();

    // Event trước khi update - xóa ảnh cũ
    static::updating(function ($image) {
      if ($image->isDirty('path') && $image->getOriginal('path')) {
        $oldPath = str_replace(env('APP_URL') . '/', '', $image->getOriginal('path'));
        $image->deleteImage($oldPath);
      }
    });

    // Event trước khi delete - xóa ảnh
    static::deleting(function ($image) {
      if ($image->path) {
        $path = str_replace(env('APP_URL') . '/', '', $image->path);
        $image->deleteImage($path);
      }
    });
  }

  public function imageable()
  {
    return $this->morphTo();
  }
}