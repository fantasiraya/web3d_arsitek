<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->decimal('position_x', 10, 6);
            $table->decimal('position_y', 10, 6);
            $table->decimal('position_z', 10, 6);
            $table->decimal('normal_x', 10, 6)->nullable();
            $table->decimal('normal_y', 10, 6)->nullable();
            $table->decimal('normal_z', 10, 6)->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
