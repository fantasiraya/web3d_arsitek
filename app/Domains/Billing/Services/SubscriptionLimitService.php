<?php

namespace App\Domains\Billing\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\UserPlanOverride;
use App\Domains\Project\Models\Project;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;

class SubscriptionLimitService
{
    public function __construct(
        protected SystemSettingRepository $settings
    ) {}

    /**
     * Per-request in-memory plan cache.
     * Eliminates repeated DB queries when multiple methods call getPlanForUser()
     * for the same user within a single request lifecycle.
     *
     * @var array<string, Plan>
     */
    protected array $planCache = [];

    /**
     * Per-request override cache.
     *
     * @var array<string, UserPlanOverride|null>
     */
    protected array $overrideCache = [];

    /**
     * Flush the in-memory caches (useful in tests between calls).
     */
    public function flushCache(): void
    {
        $this->planCache    = [];
        $this->overrideCache = [];
    }

    /**
     * Get the resolved Plan for a user.
     * Memoized per request — DB hit only once per user per request lifecycle.
     */
    public function getPlanForUser(User $user): Plan
    {
        if (isset($this->planCache[$user->id])) {
            return $this->planCache[$user->id];
        }

        $plan = $this->resolvePlanForUser($user);
        $this->planCache[$user->id] = $plan;

        return $plan;
    }

    /**
     * Internal plan resolution — called only once per user per request.
     */
    protected function resolvePlanForUser(User $user): Plan
    {
        // Use already-loaded relation if available (avoids extra query)
        $activeSub = $user->relationLoaded('activeSubscription')
            ? $user->activeSubscription
            : $user->activeSubscription()->with('planModel')->first();

        if ($activeSub && $activeSub->relationLoaded('planModel') && $activeSub->planModel) {
            return $activeSub->planModel;
        }

        if ($activeSub && ! $activeSub->relationLoaded('planModel')) {
            $activeSub->load('planModel');
            if ($activeSub->planModel) {
                return $activeSub->planModel;
            }
        }

        // Resolve slug from subscription or user cache column
        $slug = $activeSub?->plan ?? $user->subscription_status ?? Plan::SLUG_FREE;

        // Single query — plans are a tiny table, cache at application level
        $plan = Plan::where('slug', $slug)->first()
            ?? Plan::where('slug', Plan::SLUG_FREE)->first()
            ?? Plan::first();

        if ($plan) {
            return $plan;
        }

        // Hard fallback — should never reach here in production
        return new Plan([
            'name'               => 'Free',
            'slug'               => 'free',
            'project_limit'      => 1,
            'can_create_project' => true,
            'can_edit_project'   => true,
            'can_delete_project' => true,
            'can_export'         => false,
        ]);
    }

    /**
     * Get the user's plan override, memoized per request.
     */
    protected function getPlanOverride(User $user): ?UserPlanOverride
    {
        if (array_key_exists($user->id, $this->overrideCache)) {
            return $this->overrideCache[$user->id];
        }

        // Use already-loaded relation if available
        $override = $user->relationLoaded('planOverride')
            ? $user->planOverride
            : $user->planOverride; // Eloquent accessor — loads once and is cached by Eloquent

        $this->overrideCache[$user->id] = $override;

        return $override;
    }

    /**
     * Get the effective project limit for a user.
     * Returns null for unlimited, or an integer limit.
     */
    public function getEffectiveProjectLimit(User $user): ?int
    {
        $override = $this->getPlanOverride($user);

        if ($override) {
            if ($override->is_unlimited) {
                return null;
            }

            if ($override->custom_project_limit !== null) {
                return (int) $override->custom_project_limit;
            }
        }

        // Fallback to Plan limit (memoized)
        $plan = $this->getPlanForUser($user);

        return $plan->project_limit !== null ? (int) $plan->project_limit : null;
    }

    /**
     * Check if user has an active custom limit override.
     */
    public function hasCustomLimitOverride(User $user): bool
    {
        return $this->getPlanOverride($user) !== null;
    }

    /**
     * Validate whether user can create a new project.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canCreateProject(User $user): array
    {
        if (! $user->isActiveAccount()) {
            return [
                'allowed' => false,
                'reason' => 'Account is suspended. Please contact support.',
            ];
        }

        $plan = $this->getPlanForUser($user);

        if (! $plan->can_create_project) {
            return [
                'allowed' => false,
                'reason' => 'Your current plan does not allow project creation.',
            ];
        }

        $effectiveLimit = $this->getEffectiveProjectLimit($user);

        // Null means unlimited
        if ($effectiveLimit === null) {
            return ['allowed' => true, 'reason' => null];
        }

        $currentProjectsCount = Project::where('user_id', $user->id)->count();

        if ($currentProjectsCount >= $effectiveLimit) {
            // Free plan with default 1 limit
            if ($plan->slug === Plan::SLUG_FREE && ! $this->hasCustomLimitOverride($user)) {
                return [
                    'allowed' => false,
                    'reason' => 'Your Free plan allows you to create only 1 project. Upgrade your plan to create more projects.',
                ];
            }

            return [
                'allowed' => false,
                'reason' => 'You have reached your project limit. Upgrade your plan or delete an existing project to create a new one.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Validate whether user can edit a project.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canEditProject(User $user, Project $project): array
    {
        if (! $user->isActiveAccount()) {
            return [
                'allowed' => false,
                'reason' => 'Account is suspended. Please contact support.',
            ];
        }

        if ($project->user_id !== $user->id) {
            return [
                'allowed' => false,
                'reason' => 'Hanya arsitek pemilik proyek yang dapat mengubah pengaturan proyek.',
            ];
        }

        $plan = $this->getPlanForUser($user);

        if (! $plan->can_edit_project) {
            return [
                'allowed' => false,
                'reason' => 'Project editing is not available on the Free plan. Upgrade your plan to edit projects.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Validate whether user can delete a project.
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function canDeleteProject(User $user, Project $project): array
    {
        if (! $user->isActiveAccount()) {
            return [
                'allowed' => false,
                'reason' => 'Account is suspended. Please contact support.',
            ];
        }

        if ($project->user_id !== $user->id) {
            return [
                'allowed' => false,
                'reason' => 'Hanya arsitek pemilik proyek yang dapat menghapus proyek.',
            ];
        }

        $plan = $this->getPlanForUser($user);

        if (! $plan->can_delete_project) {
            return [
                'allowed' => false,
                'reason' => 'Project deletion is not available on your current plan.',
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check if user can export.
     */
    public function canExport(User $user): bool
    {
        $plan = $this->getPlanForUser($user);

        return $plan->can_export;
    }

    /**
     * Check if user can use a specific feature.
     */
    public function canUseFeature(User $user, string $featureKey): bool
    {
        $plan = $this->getPlanForUser($user);

        return (bool) ($plan->{$featureKey} ?? false);
    }

    /**
     * Get maximum file size (MB) allowed for the user's plan.
     * Cek custom override per-user dulu, lalu fallback ke system_settings per-plan.
     */
    public function getMaxFileSizeMb(User $user): int
    {
        // 1. Cek custom override per-user dari admin (memoized)
        $override = $this->getPlanOverride($user);
        if ($override && $override->custom_file_size_mb !== null) {
            return (int) $override->custom_file_size_mb;
        }

        // 2. Baca dari system_settings berdasarkan plan (plan memoized, settings cached)
        $plan    = $this->getPlanForUser($user);
        $key     = match ($plan->slug) {
            Plan::SLUG_FREE        => 'free_tier_max_file_size_mb',
            Plan::SLUG_PRO         => 'pro_tier_max_file_size_mb',
            Plan::SLUG_ENTERPRISE  => 'enterprise_tier_max_file_size_mb',
            default                => 'free_tier_max_file_size_mb',
        };
        $default = match ($plan->slug) {
            Plan::SLUG_FREE        => 15,
            Plan::SLUG_PRO         => 100,
            Plan::SLUG_ENTERPRISE  => 100,
            default                => 15,
        };

        return (int) ($this->settings->get($key, $default) ?: $default);
    }

    /**
     * Validate whether uploaded file size is allowed for the user's plan.
     *
     * @return array{allowed: bool, reason: ?string, max_mb: int}
     */
    public function canUploadFile(User $user, int $fileSizeBytes): array
    {
        $maxMb    = $this->getMaxFileSizeMb($user);
        $maxBytes = $maxMb * 1024 * 1024;

        if ($fileSizeBytes > $maxBytes) {
            $actualMb = round($fileSizeBytes / (1024 * 1024), 1);

            return [
                'allowed' => false,
                'reason'  => "Ukuran file ({$actualMb} MB) melebihi batas paket Anda ({$maxMb} MB). Upgrade ke paket Pro untuk batas 100 MB.",
                'max_mb'  => $maxMb,
            ];
        }

        return ['allowed' => true, 'reason' => null, 'max_mb' => $maxMb];
    }
}
