<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('match_type', 20)->default('name_pattern'); // name_pattern, material, exact
            $table->string('pattern');
            $table->foreignUuid('rab_price_item_id')->constrained('rab_price_items')->cascadeOnDelete();
            $table->string('quantity_basis', 20)->default('area'); // area, volume, count, length
            $table->timestamps();

            $table->unique(['user_id', 'match_type', 'pattern']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_mappings');
    }
};
