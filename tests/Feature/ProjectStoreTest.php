<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Jobs\DracoCompressionJob;
use App\Domains\Project\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('authenticated user can create project with large 3D file up to 100MB', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // 15MB 3D file simulation (exceeds default php 2M and 8M limits)
    $file = UploadedFile::fake()->create('villa_model.glb', 15 * 1024, 'model/gltf-binary');

    $response = $this->actingAs($user)->post('/projects', [
        'title' => 'Villa Modern 3D',
        'description' => 'Model arsitektur villa tropis modern',
        'file' => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $project = Project::where('title', 'Villa Modern 3D')->first();
    expect($project)->not->toBeNull()
        ->and($project->user_id)->toBe($user->id)
        ->and($project->file_size_bytes)->toBe(15 * 1024 * 1024);

    Storage::disk('public')->assertExists($project->file_path);
    Queue::assertPushed(DracoCompressionJob::class);
});
