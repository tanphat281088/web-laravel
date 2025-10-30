<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $table      = 'khach_hang_point_events';
    private string $column     = 'don_hang_id';
    private string $uniqueName = 'uq_point_event_donhang';
    private string $normalIdx  = 'idx_point_event_donhang';

    /** Kiểm tra index/unique có tồn tại không */
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

    /** Kiểm tra cột tồn tại */
    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    public function up(): void
    {
        // Nếu bảng/ cột không tồn tại thì bỏ qua
        if (!Schema::hasTable($this->table) || !$this->columnExists($this->table, $this->column)) {
            return;
        }

        // 1) Chỉ DROP UNIQUE nếu UNIQUE đang tồn tại
        if ($this->indexExists($this->table, $this->uniqueName)) {
            try {
                Schema::table($this->table, function (Blueprint $table) {
                    $table->dropUnique($this->uniqueName);
                });
            } catch (\Throwable $e) {
                // Fallback SQL thô (trong một số môi trường)
                try {
                    DB::statement("ALTER TABLE `{$this->table}` DROP INDEX `{$this->uniqueName}`");
                } catch (\Throwable $e2) {
                    // Không ném lỗi — mục tiêu là "bỏ unique nếu có"
                }
            }
        }

        // 2) Thêm INDEX thường nếu chưa có
        if (!$this->indexExists($this->table, $this->normalIdx)) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->index($this->column, $this->normalIdx);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table) || !$this->columnExists($this->table, $this->column)) {
            return;
        }

        // 1) Bỏ INDEX thường nếu đang có
        if ($this->indexExists($this->table, $this->normalIdx)) {
            try {
                Schema::table($this->table, function (Blueprint $table) {
                    $table->dropIndex($this->normalIdx);
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE `{$this->table}` DROP INDEX `{$this->normalIdx}`");
                } catch (\Throwable $e2) {
                    // bỏ qua
                }
            }
        }

        // 2) Khôi phục UNIQUE (nếu chưa có) — có thể fail nếu dữ liệu hiện tại vi phạm unique
        if (!$this->indexExists($this->table, $this->uniqueName)) {
            try {
                Schema::table($this->table, function (Blueprint $table) {
                    $table->unique($this->column, $this->uniqueName);
                });
            } catch (\Throwable $e) {
                // Nếu đã có >1 event/đơn, UNIQUE sẽ không thêm được — chấp nhận bỏ qua
            }
        }
    }
};
