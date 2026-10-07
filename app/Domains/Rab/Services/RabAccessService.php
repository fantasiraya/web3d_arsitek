<?php

namespace App\Domains\Rab\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\Rab\Models\RabDocument;

/**
 * Gating dan kontrol akses modul RAB.
 *
 * Aturan (AI_INSTRUCTIONS.md Section M):
 *  - Fitur RAB hanya aktif jika plans.can_use_rab = true.
 *  - Hanya pemilik project yang bisa membuat/mengedit.
 *  - Klien accepted boleh melihat RAB read-only jika is_visible_to_clients = true.
 *  - Downgrade tidak menghapus data; hanya memblokir buat/edit baru.
 */
class RabAccessService
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    /**
     * Apakah user boleh membuat/mengedit dokumen RAB?
     * Syarat: pemilik project DAN paket aktif punya can_use_rab = true.
     */
    public function canEdit(User $user, Project $project): bool
    {
        if ($project->user_id !== $user->id) {
            return false;
        }

        return $this->hasPlanAccess($user);
    }

    /**
     * Apakah user boleh melihat RAB?
     *  - Pemilik project: selalu boleh (meski sudah downgrade).
     *  - Klien accepted: boleh jika is_visible_to_clients = true pada dokumen.
     */
    public function canView(User $user, Project $project, RabDocument $document): bool
    {
        // Owner selalu bisa lihat
        if ($project->user_id === $user->id) {
            return true;
        }

        // Klien accepted + visibilitas dinyalakan
        if (! $document->is_visible_to_clients) {
            return false;
        }

        return ProjectClient::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('status', ProjectClient::STATUS_ACCEPTED)
            ->exists();
    }

    /**
     * Apakah user boleh mengakses daftar RAB suatu project?
     *  - Pemilik: ya.
     *  - Klien accepted: ya (daftar ditampilkan; dokumen hidden disembunyikan di controller).
     */
    public function canAccessProject(User $user, Project $project): bool
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
     * Apakah paket aktif user memiliki akses RAB (can_use_rab = true)?
     */
    public function hasPlanAccess(User $user): bool
    {
        $plan = $this->limitService->getPlanForUser($user);

        return (bool) ($plan->can_use_rab ?? false);
    }

    /**
     * Lempar 403 jika user tidak boleh membuat/mengedit RAB.
     */
    public function authorizeEdit(User $user, Project $project): void
    {
        if (! $this->canEdit($user, $project)) {
            if ($project->user_id !== $user->id) {
                abort(403, 'Hanya pemilik project yang dapat mengelola RAB.');
            }

            abort(403, 'Paket langganan Anda tidak mendukung fitur RAB. Upgrade ke Pro atau Enterprise.');
        }
    }

    /**
     * Lempar 403 jika user tidak boleh melihat dokumen RAB ini.
     */
    public function authorizeView(User $user, Project $project, RabDocument $document): void
    {
        if (! $this->canView($user, $project, $document)) {
            abort(403, 'Anda tidak memiliki akses untuk melihat dokumen RAB ini.');
        }
    }
}
