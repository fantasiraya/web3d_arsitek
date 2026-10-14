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

test('generate prompt returns fallback prompt when gemini api key is not set', function () {
    config(['services.gemini.api_key' => null]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'Villa Modern']);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/captures/generate-prompt", [
        'image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'scene_metadata' => [
            'room_name' => 'Bukaan Kaca & Jendela',
            'hour' => '16:30',
            'lighting_desc' => 'golden hour sunset',
            'clipping_enabled' => false,
            'camera_perspective' => 'eye-level 35mm',
            'detected_object' => 'floor-to-ceiling glass window',
            'detected_materials' => ['clear float glass', 'timber wood frame'],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['prompt', 'source']);
    expect($response->json('source'))->toBe('local-spatial-3d');
    expect($response->json('prompt'))->toContain('Villa Modern');
    expect($response->json('prompt'))->toStartWith('Strict image-to-image architectural photorealistic rendering of the attached 3D reference image.');
});

test('generate prompt calls gemini api when key is set', function () {
    config([
        'services.gemini.api_key' => 'fake-gemini-key',
        'services.gemini.model' => 'gemini-2.5-flash',
    ]);

    \Illuminate\Support\Facades\Http::fake([
        'generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image. Modern tropical pavilion with expansive floor-to-ceiling glass glazing during warm golden hour.'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'Villa Modern']);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/captures/generate-prompt", [
        'image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'scene_metadata' => [
            'room_name' => 'Bukaan Kaca & Jendela',
            'hour' => '16:30',
            'lighting_desc' => 'golden hour sunset',
            'clipping_enabled' => false,
        ],
    ]);

    $response->assertOk();
    expect($response->json('source'))->toBe('gemini-vision');
    expect($response->json('prompt'))->toContain('Modern tropical pavilion');
    expect($response->json('prompt'))->toStartWith('Strict image-to-image architectural photorealistic rendering of the attached 3D reference image.');
});

test('user can download capture image as attachment', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fakePath = "projects/{$project->id}/captures/test-capture.png";
    \Illuminate\Support\Facades\Storage::disk('public')->put($fakePath, 'dummy png content');

    $capture = ProjectCapture::factory()->create([
        'project_id'    => $project->id,
        'user_id'       => $user->id,
        'room_name'     => 'Ruang Keluarga',
        'captured_hour' => '15:30',
        'file_path'     => $fakePath,
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/captures/{$capture->id}/download");

    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->headers->get('content-disposition'))->toContain('pitcharch-ruang-keluarga-15-30.png');
});

test('generate prompt uses database setting to override env provider', function () {
    // Set .env to gemini
    config([
        'services.vision_ai.provider' => 'gemini',
        'services.vision_ai.providers.gemini.api_key' => 'fake-gemini-key',
    ]);

    // Simpan di Database tabel system_settings bahwa provider aktif adalah openai
    \App\Domains\SystemConfig\Models\SystemSetting::updateOrCreate(
        ['key' => 'vision_ai_provider'],
        ['value' => 'openai', 'type' => 'string']
    );
    \App\Domains\SystemConfig\Models\SystemSetting::updateOrCreate(
        ['key' => 'openai_api_key'],
        ['value' => 'fake-openai-db-key', 'type' => 'string']
    );
    \App\Domains\SystemConfig\Models\SystemSetting::updateOrCreate(
        ['key' => 'openai_model'],
        ['value' => 'gpt-4o-mini', 'type' => 'string']
    );

    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image. Contemporary Scandinavian wooden house generated via OpenAI GPT-4o.',
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'title' => 'Villa Skandinavia']);

    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/captures/generate-prompt", [
        'image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'scene_metadata' => [
            'room_name' => 'Fasad Utama',
            'hour' => '10:00',
        ],
    ]);

    $response->assertOk();
    expect($response->json('source'))->toBe('openai-vision');
    expect($response->json('provider'))->toBe('openai');
    expect($response->json('prompt'))->toContain('OpenAI GPT-4o');
});

