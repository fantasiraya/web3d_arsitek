<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SaveCaptureAction
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function execute(User $user, Project $project, array $data): ProjectCapture
    {
        // 1. Authorization: Only the architect (project owner) can save captures
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat menyimpan gambar ke galeri.');
        }

        return \Illuminate\Support\Facades\Cache::lock("user:{$user->id}:create_capture", 15)->block(5, function () use ($user, $project, $data) {
            // 2. Quota check: Enforce per-image quota based on active subscription
            $check = $this->limitService->canCreateCapture($user, $project);
            if (! $check['allowed']) {
                abort(403, $check['reason'] ?? 'Batas kuota gambar untuk paket Anda telah tercapai.');
            }

            // 3. Process base64 image data
            $imageData = $data['image'];
            if (str_contains($imageData, ',')) {
                $imageData = explode(',', $imageData)[1];
            }

            $decoded = base64_decode($imageData);
            if ($decoded === false) {
                abort(422, 'Format data gambar base64 tidak valid.');
            }

            $captureId = (string) Str::uuid();
            $fileName = "{$captureId}.png";
            $filePath = "projects/{$project->id}/captures/{$fileName}";

            Storage::disk('public')->put($filePath, $decoded);
            $fileSize = strlen($decoded);

            return ProjectCapture::create([
                'id'                    => $captureId,
                'project_id'            => $project->id,
                'user_id'               => $user->id,
                'title'                 => $data['title'] ?? null,
                'room_name'             => $data['room_name'] ?? 'Sudut Bebas',
                'captured_hour'         => $data['captured_hour'] ?? '12:00',
                'file_path'             => $filePath,
                'file_size_bytes'       => $fileSize,
                'width'                 => (int) ($data['width'] ?? 1920),
                'height'                => (int) ($data['height'] ?? 1080),
                'is_visible_to_clients' => (bool) ($data['is_visible_to_clients'] ?? false),
                'prompt'                => $data['prompt'] ?? null,
            ]);
        });
    }
}

