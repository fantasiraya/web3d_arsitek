<?php

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Models\Transaction;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\Project\Models\ProjectVersion;
use App\Domains\SystemConfig\Models\SystemSetting;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Str;

test('user model uses uuid, defaults to free subscription, and has no role column', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->subscription_status)->toBe('free')
        ->and($user->isPro())->toBeFalse()
        ->and(array_key_exists('role', $user->getAttributes()))->toBeFalse();
});

test('user model supports pro and google states', function () {
    $proUser = User::factory()->pro()->create();
    expect($proUser->isPro())->toBeTrue()
        ->and($proUser->subscription_status)->toBe('pro');

    $googleUser = User::factory()->google()->create();
    expect($googleUser->google_id)->not->toBeNull()
        ->and($googleUser->password)->toBeNull();
});

test('system setting seeder seeds default quotas and returns typed values', function () {
    $this->seed(SystemSettingSeeder::class);

    $maxProjects = SystemSetting::where('key', 'free_tier_max_projects')->first();
    expect($maxProjects)->not->toBeNull()
        ->and($maxProjects->getTypedValue())->toBe(2);

    $maxFileSize = SystemSetting::where('key', 'free_tier_max_file_size_mb')->first();
    expect($maxFileSize)->not->toBeNull()
        ->and($maxFileSize->getTypedValue())->toBe(15);

    $defaultRevisions = SystemSetting::where('key', 'free_tier_default_client_revisions')->first();
    expect($defaultRevisions)->not->toBeNull()
        ->and($defaultRevisions->getTypedValue())->toBe(3);

    $maxInvitedClients = SystemSetting::where('key', 'free_tier_max_invited_clients')->first();
    expect($maxInvitedClients)->not->toBeNull()
        ->and($maxInvitedClients->getTypedValue())->toBe(5);
});

test('project and project version models handle uuid relations and revision limits', function () {
    $architect = User::factory()->create();
    $project = Project::factory()->for($architect, 'user')->create([
        'max_revisions_allowed' => 3,
        'current_revision_count' => 2,
    ]);

    expect(Str::isUuid($project->id))->toBeTrue()
        ->and($project->user->id)->toBe($architect->id)
        ->and($project->hasReachedRevisionLimit())->toBeFalse();

    $project->current_revision_count = 3;
    expect($project->hasReachedRevisionLimit())->toBeTrue();

    $version = ProjectVersion::factory()->for($project)->create([
        'version_number' => 1,
    ]);

    expect(Str::isUuid($version->id))->toBeTrue()
        ->and($version->project->id)->toBe($project->id)
        ->and($project->versions)->toHaveCount(1);
});

test('project client invitations support status flow and unique constraint', function () {
    $architect = User::factory()->create();
    $project = Project::factory()->for($architect, 'user')->create();

    $invitation = ProjectClient::factory()->for($project)->create([
        'email' => 'client@example.com',
        'invited_by' => $architect->id,
    ]);

    expect(Str::isUuid($invitation->id))->toBeTrue()
        ->and($invitation->isPending())->toBeTrue()
        ->and($invitation->isAccepted())->toBeFalse();

    $clientUser = User::factory()->create(['email' => 'client@example.com']);
    $invitation->update([
        'status' => ProjectClient::STATUS_ACCEPTED,
        'user_id' => $clientUser->id,
        'accepted_at' => now(),
    ]);

    expect($invitation->isAccepted())->toBeTrue()
        ->and($invitation->user->id)->toBe($clientUser->id);
});

test('comment model supports 3d spatial coordinates and threaded replies', function () {
    $project = Project::factory()->create();
    $client = User::factory()->create();

    $rootComment = Comment::factory()->for($project)->for($client, 'user')->create([
        'position_x' => 1.25,
        'position_y' => 2.50,
        'position_z' => -3.75,
        'normal_x' => 0.0,
        'normal_y' => 1.0,
        'normal_z' => 0.0,
        'content' => 'Ganti material dinding fasad',
    ]);

    expect($rootComment->isRoot())->toBeTrue()
        ->and($rootComment->position_x)->toBe(1.25)
        ->and($rootComment->position_y)->toBe(2.50)
        ->and($rootComment->position_z)->toBe(-3.75);

    $replyComment = Comment::factory()->for($project)->for($project->user, 'user')->create([
        'parent_id' => $rootComment->id,
        'content' => 'Siap, kami revisi ke material batu alam',
    ]);

    expect($replyComment->isRoot())->toBeFalse()
        ->and($replyComment->parent->id)->toBe($rootComment->id)
        ->and($rootComment->replies)->toHaveCount(1);
});

test('chat message supports project room and read receipts', function () {
    $project = Project::factory()->create();
    $sender = User::factory()->create();

    $message = ChatMessage::factory()->for($project)->create([
        'sender_id' => $sender->id,
        'message' => 'Halo Pak Arsitek, untuk revisi fasad sudah siap dicek?',
    ]);

    expect(Str::isUuid($message->id))->toBeTrue()
        ->and($message->sender->id)->toBe($sender->id)
        ->and($message->read_at)->toBeNull();

    $message->markAsRead();
    expect($message->read_at)->not->toBeNull();
});

test('transactions and subscriptions handle payment and active states', function () {
    $user = User::factory()->create();

    $transaction = Transaction::factory()->settled()->create([
        'user_id' => $user->id,
        'amount' => 150000.00,
    ]);

    expect($transaction->isSettled())->toBeTrue();

    $subscription = Subscription::factory()->create([
        'user_id' => $user->id,
        'transaction_id' => $transaction->id,
        'plan' => 'pro',
        'status' => 'active',
        'expires_at' => now()->addMonth(),
    ]);

    expect($subscription->isActive())->toBeTrue()
        ->and($subscription->transaction->id)->toBe($transaction->id)
        ->and($user->activeSubscription->id)->toBe($subscription->id);
});
