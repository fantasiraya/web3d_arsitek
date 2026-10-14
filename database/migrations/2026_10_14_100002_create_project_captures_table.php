<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_captures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete(); // arsitek yang mengambil
            $table->string('title')->nullable();
            $table->string('room_name')->default('Sudut Bebas'); // "Ruang Tamu", "Kamar Tidur", "Fasad Depan", dll
            $table->string('captured_hour', 10)->default('12:00'); // "08:00", "12:00", "17:00", "20:00", dll
            $table->string('file_path');
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->unsignedInteger('width')->default(1920);
            $table->unsignedInteger('height')->default(1080);
            $table->boolean('is_visible_to_clients')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_captures');
    }
};

