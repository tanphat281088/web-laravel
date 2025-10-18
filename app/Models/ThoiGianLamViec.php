<?php

namespace App\Models;

use App\Traits\DateTimeFormatter;
use App\Traits\UserTrackable;
use App\Traits\UserNameResolver;
use Illuminate\Database\Eloquent\Model;

class ThoiGianLamViec extends Model
{
  //

  use UserTrackable, UserNameResolver, DateTimeFormatter;

  protected $guarded = [];
}