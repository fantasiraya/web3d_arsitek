<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drawing_sheets') && ! Schema::hasColumn('drawing_sheets', 'image_path')) {
            Schema::table('drawing_sheets', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('unit');
            });
        }

        if (Schema::hasTable('project_captures') && ! Schema::hasColumn('project_captures', 'key_plan_image_path')) {
            Schema::table('project_captures', function (Blueprint $table) {
                $table->string('key_plan_image_path')->nullable()->after('depth_map_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('drawing_sheets') && Schema::hasColumn('drawing_sheets', 'image_path')) {
            Schema::table('drawing_sheets', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }

        if (Schema::hasTable('project_captures') && Schema::hasColumn('project_captures', 'key_plan_image_path')) {
            Schema::table('project_captures', function (Blueprint $table) {
                $table->dropColumn('key_plan_image_path');
            });
        }
    }
};

