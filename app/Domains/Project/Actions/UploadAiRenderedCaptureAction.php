<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadAiRenderedCaptureAction
{
    public function execute(User $user, Project $project, ProjectCapture $capture, UploadedFile $file): ProjectCapture
    {
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya pemilik proyek yang dapat mengunggah gambar hasil render AI.');
        }

        if ($capture->project_id !== $project->id) {
            abort(422, 'Capture tidak cocok dengan proyek.');
        }

        // Hapus file AI render lama jika ada
        if ($capture->ai_rendered_path && Storage::disk('public')->exists($capture->ai_rendered_path)) {
            Storage::disk('public')->delete($capture->ai_rendered_path);
        }

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'ai_render_' . Str::uuid() . '.' . $extension;
        $path = $file->storeAs("projects/{$project->id}/captures/ai", $filename, 'public');

        $capture->update([
            'ai_rendered_path' => $path,
        ]);

        return $capture->fresh();
    }
}

