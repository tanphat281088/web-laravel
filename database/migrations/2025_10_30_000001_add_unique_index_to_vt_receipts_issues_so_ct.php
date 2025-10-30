<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $receiptTable = 'vt_receipts';
    private string $issueTable   = 'vt_issues';

    private string $receiptIdx   = 'vt_receipts_so_ct_unique';
    private string $issueIdx     = 'vt_issues_so_ct_unique';

    /** Kiểm tra index/unique tồn tại (MySQL/MariaDB) */
    private function indexExists(string $table, string $index): bool
    {
        $sql = "SELECT COUNT(1) AS c
                  FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = ?
                   AND INDEX_NAME   = ?";
        $row = DB::selectOne($sql, [$table, $index]);
        return !empty($row) && (int)($row->c ?? 0) > 0;
    }

    public function up(): void
    {
        // vt_receipts.so_ct UNIQUE
        if (Schema::hasTable($this->receiptTable) && Schema::hasColumn($this->receiptTable, 'so_ct')) {
            if (! $this->indexExists($this->receiptTable, $this->receiptIdx)) {
                Schema::table($this->receiptTable, function (Blueprint $table) {
                    $table->unique('so_ct', $this->receiptIdx);
                });
            }
        }

        // vt_issues.so_ct UNIQUE
        if (Schema::hasTable($this->issueTable) && Schema::hasColumn($this->issueTable, 'so_ct')) {
            if (! $this->indexExists($this->issueTable, $this->issueIdx)) {
                Schema::table($this->issueTable, function (Blueprint $table) {
                    $table->unique('so_ct', $this->issueIdx);
                });
            }
        }
    }

    public function down(): void
    {
        // Drop UNIQUE nếu có (không lỗi nếu không tồn tại)
        if (Schema::hasTable($this->receiptTable) && $this->indexExists($this->receiptTable, $this->receiptIdx)) {
            try {
                Schema::table($this->receiptTable, function (Blueprint $table) {
                    $table->dropUnique($this->receiptIdx);
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE `{$this->receiptTable}` DROP INDEX `{$this->receiptIdx}`");
                } catch (\Throwable $e2) {}
            }
        }

        if (Schema::hasTable($this->issueTable) && $this->indexExists($this->issueTable, $this->issueIdx)) {
            try {
                Schema::table($this->issueTable, function (Blueprint $table) {
                    $table->dropUnique($this->issueIdx);
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE `{$this->issueTable}` DROP INDEX `{$this->issueIdx}`");
                } catch (\Throwable $e2) {}
            }
        }
    }
};
