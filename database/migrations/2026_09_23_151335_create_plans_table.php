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
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('project_limit')->nullable(); // null = unlimited
            $table->boolean('can_create_project')->default(true);
            $table->boolean('can_edit_project')->default(true);
            $table->boolean('can_delete_project')->default(true);
            $table->boolean('can_export')->default(false);
            $table->integer('max_team_members')->default(1);
            $table->boolean('api_access')->default(false);
            $table->boolean('audit_log')->default(false);
            $table->boolean('advanced_analytics')->default(false);
            $table->boolean('sso')->default(false);
            $table->boolean('custom_branding')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->string('status', 20)->default('active'); // active, inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
