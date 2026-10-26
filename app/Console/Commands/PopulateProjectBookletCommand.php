<?php

namespace App\Console\Commands;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Actions\CreateDrawingSheetAction;
use App\Domains\Drawing\Actions\SyncDrawingElementsAction;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Project\Models\Project;
use App\Domains\Rab\Actions\GenerateRabFromWorksheetAction;
use App\Domains\Rab\Models\RabDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PopulateProjectBookletCommand extends Command
{
    protected $signature = 'project:populate-booklet {project_id=01a1733d-af91-715a-bdc4-4712d5e4f093}';
    protected $description = 'Populate complete working sheets, elements, and RAB metadata for project';

    public function handle(
        CreateDrawingSheetAction $createSheetAction,
        SyncDrawingElementsAction $syncElementsAction,
        GenerateRabFromWorksheetAction $generateRabAction
    ): int {
        $projectId = $this->argument('project_id');
        $this->info("Setting up booklet structure for project: {$projectId}");

        $project = Project::findOrFail($projectId);
        $user = User::findOrFail($project->user_id);

        DB::beginTransaction();
        try {
            // 1. Update User Profile to Match TRIVES Studio
            $user->update([
                'name'         => 'FADIL',
                'company_name' => 'TRIVES',
                'company_type' => 'Studio Arsitektur',
                'city_name'    => 'Jember',
            ]);
            $this->info("Updated user profile: {$user->name} ({$user->company_name})");

            // 2. Update Project Metadata
            $project->update([
                'title'          => 'Renovasi & Interior SEMANGKOKae',
                'description'    => 'Renovasi & Interior SEMANGKOKae',
                'client_name'    => 'Mr. Jerry',
                'client_address' => 'JL. Sentot Prawirodirdjo, Kaliwates, Jember',
                'solar_date'     => '2026-09-15',
            ]);
            $this->info("Updated project metadata: {$project->title}");

            // 3. Prepare Storage Directories
            $sheetDir = "projects/{$project->id}/sheets";
            $captureDir = "projects/{$project->id}/captures";
            $keyPlanDir = "projects/{$project->id}/captures/keyplans";

            Storage::disk('public')->makeDirectory($sheetDir);
            Storage::disk('public')->makeDirectory($captureDir);
            Storage::disk('public')->makeDirectory($keyPlanDir);

            // 4. Save Master Denah Image
            $denahSrc = '/tmp/pdf_extract/denah_clean.png';
            $denahDestRel = "{$sheetDir}/sheet_denah_master.png";
            if (file_exists($denahSrc)) {
                Storage::disk('public')->put($denahDestRel, file_get_contents($denahSrc));
                $this->info("Master floor plan saved to storage: {$denahDestRel}");
            }

            // 5. Create or Update Master DrawingSheet
            $existingSheet = DrawingSheet::where('project_id', $project->id)
                ->where('title', 'DENAH')
                ->first();

            if (! $existingSheet) {
                $drawingSheet = $createSheetAction->execute($user, $project, [
                    'title'          => 'DENAH',
                    'type'           => DrawingSheet::TYPE_PLAN,
                    'scale'          => '1:100',
                    'paper_size'     => 'A3',
                    'orientation'    => 'landscape',
                    'width'          => 2050,
                    'height'         => 1660,
                    'grid_spacing'   => 20,
                    'unit'           => 'm',
                    'client_name'    => 'Mr. Jerry',
                    'client_address' => 'JL. Sentot Prawirodirdjo, Kaliwates, Jember',
                ]);
            } else {
                $drawingSheet = $existingSheet;
                $drawingSheet->update([
                    'type'        => DrawingSheet::TYPE_PLAN,
                    'scale'       => '1:100',
                    'paper_size'  => 'A3',
                    'orientation' => 'landscape',
                    'width'       => 2050,
                    'height'      => 1660,
                    'unit'        => 'm',
                ]);
            }

            $drawingSheet->update(['image_path' => $denahDestRel]);
            $this->info("DrawingSheet ready: ID {$drawingSheet->id}");

            // 6. Define & Sync Drawing Elements (Room labels for KeyPlan and Canvas)
            $roomElementsDef = [
                ['id' => '01a1733f-0001-7000-8000-000000000001', 'name' => 'TERAS', 'code' => 'R-01', 'x' => 600, 'y' => 700, 'sub' => 'Area Duduk Luar'],
                ['id' => '01a1733f-0002-7000-8000-000000000002', 'name' => 'KASIR', 'code' => 'R-02', 'x' => 750, 'y' => 550, 'sub' => 'Meja Kasir & Order'],
                ['id' => '01a1733f-0003-7000-8000-000000000003', 'name' => 'AREA 1', 'code' => 'R-03', 'x' => 580, 'y' => 470, 'sub' => 'Dining Hall 1'],
                ['id' => '01a1733f-0004-7000-8000-000000000004', 'name' => 'AREA 2', 'code' => 'R-04', 'x' => 580, 'y' => 380, 'sub' => 'Dining Hall 2'],
                ['id' => '01a1733f-0005-7000-8000-000000000005', 'name' => 'AREA 3', 'code' => 'R-04', 'x' => 580, 'y' => 310, 'sub' => 'Dining Hall 3'],
                ['id' => '01a1733f-0006-7000-8000-000000000006', 'name' => 'AREA 4', 'code' => 'R-05', 'x' => 720, 'y' => 280, 'sub' => 'Lounge Tengah'],
                ['id' => '01a1733f-0007-7000-8000-000000000007', 'name' => 'AREA 5', 'code' => 'R-06', 'x' => 720, 'y' => 140, 'sub' => 'Outdoor Semi-Terbuka'],
                ['id' => '01a1733f-0008-7000-8000-000000000008', 'name' => 'AREA VIP', 'code' => 'R-07', 'x' => 840, 'y' => 200, 'sub' => 'Ruang Privat / Meeting'],
                ['id' => '01a1733f-0009-7000-8000-000000000009', 'name' => 'DAPUR', 'code' => null, 'x' => 560, 'y' => 200, 'sub' => 'Kitchen & Barista'],
                ['id' => '01a1733f-0010-7000-8000-000000000010', 'name' => 'AREA KM', 'code' => 'R-09', 'x' => 680, 'y' => 400, 'sub' => 'Toilet & Wastafel'],
                ['id' => '01a1733f-0011-7000-8000-000000000011', 'name' => 'GUDANG', 'code' => null, 'x' => 800, 'y' => 450, 'sub' => 'Storage Room'],
                ['id' => '01a1733f-0012-7000-8000-000000000012', 'name' => 'AREA PARKIR', 'code' => null, 'x' => 600, 'y' => 800, 'sub' => 'Parkir Pengunjung'],
            ];

            $syncElements = [];
            foreach ($roomElementsDef as $idx => $r) {
                $syncElements[] = [
                    'id'         => $r['id'],
                    'type'       => 'room_label',
                    'sort_order' => $idx + 1,
                    'data'       => [
                        'x'            => $r['x'],
                        'y'            => $r['y'],
                        'text'         => "{$r['name']}\n{$r['sub']}",
                        'label'        => $r['name'],
                        'capture_code' => $r['code'],
                    ],
                ];
            }

            $syncElementsAction->execute($user, $drawingSheet, $syncElements, [
                'width'       => 2050,
                'height'      => 1660,
                'paper_size'  => 'A3',
                'orientation' => 'landscape',
            ]);
            $this->info('Synced 12 DrawingElement room records.');

            // 7. Architectural RAB Document
            $existingRab = RabDocument::where('project_id', $project->id)->first();
            if (! $existingRab) {
                $rabDoc = $generateRabAction->execute($user, $project, [
                    'title' => 'RAB Renovasi & Interior SEMANGKOKae',
                ]);
                $rabDoc->update(['is_visible_to_clients' => true]);
                $this->info("Generated RAB Document: {$rabDoc->title}");
            }

            DB::commit();
            $this->info('Structure setup complete. Captures gallery is ready for 3D snapshots!');
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
