<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Jobs\DracoCompressionJob;
use App\Domains\Project\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('project owner can update project details', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Title',
        'description' => 'Old Description',
        'max_revisions_allowed' => 3,
    ]);

    $response = $this->actingAs($user)->patch("/projects/{$project->id}", [
        'title' => 'Updated Title',
        'description' => 'Updated Description',
        'max_revisions_allowed' => 5,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $project->refresh();
    expect($project->title)->toBe('Updated Title')
        ->and($project->description)->toBe('Updated Description')
        ->and($project->max_revisions_allowed)->toBe(5);
});

test('project owner can upload and update 3d model file', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'file_path' => 'projects/models/old.glb',
        'file_size_bytes' => 1024,
    ]);

    $newFile = UploadedFile::fake()->create('new_model.glb', 2048, 'model/gltf-binary');

    $response = $this->actingAs($user)->patch("/projects/{$project->id}", [
        'title' => 'New Model Title',
        'file' => $newFile,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $project->refresh();
    expect($project->title)->toBe('New Model Title')
        ->and($project->file_path)->not->toBe('projects/models/old.glb')
        ->and($project->file_size_bytes)->toBeGreaterThan(0);

    Storage::disk('public')->assertExists($project->file_path);
    Queue::assertPushed(DracoCompressionJob::class);
});

test('non-owner cannot update project', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Original Title',
    ]);

    $response = $this->actingAs($otherUser)->patch("/projects/{$project->id}", [
        'title' => 'Hacked Title',
    ]);

    $response->assertForbidden();

    $project->refresh();
    expect($project->title)->toBe('Original Title');
});
