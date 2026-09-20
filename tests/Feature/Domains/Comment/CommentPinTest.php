<?php

use App\Domains\Auth\Models\User;
use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authorized user can update their comment', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'max_revisions_allowed' => 5,
        'current_revision_count' => 1,
    ]);

    $comment = Comment::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'content' => 'Original comment content',
        'position_x' => 1.0,
        'position_y' => 2.0,
        'position_z' => 3.0,
    ]);

    $response = $this->actingAs($owner)->patchJson(
        route('projects.comments.update', ['project' => $project->id, 'comment' => $comment->id]),
        ['content' => 'Updated comment feedback']
    );

    $response->assertOk()
        ->assertJsonPath('data.content', 'Updated comment feedback');

    expect($comment->fresh()->content)->toBe('Updated comment feedback');
});

test('unauthorized user cannot update another users comment', function () {
    $owner = User::factory()->create();
    $client = User::factory()->create();
    $otherUser = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $owner->id,
    ]);

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'invited_by' => $owner->id,
        'email' => $otherUser->email,
        'user_id' => $otherUser->id,
        'status' => 'accepted',
    ]);

    $comment = Comment::factory()->create([
        'project_id' => $project->id,
        'user_id' => $client->id,
        'content' => 'Client original feedback',
    ]);

    $response = $this->actingAs($otherUser)->patchJson(
        route('projects.comments.update', ['project' => $project->id, 'comment' => $comment->id]),
        ['content' => 'Hacked feedback']
    );

    $response->assertForbidden();
    expect($comment->fresh()->content)->toBe('Client original feedback');
});
