<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Actions\InviteClientAction;
use App\Domains\Project\Actions\RevokeClientAccessAction;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\Project\Notifications\ClientInvitationNotification;
use Illuminate\Support\Facades\Notification;

it('invites a new client and sends notification', function () {
    Notification::fake();

    $inviter = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $inviter->id]);

    $action = app(InviteClientAction::class);
    $client = $action->execute($project, $inviter, 'client@example.com');

    expect($client)->toBeInstanceOf(ProjectClient::class)
        ->email->toBe('client@example.com')
        ->status->toBe(ProjectClient::STATUS_PENDING)
        ->user_id->toBeNull();

    Notification::assertSentOnDemand(
        ClientInvitationNotification::class,
        function (ClientInvitationNotification $notification, $channels, $notifiable) use ($project) {
            return $notifiable->routes['mail'] === 'client@example.com' &&
                   $notification->project->id === $project->id;
        }
    );
});

it('links existing user automatically on invitation', function () {
    Notification::fake();

    $inviter = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $inviter->id]);

    $existingUser = User::factory()->create(['email' => 'existing@example.com']);

    $action = app(InviteClientAction::class);
    $client = $action->execute($project, $inviter, 'existing@example.com');

    expect($client->user_id)->toBe($existingUser->id);
});

it('revokes client access', function () {
    $client = ProjectClient::factory()->create(['status' => ProjectClient::STATUS_ACCEPTED]);

    $action = app(RevokeClientAccessAction::class);
    $action->execute($client);

    expect($client->fresh()->status)->toBe(ProjectClient::STATUS_REVOKED);
});
