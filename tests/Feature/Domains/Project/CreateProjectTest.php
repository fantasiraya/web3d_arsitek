<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Actions\CreateProjectAction;
use App\Domains\Project\Models\Project;
use App\Domains\SystemConfig\Models\SystemSetting;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('creates a project when under quota', function () {
    SystemSetting::factory()->create([
        'key' => 'quota.free.max_projects',
        'value' => '3',
        'type' => 'integer',
    ]);
    SystemSetting::factory()->create([
        'key' => 'quota.free.max_revisions_per_project',
        'value' => '2',
        'type' => 'integer',
    ]);

    $user = User::factory()->create(['subscription_status' => 'free']);
    $action = app(CreateProjectAction::class);

    $project = $action->execute($user, [
        'name' => 'Test Project',
        'description' => 'Test description',
    ]);

    expect($project)->toBeInstanceOf(Project::class)
        ->name->toBe('Test Project')
        ->user_id->toBe($user->id)
        ->max_revisions_allowed->toBe(2)
        ->status->toBe(Project::STATUS_DRAFT);
});

it('throws 403 when quota is exceeded', function () {
    SystemSetting::factory()->create([
        'key' => 'quota.free.max_projects',
        'value' => '1',
        'type' => 'integer',
    ]);

    $user = User::factory()->create(['subscription_status' => 'free']);
    $action = app(CreateProjectAction::class);

    // Create first project
    $action->execute($user, ['name' => 'Project 1']);

    // Second project should fail
    $action->execute($user, ['name' => 'Project 2']);
})->throws(HttpException::class, 'Project quota exceeded for your current subscription.');
