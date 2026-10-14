<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\SystemConfig\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    // Seed default settings
    SystemSetting::updateOrCreate(
        ['key' => 'free_tier_max_captures'],
        ['value' => '5', 'type' => 'integer']
    );
});

test('architect can save a 3D capture image', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    // 1x1 transparent PNG base64
    $dummyBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    $response = $this->actingAs($architect)->postJson(route('projects.captures.store', $project->id), [
        'image'                 => $dummyBase64,
        'room_name'             => 'Ruang Tamu',
        'captured_hour'         => '08:00',
        'title'                 => 'Pencahayaan Pagi Ruang Tamu',
        'width'                 => 1920,
        'height'                => 1080,
        'is_visible_to_clients' => true,
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.room_name', 'Ruang Tamu');
    $response->assertJsonPath('data.captured_hour', '08:00');

    $captureId = $response->json('data.id');
    expect(ProjectCapture::where('id', $captureId)->exists())->toBeTrue();

    $capture = ProjectCapture::find($captureId);
    expect(Storage::disk('public')->exists($capture->file_path))->toBeTrue();
});

test('client cannot save a capture to project (forbidden 403)', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $client = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $client->id,
        'email'      => $client->email,
        'status'     => 'accepted',
    ]);

    $dummyBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    $response = $this->actingAs($client)->postJson(route('projects.captures.store', $project->id), [
        'image'         => $dummyBase64,
        'room_name'     => 'Ruang Makan',
        'captured_hour' => '12:00',
    ]);

    $response->assertStatus(403);
});

test('quota limit enforcement: architect cannot exceed max captures allowed for plan', function () {
    $architect = User::factory()->create([
        'email_verified_at'   => now(),
        'subscription_status' => 'free',
    ]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    // Set max quota to 2 for testing
    SystemSetting::updateOrCreate(
        ['key' => 'free_tier_max_captures'],
        ['value' => '2', 'type' => 'integer']
    );

    // Create 2 existing captures for this architect
    ProjectCapture::factory()->count(2)->create([
        'project_id' => $project->id,
        'user_id'    => $architect->id,
    ]);

    $dummyBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    // 3rd capture should be blocked
    $response = $this->actingAs($architect)->postJson(route('projects.captures.store', $project->id), [
        'image'         => $dummyBase64,
        'room_name'     => 'Fasad Depan',
        'captured_hour' => '17:00',
    ]);

    $response->assertStatus(403);
    $response->assertSee('Batas kuota gambar');
});

test('client only sees captures that are marked as visible to clients', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $client = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $client->id,
        'email'      => $client->email,
        'status'     => 'accepted',
    ]);

    // 1 hidden capture, 1 visible capture
    ProjectCapture::factory()->create([
        'project_id'            => $project->id,
        'user_id'               => $architect->id,
        'room_name'             => 'Hidden Room',
        'is_visible_to_clients' => false,
    ]);

    ProjectCapture::factory()->create([
        'project_id'            => $project->id,
        'user_id'               => $architect->id,
        'room_name'             => 'Public Room',
        'is_visible_to_clients' => true,
    ]);

    // Architect sees both (2)
    $architectResponse = $this->actingAs($architect)->getJson(route('projects.captures.index', $project->id));
    $architectResponse->assertOk();
    $architectResponse->assertJsonCount(2, 'data');

    // Client sees only 1 (Public Room)
    $clientResponse = $this->actingAs($client)->getJson(route('projects.captures.index', $project->id));
    $clientResponse->assertOk();
    $clientResponse->assertJsonCount(1, 'data');
    $clientResponse->assertJsonPath('data.0.room_name', 'Public Room');
});

test('architect can toggle visibility of a capture', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $capture = ProjectCapture::factory()->create([
        'project_id'            => $project->id,
        'user_id'               => $architect->id,
        'is_visible_to_clients' => false,
    ]);

    $response = $this->actingAs($architect)->patchJson(route('projects.captures.toggle-visibility', [
        'project' => $project->id,
        'capture' => $capture->id,
    ]), [
        'is_visible_to_clients' => true,
    ]);

    $response->assertOk();
    expect($capture->fresh()->is_visible_to_clients)->toBeTrue();
});

test('architect can delete capture and physical file is removed', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $filePath = "projects/{$project->id}/captures/test.png";
    Storage::disk('public')->put($filePath, 'fake-image-binary');

    $capture = ProjectCapture::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $architect->id,
        'file_path'  => $filePath,
    ]);

    expect(Storage::disk('public')->exists($filePath))->toBeTrue();

    $response = $this->actingAs($architect)->deleteJson(route('projects.captures.destroy', [
        'project' => $project->id,
        'capture' => $capture->id,
    ]));

    $response->assertOk();
    expect(ProjectCapture::find($capture->id))->toBeNull();
    expect(Storage::disk('public')->exists($filePath))->toBeFalse();
});

test('architect can update project sun settings and client cannot', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $client = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create([
        'user_id'     => $architect->id,
        'latitude'    => -6.2,
        'longitude'   => 106.8,
        'north_angle' => 0,
    ]);

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $client->id,
        'email'      => $client->email,
        'status'     => 'accepted',
    ]);

    // Client update rejected 403
    $clientRes = $this->actingAs($client)->patchJson(route('projects.sun-settings.update', $project->id), [
        'latitude'    => -7.25,
        'longitude'   => 112.75,
        'north_angle' => 45,
    ]);
    $clientRes->assertStatus(403);

    // Architect update succeeds
    $architectRes = $this->actingAs($architect)->patchJson(route('projects.sun-settings.update', $project->id), [
        'latitude'    => -7.25,
        'longitude'   => 112.75,
        'north_angle' => 45,
        'solar_date'  => '2026-06-21',
    ]);
    $architectRes->assertOk();

    $project->refresh();
    expect((float) $project->latitude)->toEqual(-7.25);
    expect((float) $project->longitude)->toEqual(112.75);
    expect((float) $project->north_angle)->toEqual(45.0);
});

