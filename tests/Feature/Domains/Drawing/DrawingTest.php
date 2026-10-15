<?php

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Drawing\Models\DrawingElement;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Pastikan plan Enterprise ada dengan can_use_drawing = true
    Plan::firstOrCreate(
        ['slug' => 'enterprise'],
        [
            'name'            => 'Enterprise',
            'can_use_drawing' => true,
            'can_use_rab'     => true,
            'status'          => 'active',
            'project_limit'   => 999,
        ]
    );

    // Plan Free dengan can_use_drawing = false
    Plan::firstOrCreate(
        ['slug' => 'free'],
        [
            'name'            => 'Free',
            'can_use_drawing' => false,
            'can_use_rab'     => false,
            'status'          => 'active',
            'project_limit'   => 1,
        ]
    );
});

test('free tier user cannot create drawing sheet and receives 403', function () {
    $freeUser = User::factory()->create([
        'subscription_status' => 'free',
    ]);

    $project = Project::factory()->create([
        'user_id' => $freeUser->id,
    ]);

    $response = $this->actingAs($freeUser)->post(route('drawings.store'), [
        'project_id'   => $project->id,
        'title'        => 'Denah Lantai 1',
        'type'         => 'plan',
        'scale'        => '1:100',
        'paper_size'   => 'A3',
        'orientation'  => 'landscape',
        'grid_spacing' => 20,
        'unit'         => 'm',
    ]);

    $response->assertForbidden();
});

test('enterprise user can view drawing sheets and create a new drawing sheet', function () {
    $enterpriseUser = User::factory()->create([
        'subscription_status' => 'enterprise',
    ]);

    $project = Project::factory()->create([
        'user_id' => $enterpriseUser->id,
    ]);

    // 1. Visit index
    $indexResponse = $this->actingAs($enterpriseUser)->get(route('drawings.index'));
    $indexResponse->assertOk();

    // 2. Create drawing sheet
    $createResponse = $this->actingAs($enterpriseUser)->post(route('drawings.store'), [
        'project_id'   => $project->id,
        'title'        => 'Denah Lantai 1',
        'type'         => 'plan',
        'scale'        => '1:100',
        'paper_size'   => 'A3',
        'orientation'  => 'landscape',
        'grid_spacing' => 20,
        'unit'         => 'm',
    ]);

    $sheet = DrawingSheet::where('title', 'Denah Lantai 1')->first();
    expect($sheet)->not->toBeNull();
    expect($sheet->project_id)->toBe($project->id);
    expect($sheet->type)->toBe('plan');

    $createResponse->assertRedirect(route('drawings.editor', $sheet->id));
});

test('enterprise user can access editor and sync drawing elements', function () {
    $enterpriseUser = User::factory()->create([
        'subscription_status' => 'enterprise',
    ]);

    $project = Project::factory()->create([
        'user_id' => $enterpriseUser->id,
    ]);

    $sheet = DrawingSheet::create([
        'project_id'   => $project->id,
        'user_id'      => $enterpriseUser->id,
        'title'        => 'Tampak Depan Bangunan',
        'type'         => 'elevation',
        'scale'        => '1:50',
        'paper_size'   => 'A2',
        'orientation'  => 'landscape',
        'grid_spacing' => 20,
        'unit'         => 'm',
    ]);

    // Access editor
    $editorResponse = $this->actingAs($enterpriseUser)->get(route('drawings.editor', $sheet->id));
    $editorResponse->assertOk();

    // Sync elements
    $elements = [
        [
            'type'       => 'wall',
            'data'       => ['x1' => 100, 'y1' => 100, 'x2' => 500, 'y2' => 100, 'thickness' => 12],
            'sort_order' => 0,
        ],
        [
            'type'       => 'door',
            'data'       => ['x' => 200, 'y' => 100, 'size' => 80],
            'sort_order' => 1,
        ],
        [
            'type'       => 'room_label',
            'data'       => ['x' => 300, 'y' => 250, 'text' => 'Kamar Tidur Utama'],
            'sort_order' => 2,
        ],
    ];

    $syncResponse = $this->actingAs($enterpriseUser)->postJson(route('drawings.sync', $sheet->id), [
        'elements' => $elements,
    ]);

    $syncResponse->assertOk();
    $syncResponse->assertJson(['success' => true, 'count' => 3]);

    expect(DrawingElement::where('drawing_sheet_id', $sheet->id)->count())->toBe(3);
});

test('enterprise user can export drawing sheet to DXF and SVG', function () {
    $enterpriseUser = User::factory()->create([
        'subscription_status' => 'enterprise',
    ]);

    $project = Project::factory()->create([
        'user_id' => $enterpriseUser->id,
    ]);

    $sheet = DrawingSheet::create([
        'project_id'   => $project->id,
        'user_id'      => $enterpriseUser->id,
        'title'        => 'Potongan A-A',
        'type'         => 'section',
        'scale'        => '1:100',
        'paper_size'   => 'A3',
        'orientation'  => 'landscape',
        'grid_spacing' => 20,
        'unit'         => 'm',
    ]);

    DrawingElement::create([
        'drawing_sheet_id' => $sheet->id,
        'type'             => 'wall',
        'data'             => ['x1' => 0, 'y1' => 0, 'x2' => 400, 'y2' => 0],
        'sort_order'       => 0,
    ]);

    // 1. Export DXF
    $dxfResponse = $this->actingAs($enterpriseUser)->get(route('drawings.export-dxf', $sheet->id));
    $dxfResponse->assertOk();
    $dxfResponse->assertHeader('Content-Type', 'application/dxf');

    // 2. Export SVG
    $svgResponse = $this->actingAs($enterpriseUser)->post(route('drawings.export-svg', $sheet->id));
    $svgResponse->assertOk();
    $svgResponse->assertHeader('Content-Type', 'image/svg+xml');
});

test('owner can delete drawing sheet and non-owner cannot delete it', function () {
    $owner = User::factory()->create([
        'subscription_status' => 'enterprise',
    ]);
    $stranger = User::factory()->create([
        'subscription_status' => 'enterprise',
    ]);

    $project = Project::factory()->create([
        'user_id' => $owner->id,
    ]);

    $sheet = DrawingSheet::create([
        'project_id'   => $project->id,
        'user_id'      => $owner->id,
        'title'        => 'Denah Rencana Fondasi',
        'type'         => 'plan',
        'scale'        => '1:100',
        'paper_size'   => 'A3',
        'orientation'  => 'landscape',
        'grid_spacing' => 20,
        'unit'         => 'm',
    ]);

    // Stranger attempts deletion -> 403
    $strangerResponse = $this->actingAs($stranger)->delete(route('drawings.destroy', $sheet->id));
    $strangerResponse->assertForbidden();

    // Owner deletes sheet -> success redirect
    $ownerResponse = $this->actingAs($owner)->delete(route('drawings.destroy', $sheet->id));
    $ownerResponse->assertRedirect(route('drawings.index'));

    expect(DrawingSheet::find($sheet->id))->toBeNull();
});

test('enterprise user can dynamically resize and sync sheet dimensions like figma', function () {
    $enterpriseUser = User::factory()->create([
        'subscription_status' => 'enterprise',
    ]);

    $project = Project::factory()->create([
        'user_id' => $enterpriseUser->id,
    ]);

    $sheet = DrawingSheet::create([
        'project_id'   => $project->id,
        'user_id'      => $enterpriseUser->id,
        'title'        => 'Denah Dinamis',
        'type'         => 'plan',
        'paper_size'   => 'A3',
        'orientation'  => 'landscape',
        'width'        => 1260,
        'height'       => 891,
    ]);

    // Resize dynamically like Figma (e.g. 1500 x 950, Custom)
    $response = $this->actingAs($enterpriseUser)->postJson(route('drawings.sync', $sheet->id), [
        'elements' => [],
        'sheet'    => [
            'width'       => 1500,
            'height'      => 950,
            'paper_size'  => 'Custom',
            'orientation' => 'landscape',
        ],
    ]);

    $response->assertOk();
    $sheet->refresh();
    expect($sheet->width)->toBe(1500);
    expect($sheet->height)->toBe(950);
    expect($sheet->paper_size)->toBe('Custom');
});


