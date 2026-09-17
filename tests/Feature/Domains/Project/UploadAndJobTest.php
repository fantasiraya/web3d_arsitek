<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Actions\UploadProjectFileAction;
use App\Domains\Project\Jobs\DracoCompressionJob;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('uploads project file and dispatches job', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->create('model.glb', 100);

    $action = app(UploadProjectFileAction::class);
    $version = $action->execute($project, $user, $file);

    expect($version)->toBeInstanceOf(ProjectVersion::class)
        ->project_id->toBe($project->id)
        ->version_number->toBe(1)
        ->file_path->not->toBeNull();

    Storage::disk('public')->assertExists($version->file_path);

    Queue::assertPushed(DracoCompressionJob::class, function ($job) use ($version) {
        return $job->projectVersion->id === $version->id;
    });
});
