<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('rab_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rab_template_id')->constrained('rab_templates')->cascadeOnDelete();
            $table->foreignUuid('rab_price_item_id')->constrained('rab_price_items')->cascadeOnDelete();
            $table->string('section')->default('Umum');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('rab_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_template_items');
        Schema::dropIfExists('rab_templates');
    }
};
