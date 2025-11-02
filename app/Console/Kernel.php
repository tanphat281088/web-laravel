<?php

namespace App\Console;

use App\Jobs\ScanUpcomingDeliveries;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Đăng ký lịch chạy (Scheduler).
     */
    protected function schedule(Schedule $schedule): void
    {
        // Nhắc trước 60' — quét mỗi phút (có tolerance 5')
        $schedule->job(new ScanUpcomingDeliveries(60, 5))->everyMinute();

        // Nếu muốn nhẹ hệ thống hơn, có thể dùng mỗi 5 phút:
        // $schedule->job(new ScanUpcomingDeliveries(60, 5))->everyFiveMinutes();

            // === Zalo: kiểm tra & làm mới access token (worker24h) ===
       // === Zalo: kiểm tra & làm mới access token (worker24h) ===
    $schedule->job(new \App\Jobs\Zl\RefreshZlTokenJob())->everyFiveMinutes();

    }

    /**
     * Đăng ký commands & route console (chuẩn Laravel).
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
