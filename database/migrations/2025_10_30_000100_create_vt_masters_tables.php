<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('vt_categories')) {
            Schema::create('vt_categories', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->nullable()->unique();
                $table->string('name', 191)->unique();
                $table->tinyInteger('active')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vt_groups')) {
            Schema::create('vt_groups', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->nullable()->unique();
                $table->string('name', 191)->unique();
                $table->tinyInteger('active')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vt_units')) {
            Schema::create('vt_units', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->nullable()->unique();
                $table->string('name', 191)->unique();
                $table->tinyInteger('active')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vt_units');
        Schema::dropIfExists('vt_groups');
        Schema::dropIfExists('vt_categories');
    }
};
