<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserNameResolver;
use App\Traits\UserTrackable;
use Illuminate\Database\Eloquent\Model;

class CauHinhChung extends Model
{
  use UserTrackable, UserNameResolver, DateTimeFormatter;

  protected $guarded = [];

  /**
   * Lấy tất cả cấu hình dưới dạng mảng key-value
   * 
   * @return array
   */
  public static function getAllConfig()
  {
    $configs = self::all();
    $data = [];

    foreach ($configs as $item) {
      $data[$item->ten_cau_hinh] = $item->gia_tri;
    }

    return $data;
  }
}