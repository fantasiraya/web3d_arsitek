<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Jobs\DracoCompressionJob;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Http\UploadedFile;

class UploadProjectFileAction
{
    public function execute(Project $project, User $uploader, UploadedFile $file): ProjectVersion
    {
        $path = $file->store("projects/{$project->id}/versions", 'public');

        $version = ProjectVersion::create([
            'project_id' => $project->id,
            'version_number' => $project->versions()->count() + 1,
            'file_path' => $path,
            'file_size_bytes' => $file->getSize() ?: 0,
            'is_draco_compressed' => false,
            'changelog' => 'Uploaded new version',
        ]);

        // Dispatch Draco Compression if environment supports it
        DracoCompressionJob::dispatch($version);

        return $version;
    }
}
