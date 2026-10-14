<?php

namespace App\Domains\Project\Controllers;

use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Actions\DeleteCaptureAction;
use App\Domains\Project\Actions\SaveCaptureAction;
use App\Domains\Project\Actions\ToggleCaptureVisibilityAction;
use App\Domains\Project\Actions\UpdateProjectSunSettingsAction;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Project\Requests\StoreCaptureRequest;
use App\Domains\Project\Requests\UpdateProjectSunSettingsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectCaptureController extends Controller
{
    public function index(Request $request, string $projectId, SubscriptionLimitService $limitService): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $user = $request->user();
        $isOwner = $user && $project->user_id === $user->id;

        $query = ProjectCapture::where('project_id', $project->id)
            ->with(['user:id,name,avatar'])
            ->latest();

        // Klien hanya dapat melihat capture yang diizinkan tampil
        if (! $isOwner) {
            $query->where('is_visible_to_clients', true);
        }

        $captures = $query->get();

        $quotaInfo = null;
        if ($isOwner && $user) {
            $maxCaptures = $limitService->getMaxCapturesAllowed($user);
            $totalUserCaptures = ProjectCapture::where('user_id', $user->id)->count();

            $quotaInfo = [
                'max_captures'   => $maxCaptures,
                'current_count'  => $totalUserCaptures,
                'can_capture'    => $maxCaptures === null || $totalUserCaptures < $maxCaptures,
            ];
        }

        return response()->json([
            'data'       => $captures,
            'quota'      => $quotaInfo,
            'is_owner'   => $isOwner,
        ]);
    }

    public function store(StoreCaptureRequest $request, string $projectId, SaveCaptureAction $action): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $capture = $action->execute($request->user(), $project, $request->validated());

        return response()->json([
            'message' => 'Gambar capture berhasil disimpan.',
            'data'    => $capture,
        ], 201);
    }

    public function destroy(Request $request, string $projectId, string $captureId, DeleteCaptureAction $action): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $capture = ProjectCapture::where('project_id', $project->id)->findOrFail($captureId);

        $action->execute($request->user(), $project, $capture);

        return response()->json([
            'message' => 'Gambar berhasil dihapus.',
        ]);
    }

    public function toggleVisibility(Request $request, string $projectId, string $captureId, ToggleCaptureVisibilityAction $action): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $capture = ProjectCapture::where('project_id', $project->id)->findOrFail($captureId);

        $isVisible = $request->has('is_visible_to_clients') ? $request->boolean('is_visible_to_clients') : null;
        $updated = $action->execute($request->user(), $project, $capture, $isVisible);

        return response()->json([
            'message' => 'Visibilitas capture berhasil diperbarui.',
            'data'    => $updated,
        ]);
    }

    public function updatePrompt(
        \App\Domains\Project\Requests\UpdateCapturePromptRequest $request,
        string $projectId,
        string $captureId,
        \App\Domains\Project\Actions\UpdateCapturePromptAction $action
    ): JsonResponse {
        $project = Project::findOrFail($projectId);
        $capture = ProjectCapture::where('project_id', $project->id)->findOrFail($captureId);

        $updated = $action->execute($request->user(), $project, $capture, $request->input('prompt'));

        return response()->json([
            'message' => 'Prompt berhasil diperbarui.',
            'data'    => $updated,
        ]);
    }

    public function updateSunSettings(UpdateProjectSunSettingsRequest $request, string $projectId, UpdateProjectSunSettingsAction $action): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $updated = $action->execute($request->user(), $project, $request->validated());

        return response()->json([
            'message' => 'Pengaturan posisi matahari dan arah utara berhasil disimpan.',
            'data'    => [
                'latitude'    => $updated->latitude,
                'longitude'   => $updated->longitude,
                'north_angle' => $updated->north_angle,
                'solar_date'  => $updated->solar_date?->format('Y-m-d'),
            ],
        ]);
    }
}

