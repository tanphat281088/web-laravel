<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            if (!Schema::hasColumn('khach_hangs', 'ma_kh')) {
                // nullable để backfill an toàn -> sau đó sẽ resequence
                $table->string('ma_kh', 20)->nullable()->unique()->after('id');
            }
        });

        // Backfill tạm theo id (để không null). Ở migration kế sẽ resequence đẹp KH00001…
        DB::table('khach_hangs')
            ->select('id', 'ma_kh')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (empty($row->ma_kh)) {
                        $code = 'KH' . str_pad((string) $row->id, 5, '0');
                        DB::table('khach_hangs')
                            ->where('id', $row->id)
                            ->update(['ma_kh' => $code]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('khach_hangs', function (Blueprint $table) {
            if (Schema::hasColumn('khach_hangs', 'ma_kh')) {
                // Tên index mặc định của unique là: {table}_{column}_unique
                $table->dropUnique('khach_hangs_ma_kh_unique');
                $table->dropColumn('ma_kh');
            }
        });
    }
};
