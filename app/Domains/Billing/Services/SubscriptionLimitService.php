<?php

namespace App\Domains\Billing\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\UserPlanOverride;
use App\Domains\Project\Models\Project;

class SubscriptionLimitService
{
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
}
