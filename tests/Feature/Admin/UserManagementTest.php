<?php

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\UserPlanOverride;
use App\Domains\SystemConfig\Models\AuditLog;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

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

    $this->admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $this->admin->assignRole('super_admin');
});

test('super admin can change user plan and audit log is recorded', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    $proPlan = Plan::where('slug', 'pro')->first();

    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$user->id}/plan", [
            'plan_id' => $proPlan->id,
        ]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->subscription_status)->toBe('pro');

    // Check audit log was created
    $auditLog = AuditLog::where('target_user_id', $user->id)
        ->where('action', 'change_plan')
        ->first();

    expect($auditLog)->not->toBeNull();
    expect($auditLog->admin_id)->toBe($this->admin->id);
    expect($auditLog->old_value)->toHaveKey('subscription_status');
    expect($auditLog->new_value)->toHaveKey('subscription_status');
});

test('super admin can suspend user and suspended user is blocked', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    // Suspend user
    $response = $this->actingAs($this->admin)
        ->patch("/admin/users/{$user->id}/status");

    $response->assertRedirect();

    $user->refresh();
    expect($user->status)->toBe('suspended');

    // Check audit log
    $auditLog = AuditLog::where('target_user_id', $user->id)
        ->where('action', 'suspend_user')
        ->first();

    expect($auditLog)->not->toBeNull();

    // Try to access as suspended user
    $response = $this->actingAs($user)->get('/architect/dashboard');
    $response->assertStatus(403);
});

test('super admin can activate suspended user', function () {
    $user = User::factory()->create([
        'status' => 'suspended',
        'email_verified_at' => now(),
    ]);

    // Activate user
    $response = $this->actingAs($this->admin)
        ->patch("/admin/users/{$user->id}/status");

    $response->assertRedirect();

    $user->refresh();
    expect($user->status)->toBe('active');

    // User should be able to access now
    $response = $this->actingAs($user)->get('/architect/dashboard');
    $response->assertStatus(200);
});

test('super admin can set custom project limit override', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$user->id}/limit", [
            'custom_project_limit' => 10,
            'is_unlimited' => false,
            'reason' => 'VIP customer',
        ]);

    $response->assertRedirect();

    $override = UserPlanOverride::where('user_id', $user->id)->first();

    expect($override)->not->toBeNull();
    expect($override->custom_project_limit)->toBe(10);
    expect($override->is_unlimited)->toBeFalse();
    expect($override->reason)->toBe('VIP customer');

    // Check audit log
    $auditLog = AuditLog::where('target_user_id', $user->id)
        ->where('action', 'set_limit_override')
        ->first();

    expect($auditLog)->not->toBeNull();
});

test('super admin can set unlimited override', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->post("/admin/users/{$user->id}/limit", [
            'is_unlimited' => true,
            'reason' => 'Enterprise trial',
        ]);

    $response->assertRedirect();

    $override = UserPlanOverride::where('user_id', $user->id)->first();

    expect($override)->not->toBeNull();
    expect($override->is_unlimited)->toBeTrue();
    expect($override->custom_project_limit)->toBeNull();
});

test('super admin can remove custom project limit override', function () {
    $user = User::factory()->create([
        'subscription_status' => 'free',
        'email_verified_at' => now(),
    ]);

    // Create override first
    UserPlanOverride::create([
        'user_id' => $user->id,
        'custom_project_limit' => 10,
        'is_unlimited' => false,
    ]);

    // Remove override
    $response = $this->actingAs($this->admin)
        ->delete("/admin/users/{$user->id}/limit");

    $response->assertRedirect();

    $override = UserPlanOverride::where('user_id', $user->id)->first();
    expect($override)->toBeNull();

    // Check audit log
    $auditLog = AuditLog::where('target_user_id', $user->id)
        ->where('action', 'remove_limit_override')
        ->first();

    expect($auditLog)->not->toBeNull();
});

test('non-admin user cannot access user management endpoints', function () {
    $regularUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $targetUser = User::factory()->create();

    // Try to change plan
    $response = $this->actingAs($regularUser)
        ->post("/admin/users/{$targetUser->id}/plan", ['plan_id' => 'pro']);

    $response->assertStatus(403);

    // Try to suspend
    $response = $this->actingAs($regularUser)
        ->patch("/admin/users/{$targetUser->id}/status");

    $response->assertStatus(403);

    // Try to set limit
    $response = $this->actingAs($regularUser)
        ->post("/admin/users/{$targetUser->id}/limit", ['custom_project_limit' => 10]);

    $response->assertStatus(403);
});

test('admin actions include IP address and user agent in audit log', function () {
    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->withHeaders([
            'User-Agent' => 'Test Browser/1.0',
            'X-Forwarded-For' => '192.168.1.100',
        ])
        ->patch("/admin/users/{$user->id}/status");

    $response->assertRedirect();

    $auditLog = AuditLog::where('target_user_id', $user->id)->latest()->first();

    expect($auditLog->user_agent)->toContain('Test Browser');
    expect($auditLog->ip_address)->not->toBeNull();
});

test('super admin can view user detail with audit history', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    // Create some audit logs
    AuditLog::create([
        'admin_id' => $this->admin->id,
        'target_user_id' => $user->id,
        'action' => 'change_plan',
        'old_value' => ['plan' => 'free'],
        'new_value' => ['plan' => 'pro'],
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/users/{$user->id}");

    $response->assertStatus(200);
    $response->assertSee($user->name);
    $response->assertSee($user->email);
});
