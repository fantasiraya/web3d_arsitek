<?php

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\UserPlanOverride;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;

beforeEach(function () {
    // Ensure plans exist
    Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'project_limit' => 1,
        'can_create_project' => true,
        'can_edit_project' => true,
        'can_delete_project' => true,
        'can_export' => false,
    ]);

    Plan::firstOrCreate(['slug' => 'pro'], [
        'name' => 'Pro',
        'project_limit' => 20,
        'can_create_project' => true,
        'can_edit_project' => true,
        'can_delete_project' => true,
        'can_export' => true,
    ]);

    Plan::firstOrCreate(['slug' => 'enterprise'], [
        'name' => 'Enterprise',
        'project_limit' => null, // Unlimited
        'can_create_project' => true,
        'can_edit_project' => true,
        'can_delete_project' => true,
        'can_export' => true,
    ]);

    $this->limitService = app(SubscriptionLimitService::class);
});

test('free user cannot create more than 1 project with exact 403 message', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    // Create first project - should succeed
    Project::factory()->create(['user_id' => $user->id]);

    // Try to create second project - should fail
    $result = $this->limitService->canCreateProject($user);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('Your Free plan allows you to create only 1 project. Upgrade your plan to create more projects.');
});

test('free user cannot edit project when can_edit_project is disabled', function () {
    // Update free plan to disable editing
    Plan::where('slug', 'free')->update(['can_edit_project' => false]);

    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    $project = Project::factory()->create(['user_id' => $user->id]);

    $result = $this->limitService->canEditProject($user, $project);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('Project editing is not available on the Free plan. Upgrade your plan to edit projects.');

    // Restore default
    Plan::where('slug', 'free')->update(['can_edit_project' => true]);
});

test('pro user can create up to 20 projects and 21st fails with exact 403 message', function () {
    $user = User::factory()->create([
        'subscription_status' => 'pro',
        'email_verified_at' => now(),
    ]);

    // Create 20 projects - should all succeed
    Project::factory()->count(20)->create(['user_id' => $user->id]);

    $result = $this->limitService->canCreateProject($user);
    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('You have reached your project limit. Upgrade your plan or delete an existing project to create a new one.');
});

test('custom override overrides plan limit', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    // Set custom override to 5
    UserPlanOverride::create([
        'user_id' => $user->id,
        'custom_project_limit' => 5,
        'is_unlimited' => false,
        'reason' => 'Test override',
    ]);

    // Create 5 projects
    Project::factory()->count(5)->create(['user_id' => $user->id]);

    $result = $this->limitService->canCreateProject($user);
    expect($result['allowed'])->toBeFalse();

    // Should only have 5 projects
    expect(Project::where('user_id', $user->id)->count())->toBe(5);

    $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
    expect($effectiveLimit)->toBe(5);
});

test('removing custom override restores plan limit', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    // Set custom override to 5
    $override = UserPlanOverride::create([
        'user_id' => $user->id,
        'custom_project_limit' => 5,
        'is_unlimited' => false,
    ]);

    $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
    expect($effectiveLimit)->toBe(5);

    // Remove override
    $override->delete();
    $user->refresh();

    // Should revert to free plan limit (1)
    $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
    expect($effectiveLimit)->toBe(1);
});

test('enterprise plan allows unlimited projects', function () {
    $user = User::factory()->create([
        'subscription_status' => 'enterprise',
        'email_verified_at' => now(),
    ]);

    // Create 100 projects
    Project::factory()->count(100)->create(['user_id' => $user->id]);

    $result = $this->limitService->canCreateProject($user);
    expect($result['allowed'])->toBeTrue();

    $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
    expect($effectiveLimit)->toBeNull(); // null means unlimited
});

test('suspended user cannot create project', function () {
    $user = User::factory()->create([
        'subscription_status' => 'pro',
        'status' => 'suspended',
        'email_verified_at' => now(),
    ]);

    $result = $this->limitService->canCreateProject($user);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toBe('Account is suspended. Please contact support.');
});

test('unlimited override allows unlimited projects', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    // Set unlimited override
    UserPlanOverride::create([
        'user_id' => $user->id,
        'custom_project_limit' => null,
        'is_unlimited' => true,
        'reason' => 'VIP customer',
    ]);

    // Create 50 projects
    Project::factory()->count(50)->create(['user_id' => $user->id]);

    $result = $this->limitService->canCreateProject($user);
    expect($result['allowed'])->toBeTrue();

    $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
    expect($effectiveLimit)->toBeNull(); // null means unlimited
});

test('user can edit own project when plan allows', function () {
    $user = User::factory()->create([
        'subscription_status' => 'pro',
        'email_verified_at' => now(),
    ]);

    $project = Project::factory()->create(['user_id' => $user->id]);

    $result = $this->limitService->canEditProject($user, $project);

    expect($result['allowed'])->toBeTrue();
    expect($result['reason'])->toBeNull();
});

test('user cannot edit other users project', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create([
        'subscription_status' => 'pro',
        'email_verified_at' => now(),
    ]);

    $project = Project::factory()->create(['user_id' => $owner->id]);

    $result = $this->limitService->canEditProject($otherUser, $project);

    expect($result['allowed'])->toBeFalse();
    expect($result['reason'])->toContain('pemilik proyek');
});

test('user can delete own project when plan allows', function () {
    $user = User::factory()->create([
        'subscription_status' => 'pro',
        'email_verified_at' => now(),
    ]);

    $project = Project::factory()->create(['user_id' => $user->id]);

    $result = $this->limitService->canDeleteProject($user, $project);

    expect($result['allowed'])->toBeTrue();
});

test('free user can export when can_export is enabled', function () {
    // Default free plan has can_export = false
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    $canExport = $this->limitService->canExport($user);
    expect($canExport)->toBeFalse();

    // Pro user can export
    $proUser = User::factory()->create([
        'subscription_status' => 'pro',
        'email_verified_at' => now(),
    ]);

    $canExport = $this->limitService->canExport($proUser);
    expect($canExport)->toBeTrue();
});
