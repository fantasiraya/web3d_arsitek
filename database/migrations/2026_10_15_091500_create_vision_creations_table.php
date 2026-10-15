<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vision_creations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('room_name')->default('Arsitektur');
            $table->string('prompt_type', 20)->default('image'); // 'image' | 'video'
            $table->string('file_path');
            $table->string('end_file_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->unsignedInteger('width')->default(1920);
            $table->unsignedInteger('height')->default(1080);
            $table->longText('prompt')->nullable();
            $table->text('negative_prompt')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vision_creations');
    }
};

