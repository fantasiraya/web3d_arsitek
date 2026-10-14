<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->default(-6.2088000)->after('current_revision_count'); // Default Jakarta
            $table->decimal('longitude', 10, 7)->default(106.8456000)->after('latitude');
            $table->float('north_angle')->default(0.0)->after('longitude'); // 0-360 derajat rotasi utara
            $table->date('solar_date')->nullable()->after('north_angle'); // default hari ini jika null
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'north_angle', 'solar_date']);
        });
    }
};

