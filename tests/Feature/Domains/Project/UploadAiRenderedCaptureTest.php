<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('architect can upload an AI-rendered image to a capture', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $capture = ProjectCapture::create([
        'project_id'    => $project->id,
        'user_id'       => $architect->id,
        'room_name'     => 'Kamar Utama',
        'captured_hour' => '12:00',
        'file_path'     => "projects/{$project->id}/captures/test.png",
    ]);

    $file = UploadedFile::fake()->image('ai_render.png', 1920, 1080);

    $response = $this->actingAs($architect)->post(route('projects.captures.ai-render.upload', [
        'project' => $project->id,
        'capture' => $capture->id,
    ]), [
        'image' => $file,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(200);
    $response->assertJsonPath('data.room_name', 'Kamar Utama');

    $capture->refresh();
    expect($capture->ai_rendered_path)->not->toBeNull();
    expect(Storage::disk('public')->exists($capture->ai_rendered_path))->toBeTrue();
});

test('non-owner cannot upload AI-rendered image (forbidden 403)', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $capture = ProjectCapture::create([
        'project_id'    => $project->id,
        'user_id'       => $architect->id,
        'room_name'     => 'Teras',
        'captured_hour' => '12:00',
        'file_path'     => "projects/{$project->id}/captures/test.png",
    ]);

    $file = UploadedFile::fake()->image('ai_render.jpg');

    $response = $this->actingAs($otherUser)->post(route('projects.captures.ai-render.upload', [
        'project' => $project->id,
        'capture' => $capture->id,
    ]), [
        'image' => $file,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(403);
});

test('deleting capture also deletes ai_rendered_path physical file', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $fakeFilePath = "projects/{$project->id}/captures/test.png";
    $fakeAiPath = "projects/{$project->id}/captures/ai/ai_render_test.png";

    Storage::disk('public')->put($fakeFilePath, 'dummy 3d');
    Storage::disk('public')->put($fakeAiPath, 'dummy ai');

    $capture = ProjectCapture::create([
        'project_id'       => $project->id,
        'user_id'          => $architect->id,
        'room_name'        => 'Dapur',
        'captured_hour'    => '12:00',
        'file_path'        => $fakeFilePath,
        'ai_rendered_path' => $fakeAiPath,
    ]);

    expect(Storage::disk('public')->exists($fakeFilePath))->toBeTrue();
    expect(Storage::disk('public')->exists($fakeAiPath))->toBeTrue();

    $capture->delete();

    expect(Storage::disk('public')->exists($fakeFilePath))->toBeFalse();
    expect(Storage::disk('public')->exists($fakeAiPath))->toBeFalse();
});

