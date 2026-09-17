<?php

namespace App\Domains\Project\Jobs;

use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DracoCompressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ProjectVersion $projectVersion
    ) {}

    public function handle(): void
    {
        // Path resolution
        $disk = Storage::disk('public');
        $originalRelativePath = $this->projectVersion->file_path;

        if (! $disk->exists($originalRelativePath)) {
            Log::error("DracoCompressionJob: File not found for ProjectVersion {$this->projectVersion->id}");

            return;
        }

        $fullOriginalPath = $disk->path($originalRelativePath);
        $compressedRelativePath = preg_replace('/\.glb$/i', '_draco.glb', $originalRelativePath);
        $fullCompressedPath = $disk->path($compressedRelativePath);

        // Run gltf-pipeline
        $process = new Process([
            'npx', 'gltf-pipeline',
            '-i', $fullOriginalPath,
            '-o', $fullCompressedPath,
            '-d', // Drace compression flag
        ]);

        $process->setTimeout(300);

        try {
            $process->mustRun();

            // If successful, update the database
            $this->projectVersion->update([
                'file_path' => $compressedRelativePath,
            ]);

            // Optionally remove the original uncompressed file to save space
            // $disk->delete($originalRelativePath);

        } catch (ProcessFailedException $e) {
            Log::error('DracoCompressionJob failed: '.$e->getMessage());
            // Fail gracefully, keep the original uncompressed file in the database
        } catch (\Throwable $e) {
            Log::error('DracoCompressionJob unexpected error: '.$e->getMessage());
        }
    }
}
