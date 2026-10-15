<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drawing_sheets')) {
            Schema::table('drawing_sheets', function (Blueprint $table) {
                if (! Schema::hasColumn('drawing_sheets', 'width')) {
                    $table->unsignedInteger('width')->nullable()->after('orientation');
                }
                if (! Schema::hasColumn('drawing_sheets', 'height')) {
                    $table->unsignedInteger('height')->nullable()->after('width');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('drawing_sheets')) {
            Schema::table('drawing_sheets', function (Blueprint $table) {
                if (Schema::hasColumn('drawing_sheets', 'height')) {
                    $table->dropColumn('height');
                }
                if (Schema::hasColumn('drawing_sheets', 'width')) {
                    $table->dropColumn('width');
                }
            });
        }
    }
};

