<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for million-user scale.
 *
 * Each index targets a specific hot query path identified in the audit:
 *
 * projects:
 *   - (user_id, created_at)      → Dashboard "owned projects" ORDER BY latest()
 *
 * project_clients:
 *   - (project_id, user_id, status) → ProjectClientAccessMiddleware EXISTS check (most critical)
 *   - (email, user_id)              → Auto-match invitation + client project list
 *
 * comments:
 *   - (project_id, parent_id)    → RevisionLimit COUNT, ViewerController sync, PinCommentController
 *
 * transactions:
 *   - (user_id, created_at)      → Dashboard transaction history ORDER BY latest()
 *
 * users:
 *   - (subscription_status)      → Admin user filter by plan
 *   - (last_active_at)           → Admin "recently active" sorting (optional but useful)
 *
 * model_has_roles:
 *   - (model_id, role_id)        → super_admin check (Spatie default, but explicit here)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── projects ──────────────────────────────────────────────────────────
        Schema::table('projects', function (Blueprint $table) {
            // Dashboard: WHERE user_id = ? ORDER BY created_at DESC
            if (! $this->indexExists('projects', 'projects_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at'], 'projects_user_id_created_at_index');
            }
        });

        // ── project_clients ───────────────────────────────────────────────────
        Schema::table('project_clients', function (Blueprint $table) {
            // Middleware: WHERE project_id = ? AND user_id = ? AND status = 'accepted'
            if (! $this->indexExists('project_clients', 'pc_project_user_status_index')) {
                $table->index(['project_id', 'user_id', 'status'], 'pc_project_user_status_index');
            }

            // Dashboard auto-match: WHERE email = ? AND user_id IS NULL
            // + client project list: WHERE user_id = ? OR email = ?
            if (! $this->indexExists('project_clients', 'pc_email_user_id_index')) {
                $table->index(['email', 'user_id'], 'pc_email_user_id_index');
            }
        });

        // ── comments ─────────────────────────────────────────────────────────
        Schema::table('comments', function (Blueprint $table) {
            // COUNT: WHERE project_id = ? AND parent_id IS NULL
            // Covers RevisionLimitEnforcementMiddleware, ViewerController, PinCommentController
            if (! $this->indexExists('comments', 'comments_project_id_parent_id_index')) {
                $table->index(['project_id', 'parent_id'], 'comments_project_id_parent_id_index');
            }
        });

        // ── transactions ──────────────────────────────────────────────────────
        Schema::table('transactions', function (Blueprint $table) {
            // Dashboard: WHERE user_id = ? ORDER BY created_at DESC
            if (! $this->indexExists('transactions', 'transactions_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at'], 'transactions_user_id_created_at_index');
            }
        });

        // ── users ─────────────────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            // Admin filter: WHERE subscription_status = ?
            if (! $this->indexExists('users', 'users_subscription_status_index')) {
                $table->index('subscription_status', 'users_subscription_status_index');
            }

            // Admin sort by last activity
            if (! $this->indexExists('users', 'users_last_active_at_index')) {
                $table->index('last_active_at', 'users_last_active_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_user_id_created_at_index');
        });

        Schema::table('project_clients', function (Blueprint $table) {
            $table->dropIndex('pc_project_user_status_index');
            $table->dropIndex('pc_email_user_id_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_project_id_parent_id_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_id_created_at_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_subscription_status_index');
            $table->dropIndex('users_last_active_at_index');
        });
    }

    /**
     * Check if an index already exists to make the migration idempotent.
     */
    protected function indexExists(string $table, string $indexName): bool
    {
        return collect(\DB::select("SHOW INDEX FROM `{$table}`"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
