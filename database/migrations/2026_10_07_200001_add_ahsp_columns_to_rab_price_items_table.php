<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rab_price_items', function (Blueprint $table) {
            // Overhead & Profit level analisa (terpisah dari overhead RAB dokumen)
            $table->decimal('overhead_percent', 5, 2)->default(0)->after('category');
            // Flag: apakah unit_price dihitung dari komponen atau diisi manual
            $table->boolean('has_components')->default(false)->after('overhead_percent');
        });
    }

    public function down(): void
    {
        Schema::table('rab_price_items', function (Blueprint $table) {
            $table->dropColumn(['overhead_percent', 'has_components']);
        });
    }
};
