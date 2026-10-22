<?php

use App\Domains\Auth\Models\User;
use App\Domains\Project\Actions\ExtractSkpThumbnailAction;
use App\Domains\Project\Jobs\DracoCompressionJob;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated user can upload .skp sketchup file when creating 3D project', function () {
    Storage::fake('public');
    Queue::fake();

    $user = User::factory()->create();

    // Create a mock modern SKP file with an embedded PNG thumbnail inside a zip container
    $im = imagecreatetruecolor(50, 50);
    $color = imagecolorallocate($im, 200, 50, 50);
    imagefill($im, 0, 0, $color);
    ob_start();
    imagepng($im);
    $pngBytes = ob_get_clean();
    imagedestroy($im);

    $tmpZip = tempnam(sys_get_temp_dir(), 'skp_zip');
    $zip = new ZipArchive();
    $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('meta/model_thumbnail.png', $pngBytes);
    $zip->close();
    $zipContent = file_get_contents($tmpZip);
    @unlink($tmpZip);

    $skpBinary = "\xFF\xFE\xFF\x0E" . "SketchUp 2024\0" . $zipContent;

    $file = UploadedFile::fake()->createWithContent('rumah_minimalis.skp', $skpBinary);

    $response = $this->actingAs($user)->post('/projects', [
        'title'                 => 'Rumah Minimalis SketchUp',
        'description'           => 'Desain dibuat di SketchUp 2024',
        'max_revisions_allowed' => 5,
        'file'                  => $file,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $project = Project::where('title', 'Rumah Minimalis SketchUp')->first();
    expect($project)->not->toBeNull()
        ->and($project->file_path)->toEndWith('.skp')
        ->and($project->user_id)->toBe($user->id);

    Storage::disk('public')->assertExists($project->file_path);

    // Draco compression must not run for .skp
    Queue::assertNotPushed(DracoCompressionJob::class);

    // Thumbnail capture should be extracted and saved
    $capture = ProjectCapture::where('project_id', $project->id)->first();
    expect($capture)->not->toBeNull()
        ->and($capture->title)->toBe('Pratinjau Model SketchUp')
        ->and($capture->is_visible_to_clients)->toBeTrue();

    Storage::disk('public')->assertExists($capture->file_path);
});

test('viewer page receives thumbnail_url for sketchup project', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $project = Project::factory()->create([
        'user_id'   => $user->id,
        'title'     => 'Villa SketchUp',
        'file_path' => 'projects/models/villa.skp',
    ]);

    $capture = ProjectCapture::factory()->create([
        'project_id'            => $project->id,
        'user_id'               => $user->id,
        'file_path'             => 'projects/models/villa_thumb.png',
        'is_visible_to_clients' => true,
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/viewer");
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Project/Viewer')
        ->has('project', fn (Assert $proj) => $proj
            ->where('id', $project->id)
            ->where('file_path', 'projects/models/villa.skp')
            ->where('thumbnail_url', '/storage/projects/models/villa_thumb.png')
            ->etc()
        )
    );
});

test('uploading invalid extension is rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)->post('/projects', [
        'title' => 'Project with PDF',
        'file'  => $file,
    ]);

    $response->assertSessionHasErrors('file');
});
