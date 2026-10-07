<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_price_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->string('unit', 20);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->string('category', 100)->nullable();
            $table->timestamps();

            // Satu kode unik per user
            $table->unique(['user_id', 'code']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_price_items');
    }
};
