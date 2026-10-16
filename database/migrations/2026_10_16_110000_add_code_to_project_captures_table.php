<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_captures', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('id')->index(); // 'R-01', 'R-02', 'EXT-01', dll.
        });
    }

    public function down(): void
    {
        Schema::table('project_captures', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->dropColumn('code');
        });
    }
};

