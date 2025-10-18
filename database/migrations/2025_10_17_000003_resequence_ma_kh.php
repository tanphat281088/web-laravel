<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Resequence theo created_at ASC (nếu null thì xếp cuối bằng id ASC)
        DB::statement('SET @r := 0');
        DB::statement("
            UPDATE khach_hangs k
            JOIN (
                SELECT id, (@r := @r + 1) AS rn
                FROM khach_hangs
                ORDER BY 
                    CASE WHEN created_at IS NULL THEN 1 ELSE 0 END ASC,
                    created_at ASC,
                    id ASC
            ) t ON t.id = k.id
            SET k.ma_kh = CONCAT('KH', LPAD(t.rn, 5, '0'))
        ");
    }

    public function down(): void
    {
        // Không rollback (giữ mã hiện tại). Nếu cần có thể đặt về NULL:
        // DB::statement('UPDATE khach_hangs SET ma_kh = NULL');
    }
};
