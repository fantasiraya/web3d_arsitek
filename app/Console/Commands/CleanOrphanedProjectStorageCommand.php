<?php

namespace App\Console\Commands;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanedProjectStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:clean-storage {--dry-run : List orphaned files/folders without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned project directories and GLB models from storage disk';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        $activeProjectIds = Project::pluck('id')->map(fn ($id) => (string) $id)->all();
        $activeFilePaths = array_unique(array_merge(
            Project::pluck('file_path')->filter()->all(),
            ProjectVersion::pluck('file_path')->filter()->all()
        ));

        $this->info("Found " . count($activeProjectIds) . " active project(s) in the database.");

        $directories = $disk->directories('projects');
        $cleanedFolders = 0;
        $freedBytes = 0;

        foreach ($directories as $dir) {
            $folderName = basename($dir);

            // Skip special directory
            if ($folderName === 'models') {
                continue;
            }

            if (! in_array($folderName, $activeProjectIds, true)) {
                $files = $disk->allFiles($dir);
                $folderBytes = 0;
                foreach ($files as $f) {
                    $folderBytes += $disk->size($f) ?: 0;
                }

                $freedBytes += $folderBytes;
                $cleanedFolders++;

                $sizeFormatted = $this->formatBytes($folderBytes);
                if ($isDryRun) {
                    $this->line("[Dry Run] Would delete orphaned folder: {$dir} ({$sizeFormatted})");
                } else {
                    $disk->deleteDirectory($dir);
                    $this->line("Deleted orphaned folder: {$dir} ({$sizeFormatted})");
                }
            }
        }

        // Clean orphaned files in projects/models
        $cleanedFiles = 0;
        if ($disk->directoryExists('projects/models')) {
            $modelFiles = $disk->files('projects/models');
            foreach ($modelFiles as $file) {
                if (! in_array($file, $activeFilePaths, true)) {
                    $fileBytes = $disk->size($file) ?: 0;
                    $freedBytes += $fileBytes;
                    $cleanedFiles++;

                    $sizeFormatted = $this->formatBytes($fileBytes);
                    if ($isDryRun) {
                        $this->line("[Dry Run] Would delete unreferenced model: {$file} ({$sizeFormatted})");
                    } else {
                        $disk->delete($file);
                        $this->line("Deleted unreferenced model: {$file} ({$sizeFormatted})");
                    }
                }
            }
        }

        $totalFreedFormatted = $this->formatBytes($freedBytes);
        $this->newLine();
        if ($isDryRun) {
            $this->info("Dry run completed. {$cleanedFolders} orphaned folder(s) and {$cleanedFiles} unreferenced model(s) ({$totalFreedFormatted}) can be cleaned.");
        } else {
            $this->info("Storage cleanup completed! Removed {$cleanedFolders} orphaned folder(s) and {$cleanedFiles} unreferenced model(s). Total freed: {$totalFreedFormatted}.");
        }

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
