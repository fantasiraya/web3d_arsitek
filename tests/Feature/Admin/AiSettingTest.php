<?php

use App\Domains\Auth\Models\User;
use App\Domains\SystemConfig\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
});

test('super admin can access AI settings page', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->get('/admin/settings/ai');

    $response->assertStatus(200);
});

test('non-admin user gets 403 on AI settings page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/admin/settings/ai');

    $response->assertStatus(403);
});

test('super admin can update vision AI provider and keys in database', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    $response = $this->actingAs($admin)->patch('/admin/settings/ai', [
        'vision_ai_provider' => 'openai',
        'openai_api_key'     => 'sk-proj-test123456789',
        'openai_model'       => 'gpt-4o',
    ]);

    $response->assertSessionHas('success');

    expect(SystemSetting::where('key', 'vision_ai_provider')->value('value'))->toBe('openai');
    expect(SystemSetting::where('key', 'openai_api_key')->value('value'))->toBe('sk-proj-test123456789');
    expect(SystemSetting::where('key', 'openai_model')->value('value'))->toBe('gpt-4o');
});

test('secret key is not overwritten if submitted masked or empty', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    // Existing key di DB
    SystemSetting::updateOrCreate(
        ['key' => 'gemini_api_key'],
        ['value' => 'ORIGINAL_SECRET_KEY', 'type' => 'string']
    );

    // Kirim masked key
    $this->actingAs($admin)->patch('/admin/settings/ai', [
        'vision_ai_provider' => 'gemini',
        'gemini_api_key'     => 'AIza••••••••1234',
        'gemini_model'       => 'gemini-2.5-flash',
    ]);

    expect(SystemSetting::where('key', 'gemini_api_key')->value('value'))->toBe('ORIGINAL_SECRET_KEY');

    // Kirim empty key
    $this->actingAs($admin)->patch('/admin/settings/ai', [
        'vision_ai_provider' => 'gemini',
        'gemini_api_key'     => '',
        'gemini_model'       => 'gemini-2.0-flash',
    ]);

    expect(SystemSetting::where('key', 'gemini_api_key')->value('value'))->toBe('ORIGINAL_SECRET_KEY');
    expect(SystemSetting::where('key', 'gemini_model')->value('value'))->toBe('gemini-2.0-flash');
});

test('super admin can reset keys from database so system falls back to env', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    SystemSetting::updateOrCreate(
        ['key' => 'openai_api_key'],
        ['value' => 'temporary-key', 'type' => 'string']
    );

    $this->actingAs($admin)->patch('/admin/settings/ai', [
        'vision_ai_provider' => 'gemini',
        'reset_keys'         => ['openai_api_key'],
    ]);

    expect(SystemSetting::where('key', 'openai_api_key')->first())->toBeNull();
});

test('test connection endpoint verifies gemini api successfully', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('super_admin');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'displayName' => 'Gemini 2.5 Flash',
        ], 200),
    ]);

    $response = $this->actingAs($admin)->postJson('/admin/settings/ai/test', [
        'provider' => 'gemini',
        'api_key'  => 'fake-gemini-key',
        'model'    => 'gemini-2.5-flash',
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
    ]);
});

