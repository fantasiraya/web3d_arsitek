<?php

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingElement;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('architect owner can access booklet page', function () {
    $architect = User::factory()->create([
        'email_verified_at' => now(),
        'company_name'      => 'TRIVES STUDIO',
    ]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $sheet = DrawingSheet::create([
        'project_id'  => $project->id,
        'user_id'     => $architect->id,
        'title'       => 'Denah Lantai 1',
        'type'        => DrawingSheet::TYPE_PLAN,
        'scale'       => '1:100',
        'paper_size'  => 'A3',
        'orientation' => 'landscape',
        'width'       => 1260,
        'height'      => 891,
    ]);

    $capture = ProjectCapture::create([
        'project_id'       => $project->id,
        'drawing_sheet_id' => $sheet->id,
        'user_id'          => $architect->id,
        'room_name'        => 'TERAS',
        'captured_hour'    => '12:00',
        'file_path'        => "projects/{$project->id}/captures/test.png",
    ]);

    $response = $this->actingAs($architect)->get(route('projects.booklet', $project->id));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Drawing/Booklet')
        ->has('project')
        ->has('planSheet')
        ->has('captures')
        ->where('isOwner', true)
        ->where('architect.company_name', 'TRIVES STUDIO')
    );
});

test('accepted invited client can view booklet page', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $client = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    ProjectClient::factory()->create([
        'project_id' => $project->id,
        'user_id'    => $client->id,
        'email'      => $client->email,
        'status'     => 'accepted',
    ]);

    $response = $this->actingAs($client)->get(route('projects.booklet', $project->id));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('Drawing/Booklet')
        ->where('isOwner', false)
    );
});

test('uninvited user cannot access booklet page (forbidden 403)', function () {
    $architect = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create(['email_verified_at' => now()]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $response = $this->actingAs($otherUser)->get(route('projects.booklet', $project->id));

    $response->assertStatus(403);
});

