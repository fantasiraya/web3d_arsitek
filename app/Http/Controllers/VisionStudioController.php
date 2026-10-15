<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Actions\GenerateVisionPromptAction;
use App\Domains\VisionStudio\Models\VisionCreation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class VisionStudioController extends Controller
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    /**
     * Display the AI Vision Studio page with personal creations gallery.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $this->limitService->canAccessVisionStudio($user)) {
            return redirect()->route('plans.index')->with('warning', 'Fitur AI Vision Studio hanya tersedia untuk pengguna paket Pro dan Enterprise.');
        }

        $creations = VisionCreation::where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('VisionStudio/Index', [
            'initialCreations' => $creations,
        ]);
    }

    /**
     * Generate prompt using Vision AI for an uploaded image.
     */
    public function generatePrompt(
        Request $request,
        GenerateVisionPromptAction $action
    ): JsonResponse {
        $user = $request->user();

        if (! $this->limitService->canAccessVisionStudio($user)) {
            return response()->json([
                'message' => 'Fitur AI Vision Studio hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda.',
            ], 403);
        }

        $validated = $request->validate([
            'image'            => ['required', 'string'],
            'end_image'        => ['nullable', 'string'],
            'prompt_type'      => ['nullable', 'string', 'in:image,video'],
            'camera_motion'    => ['nullable', 'string'],
            'camera_motions'   => ['nullable', 'array'],
            'camera_motions.*' => ['string'],
            'video_target'     => ['nullable', 'string'],
            'duration'         => ['nullable', 'string'],
            'camera_pace'      => ['nullable', 'string'],
            'ambient_mood'     => ['nullable', 'string'],
            'lens_style'       => ['nullable', 'string'],
            'strict_geometry'  => ['nullable', 'boolean'],
            'scene_metadata'   => ['nullable', 'array'],
        ]);

        // If image is a local storage path (e.g. re-loaded from gallery), convert to base64
        $rawImage = $validated['image'];
        if (str_starts_with($rawImage, '/storage/')) {
            $storagePath = substr($rawImage, strlen('/storage/'));
            if (Storage::disk('public')->exists($storagePath)) {
                $fileBytes = Storage::disk('public')->get($storagePath);
                $mimeType = Storage::disk('public')->mimeType($storagePath) ?: 'image/png';
                $validated['image'] = 'data:' . $mimeType . ';base64,' . base64_encode($fileBytes);
            }
        }

        if (!empty($validated['end_image']) && str_starts_with($validated['end_image'], '/storage/')) {
            $storageEndPath = substr($validated['end_image'], strlen('/storage/'));
            if (Storage::disk('public')->exists($storageEndPath)) {
                $fileEndBytes = Storage::disk('public')->get($storageEndPath);
                $mimeEndType = Storage::disk('public')->mimeType($storageEndPath) ?: 'image/png';
                $validated['end_image'] = 'data:' . $mimeEndType . ';base64,' . base64_encode($fileEndBytes);
            }
        }

        $result = $action->execute($user, null, $validated);

        return response()->json($result);
    }

    /**
     * Save an uploaded image and prompt to the user's private Vision Studio Gallery.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->limitService->canAccessVisionStudio($user)) {
            return response()->json([
                'message' => 'Fitur AI Vision Studio hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda.',
            ], 403);
        }

        $validated = $request->validate([
            'image'           => ['required', 'string'],
            'end_image'       => ['nullable', 'string'],
            'room_name'       => ['nullable', 'string', 'max:255'],
            'title'           => ['nullable', 'string', 'max:255'],
            'prompt_type'     => ['nullable', 'string', 'in:image,video'],
            'prompt'          => ['nullable', 'string'],
            'negative_prompt' => ['nullable', 'string'],
            'width'           => ['nullable', 'integer'],
            'height'          => ['nullable', 'integer'],
            'metadata'        => ['nullable', 'array'],
        ]);

        // Process primary image
        $rawImage = $validated['image'];
        if (str_starts_with($rawImage, '/storage/')) {
            $storagePath = substr($rawImage, strlen('/storage/'));
            $decodedImage = Storage::disk('public')->exists($storagePath)
                ? Storage::disk('public')->get($storagePath)
                : false;
        } else {
            if (str_contains($rawImage, ',')) {
                $rawImage = explode(',', $rawImage)[1];
            }
            $decodedImage = base64_decode($rawImage);
        }

        if ($decodedImage === false) {
            return response()->json(['message' => 'Format gambar utama tidak valid.'], 422);
        }

        $creationId = (string) Str::uuid();
        $fileName = "{$creationId}.png";
        $filePath = "vision-studio/{$user->id}/{$fileName}";
        Storage::disk('public')->put($filePath, $decodedImage);
        $fileSize = strlen($decodedImage);

        // Process secondary end image if exists
        $endFilePath = null;
        if (!empty($validated['end_image'])) {
            $rawEnd = $validated['end_image'];
            if (str_contains($rawEnd, ',')) {
                $rawEnd = explode(',', $rawEnd)[1];
            }
            $decodedEnd = base64_decode($rawEnd);
            if ($decodedEnd !== false) {
                $endFileName = "{$creationId}-end.png";
                $endFilePath = "vision-studio/{$user->id}/{$endFileName}";
                Storage::disk('public')->put($endFilePath, $decodedEnd);
            }
        }

        $creation = VisionCreation::create([
            'id'              => $creationId,
            'user_id'         => $user->id,
            'title'           => $validated['title'] ?? null,
            'room_name'       => $validated['room_name'] ?? 'Arsitektur',
            'prompt_type'     => $validated['prompt_type'] ?? 'image',
            'file_path'       => $filePath,
            'end_file_path'   => $endFilePath,
            'file_size_bytes' => $fileSize,
            'width'           => (int) ($validated['width'] ?? 1920),
            'height'          => (int) ($validated['height'] ?? 1080),
            'prompt'          => $validated['prompt'] ?? null,
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'metadata'        => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'message'  => 'Berhasil disimpan ke Galeri Studio!',
            'creation' => $creation,
        ], 201);
    }

    /**
     * Update an item in the user's Vision Studio Gallery.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->limitService->canAccessVisionStudio($user)) {
            return response()->json([
                'message' => 'Fitur AI Vision Studio hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda.',
            ], 403);
        }

        $creation = VisionCreation::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'room_name'       => ['nullable', 'string', 'max:255'],
            'title'           => ['nullable', 'string', 'max:255'],
            'prompt'          => ['nullable', 'string'],
            'negative_prompt' => ['nullable', 'string'],
        ]);

        $creation->update($validated);

        return response()->json([
            'message'  => 'Karya berhasil diperbarui.',
            'creation' => $creation->fresh(),
        ]);
    }

    /**
     * Delete an item from the user's Vision Studio Gallery.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $this->limitService->canAccessVisionStudio($user)) {
            return response()->json([
                'message' => 'Fitur AI Vision Studio hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda.',
            ], 403);
        }

        $creation = VisionCreation::where('user_id', $user->id)->findOrFail($id);
        $creation->delete();

        return response()->json([
            'message' => 'Karya berhasil dihapus dari Galeri Studio.',
        ]);
    }
}
