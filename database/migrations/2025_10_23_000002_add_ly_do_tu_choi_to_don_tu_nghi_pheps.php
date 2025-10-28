<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('don_tu_nghi_pheps') && !Schema::hasColumn('don_tu_nghi_pheps', 'ly_do_tu_choi')) {
            Schema::table('don_tu_nghi_pheps', function (Blueprint $table) {
                $table->text('ly_do_tu_choi')->nullable()->after('ly_do');
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('don_tu_nghi_pheps') && Schema::hasColumn('don_tu_nghi_pheps', 'ly_do_tu_choi')) {
            Schema::table('don_tu_nghi_pheps', function (Blueprint $table) {
                $table->dropColumn('ly_do_tu_choi');
            });
        }
    }
};
