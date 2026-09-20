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

test('authorized user can unpin and delete comment from database', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
    ]);

    $comment = Comment::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'content' => 'Comment to be unpinned',
    ]);

    $response = $this->actingAs($owner)->deleteJson(
        route('projects.comments.destroy', ['project' => $project->id, 'comment' => $comment->id])
    );

    $response->assertOk()
        ->assertJsonPath('id', $comment->id);

    expect(Comment::find($comment->id))->toBeNull();
});

test('unauthorized user cannot unpin another users comment', function () {
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
        'content' => 'Client comment cannot be deleted by other invited user',
    ]);

    $response = $this->actingAs($otherUser)->deleteJson(
        route('projects.comments.destroy', ['project' => $project->id, 'comment' => $comment->id])
    );

    $response->assertForbidden();
    expect(Comment::find($comment->id))->not->toBeNull();
});

test('user cannot add comment beyond max_revisions_allowed limit', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'max_revisions_allowed' => 2,
        'current_revision_count' => 2,
    ]);

    // Create 2 root comments
    Comment::factory()->count(2)->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'parent_id' => null,
    ]);

    $response = $this->actingAs($owner)->postJson(
        route('projects.comments.store', ['project' => $project->id]),
        [
            'content' => 'Third comment should be rejected',
            'position_x' => 1.0,
            'position_y' => 1.0,
            'position_z' => 1.0,
        ]
    );

    $response->assertForbidden();
});

test('unpinning comment frees up revision slot to add new comment', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'max_revisions_allowed' => 2,
        'current_revision_count' => 2,
    ]);

    $comment1 = Comment::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'parent_id' => null,
    ]);

    $comment2 = Comment::factory()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'parent_id' => null,
    ]);

    // Unpin one comment
    $deleteResponse = $this->actingAs($owner)->deleteJson(
        route('projects.comments.destroy', ['project' => $project->id, 'comment' => $comment1->id])
    );
    $deleteResponse->assertOk();

    // Verify current_revision_count is now 1
    expect($project->fresh()->current_revision_count)->toBe(1);

    // Now adding a new comment succeeds
    $storeResponse = $this->actingAs($owner)->postJson(
        route('projects.comments.store', ['project' => $project->id]),
        [
            'content' => 'New comment after unpinning',
            'position_x' => 2.0,
            'position_y' => 2.0,
            'position_z' => 2.0,
        ]
    );

    $storeResponse->assertCreated();
    expect($project->fresh()->current_revision_count)->toBe(2);
});

test('owner can update max_revisions_allowed on project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'max_revisions_allowed' => 3,
    ]);

    $response = $this->actingAs($owner)->patchJson(
        route('projects.update', ['project' => $project->id]),
        [
            'max_revisions_allowed' => 7,
        ]
    );

    $response->assertOk();
    expect($project->fresh()->max_revisions_allowed)->toBe(7);
});
