<?php

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Project\Models\Project;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Models\RabPriceItem;

beforeEach(function () {
    // Pastikan user punya akses RAB (plan Pro / can_use_rab = true)
    Plan::updateOrCreate(
        ['slug' => 'pro'],
        [
            'name'            => 'Pro',
            'can_use_rab'     => true,
            'can_use_drawing' => true,
            'status'          => 'active',
            'project_limit'   => 999,
        ]
    );
});

test('architect can generate a room-by-room draft RAB from worksheet', function () {
    $architect = User::factory()->create([
        'email_verified_at'   => now(),
        'subscription_status' => 'pro',
    ]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    // Tambah master harga satuan custom milik arsitek
    RabPriceItem::create([
        'user_id'    => $architect->id,
        'category'   => 'Lantai',
        'name'       => 'Granit Lantai 60x60',
        'unit'       => 'm2',
        'unit_price' => 320000,
    ]);

    $payload = [
        'title' => 'RAB Renovasi Kafe Semangkok',
        'rooms' => [
            [
                'name'             => 'TERAS',
                'area_sqm'         => 15.0,
                'wall_perimeter_m' => 16.0,
            ],
            [
                'name'             => 'KASIR',
                'area_sqm'         => 12.0,
                'wall_perimeter_m' => 14.0,
            ],
        ],
    ];

    $response = $this->actingAs($architect)->postJson(
        route('rab.generate-from-worksheet', $project->id),
        $payload
    );

    $response->assertStatus(200);
    $response->assertJsonPath('document.source', 'worksheet');
    $response->assertJsonPath('document.status', 'draft');

    $docId = $response->json('document.id');
    $doc = RabDocument::with('items')->findOrFail($docId);

    expect($doc->source)->toBe('worksheet');
    expect($doc->status)->toBe('draft');
    expect($doc->items->count())->toBeGreaterThanOrEqual(5);

    // Cek ada section TERAS, KASIR, dan PEKERJAAN PERSIAPAN
    $sections = $doc->items->pluck('section')->unique()->toArray();
    expect($sections)->toContain('PEKERJAAN PERSIAPAN');
    expect($sections)->toContain('TERAS');
    expect($sections)->toContain('KASIR');

    // Cek total kalkulasi otomatis via RecalculateRabAction
    expect($doc->subtotal)->toBeGreaterThan(0);
    expect($doc->overhead_amount)->toBeGreaterThan(0);
    expect($doc->ppn_amount)->toBeGreaterThan(0);
    expect($doc->total)->toBeGreaterThan($doc->subtotal);

    // Cek item ditandai is_estimate = true
    $estimateItems = $doc->items->where('is_estimate', true);
    expect($estimateItems->count())->toBe($doc->items->count());
});

test('unauthorized user cannot generate worksheet RAB (forbidden 403)', function () {
    $architect = User::factory()->create([
        'email_verified_at'   => now(),
        'subscription_status' => 'pro',
    ]);
    $otherUser = User::factory()->create([
        'email_verified_at'   => now(),
        'subscription_status' => 'pro',
    ]);
    $project = Project::factory()->create(['user_id' => $architect->id]);

    $response = $this->actingAs($otherUser)->postJson(
        route('rab.generate-from-worksheet', $project->id),
        [
            'rooms' => [
                ['name' => 'TERAS', 'area_sqm' => 10],
            ],
        ]
    );

    $response->assertStatus(403);
});
