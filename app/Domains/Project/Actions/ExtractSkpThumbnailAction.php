<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ExtractSkpThumbnailAction
{
    /**
     * Extract thumbnail from SketchUp (.skp) file and register it as a ProjectCapture.
     */
    public function execute(Project $project, User $user, string $absoluteSkpPath): ?ProjectCapture
    {
        try {
            $pngData = $this->extractPngData($absoluteSkpPath);

            if (! $pngData) {
                $pngData = $this->generatePlaceholderPng($project->title);
            }

            if (! $pngData) {
                return null;
            }

            $disk = Storage::disk('public');
            $dir = "projects/{$project->id}/captures";
            $filename = 'skp_thumb_' . Str::random(16) . '.png';
            $relativeCapturePath = "{$dir}/{$filename}";

            $disk->put($relativeCapturePath, $pngData);

            $imageInfo = @getimagesizefromstring($pngData);
            $width = $imageInfo ? (int) $imageInfo[0] : 1280;
            $height = $imageInfo ? (int) $imageInfo[1] : 720;
            $fileSizeBytes = strlen($pngData);

            // Create ProjectCapture entry so it automatically functions as project thumbnail
            return ProjectCapture::create([
                'id'                    => (string) Str::uuid(),
                'code'                  => 'SKP-01',
                'project_id'            => $project->id,
                'user_id'               => $user->id,
                'title'                 => 'Pratinjau Model SketchUp',
                'room_name'             => 'Model SketchUp (.skp)',
                'captured_hour'         => '12:00',
                'file_path'             => $relativeCapturePath,
                'file_size_bytes'       => $fileSizeBytes,
                'width'                 => $width,
                'height'                => $height,
                'is_visible_to_clients' => true,
                'prompt'                => 'Pratinjau gambar 3D dari file SketchUp (.skp)',
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExtractSkpThumbnailAction failed: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'path'       => $absoluteSkpPath,
            ]);

            return null;
        }
    }

    /**
     * Extract PNG bytes from SketchUp (.skp) file.
     * Supports both modern SketchUp (VFF / ZIP container) and legacy embedded streams.
     */
    public function extractPngData(string $filePath): ?string
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return null;
        }

        // Read file contents (limit to reasonable size or stream)
        $fileSize = filesize($filePath);
        if ($fileSize <= 0) {
            return null;
        }

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            return null;
        }

        // Read up to 25MB to locate embedded thumbnail / zip container
        $maxScan = min($fileSize, 25 * 1024 * 1024);
        $content = fread($handle, $maxScan);
        fclose($handle);

        if (! $content) {
            return null;
        }

        // ── 1. Cek VFF Format (SketchUp 2021+) yang berbasis ZIP container ────
        $zipSigPos = strpos($content, "PK\x03\x04");
        if ($zipSigPos !== false) {
            $zipData = substr($content, $zipSigPos);
            $tmpZip = tempnam(sys_get_temp_dir(), 'skp_zip_');
            if ($tmpZip && file_put_contents($tmpZip, $zipData)) {
                $zip = new ZipArchive();
                if ($zip->open($tmpZip) === true) {
                    // Cek nama thumbnail standar SketchUp
                    $thumbnailCandidates = [
                        'meta/model_thumbnail.png',
                        'meta/thumbnail.png',
                        'doc_thumbnail.png',
                        'thumbnail.png',
                    ];

                    foreach ($thumbnailCandidates as $candidate) {
                        if ($zip->locateName($candidate) !== false) {
                            $extracted = $zip->getFromName($candidate);
                            if ($extracted && $this->isValidPng($extracted)) {
                                $zip->close();
                                @unlink($tmpZip);
                                return $extracted;
                            }
                        }
                    }

                    // Jika tidak ditemukan candidate persis, cari entry yang memiliki kata thumbnail / preview
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);
                        if (preg_match('/(thumbnail|preview|model_thumb|doc_thumb).*\.png$/i', $name)) {
                            $extracted = $zip->getFromIndex($i);
                            if ($extracted && $this->isValidThumbnailPng($extracted)) {
                                $zip->close();
                                @unlink($tmpZip);
                                return $extracted;
                            }
                        }
                    }

                    $zip->close();
                }
                @unlink($tmpZip);
            }
        }

        // ── 2. Cek Embedded Raw PNG Stream (Format OLE SketchUp lama atau raw bytes) ─
        $pngMagic = "\x89PNG\r\n\x1a\n";
        $pngStart = strpos($content, $pngMagic);
        if ($pngStart !== false) {
            $iendMagic = "IEND\xae\x42\x60\x82";
            $iendPos = strpos($content, $iendMagic, $pngStart);
            if ($iendPos !== false) {
                $candidatePng = substr($content, $pngStart, ($iendPos + 8) - $pngStart);
                if ($this->isValidThumbnailPng($candidatePng)) {
                    return $candidatePng;
                }
            }
        }

        return null;
    }

    /**
     * Check if binary data is a valid PNG thumbnail (not a large texture atlas / material).
     */
    protected function isValidThumbnailPng(string $data): bool
    {
        if (strlen($data) < 8 || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }

        $info = @getimagesizefromstring($data);
        if (! $info) {
            return false;
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $fileSize = strlen($data);

        // Jika ukuran file > 1.5MB atau resolusi >= 1024x1024, ini adalah texture atlas / UV material, bukan thumbnail
        if ($fileSize > 1.5 * 1024 * 1024 || ($width >= 1024 && $height >= 1024)) {
            return false;
        }

        return true;
    }

    /**
     * Check if binary data is a valid PNG image.
     */
    protected function isValidPng(string $data): bool
    {
        return $this->isValidThumbnailPng($data);
    }

    /**
     * Generate fallback preview PNG card when .skp has no internal thumbnail.
     */
    protected function generatePlaceholderPng(string $title): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $width = 1200;
        $height = 675; // 16:9 aspect ratio

        $image = imagecreatetruecolor($width, $height);
        if (! $image) {
            return null;
        }

        // Background dark slate gradient
        $bgColor = imagecolorallocate($image, 15, 23, 42); // #0f172a
        imagefill($image, 0, 0, $bgColor);

        // Grid lines (blueprint style)
        $gridColor = imagecolorallocatealpha($image, 255, 255, 255, 115);
        for ($x = 0; $x < $width; $x += 40) {
            imageline($image, $x, 0, $x, $height, $gridColor);
        }
        for ($y = 0; $y < $height; $y += 40) {
            imageline($image, 0, $y, $width, $y, $gridColor);
        }

        // Center card
        $cardBg = imagecolorallocate($image, 30, 41, 59); // #1e293b
        imagefilledrectangle($image, 300, 160, 900, 515, $cardBg);

        $borderColor = imagecolorallocate($image, 239, 68, 68); // #ef4444 (SketchUp red)
        imagerectangle($image, 300, 160, 900, 515, $borderColor);

        // Texts
        $redBadgeColor = imagecolorallocate($image, 220, 38, 38);
        imagefilledrectangle($image, 480, 200, 720, 245, $redBadgeColor);

        $white = imagecolorallocate($image, 255, 255, 255);
        $gray = imagecolorallocate($image, 148, 163, 184);

        $badgeText = 'SKETCHUP (.SKP)';
        imagestring($image, 5, 510, 215, $badgeText, $white);

        $cleanTitle = Str::limit($title, 40);
        imagestring($image, 5, 340, 290, "PROYEK: " . strtoupper($cleanTitle), $white);

        $info1 = 'Model 3D arsitektur SketchUp berhasil dimuat.';
        $info2 = 'Tersedia pratinjau gambar 3D & pin revisi interaktif.';
        imagestring($image, 4, 340, 360, $info1, $gray);
        imagestring($image, 4, 340, 390, $info2, $gray);

        ob_start();
        imagepng($image);
        $pngData = ob_get_clean();
        imagedestroy($image);

        return $pngData ?: null;
    }
}
