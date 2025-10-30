<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tên index/unique theo bạn đang dùng
    private string $uniqueName = 'uq_point_event_donhang';
    private string $normalIdx  = 'idx_point_event_donhang';

    /** Kiểm tra index/unique có tồn tại chưa (MySQL/MariaDB) */
    private function indexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $db   = $conn->getDatabaseName();
        $sql  = "SELECT COUNT(1) AS c
                   FROM INFORMATION_SCHEMA.STATISTICS
                  WHERE TABLE_SCHEMA = ?
                    AND TABLE_NAME   = ?
                    AND INDEX_NAME   = ?";
        $row = $conn->selectOne($sql, [$db, $table, $index]);
        return !empty($row) && (int)($row->c ?? 0) > 0;
    }

    /** Kiểm tra cột có tồn tại không */
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    /** Tìm tên FK đang ràng buộc don_hang_id → don_hangs(id) (nếu có) */
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
        // An toàn: chỉ thao tác khi có cột don_hang_id
        if (! $this->columnExists('khach_hang_point_events', 'don_hang_id')) {
            // Không có cột thì coi như không cần migrate gì
            return;
        }

        // 1) Đảm bảo có INDEX thường cho don_hang_id (nếu chưa có)
        if (! $this->indexExists('khach_hang_point_events', $this->normalIdx)) {
            Schema::table('khach_hang_point_events', function (Blueprint $table) {
                $table->index('don_hang_id', $this->normalIdx);
            });
        }

        // 2) Drop FK tạm thời nếu có (để thao tác UNIQUE/INDEX không bị cản trở)
        $fk = $this->detectFkName();
        if ($fk) {
            try {
                DB::statement("ALTER TABLE `khach_hang_point_events` DROP FOREIGN KEY `{$fk}`");
            } catch (\Throwable $e) {
                // Bỏ qua nếu không drop được (không critical)
            }
        }

        // 3) Drop UNIQUE cũ nếu đang tồn tại
        if ($this->indexExists('khach_hang_point_events', $this->uniqueName)) {
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
        }

        // 4) Add FK trở lại (ON UPDATE CASCADE, ON DELETE SET NULL — chỉnh nếu schema bạn yêu cầu khác)
        if ($fk) {
            try {
                DB::statement("
                    ALTER TABLE `khach_hang_point_events`
                    ADD CONSTRAINT `{$fk}`
                    FOREIGN KEY (`don_hang_id`) REFERENCES `don_hangs`(`id`)
                    ON UPDATE CASCADE ON DELETE SET NULL
                ");
            } catch (\Throwable $e) {
                // Bỏ qua nếu không add được; FK có thể đã tồn tại lại
            }
        }
    }

    public function down(): void
    {
        // An toàn: chỉ thao tác khi có cột don_hang_id
        if (! $this->columnExists('khach_hang_point_events', 'don_hang_id')) {
            return;
        }

        // 1) Drop FK tạm thời nếu có
        $fk = $this->detectFkName();
        if ($fk) {
            try {
                DB::statement("ALTER TABLE `khach_hang_point_events` DROP FOREIGN KEY `{$fk}`");
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 2) Thêm UNIQUE trở lại (nếu dữ liệu hiện tại cho phép)
        if (! $this->indexExists('khach_hang_point_events', $this->uniqueName)) {
            try {
                Schema::table('khach_hang_point_events', function (Blueprint $table) {
                    $table->unique('don_hang_id', $this->uniqueName);
                });
            } catch (\Throwable $e) {
                // Nếu đã có >1 event/đơn, UNIQUE sẽ không thêm được — giữ nguyên
            }
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
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // 4) (Tuỳ chọn) Bỏ INDEX thường — KHÔNG bắt buộc:
        // if ($this->indexExists('khach_hang_point_events', $this->normalIdx)) {
        //     Schema::table('khach_hang_point_events', fn (Blueprint $t) => $t->dropIndex($this->normalIdx));
        // }
    }
};
