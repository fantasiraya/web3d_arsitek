<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;

test('architect can save capture with prompt', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $dummyImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/captures", [
        'image'         => $dummyImage,
        'room_name'     => 'Ruang Tamu',
        'captured_hour' => '14:30',
        'prompt'        => 'Modern minimalist living room, cinematic lighting',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('project_captures', [
        'project_id' => $project->id,
        'room_name'  => 'Ruang Tamu',
        'prompt'     => 'Modern minimalist living room, cinematic lighting',
    ]);
});

test('architect can update capture prompt with auto-save endpoint', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $capture = ProjectCapture::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $user->id,
        'prompt'     => 'Initial prompt',
    ]);

    $response = $this->actingAs($user)->patchJson("/projects/{$project->id}/captures/{$capture->id}/prompt", [
        'prompt' => 'Updated architectural prompt with travertine details',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('project_captures', [
        'id'     => $capture->id,
        'prompt' => 'Updated architectural prompt with travertine details',
    ]);
});

test('unauthorized user cannot update capture prompt', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);
    $capture = ProjectCapture::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $owner->id,
        'prompt'     => 'Original prompt',
    ]);

    $response = $this->actingAs($otherUser)->patchJson("/projects/{$project->id}/captures/{$capture->id}/prompt", [
        'prompt' => 'Hacked prompt',
    ]);

    $response->assertForbidden();
});

