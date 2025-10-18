<?php

namespace App\Providers;

use App\Console\Commands\MakeModuleCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    //
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    // Thiết lập múi giờ mặc định là Việt Nam
    date_default_timezone_set('Asia/Ho_Chi_Minh');

    // Thiết lập định dạng thời gian mặc định cho Carbon
    Carbon::setToStringFormat('Y-m-d H:i:s');

    if ($this->app->runningInConsole()) {
      $this->commands([
        MakeModuleCommand::class,
      ]);
    }

    Schema::defaultStringLength(191);
  }
}