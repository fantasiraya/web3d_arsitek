<?php

namespace App\Domains\Drawing\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;

/**
 * Kontrol akses dan gating modul Lembar Kerja Drawing 2D.
 *
 * Aturan (AI_INSTRUCTIONS.md Section O):
 *  - Fitur Drawing 2D hanya aktif jika plans.can_use_drawing = true (hanya Enterprise).
 *  - Hanya arsitek pemilik project yang bisa membuat/mengedit/menghapus.
 *  - Klien accepted boleh melihat lembar kerja secara read-only.
 */
class DrawingAccessService
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    /**
     * Apakah paket aktif user memiliki akses fitur Drawing 2D (Enterprise / can_use_drawing)?
     */
    public function hasPlanAccess(User $user): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $plan = $this->limitService->getPlanForUser($user);

        return (bool) ($plan->can_use_drawing ?? false);
    }

    /**
     * Apakah user boleh membuat atau mengedit lembar kerja 2D pada project?
     */
    public function canEdit(User $user, Project $project): bool
    {
        if ($project->user_id !== $user->id) {
            return false;
        }

        return $this->hasPlanAccess($user);
    }

    /**
     * Apakah user boleh melihat lembar kerja ini?
     *  - Pemilik project: selalu boleh
     *  - Klien dengan status accepted: boleh (read-only)
     */
    public function canView(User $user, Project $project, DrawingSheet $sheet): bool
    {
        if ($project->user_id === $user->id) {
            return true;
        }

        return ProjectClient::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('status', ProjectClient::STATUS_ACCEPTED)
            ->exists();
    }

    /**
     * Lempar exception 403 jika user tidak berhak membuat/mengubah drawing sheet.
     */
    public function authorizeEdit(User $user, Project $project): void
    {
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat mengelola lembar kerja 2D.');
        }

        if (! $this->hasPlanAccess($user)) {
            abort(403, 'Fitur Lembar Kerja 2D (Drawing CAD) eksklusif untuk paket Enterprise. Silakan upgrade paket Anda.');
        }
    }

    /**
     * Lempar exception 403 jika user tidak berhak melihat drawing sheet.
     */
    public function authorizeView(User $user, Project $project, DrawingSheet $sheet): void
    {
        if (! $this->canView($user, $project, $sheet)) {
            abort(403, 'Anda tidak memiliki izin untuk melihat lembar kerja 2D ini.');
        }
    }
}

