<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('luong_profiles', function (Blueprint $table) {
            // ===== Core payroll profile =====
            if (!Schema::hasColumn('luong_profiles', 'salary_mode')) {
                // 'khoan' = lương khoán (không theo công), 'cham_cong' = lương theo công
                $table->enum('salary_mode', ['khoan','cham_cong'])
                      ->default('cham_cong')
                      ->after('he_so');
            }

            if (!Schema::hasColumn('luong_profiles', 'cong_chuan_override')) {
                // Công chuẩn áp dụng cho tháng (nếu null sẽ dùng cong_chuan mặc định)
                $table->integer('cong_chuan_override')->nullable()->after('cong_chuan');
            }

            // ===== Allowances (đồng/tháng hoặc đồng/ngày) =====
            if (!Schema::hasColumn('luong_profiles', 'support_allowance')) {
                $table->integer('support_allowance')->default(0)->after('phu_cap_mac_dinh');
            }
            if (!Schema::hasColumn('luong_profiles', 'phone_allowance')) {
                $table->integer('phone_allowance')->default(0)->after('support_allowance');
            }
            if (!Schema::hasColumn('luong_profiles', 'meal_per_day')) {
                $table->integer('meal_per_day')->default(0)->after('phone_allowance'); // đ/ngày
            }
            if (!Schema::hasColumn('luong_profiles', 'meal_extra_default')) {
                $table->integer('meal_extra_default')->default(0)->after('meal_per_day'); // đ/tháng
            }

            // ===== Insurance config =====
            if (!Schema::hasColumn('luong_profiles', 'apply_insurance')) {
                $table->boolean('apply_insurance')->default(true)->after('meal_extra_default');
            }
            if (!Schema::hasColumn('luong_profiles', 'insurance_base_mode')) {
                // 'prorate' (khuyên dùng) | 'base' (theo base cả tháng) | 'none' (không trừ BH)
                $table->enum('insurance_base_mode', ['base','prorate','none'])
                      ->default('prorate')
                      ->after('apply_insurance');
            }

            // Tỷ lệ BH (nếu bảng của bạn chưa có các cột này)
            if (!Schema::hasColumn('luong_profiles', 'pt_bhxh')) {
                $table->decimal('pt_bhxh', 5, 2)->default(8.00);
            }
            if (!Schema::hasColumn('luong_profiles', 'pt_bhyt')) {
                $table->decimal('pt_bhyt', 5, 2)->default(1.50);
            }
            if (!Schema::hasColumn('luong_profiles', 'pt_bhtn')) {
                $table->decimal('pt_bhtn', 5, 2)->default(1.00);
            }
        });
    }

    public function down(): void
    {
        Schema::table('luong_profiles', function (Blueprint $table) {
            // Tuỳ nhu cầu: có thể giữ lại cột; dưới đây là drop an toàn
            if (Schema::hasColumn('luong_profiles', 'salary_mode'))          $table->dropColumn('salary_mode');
            if (Schema::hasColumn('luong_profiles', 'cong_chuan_override'))  $table->dropColumn('cong_chuan_override');
            if (Schema::hasColumn('luong_profiles', 'support_allowance'))    $table->dropColumn('support_allowance');
            if (Schema::hasColumn('luong_profiles', 'phone_allowance'))      $table->dropColumn('phone_allowance');
            if (Schema::hasColumn('luong_profiles', 'meal_per_day'))         $table->dropColumn('meal_per_day');
            if (Schema::hasColumn('luong_profiles', 'meal_extra_default'))   $table->dropColumn('meal_extra_default');
            if (Schema::hasColumn('luong_profiles', 'apply_insurance'))      $table->dropColumn('apply_insurance');
            if (Schema::hasColumn('luong_profiles', 'insurance_base_mode'))  $table->dropColumn('insurance_base_mode');
            // 3 cột %BH chỉ drop nếu bạn thực sự muốn rollback hoàn toàn
            // if (Schema::hasColumn('luong_profiles', 'pt_bhxh')) $table->dropColumn('pt_bhxh');
            // if (Schema::hasColumn('luong_profiles', 'pt_bhyt')) $table->dropColumn('pt_bhyt');
            // if (Schema::hasColumn('luong_profiles', 'pt_bhtn')) $table->dropColumn('pt_bhtn');
        });
    }
};
