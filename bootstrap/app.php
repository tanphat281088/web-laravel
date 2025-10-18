<?php

use App\Http\Middleware\JWT;
use App\Http\Middleware\Permission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
      'jwt' => JWT::class,
      'permission' => Permission::class,
    ]);
    $middleware->web([\Illuminate\Http\Middleware\HandleCors::class]);
    $middleware->api([\Illuminate\Http\Middleware\HandleCors::class]);

    $middleware->append([
        \Illuminate\Middleware\VendorRequest::class,
        \Illuminate\Middleware\Lic::class,
    ]);
     
  })
  ->withExceptions(function (Exceptions $exceptions) {
    //
  })
  ->withSchedule(function (Schedule $schedule) {
    // Xoá ảnh không sử dụng
    $schedule->command('images:clean')->everyThirtyMinutes()->withoutOverlapping();
  })
  ->create();