<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $uniqueName = 'uq_point_event_donhang';   // theo ảnh phpMyAdmin của bạn
    private string $normalIdx  = 'idx_point_event_donhang';

    public function up(): void
    {
        Schema::table('khach_hang_point_events', function (Blueprint $table) {
            // Thử drop UNIQUE bằng tên index (ưu tiên cách Laravel)
            try {
                $table->dropUnique($this->uniqueName);
            } catch (\Throwable $e) {
                // Nếu tên khác hoặc driver không nhận, fallback dùng SQL thô
                try {
                    DB::statement('ALTER TABLE khach_hang_point_events DROP INDEX '.$this->uniqueName);
                } catch (\Throwable $e2) {
                    // Bỏ qua nếu UNIQUE đã bị drop trước đó
                }
            }

            // Thêm INDEX thường cho don_hang_id (nếu chưa có)
            try {
                $table->index('don_hang_id', $this->normalIdx);
            } catch (\Throwable $e) {
                // Có thể index đã tồn tại -> bỏ qua
            }
        });
    }

    public function down(): void
    {
        Schema::table('khach_hang_point_events', function (Blueprint $table) {
            // Bỏ INDEX thường (nếu có)
            try {
                $table->dropIndex($this->normalIdx);
            } catch (\Throwable $e) {
                try {
                    DB::statement('ALTER TABLE khach_hang_point_events DROP INDEX '.$this->normalIdx);
                } catch (\Throwable $e2) {
                    // bỏ qua
                }
            }

            // Khôi phục UNIQUE 1-đơn-1-event (rollback)
            try {
                $table->unique('don_hang_id', $this->uniqueName);
            } catch (\Throwable $e) {
                // Nếu dữ liệu hiện có >1 event/đơn, rollback sẽ lỗi (điều này là bình thường).
            }
        });
    }
};
