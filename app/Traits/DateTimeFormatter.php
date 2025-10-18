<?php

namespace App\Traits;

use Carbon\Carbon;

trait DateTimeFormatter
{
  /**
   * Hàm này sẽ tự động chạy khi model được lấy từ database
   */
  public function getCreatedAtAttribute($value)
  {
    if ($value) {
      return Carbon::parse($value)->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s');
    }
    return $value;
  }

  /**
   * Hàm này sẽ tự động chạy khi model được lấy từ database
   */
  public function getUpdatedAtAttribute($value)
  {
    if ($value) {
      return Carbon::parse($value)->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s');
    }
    return $value;
  }
}