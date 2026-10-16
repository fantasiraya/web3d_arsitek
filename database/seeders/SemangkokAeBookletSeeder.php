<?php

namespace Database\Seeders;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingElement;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SemangkokAeBookletSeeder extends Seeder
{
    public function run(): void
    {
        $projectId = '01a10cfe-f0e5-73fa-af1d-960c69245083';
        $userId = '01a10be9-9f95-71a7-a5a9-9626262296af';

        // 1. Update Architect User to match PDF (FADIL, TRIVES, Jember)
        $user = User::find($userId) ?? User::where('email', 'test@gmail.com')->first();
        if ($user) {
            $user->update([
                'name'         => 'FADIL',
                'company_name' => 'TRIVES',
                'company_type' => 'Architectural Studio',
                'city_name'    => 'Jember',
            ]);
            $userId = $user->id;
        }

        // 2. Update Project to match PDF (Renovasi & Interior SEMANGKOKae)
        $project = Project::find($projectId);
        if (! $project) {
            $project = Project::create([
                'id'                     => $projectId,
                'user_id'                => $userId,
                'title'                  => 'Renovasi & Interior SEMANGKOKae',
                'slug'                   => 'renovasi-interior-semangkokae',
                'description'            => 'JL. Sentot Prawirodirdjo, Kaliwates, Jember',
                'client_name'            => 'Mr. Jerry',
                'client_address'         => 'JL. Sentot Prawirodirdjo, Kaliwates, Jember',
                'file_path'              => 'projects/models/semangkokae.glb',
                'file_size_bytes'        => 1024000,
                'is_draco_compressed'    => false,
                'share_token'            => Str::random(32),
                'max_revisions_allowed'  => 5,
                'current_revision_count' => 1,
            ]);
        } else {
            $project->update([
                'title'          => 'Renovasi & Interior SEMANGKOKae',
                'description'    => 'JL. Sentot Prawirodirdjo, Kaliwates, Jember',
                'client_name'    => 'Mr. Jerry',
                'client_address' => 'JL. Sentot Prawirodirdjo, Kaliwates, Jember',
            ]);
        }


        // 3. Ensure target directory exists for project captures
        $storageDir = storage_path("app/public/projects/{$project->id}/captures");
        File::ensureDirectoryExists($storageDir);

        $extractedDir = '/home/fatkulumar/.gemini/antigravity/brain/f78f6cda-f59d-4038-abca-6f51ab4628f7/scratch/extracted_pdf_images';

        // 4. Create or Update Master Floor Plan Sheet ("DENAH")
        $denahSheet = DrawingSheet::where('project_id', $project->id)
            ->where('title', 'DENAH')
            ->first();

        if (! $denahSheet) {
            $denahSheet = DrawingSheet::create([
                'id'           => Str::uuid()->toString(),
                'project_id'   => $project->id,
                'user_id'      => $userId,
                'title'        => 'DENAH',
                'type'         => DrawingSheet::TYPE_PLAN,
                'scale'        => '1:100',
                'paper_size'   => 'A3',
                'orientation'  => 'landscape',
                'width'        => 1440,
                'height'       => 810,
                'grid_spacing' => 20,
                'unit'         => 'cm',
                'sort_order'   => 1,
            ]);
        } else {
            // Clear existing elements to rebuild accurate floor plan
            DrawingElement::where('drawing_sheet_id', $denahSheet->id)->delete();
        }

        // 5. Populate CAD Elements for DENAH (Flooring, Walls, Columns, Doors, Windows, Furniture, Dimensions, Room Labels)
        $elementsToInsert = [];
        $order = 1;

        // 5A. FLOORING & ZONES (Lantai Kayu Parquet, Keramik, Teras Paving, Jalan)
        $zones = [
            // Jalan Utama (Aspal Hitam + Garis Kuning)
            ['type' => 'zone', 'data' => ['x' => 450, 'y' => 740, 'width' => 520, 'height' => 60, 'category' => 'road']],
            // Area Parkir (Paving Abu-abu Bersih)
            ['type' => 'zone', 'data' => ['x' => 480, 'y' => 590, 'width' => 460, 'height' => 150, 'fill' => '#f8fafc', 'stroke' => '#e2e8f0']],
            // Teras Depan
            ['type' => 'zone', 'data' => ['x' => 560, 'y' => 520, 'width' => 200, 'height' => 70, 'fill' => '#f1f5f9', 'stroke' => '#cbd5e1']],
            // Lantai Kayu / Parquet Area Santap (Area 1, 2, 3)
            ['type' => 'floor_finish', 'data' => ['x' => 480, 'y' => 240, 'width' => 160, 'height' => 280, 'floor_type' => 'wood']],
            // Lantai Kayu / Parquet Area 4
            ['type' => 'floor_finish', 'data' => ['x' => 640, 'y' => 200, 'width' => 140, 'height' => 170, 'floor_type' => 'wood']],
            // Lantai Kayu / Parquet Area 5
            ['type' => 'floor_finish', 'data' => ['x' => 640, 'y' => 80, 'width' => 140, 'height' => 120, 'floor_type' => 'wood']],
            // Lantai Kayu / Parquet Eksklusif Area VIP
            ['type' => 'floor_finish', 'data' => ['x' => 780, 'y' => 80, 'width' => 160, 'height' => 290, 'floor_type' => 'wood']],
            // Lantai Keramik Dapur
            ['type' => 'floor_finish', 'data' => ['x' => 480, 'y' => 80, 'width' => 160, 'height' => 160, 'floor_type' => 'tile']],
            // Lantai Keramik Kamar Mandi (KM)
            ['type' => 'floor_finish', 'data' => ['x' => 610, 'y' => 420, 'width' => 70, 'height' => 100, 'floor_type' => 'tile']],
            // Lantai Kasir & Gudang
            ['type' => 'zone', 'data' => ['x' => 680, 'y' => 370, 'width' => 100, 'height' => 150, 'fill' => '#ffffff', 'stroke' => '#e2e8f0']],
            ['type' => 'zone', 'data' => ['x' => 780, 'y' => 370, 'width' => 160, 'height' => 150, 'fill' => '#f8fafc', 'stroke' => '#e2e8f0']],
        ];

        foreach ($zones as $z) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => $z['type'],
                'data'             => $z['data'],
                'sort_order'       => $order++,
            ]);
        }

        // 5B. PERABOTAN & FURNITURE (Meja Makan, Kursi, Meja Kasir, Kitchen, Toilet)
        $furniture = [
            // Meja & Kursi Area 1
            ['type' => 'table_dining', 'data' => ['x' => 520, 'y' => 445, 'width' => 50, 'height' => 28]],
            // Meja & Kursi Area 2
            ['type' => 'table_dining', 'data' => ['x' => 520, 'y' => 365, 'width' => 50, 'height' => 28]],
            // Meja & Kursi Area 3
            ['type' => 'table_dining', 'data' => ['x' => 520, 'y' => 275, 'width' => 50, 'height' => 28]],
            // Meja & Kursi Area 4
            ['type' => 'table_dining', 'data' => ['x' => 670, 'y' => 245, 'width' => 55, 'height' => 30]],
            ['type' => 'table_dining', 'data' => ['x' => 670, 'y' => 315, 'width' => 55, 'height' => 30]],
            // Meja & Kursi Area 5
            ['type' => 'table_dining', 'data' => ['x' => 670, 'y' => 115, 'width' => 55, 'height' => 30]],
            // Meja Bundar Area VIP (Private Dining)
            ['type' => 'table_round', 'data' => ['x' => 850, 'y' => 150, 'radius' => 22]],
            ['type' => 'table_round', 'data' => ['x' => 850, 'y' => 280, 'radius' => 22]],
            // Meja Bundar Teras Outdoor
            ['type' => 'table_round', 'data' => ['x' => 600, 'y' => 555, 'radius' => 16]],
            ['type' => 'table_round', 'data' => ['x' => 720, 'y' => 555, 'radius' => 16]],
            // Counter Meja Kasir & Barista
            ['type' => 'counter_bar', 'data' => ['x' => 685, 'y' => 490, 'width' => 90, 'height' => 22]],
            // Meja Dapur / Kitchen Island Service
            ['type' => 'kitchen_table', 'data' => ['x' => 500, 'y' => 110, 'width' => 75, 'height' => 28]],
            // Kloset Kamar Mandi
            ['type' => 'toilet', 'data' => ['x' => 635, 'y' => 445]],
        ];

        foreach ($furniture as $f) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => $f['type'],
                'data'             => $f['data'],
                'sort_order'       => $order++,
            ]);
        }

        // 5C. DIMENSION LINES (Ukuran Satuan Meter Arsitektural Terukur)
        $dimensions = [
            // Dimensi Total Lebar Bangunan Atas (12.00 m)
            ['x1' => 480, 'y1' => 45, 'x2' => 940, 'y2' => 45, 'text' => '12.00 m'],
            // Dimensi Parsial Atas: Dapur (4.00 m), Area 5 (3.50 m), VIP (4.50 m)
            ['x1' => 480, 'y1' => 65, 'x2' => 640, 'y2' => 65, 'text' => '4.00 m'],
            ['x1' => 640, 'y1' => 65, 'x2' => 780, 'y2' => 65, 'text' => '3.50 m'],
            ['x1' => 780, 'y1' => 65, 'x2' => 940, 'y2' => 65, 'text' => '4.50 m'],
            // Dimensi Total Panjang Bangunan Sisi Kiri (11.00 m)
            ['x1' => 435, 'y1' => 80, 'x2' => 435, 'y2' => 520, 'text' => '11.00 m'],
            // Dimensi Parsial Kiri: Dapur (4.00 m), Area 3 (2.50 m), Area 2 (2.00 m), Area 1 (2.50 m)
            ['x1' => 455, 'y1' => 80, 'x2' => 455, 'y2' => 240, 'text' => '4.00 m'],
            ['x1' => 455, 'y1' => 240, 'x2' => 455, 'y2' => 340, 'text' => '2.50 m'],
            ['x1' => 455, 'y1' => 340, 'x2' => 455, 'y2' => 420, 'text' => '2.00 m'],
            ['x1' => 455, 'y1' => 420, 'x2' => 455, 'y2' => 520, 'text' => '2.50 m'],
            // Dimensi Teras Depan (2.50 m)
            ['x1' => 535, 'y1' => 520, 'x2' => 535, 'y2' => 590, 'text' => '2.50 m'],
            // Dimensi Lebar Teras (6.00 m)
            ['x1' => 560, 'y1' => 610, 'x2' => 760, 'y2' => 610, 'text' => '6.00 m'],
            // Dimensi Area Parkir (4.50 m)
            ['x1' => 445, 'y1' => 590, 'x2' => 445, 'y2' => 740, 'text' => '4.50 m'],
        ];

        foreach ($dimensions as $dim) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => 'dimension',
                'data'             => $dim,
                'sort_order'       => $order++,
            ]);
        }

        // Outer & Boundary Walls
        $outerWalls = [

            // Outer Main Café Building
            ['x1' => 480, 'y1' => 80, 'x2' => 940, 'y2' => 80, 'thickness' => 14],
            ['x1' => 940, 'y1' => 80, 'x2' => 940, 'y2' => 520, 'thickness' => 14],
            ['x1' => 940, 'y1' => 520, 'x2' => 480, 'y2' => 520, 'thickness' => 14],
            ['x1' => 480, 'y1' => 520, 'x2' => 480, 'y2' => 80, 'thickness' => 14],

            // Teras Boundary
            ['x1' => 560, 'y1' => 520, 'x2' => 560, 'y2' => 590, 'thickness' => 8],
            ['x1' => 560, 'y1' => 590, 'x2' => 760, 'y2' => 590, 'thickness' => 8],
            ['x1' => 760, 'y1' => 590, 'x2' => 760, 'y2' => 520, 'thickness' => 8],

            // Parkir Boundary Lines
            ['x1' => 480, 'y1' => 590, 'x2' => 940, 'y2' => 590, 'thickness' => 4],
            ['x1' => 480, 'y1' => 740, 'x2' => 940, 'y2' => 740, 'thickness' => 6],

            // Jalan Boundary Lines
            ['x1' => 450, 'y1' => 740, 'x2' => 970, 'y2' => 740, 'thickness' => 6],
            ['x1' => 450, 'y1' => 800, 'x2' => 970, 'y2' => 800, 'thickness' => 6],
        ];

        foreach ($outerWalls as $w) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => 'wall',
                'data'             => $w,
                'sort_order'       => $order++,
            ]);
        }

        // Interior Partition Walls
        $interiorWalls = [
            // Vertical Column 1 (Left to Middle)
            ['x1' => 640, 'y1' => 80, 'x2' => 640, 'y2' => 520, 'thickness' => 10],
            // Vertical Column 2 (Middle to Right)
            ['x1' => 780, 'y1' => 80, 'x2' => 780, 'y2' => 520, 'thickness' => 10],

            // Left Rooms Partitions
            ['x1' => 480, 'y1' => 240, 'x2' => 640, 'y2' => 240, 'thickness' => 8], // Dapur bottom
            ['x1' => 480, 'y1' => 340, 'x2' => 640, 'y2' => 340, 'thickness' => 8], // Area 3 bottom
            ['x1' => 480, 'y1' => 420, 'x2' => 640, 'y2' => 420, 'thickness' => 8], // Area 2 bottom
            ['x1' => 610, 'y1' => 420, 'x2' => 610, 'y2' => 520, 'thickness' => 8], // KM left wall

            // Center Rooms Partitions
            ['x1' => 640, 'y1' => 200, 'x2' => 780, 'y2' => 200, 'thickness' => 8], // Area 5 bottom
            ['x1' => 640, 'y1' => 370, 'x2' => 780, 'y2' => 370, 'thickness' => 8], // Area 4 bottom

            // Right Rooms Partitions
            ['x1' => 780, 'y1' => 220, 'x2' => 940, 'y2' => 220, 'thickness' => 8], // VIP upper
            ['x1' => 780, 'y1' => 370, 'x2' => 940, 'y2' => 370, 'thickness' => 8], // Gudang top
        ];

        foreach ($interiorWalls as $w) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => 'wall',
                'data'             => $w,
                'sort_order'       => $order++,
            ]);
        }

        // Structural Columns (Hitam 18x18)
        $columns = [
            ['x' => 471, 'y' => 71, 'width' => 18, 'height' => 18],
            ['x' => 631, 'y' => 71, 'width' => 18, 'height' => 18],
            ['x' => 771, 'y' => 71, 'width' => 18, 'height' => 18],
            ['x' => 931, 'y' => 71, 'width' => 18, 'height' => 18],

            ['x' => 471, 'y' => 231, 'width' => 18, 'height' => 18],
            ['x' => 631, 'y' => 191, 'width' => 18, 'height' => 18],
            ['x' => 771, 'y' => 211, 'width' => 18, 'height' => 18],
            ['x' => 931, 'y' => 211, 'width' => 18, 'height' => 18],

            ['x' => 471, 'y' => 331, 'width' => 18, 'height' => 18],
            ['x' => 631, 'y' => 361, 'width' => 18, 'height' => 18],
            ['x' => 771, 'y' => 361, 'width' => 18, 'height' => 18],
            ['x' => 931, 'y' => 361, 'width' => 18, 'height' => 18],

            ['x' => 471, 'y' => 511, 'width' => 18, 'height' => 18],
            ['x' => 631, 'y' => 511, 'width' => 18, 'height' => 18],
            ['x' => 771, 'y' => 511, 'width' => 18, 'height' => 18],
            ['x' => 931, 'y' => 511, 'width' => 18, 'height' => 18],

            ['x' => 551, 'y' => 581, 'width' => 18, 'height' => 18],
            ['x' => 751, 'y' => 581, 'width' => 18, 'height' => 18],
        ];

        foreach ($columns as $c) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => 'column',
                'data'             => $c,
                'sort_order'       => $order++,
            ]);
        }

        // Doors & Openings
        $doors = [
            ['x' => 670, 'y' => 515, 'width' => 45, 'height' => 15, 'label' => 'Pintu Utama'],
            ['x' => 550, 'y' => 235, 'width' => 35, 'height' => 12, 'label' => 'Pintu Dapur'],
            ['x' => 625, 'y' => 415, 'width' => 30, 'height' => 12, 'label' => 'Pintu KM'],
            ['x' => 775, 'y' => 245, 'width' => 35, 'height' => 12, 'label' => 'Pintu VIP'],
            ['x' => 775, 'y' => 385, 'width' => 35, 'height' => 12, 'label' => 'Pintu Gudang'],
        ];

        foreach ($doors as $d) {
            DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => 'door',
                'data'             => $d,
                'sort_order'       => $order++,
            ]);
        }

        // Room Labels (`room_label`) that can be highlighted in Key Plan
        $roomsData = [
            ['key' => 'TERAS', 'name' => "TERAS\nSemi-Outdoor Seating", 'code' => 'R-01', 'x' => 645, 'y' => 555],
            ['key' => 'KASIR', 'name' => "KASIR\nCounter & Barista", 'code' => 'R-02', 'x' => 710, 'y' => 465],
            ['key' => 'AREA 1', 'name' => "AREA 1\nIndoor Dining", 'code' => 'R-03', 'x' => 560, 'y' => 470],
            ['key' => 'AREA 2 & 3', 'name' => "AREA 2 & 3\nDining Booth", 'code' => 'R-04', 'x' => 560, 'y' => 350],
            ['key' => 'AREA 4', 'name' => "AREA 4\nCenter Seating", 'code' => 'R-05', 'x' => 710, 'y' => 285],
            ['key' => 'AREA 5', 'name' => "AREA 5\nBack Dining", 'code' => 'R-06', 'x' => 710, 'y' => 145],
            ['key' => 'AREA VIP', 'name' => "AREA VIP\nPrivate Lounge", 'code' => 'R-07', 'x' => 850, 'y' => 220],
            ['key' => 'AREA PINTU KAMAR MANDI', 'name' => "KM\nRestroom & Vanity", 'code' => 'R-09', 'x' => 630, 'y' => 475],
            ['key' => 'DAPUR', 'name' => "DAPUR\nKitchen Service", 'code' => null, 'x' => 560, 'y' => 170],
            ['key' => 'GUDANG', 'name' => "GUDANG\nStorage Area", 'code' => null, 'x' => 850, 'y' => 450],
            ['key' => 'AREA PARKIR', 'name' => "AREA PARKIR\nVehicle Parking", 'code' => null, 'x' => 710, 'y' => 665],
            ['key' => 'JALAN', 'name' => "JALAN\nMain Access Road", 'code' => null, 'x' => 710, 'y' => 770],
        ];

        $roomElementMap = [];

        foreach ($roomsData as $r) {
            $el = DrawingElement::create([
                'id'               => Str::uuid()->toString(),
                'drawing_sheet_id' => $denahSheet->id,
                'type'             => 'room_label',
                'data'             => [
                    'x'            => $r['x'],
                    'y'            => $r['y'],
                    'text'         => $r['name'],
                    'capture_code' => $r['code'],
                ],
                'sort_order'       => $order++,
            ]);

            $roomElementMap[$r['key']] = $el->id;
        }

        // 6. Delete old captures for this project to cleanly sync with the 12 PDF captures
        ProjectCapture::where('project_id', $project->id)->delete();

        // 7. Define All 12 Captures matching Pages 3 to 14
        $capturesSpecs = [
            // Page 3: TAMPAK DEPAN View 1
            [
                'code'          => 'EXT-01',
                'room_name'     => 'TAMPAK DEPAN',
                'captured_hour' => '09:30',
                'src_image'     => 'img-009.png',
                'dest_image'    => 'ext_01_tampak_depan_1.png',
                'sort_order'    => 1,
                'room_key'      => null,
                'description'   => 'Perspektif Fasad Tampak Depan View 1 - Finishing material dinding, signage arsitektural, dan pencahayaan alami pagi hari.',
            ],
            // Page 4: TAMPAK DEPAN View 2
            [
                'code'          => 'EXT-02',
                'room_name'     => 'TAMPAK DEPAN',
                'captured_hour' => '10:00',
                'src_image'     => 'img-012.png',
                'dest_image'    => 'ext_02_tampak_depan_2.png',
                'sort_order'    => 2,
                'room_key'      => null,
                'description'   => 'Perspektif Fasad Tampak Depan View 2 - Sudut pandang entrance samping dan area drop-off kendaraan.',
            ],
            // Page 5: TERAS (R-01)
            [
                'code'          => 'R-01',
                'room_name'     => 'TERAS',
                'captured_hour' => '10:30',
                'src_image'     => 'img-015.png',
                'dest_image'    => 'r_01_teras.png',
                'sort_order'    => 3,
                'room_key'      => 'TERAS',
                'description'   => 'Area duduk semi-outdoor teras depan dengan vegetasi penyejuk dan kanopi peneduh.',
            ],
            // Page 6: KASIR (R-02)
            [
                'code'          => 'R-02',
                'room_name'     => 'KASIR',
                'captured_hour' => '11:00',
                'src_image'     => 'img-020.png',
                'dest_image'    => 'r_02_kasir.png',
                'sort_order'    => 4,
                'room_key'      => 'KASIR',
                'description'   => 'Meja kasir dan service counter kafe dengan backdrop kayu vertikal serta spotlighting.',
            ],
            // Page 7: AREA 1 (R-03)
            [
                'code'          => 'R-03',
                'room_name'     => 'AREA 1',
                'captured_hour' => '11:30',
                'src_image'     => 'img-024.png',
                'dest_image'    => 'r_03_area1.png',
                'sort_order'    => 5,
                'room_key'      => 'AREA 1',
                'description'   => 'Area santap indoor 1 dengan bukaan jendela lebar menghadap taman depan kafe.',
            ],
            // Page 8: AREA 2 & 3 (R-04)
            [
                'code'          => 'R-04',
                'room_name'     => 'AREA 2 & 3',
                'captured_hour' => '12:00',
                'src_image'     => 'img-028.png',
                'dest_image'    => 'r_04_area2_3.png',
                'sort_order'    => 6,
                'room_key'      => 'AREA 2 & 3',
                'description'   => 'Kombinasi seating zone area 2 dan 3 dengan meja komunal dan kursi ergonomis.',
            ],
            // Page 9: AREA 4 (R-05)
            [
                'code'          => 'R-05',
                'room_name'     => 'AREA 4',
                'captured_hour' => '13:00',
                'src_image'     => 'img-032.png',
                'dest_image'    => 'r_05_area4.png',
                'sort_order'    => 7,
                'room_key'      => 'AREA 4',
                'description'   => 'Area santap tengah 4 dengan partisi tanaman gantung hias dan ambient ceiling light.',
            ],
            // Page 10: AREA 5 (R-06)
            [
                'code'          => 'R-06',
                'room_name'     => 'AREA 5',
                'captured_hour' => '13:30',
                'src_image'     => 'img-036.png',
                'dest_image'    => 'r_06_area5.png',
                'sort_order'    => 8,
                'room_key'      => 'AREA 5',
                'description'   => 'Area santap sisi belakang 5 dengan suasana lebih tenang dan akses cepat ke counter service.',
            ],
            // Page 11: AREA VIP View 1 (R-07)
            [
                'code'          => 'R-07',
                'room_name'     => 'AREA VIP',
                'captured_hour' => '14:00',
                'src_image'     => 'img-040.png',
                'dest_image'    => 'r_07_vip_view1.png',
                'sort_order'    => 9,
                'room_key'      => 'AREA VIP',
                'description'   => 'Ruang VIP privat View 1 - Meja rapat/private dining tertutup dengan panel peredam suara.',
            ],
            // Page 12: AREA VIP View 2 (R-08)
            [
                'code'          => 'R-08',
                'room_name'     => 'AREA VIP',
                'captured_hour' => '14:30',
                'src_image'     => 'img-044.png',
                'dest_image'    => 'r_08_vip_view2.png',
                'sort_order'    => 10,
                'room_key'      => 'AREA VIP',
                'description'   => 'Ruang VIP privat View 2 - Sudut sofa santai dan display cabinet pajangan eksklusif.',
            ],
            // Page 13: AREA PINTU KAMAR MANDI View 1 (R-09)
            [
                'code'          => 'R-09',
                'room_name'     => 'AREA PINTU KAMAR MANDI',
                'captured_hour' => '15:00',
                'src_image'     => 'img-048.png',
                'dest_image'    => 'r_09_km_view1.png',
                'sort_order'    => 11,
                'room_key'      => 'AREA PINTU KAMAR MANDI',
                'description'   => 'Foyer pintu kamar mandi View 1 dengan wastafel, cermin LED bulat, dan lantai anti-selip.',
            ],
            // Page 14: AREA PINTU KAMAR MANDI View 2 (R-10)
            [
                'code'          => 'R-10',
                'room_name'     => 'AREA PINTU KAMAR MANDI',
                'captured_hour' => '15:30',
                'src_image'     => 'img-052.png',
                'dest_image'    => 'r_10_km_view2.png',
                'sort_order'    => 12,
                'room_key'      => 'AREA PINTU KAMAR MANDI',
                'description'   => 'Akses koridor toilet View 2 dengan signage petunjuk arsitektural dan wall lamp sconce.',
            ],
        ];

        foreach ($capturesSpecs as $spec) {
            $srcPath = "{$extractedDir}/{$spec['src_image']}";
            $destRelPath = "projects/{$project->id}/captures/{$spec['dest_image']}";
            $destFullPath = storage_path("app/public/{$destRelPath}");

            if (File::exists($srcPath)) {
                File::copy($srcPath, $destFullPath);
            }

            $roomElementId = $spec['room_key'] ? ($roomElementMap[$spec['room_key']] ?? null) : null;

            ProjectCapture::create([
                'id'                    => Str::uuid()->toString(),
                'code'                  => $spec['code'],
                'project_id'            => $project->id,
                'drawing_sheet_id'      => null,
                'room_element_id'       => $roomElementId,
                'user_id'               => $userId,
                'title'                 => "{$spec['code']} - {$spec['room_name']}",
                'room_name'             => $spec['room_name'],
                'captured_hour'         => $spec['captured_hour'],
                'file_path'             => $destRelPath,
                'file_size_bytes'       => File::exists($destFullPath) ? File::size($destFullPath) : 1024000,
                'width'                 => 1500,
                'height'                => 750,
                'is_visible_to_clients' => true,
                'prompt'                => "Architectural photography of {$spec['room_name']}, photorealistic, natural sunlight, warm tone",
                'description'           => $spec['description'],
                'sort_order'            => $spec['sort_order'],
            ]);
        }

        echo "Successfully seeded Semangkok Ae Booklet data for Project {$project->id}!\n";
    }
}

