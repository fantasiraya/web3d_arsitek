<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rab_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('project_version_id')->nullable()->constrained('project_versions')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('rab_template_id')->nullable()->constrained('rab_templates')->nullOnDelete();
            $table->string('title');
            $table->string('source', 10)->default('manual'); // manual, csv, glb
            $table->string('status', 10)->default('draft');  // draft, final
            $table->boolean('is_visible_to_clients')->default(false);
            $table->decimal('overhead_percent', 5, 2)->default(0);
            $table->decimal('ppn_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('overhead_amount', 16, 2)->default(0);
            $table->decimal('ppn_amount', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rab_documents');
    }
};
