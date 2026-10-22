<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Jobs\DracoCompressionJob;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class UploadProjectFileAction
{
    public function execute(Project $project, User $uploader, UploadedFile $file): ProjectVersion
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'glb');
        $filename = Str::random(40) . '.' . $ext;
        $path = $file->storeAs("projects/{$project->id}/versions", $filename, 'public');

        $version = ProjectVersion::create([
            'project_id' => $project->id,
            'version_number' => $project->versions()->count() + 1,
            'file_path' => $path,
            'file_size_bytes' => $file->getSize() ?: 0,
            'is_draco_compressed' => false,
            'changelog' => 'Uploaded new version',
        ]);

        // Dispatch Draco Compression only for GLB/GLTF models
        if (in_array($ext, ['glb', 'gltf'])) {
            DracoCompressionJob::dispatch($version);
        } elseif ($ext === 'skp') {
            app(\App\Domains\Project\Actions\ConvertSkpToGlbAction::class)->execute($path);
        }

        return $version;
    }
}
