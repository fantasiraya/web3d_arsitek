<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen Analisa Harga Satuan Pekerjaan (AHSP).
 *
 * Setiap baris = satu sub-item komponen:
 *   tenaga   : Pekerja, Tukang Batu, Kepala Tukang, Mandor
 *   bahan    : Batu belah, Semen, Pasir, dll.
 *   peralatan: Molen, Stamper, dll.
 *
 * unit_price_analisa = unit_price × coefficient  (= "Jumlah Harga" per baris)
 * Total semua komponen + overhead% = unit_price di rab_price_items
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_price_item_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rab_price_item_id')->constrained('rab_price_items')->cascadeOnDelete();

            // Jenis komponen: tenaga, bahan, peralatan
            $table->string('component_type', 20)->default('bahan');

            // Nama komponen (contoh: "Pekerja", "Batu Belah", "Molen")
            $table->string('name');

            // Kode referensi opsional (SNI atau kode internal)
            $table->string('code', 50)->nullable();

            // Satuan (OH, m³, kg, jam, dll.)
            $table->string('unit', 20);

            // Koefisien analisa (contoh: 1.5 OH Pekerja per m³ pondasi)
            $table->decimal('coefficient', 10, 4)->default(0);

            // Harga satuan komponen (contoh: Rp 65.000/OH)
            $table->decimal('unit_price', 14, 2)->default(0);

            // Hasil = coefficient × unit_price (disimpan, dihitung ulang saat save)
            $table->decimal('amount', 16, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['rab_price_item_id', 'component_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_price_item_components');
    }
};
