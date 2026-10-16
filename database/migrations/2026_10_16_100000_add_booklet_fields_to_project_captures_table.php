<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_captures', function (Blueprint $table) {
            $table->string('ai_rendered_path')->nullable()->after('file_path');
            $table->foreignUuid('drawing_sheet_id')->nullable()->after('project_id')->constrained('drawing_sheets')->nullOnDelete();
            $table->uuid('room_element_id')->nullable()->after('drawing_sheet_id');
            $table->text('description')->nullable()->after('prompt');
            $table->unsignedInteger('sort_order')->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('project_captures', function (Blueprint $table) {
            $table->dropForeign(['drawing_sheet_id']);
            $table->dropColumn([
                'ai_rendered_path',
                'drawing_sheet_id',
                'room_element_id',
                'description',
                'sort_order',
            ]);
        });
    }
};

