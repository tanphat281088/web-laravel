<?php

namespace App\Jobs;

use App\Models\DonHang;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ScanUpcomingDeliveries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Khoảng phút nhắc trước (default 60).
     * Có thể đọc từ env/config nếu muốn cấu hình động.
     */
    protected int $remindMinutes;

    /**
     * Biên độ phút để chống lệch cron (default 5 phút).
     * Ví dụ cron mỗi 1-5 phút, ta quét [T+60, T+65].
     */
    protected int $toleranceMinutes;

    public function __construct(int $remindMinutes = 60, int $toleranceMinutes = 5)
    {
        $this->remindMinutes    = $remindMinutes;
        $this->toleranceMinutes = $toleranceMinutes;
        // tùy chọn: $this->onQueue('scheduling'); // nếu muốn đưa vào queue riêng
    }

    public function handle(): void
    {
        $now = Carbon::now();

        $from = $now->copy()->addMinutes($this->remindMinutes);
        $to   = $now->copy()->addMinutes($this->remindMinutes + $this->toleranceMinutes);

        // Chỉ nhắc đơn Chưa giao (0); bỏ Đã giao/Đã hủy
        $base = DonHang::query()
            ->where('trang_thai_don_hang', DonHang::TRANG_THAI_CHUA_GIAO)
            ->whereNotNull('nguoi_nhan_thoi_gian')
            ->whereBetween('nguoi_nhan_thoi_gian', [$from, $to])
            ->select([
                'id',
                'ma_don_hang',
                'ten_khach_hang',
                'dia_chi_giao_hang',
                'nguoi_nhan_ten',
                'nguoi_nhan_sdt',
                'nguoi_nhan_thoi_gian',
            ])
            ->orderBy('id');

        $base->chunkById(200, function ($orders) use ($from) {
            foreach ($orders as $order) {
                $this->createReminderIfNotExists($order->id, $from);
            }
        });
    }

    /**
     * Tạo bản ghi nhắc nếu chưa có (idempotent).
     * - type = '60min' theo remindMinutes
     * - channels = ["inapp"] (tùy chọn; sau này có thể thêm "push","tts")
     */
    protected function createReminderIfNotExists(int $donHangId, Carbon $scheduledAt): void
    {
        $type = $this->remindMinutes . 'min';

        try {
            DB::table('delivery_reminders')->insert([
                'don_hang_id'  => $donHangId,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'type'         => $type,
                'channels'     => json_encode(['inapp']), // có thể cập nhật sau khi gửi push/tts
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // TODO (tuỳ chọn): Broadcast event để FE hiện banner ngay:
            // event(new \App\Events\DeliveryReminderCreated($donHangId, $scheduledAt, $type));
        } catch (QueryException $e) {
            // Nếu vi phạm unique (đã tồn tại) thì bỏ qua
            // Tên unique key đã tạo trong migration: uniq_reminder
            if (! str_contains(strtolower($e->getMessage()), 'uniq_reminder')) {
                // Log những lỗi khác để theo dõi
                report($e);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
