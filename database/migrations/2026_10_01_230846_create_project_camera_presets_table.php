<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_camera_presets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('created_by'); // arsitek yang menyimpan

            $table->string('name', 80);          // "Eksterior Utama", "Kamar Tidur", dll

            // Posisi kamera
            $table->float('position_x');
            $table->float('position_y');
            $table->float('position_z');

            // Target OrbitControls (titik yang dilihat kamera)
            $table->float('target_x');
            $table->float('target_y');
            $table->float('target_z');

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('project_id')
                  ->references('id')->on('projects')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->cascadeOnDelete();

            // Maks 8 preset per project
            $table->unique(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_camera_presets');
    }
};
