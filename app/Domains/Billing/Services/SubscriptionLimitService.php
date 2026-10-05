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
     * Get the resolved Plan for a user.
     */
    public function getPlanForUser(User $user): Plan
    {
        // 1. Check active subscription relation with plan model
        $activeSub = $user->activeSubscription()->with('planModel')->first();
        if ($activeSub && $activeSub->planModel) {
            return $activeSub->planModel;
        }

        // 2. Check by subscription_status slug or active subscription plan slug
        $slug = $activeSub->plan ?? $user->subscription_status ?? Plan::SLUG_FREE;

        $plan = Plan::where('slug', $slug)->first();
        if ($plan) {
            return $plan;
        }

        // 3. Fallback to free plan or first active plan
        return Plan::where('slug', Plan::SLUG_FREE)->first()
            ?? Plan::first()
            ?? new Plan([
                'name' => 'Free',
                'slug' => 'free',
                'project_limit' => 1,
                'can_create_project' => true,
                'can_edit_project' => true,
                'can_delete_project' => true,
                'can_export' => false,
            ]);
    }

    /**
     * Get the effective project limit for a user.
     * Returns null for unlimited, or an integer limit.
     */
    public function getEffectiveProjectLimit(User $user): ?int
    {
        // Check for custom override
        /** @var UserPlanOverride|null $override */
        $override = $user->planOverride;

        if ($override) {
            if ($override->is_unlimited) {
                return null;
            }

            if ($override->custom_project_limit !== null) {
                return (int) $override->custom_project_limit;
            }
        }

        // Fallback to Plan limit
        $plan = $this->getPlanForUser($user);

        return $plan->project_limit !== null ? (int) $plan->project_limit : null;
    }

    /**
     * Check if user has an active custom limit override.
     */
    public function hasCustomLimitOverride(User $user): bool
    {
        return $user->planOverride !== null;
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
        // 1. Cek custom override per-user dari admin
        $override = $user->planOverride;
        if ($override && $override->custom_file_size_mb !== null) {
            return (int) $override->custom_file_size_mb;
        }

        // 2. Baca dari system_settings berdasarkan plan
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
