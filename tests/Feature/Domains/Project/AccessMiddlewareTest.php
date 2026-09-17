<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'project.access'])->get('/test-project-access/{project}', function (Project $project) {
        return 'Accessed Project '.$project->id;
    });

    Route::middleware(['web', 'project.revision_limit'])->post('/test-project-revision/{project}', function (Project $project) {
        return 'Revision allowed';
    });
});

it('allows project owner to access', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->get("/test-project-access/{$project->id}")
        ->assertOk()
        ->assertSee('Accessed Project '.$project->id);
});

it('allows accepted client to access', function () {
    $clientUser = User::factory()->create();
    $project = Project::factory()->create();

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'user_id' => $clientUser->id,
        'status' => ProjectClient::STATUS_ACCEPTED,
    ]);

    $this->actingAs($clientUser)
        ->get("/test-project-access/{$project->id}")
        ->assertOk();
});

it('denies pending client to access', function () {
    $clientUser = User::factory()->create();
    $project = Project::factory()->create();

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'user_id' => $clientUser->id,
        'status' => ProjectClient::STATUS_PENDING,
    ]);

    $this->actingAs($clientUser)
        ->get("/test-project-access/{$project->id}")
        ->assertForbidden();
});

it('denies random user to access', function () {
    $randomUser = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($randomUser)
        ->get("/test-project-access/{$project->id}")
        ->assertForbidden();
});

it('blocks revision if limit is reached', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'max_revisions_allowed' => 3,
        'current_revision_count' => 3,
    ]);

    $this->actingAs($owner)
        ->post("/test-project-revision/{$project->id}")
        ->assertForbidden();
});

it('allows revision if under limit', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'max_revisions_allowed' => 3,
        'current_revision_count' => 2,
    ]);

    $this->actingAs($owner)
        ->post("/test-project-revision/{$project->id}")
        ->assertOk();
});
