<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rab_document_id')->constrained('rab_documents')->cascadeOnDelete();
            $table->foreignUuid('rab_price_item_id')->nullable()->constrained('rab_price_items')->nullOnDelete();
            $table->string('section')->default('Umum');
            $table->string('description');
            $table->string('unit', 20);
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('waste_percent', 5, 2)->default(0);
            $table->decimal('unit_price', 14, 2)->default(0); // snapshot harga saat item dibuat
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->string('source_ref')->nullable(); // nama objek .glb / nomor baris CSV
            $table->boolean('is_mapped')->default(true);
            $table->boolean('is_estimate')->default(false); // true untuk jalur .glb
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('rab_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_items');
    }
};
