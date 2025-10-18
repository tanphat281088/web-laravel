<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait UserNameResolver
{
  /**
   * Boot the trait.
   */
  protected static function bootUserNameResolver()
  {
    static::addGlobalScope('withUserNames', function (Builder $builder) {
      $builder->addSelect([
        'ten_nguoi_tao' => User::select('name')
          ->whereColumn('id', 'nguoi_tao')
          ->limit(1),
        'ten_nguoi_cap_nhat' => User::select('name')
          ->whereColumn('id', 'nguoi_cap_nhat')
          ->limit(1)
      ]);
    });
  }
}