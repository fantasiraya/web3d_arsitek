<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan kolom gating can_use_drawing ke plans jika belum ada
        if (Schema::hasTable('plans') && ! Schema::hasColumn('plans', 'can_use_drawing')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->boolean('can_use_drawing')->default(false)->after('can_use_rab');
            });

            // Enterprise saja yang mendapat akses drawing
            DB::table('plans')->where('slug', 'enterprise')->update(['can_use_drawing' => true]);
        }

        // 2. Tabel drawing_sheets
        if (! Schema::hasTable('drawing_sheets')) {
            Schema::create('drawing_sheets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->string('type', 20)->default('plan'); // plan, elevation, section
                $table->string('scale', 20)->default('1:100');
                $table->string('paper_size', 20)->default('A3');
                $table->string('orientation', 20)->default('landscape');
                $table->unsignedInteger('grid_spacing')->default(20);
                $table->string('unit', 10)->default('m');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['project_id', 'sort_order']);
                $table->index('user_id');
            });
        }

        // 3. Tabel drawing_elements
        if (! Schema::hasTable('drawing_elements')) {
            Schema::create('drawing_elements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('drawing_sheet_id')->constrained('drawing_sheets')->cascadeOnDelete();
                $table->string('type', 30); // wall, door, window, stair, column, dimension, text, line, rect, room_label
                $table->json('data'); // Payload Konva.js (koordinat, styling, layer, dll)
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['drawing_sheet_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_elements');
        Schema::dropIfExists('drawing_sheets');

        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'can_use_drawing')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('can_use_drawing');
            });
        }
    }
};

