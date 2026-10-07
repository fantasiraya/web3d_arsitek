<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('can_use_rab')->default(false)->after('priority_support');
        });

        // Pro & Enterprise dapat akses RAB
        DB::table('plans')->whereIn('slug', ['pro', 'enterprise'])->update(['can_use_rab' => true]);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('can_use_rab');
        });
    }
};
