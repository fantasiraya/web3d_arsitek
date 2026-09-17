<?php

use App\Domains\Auth\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a sanctum token via api', function () {
    $user = User::factory()->create([
        'email' => 'api@test.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/sanctum/token', [
        'email' => 'api@test.com',
        'password' => 'password123',
        'device_name' => 'test-device',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token']);

    // Verify token can access protected route
    $token = $response->json('token');

    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->getJson('/api/user')->assertOk();
});

it('fails to create token with invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'api2@test.com',
        'password' => Hash::make('password123'),
    ]);

    $this->postJson('/api/sanctum/token', [
        'email' => 'api2@test.com',
        'password' => 'wrongpassword',
        'device_name' => 'test-device',
    ])->assertStatus(401);
});
