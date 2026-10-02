<?php

namespace App\Domains\Project\Controllers;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCameraPreset;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CameraPresetController extends Controller
{
    /**
     * GET /projects/{project}/camera-presets
     * List semua preset kamera milik project — bisa diakses arsitek & klien.
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $presets = ProjectCameraPreset::where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn (ProjectCameraPreset $p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'position'   => ['x' => $p->position_x, 'y' => $p->position_y, 'z' => $p->position_z],
                'target'     => ['x' => $p->target_x,   'y' => $p->target_y,   'z' => $p->target_z],
                'sort_order' => $p->sort_order,
                'created_by' => $p->created_by,
            ]);

        return response()->json(['data' => $presets]);
    }

    /**
     * POST /projects/{project}/camera-presets
     * Simpan preset baru — hanya arsitek pemilik project.
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        // Hanya arsitek pemilik yang boleh menyimpan preset
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat menyimpan preset kamera.');
        }

        // Cek limit 8 preset per project
        $count = ProjectCameraPreset::where('project_id', $project->id)->count();
        if ($count >= ProjectCameraPreset::MAX_PER_PROJECT) {
            return response()->json([
                'message' => 'Maksimal ' . ProjectCameraPreset::MAX_PER_PROJECT . ' preset per proyek. Hapus preset yang tidak digunakan.',
            ], 422);
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:80'],
            'position_x' => ['required', 'numeric'],
            'position_y' => ['required', 'numeric'],
            'position_z' => ['required', 'numeric'],
            'target_x'   => ['required', 'numeric'],
            'target_y'   => ['required', 'numeric'],
            'target_z'   => ['required', 'numeric'],
        ]);

        $preset = ProjectCameraPreset::create([
            'project_id'  => $project->id,
            'created_by'  => $request->user()->id,
            'name'        => $validated['name'],
            'position_x'  => $validated['position_x'],
            'position_y'  => $validated['position_y'],
            'position_z'  => $validated['position_z'],
            'target_x'    => $validated['target_x'],
            'target_y'    => $validated['target_y'],
            'target_z'    => $validated['target_z'],
            'sort_order'  => $count, // append di akhir
        ]);

        return response()->json([
            'data' => [
                'id'         => $preset->id,
                'name'       => $preset->name,
                'position'   => ['x' => $preset->position_x, 'y' => $preset->position_y, 'z' => $preset->position_z],
                'target'     => ['x' => $preset->target_x,   'y' => $preset->target_y,   'z' => $preset->target_z],
                'sort_order' => $preset->sort_order,
                'created_by' => $preset->created_by,
            ],
        ], 201);
    }

    /**
     * DELETE /projects/{project}/camera-presets/{preset}
     * Hapus preset — hanya arsitek pemilik project.
     */
    public function destroy(Request $request, string $projectId, string $presetId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat menghapus preset kamera.');
        }

        $preset = ProjectCameraPreset::where('project_id', $project->id)
            ->findOrFail($presetId);

        $preset->delete();

        // Re-index sort_order agar tetap rapi
        ProjectCameraPreset::where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->each(function (ProjectCameraPreset $p, int $idx) {
                $p->update(['sort_order' => $idx]);
            });

        return response()->json(['message' => 'Preset berhasil dihapus.']);
    }
}
