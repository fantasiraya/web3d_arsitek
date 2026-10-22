<?php

namespace App\Domains\Project\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ConvertSkpToGlbAction
{
    /**
     * Convert an .skp file to .glb.
     * Returns the relative path to the converted .glb if successful, or null on failure.
     */
    public function execute(string $relativeSkpPath): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($relativeSkpPath)) {
            return null;
        }

        $absoluteSkpPath = $disk->path($relativeSkpPath);
        $relativeGlbPath = preg_replace('/\.skp$/i', '.glb', $relativeSkpPath);
        $absoluteGlbPath = $disk->path($relativeGlbPath);

        // Jika file .glb sudah ada dan valid (> 1KB), langsung gunakan
        if ($disk->exists($relativeGlbPath) && $disk->size($relativeGlbPath) > 1000) {
            return $relativeGlbPath;
        }

        $scriptPath = base_path('scripts/convert-skp-to-glb.mjs');
        if (! file_exists($scriptPath)) {
            return null;
        }

        try {
            $nodeBinary = trim(shell_exec('which node') ?: 'node');
            $command = [$nodeBinary, $scriptPath, $absoluteSkpPath, $absoluteGlbPath];
            $process = Process::timeout(120)->run($command);

            if ($process->successful() && file_exists($absoluteGlbPath) && filesize($absoluteGlbPath) > 1000) {
                Log::info("ConvertSkpToGlbAction: Berhasil mengonversi {$relativeSkpPath} ke {$relativeGlbPath}");
                return $relativeGlbPath;
            }

            Log::warning("ConvertSkpToGlbAction: Gagal konversi .skp ke .glb", [
                'output' => $process->output(),
                'error'  => $process->errorOutput(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning("ConvertSkpToGlbAction exception: " . $e->getMessage());
            return null;
        }
    }
}

