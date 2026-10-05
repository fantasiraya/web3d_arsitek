<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_plan_overrides', function (Blueprint $table) {
            // Custom file size limit per user (MB). Null = gunakan default plan.
            $table->unsignedInteger('custom_file_size_mb')->nullable()->after('custom_project_limit');
        });
    }

    public function down(): void
    {
        Schema::table('user_plan_overrides', function (Blueprint $table) {
            $table->dropColumn('custom_file_size_mb');
        });
    }
};
