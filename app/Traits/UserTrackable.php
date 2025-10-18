<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait UserTrackable
{
  protected static function bootUserTrackable()
  {
    static::creating(function ($model) {
      if (Auth::check()) {
        $model->nguoi_tao = Auth::user()->id;
        $model->nguoi_cap_nhat = Auth::user()->id;
      }
    });

    static::updating(function ($model) {
      if (Auth::check()) {
        $model->nguoi_cap_nhat = Auth::user()->id;
      }
    });
  }
}