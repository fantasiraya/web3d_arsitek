<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\ProjectClient;
use Laravel\Socialite\Facades\Socialite;

it('redirects to google auth', function () {
    $response = $this->get('/auth/google');
    $response->assertRedirectContains('accounts.google.com');
});

it('authenticates user and logs them in', function () {
    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getId')->andReturn('google-id-123')
        ->shouldReceive('getEmail')->andReturn('test@google.com')
        ->shouldReceive('getName')->andReturn('Google User')
        ->shouldReceive('getAvatar')->andReturn('https://google.com/avatar.jpg');

    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();

    $user = User::where('email', 'test@google.com')->first();
    expect($user)->not->toBeNull()
        ->google_id->toBe('google-id-123')
        ->name->toBe('Google User');
});

it('matches pending project client invitations on google auth login', function () {
    // Create a pending invitation
    $invitation = ProjectClient::factory()->create([
        'email' => 'invited@google.com',
        'status' => ProjectClient::STATUS_PENDING,
    ]);

    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
    $abstractUser->shouldReceive('getId')->andReturn('google-id-456')
        ->shouldReceive('getEmail')->andReturn('invited@google.com')
        ->shouldReceive('getName')->andReturn('Invited User')
        ->shouldReceive('getAvatar')->andReturn('https://google.com/avatar.jpg');

    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

    $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $invitation->refresh();

    expect($invitation->status)->toBe(ProjectClient::STATUS_ACCEPTED);

    $user = User::where('email', 'invited@google.com')->first();
    expect($invitation->user_id)->toBe($user->id);
});
