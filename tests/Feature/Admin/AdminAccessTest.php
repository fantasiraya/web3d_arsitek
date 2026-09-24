<?php

use App\Domains\Auth\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Ensure super_admin role exists
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

test('super admin can access admin dashboard', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
});

test('non-admin user gets 403 forbidden on admin routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/admin/dashboard');

    $response->assertStatus(403);
});

test('unauthenticated user redirected to login', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('super admin can access all admin routes', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    $routes = [
        '/admin/dashboard',
        '/admin/users',
        '/admin/projects',
        '/admin/subscriptions',
        '/admin/plans',
        '/admin/audit-logs',
    ];

    foreach ($routes as $route) {
        $response = $this->actingAs($admin)->get($route);
        expect($response->status())->toBe(200, "Failed accessing: {$route}");
    }
});

test('non-verified user cannot access admin routes', function () {
    $admin = User::factory()->create([
        'email_verified_at' => null, // Not verified
    ]);
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertRedirect('/verify-email');
});

test('suspended admin user cannot access admin routes', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'status' => 'suspended',
    ]);
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(403);
    $response->assertSee('ditangguhkan');
});
