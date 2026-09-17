<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\SystemConfig\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    SystemSetting::factory()->create([
        'key' => 'quota.free.max_projects',
        'value' => '3',
        'type' => 'integer',
    ]);
    SystemSetting::factory()->create([
        'key' => 'quota.free.max_revisions_per_project',
        'value' => '3',
        'type' => 'integer',
    ]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard with dual-capacity props', function () {
    $architect = User::factory()->create();
    $otherArchitect = User::factory()->create();

    // Create a project owned by this architect
    $ownedProject = Project::factory()->create([
        'user_id' => $architect->id,
        'title' => 'Villa Canggu 3D',
    ]);

    // Create a project where this user is invited as Client
    $clientProject = Project::factory()->create([
        'user_id' => $otherArchitect->id,
        'title' => 'Modern Office Jakarta',
    ]);
    ProjectClient::factory()->create([
        'project_id' => $clientProject->id,
        'user_id' => $architect->id,
        'email' => $architect->email,
        'invited_by' => $otherArchitect->id,
        'status' => 'pending',
    ]);

    $this->actingAs($architect);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('ownedProjects', 1)
        ->where('ownedProjects.0.title', 'Villa Canggu 3D')
        ->has('clientProjects', 1)
        ->where('clientProjects.0.title', 'Modern Office Jakarta')
        ->where('clientProjects.0.invitation_status', 'pending')
        ->has('stats')
        ->where('stats.owned_count', 1)
        ->where('stats.client_count', 1)
    );
});

test('architect can create a project with 3D model upload', function () {
    Storage::fake('public');

    $architect = User::factory()->create();
    $this->actingAs($architect);

    $file = UploadedFile::fake()->create('villa.glb', 1024, 'model/gltf-binary');

    $response = $this->post(route('projects.store'), [
        'title' => 'Penthouse Bali',
        'description' => 'Desain tropis modern',
        'max_revisions_allowed' => 3,
        'file' => $file,
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'user_id' => $architect->id,
        'title' => 'Penthouse Bali',
    ]);
});

test('architect cannot create a project with a file larger than 100 MB', function () {
    $architect = User::factory()->create();
    $this->actingAs($architect);

    $file = UploadedFile::fake()->create('villa.glb', 102401, 'model/gltf-binary');

    $response = $this->post(route('projects.store'), [
        'title' => 'Penthouse Bali',
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseMissing('projects', [
        'user_id' => $architect->id,
        'title' => 'Penthouse Bali',
    ]);
});

test('architect can invite a client to review a project', function () {
    $architect = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $this->actingAs($architect);

    $response = $this->post(route('projects.invite', $project->id), [
        'email' => 'client@example.com',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('project_clients', [
        'project_id' => $project->id,
        'email' => 'client@example.com',
        'status' => 'pending',
    ]);
});

test('client can accept an invitation', function () {
    $architect = User::factory()->create();
    $clientUser = User::factory()->create(['email' => 'client@example.com']);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $invitation = ProjectClient::factory()->create([
        'project_id' => $project->id,
        'email' => $clientUser->email,
        'invited_by' => $architect->id,
        'status' => 'pending',
    ]);

    $this->actingAs($clientUser);

    $response = $this->post(route('projects.accept-invitation', $project->id));
    $response->assertRedirect(route('projects.viewer', $project->id));

    $this->assertDatabaseHas('project_clients', [
        'id' => $invitation->id,
        'user_id' => $clientUser->id,
        'status' => 'accepted',
    ]);
});

test('post too large exception is handled gracefully with validation error', function () {
    $architect = User::factory()->create();
    $this->actingAs($architect);

    $response = $this->call('POST', route('projects.store'), [], [], [], [
        'CONTENT_LENGTH' => 500 * 1024 * 1024,
        'HTTP_X_INERTIA' => 'true',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('file');
});
