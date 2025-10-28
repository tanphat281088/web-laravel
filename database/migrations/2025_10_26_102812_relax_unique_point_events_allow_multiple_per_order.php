<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tên index theo ảnh bạn gửi
    private string $uniqueName = 'uq_point_event_donhang';
    private string $normalIdx  = 'idx_point_event_donhang';

    /** Tìm tên FK đang ràng buộc don_hang_id → don_hangs(id) */
    private function detectFkName(): ?string
    {
        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'khach_hang_point_events'
              AND COLUMN_NAME = 'don_hang_id'
              AND REFERENCED_TABLE_NAME = 'don_hangs'
              AND REFERENCED_COLUMN_NAME = 'id'
            LIMIT 1
        ");
        return $rows[0]->CONSTRAINT_NAME ?? null;
    }

    public function up(): void
    {
        // 0) (Khuyến nghị) Bạn đã backup trước khi migrate.

        // 1) Đảm bảo có INDEX thường trước (để FK có index bám sau khi bỏ UNIQUE)
        Schema::table('khach_hang_point_events', function (Blueprint $table) {
            try { $table->index('don_hang_id', $this->normalIdx); } catch (\Throwable $e) {}
        });

        // 2) Drop FK tạm thời nếu có
        if ($fk = $this->detectFkName()) {
            DB::statement("ALTER TABLE `khach_hang_point_events` DROP FOREIGN KEY `{$fk}`");
        }

        // 3) Drop UNIQUE cũ
        try {
            Schema::table('khach_hang_point_events', function (Blueprint $table) {
                $table->dropUnique($this->uniqueName);
            });
        } catch (\Throwable $e) {
            // Fallback SQL thô cho MySQL
            try {
                DB::statement("ALTER TABLE `khach_hang_point_events` DROP INDEX `{$this->uniqueName}`");
            } catch (\Throwable $e2) {
                // Nếu vẫn lỗi, ném lại để fail rõ ràng
                throw $e2;
            }
        }

        // 4) Add FK trở lại (dùng ON UPDATE CASCADE, ON DELETE SET NULL an toàn)
        //    (Chỉnh lại nếu schema của bạn dùng hành vi khác)
        if ($fk) {
            DB::statement("
                ALTER TABLE `khach_hang_point_events`
                ADD CONSTRAINT `{$fk}`
                FOREIGN KEY (`don_hang_id`) REFERENCES `don_hangs`(`id`)
                ON UPDATE CASCADE ON DELETE SET NULL
            ");
        }
    }

    public function down(): void
    {
        // Rollback: cố gắng trả về UNIQUE (chỉ thành công nếu mỗi đơn tối đa 1 event)
        // 1) Drop FK tạm
        if ($fk = $this->detectFkName()) {
            try { DB::statement("ALTER TABLE `khach_hang_point_events` DROP FOREIGN KEY `{$fk}`"); } catch (\Throwable $e) {}
        }

        // 2) Thêm UNIQUE trở lại
        try {
            Schema::table('khach_hang_point_events', function (Blueprint $table) {
                $table->unique('don_hang_id', $this->uniqueName);
            });
        } catch (\Throwable $e) {
            // Nếu đã có >1 event/đơn, UNIQUE sẽ không thêm được — chấp nhận giữ nguyên
        }

        // 3) Thêm lại FK
        if ($fk) {
            try {
                DB::statement("
                    ALTER TABLE `khach_hang_point_events`
                    ADD CONSTRAINT `{$fk}`
                    FOREIGN KEY (`don_hang_id`) REFERENCES `don_hangs`(`id`)
                    ON UPDATE CASCADE ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {}
        }

        // 4) (Tuỳ chọn) Bỏ INDEX thường — KHÔNG bắt buộc:
        // try { Schema::table('khach_hang_point_events', fn (Blueprint $t) => $t->dropIndex($this->normalIdx)); } catch (\Throwable $e) {}
    }
};
