<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('deleting a project deletes all associated files from public storage', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $disk = Storage::disk('public');

    $dummyGlb = 'projects/models/' . Str::random(40) . '.glb';
    $disk->put($dummyGlb, 'dummy 3d content');

    $project = Project::create([
        'user_id' => $user->id,
        'title' => 'Project To Delete',
        'slug' => 'project-to-delete-' . Str::random(8),
        'share_token' => Str::random(32),
        'file_path' => $dummyGlb,
        'file_size_bytes' => 100,
        'is_draco_compressed' => false,
        'max_revisions_allowed' => 3,
    ]);

    $capturePath = "projects/{$project->id}/captures/sample.png";
    $disk->put($capturePath, 'png content');

    $capture = ProjectCapture::create([
        'id' => (string) Str::uuid(),
        'project_id' => $project->id,
        'user_id' => $user->id,
        'file_path' => $capturePath,
        'room_name' => 'Kamar Tidur',
        'captured_hour' => '12:00',
        'file_size_bytes' => 50,
        'width' => 1920,
        'height' => 1080,
    ]);

    expect($disk->exists($dummyGlb))->toBeTrue();
    expect($disk->exists($capturePath))->toBeTrue();

    // Perform deletion
    $project->delete();

    expect($disk->exists($dummyGlb))->toBeFalse();
    expect($disk->exists($capturePath))->toBeFalse();
    expect($disk->directoryExists("projects/{$project->id}"))->toBeFalse();
});

test('project capture urls return root-relative storage paths', function () {
    $capture = new ProjectCapture([
        'file_path' => 'projects/sample/captures/img.png',
        'ai_rendered_path' => 'projects/sample/captures/ai/img_ai.jpg',
    ]);

    expect($capture->url)->toBe('/storage/projects/sample/captures/img.png');
    expect($capture->ai_rendered_url)->toBe('/storage/projects/sample/captures/ai/img_ai.jpg');
});
