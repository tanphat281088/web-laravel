<?php

namespace App\Providers;

use App\Console\Commands\MakeModuleCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

// Đăng ký Observer cho Đơn hàng
use App\Models\DonHang;
use App\Observers\DonHangObserver;

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

    // Kích hoạt quan sát Đơn hàng -> tự đồng bộ phiếu thu khi tạo/sửa đơn
    DonHang::observe(DonHangObserver::class);
  }
}
