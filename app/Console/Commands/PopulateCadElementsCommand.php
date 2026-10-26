<?php

namespace App\Console\Commands;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Actions\SyncDrawingElementsAction;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Project\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PopulateCadElementsCommand extends Command
{
    protected $signature = 'project:populate-cad {sheet_id=01a17377-7cdb-711f-b046-71077bf94624}';
    protected $description = 'Populate complete CAD architectural floor plan elements into DrawingSheet';

    public function handle(SyncDrawingElementsAction $syncAction): int
    {
        $sheetId = $this->argument('sheet_id');
        $sheet = DrawingSheet::findOrFail($sheetId);
        $project = Project::findOrFail($sheet->project_id);
        $user = User::findOrFail($project->user_id);

        $this->info("Populating CAD architectural elements for sheet: {$sheet->title} ({$sheet->id})");

        $elements = [];
        $order = 1;

        // ── 1. STRUCTURAL COLUMNS (KOLOM STRUKTUR BETON) ─────────────────────
        $colPositions = [
            // Grid 1 (y = 130)
            [200, 130], [370, 130], [540, 130], [710, 130], [880, 130],
            // Grid 2 (y = 260)
            [200, 260], [370, 260], [540, 260], [710, 260], [880, 260],
            // Grid 3 (y = 400)
            [200, 400], [370, 400], [540, 400], [710, 400],
            // Grid 4 (y = 540)
            [200, 540], [370, 540], [540, 540], [710, 540],
            // Grid 5 (y = 670)
            [200, 670], [370, 670], [540, 670],
        ];

        foreach ($colPositions as [$cx, $cy]) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'column',
                'sort_order' => $order++,
                'data'       => ['x' => $cx - 10, 'y' => $cy - 10, 'size' => 20],
            ];
        }

        // ── 2. EXTERIOR WALLS (DINDING LUAR UTAMA - THICKNESS 14) ────────────
        $extWalls = [
            // Top wall (Rear boundary Grid 1)
            [200, 130, 880, 130],
            // Left wall (Grid A)
            [200, 130, 200, 670],
            // VIP right wall (Grid E)
            [880, 130, 880, 260],
            // Step wall to corridor (Grid 2)
            [710, 260, 880, 260],
            // Corridor right exterior wall (Grid D)
            [710, 260, 710, 540],
            // Terrace right wall (Grid C)
            [540, 540, 540, 670],
            // Terrace front boundary (Grid 5)
            [200, 670, 540, 670],
            // Front building wall with main entrance (Grid 4)
            [200, 540, 540, 540],
        ];

        foreach ($extWalls as [$x1, $y1, $x2, $y2]) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'wall',
                'sort_order' => $order++,
                'data'       => ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 'thickness' => 14],
            ];
        }

        // ── 3. INTERIOR PARTITION WALLS (SEKAT RUANGAN - THICKNESS 10) ───────
        $intWalls = [
            // Dapur bottom wall
            [200, 260, 370, 260],
            // Dapur right wall
            [370, 130, 370, 260],
            // VIP room left partition
            [540, 130, 540, 260],
            // Toilet & Gudang top wall
            [540, 260, 710, 260],
            // Toilet & Gudang bottom wall
            [540, 400, 710, 400],
            // Toilet / Gudang divider
            [625, 260, 625, 400],
            // Kasir rear / corridor partition
            [540, 400, 540, 540],
        ];

        foreach ($intWalls as [$x1, $y1, $x2, $y2]) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'wall',
                'sort_order' => $order++,
                'data'       => ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2, 'thickness' => 10],
            ];
        }

        // ── 4. DOORS (PINTU AYUN, PINTU GANDA & PINTU GESER) ──────────────────
        // Pintu Utama Entrance (Double Door)
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'double_door',
            'sort_order' => $order++,
            'data'       => ['x' => 320, 'y' => 540, 'width' => 80],
        ];
        // Pintu VIP Room
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'door',
            'sort_order' => $order++,
            'data'       => ['x' => 540, 'y' => 220, 'size' => 38],
        ];
        // Pintu Service Dapur (Sliding Door)
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'sliding_door',
            'sort_order' => $order++,
            'data'       => ['x' => 270, 'y' => 252, 'width' => 70, 'height' => 16],
        ];
        // Pintu Kamar Mandi (KM)
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'door',
            'sort_order' => $order++,
            'data'       => ['x' => 540, 'y' => 360, 'size' => 36],
        ];
        // Pintu Gudang
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'door',
            'sort_order' => $order++,
            'data'       => ['x' => 625, 'y' => 360, 'size' => 36],
        ];
        // Pintu Geser Kaca Teras ke Dining
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'sliding_door',
            'sort_order' => $order++,
            'data'       => ['x' => 430, 'y' => 532, 'width' => 75, 'height' => 16],
        ];
        // Pintu Geser Kaca Area 5 (Taman Belakang)
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'sliding_door',
            'sort_order' => $order++,
            'data'       => ['x' => 420, 'y' => 122, 'width' => 80, 'height' => 16],
        ];

        // ── 5. WINDOWS (BUKAAN JENDELA ARSITEKTUR) ───────────────────────────
        $windows = [
            ['x' => 225, 'y' => 532, 'width' => 70, 'height' => 16], // Facade Teras kiri
            ['x' => 460, 'y' => 532, 'width' => 60, 'height' => 16], // Facade Teras kanan
            ['x' => 192, 'y' => 450, 'width' => 16, 'height' => 60], // Dining Area 1 kiri
            ['x' => 192, 'y' => 320, 'width' => 16, 'height' => 60], // Dining Area 2 kiri
            ['x' => 240, 'y' => 122, 'width' => 60, 'height' => 16], // Dapur atas
            ['x' => 740, 'y' => 122, 'width' => 80, 'height' => 16], // VIP atas
            ['x' => 872, 'y' => 180, 'width' => 16, 'height' => 60], // VIP samping kanan
        ];

        foreach ($windows as $wData) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'window',
                'sort_order' => $order++,
                'data'       => $wData,
            ];
        }

        // ── 6. FURNITURE & SANITAIR (PERABOT, KASIR & DAPUR) ─────────────────
        // Dapur: Kitchen Set & Counter
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'kitchen',
            'sort_order' => $order++,
            'data'       => ['x' => 215, 'y' => 145, 'width' => 140, 'height' => 50],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'rect',
            'sort_order' => $order++,
            'data'       => ['x' => 230, 'y' => 210, 'width' => 90, 'height' => 35, 'stroke' => '#94a3b8', 'strokeWidth' => 1.5, 'fill' => 'rgba(148, 163, 184, 0.25)'],
        ];

        // Toilet / KM: Toilet closet & Washbasin
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'toilet',
            'sort_order' => $order++,
            'data'       => ['x' => 555, 'y' => 280],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'washbasin',
            'sort_order' => $order++,
            'data'       => ['x' => 555, 'y' => 345, 'width' => 45, 'height' => 35],
        ];

        // Gudang: Storage Racks
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'rect',
            'sort_order' => $order++,
            'data'       => ['x' => 640, 'y' => 275, 'width' => 55, 'height' => 75, 'stroke' => '#64748b', 'strokeWidth' => 1.5, 'fill' => 'rgba(100, 116, 139, 0.2)'],
        ];

        // Kasir: Meja Counter L-Shape & POS
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'rect',
            'sort_order' => $order++,
            'data'       => ['x' => 560, 'y' => 440, 'width' => 120, 'height' => 40, 'stroke' => '#d97706', 'strokeWidth' => 2, 'fill' => 'rgba(217, 119, 6, 0.25)'],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'rect',
            'sort_order' => $order++,
            'data'       => ['x' => 575, 'y' => 495, 'width' => 80, 'height' => 30, 'stroke' => '#b45309', 'strokeWidth' => 1.5, 'fill' => 'rgba(180, 83, 9, 0.15)'],
        ];

        // Dining Tables (Meja Makan & Kursi)
        $tables = [
            // Area 1
            ['x' => 230, 'y' => 440, 'width' => 75, 'height' => 50],
            ['x' => 320, 'y' => 440, 'width' => 75, 'height' => 50],
            // Area 2 & 3
            ['x' => 230, 'y' => 310, 'width' => 75, 'height' => 50],
            ['x' => 320, 'y' => 310, 'width' => 75, 'height' => 50],
            ['x' => 410, 'y' => 310, 'width' => 75, 'height' => 50],
            ['x' => 410, 'y' => 440, 'width' => 75, 'height' => 50],
            // Area 5 (Semi-outdoor)
            ['x' => 390, 'y' => 145, 'width' => 65, 'height' => 45],
            ['x' => 470, 'y' => 145, 'width' => 65, 'height' => 45],
            // Teras Depan
            ['x' => 230, 'y' => 595, 'width' => 75, 'height' => 50],
            ['x' => 340, 'y' => 595, 'width' => 75, 'height' => 50],
        ];

        foreach ($tables as $tData) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'table',
                'sort_order' => $order++,
                'data'       => $tData,
            ];
        }

        // Area 4: Lounge Sofa & Coffee Table
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'sofa',
            'sort_order' => $order++,
            'data'       => ['x' => 395, 'y' => 195, 'width' => 120, 'height' => 50],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'table',
            'sort_order' => $order++,
            'data'       => ['x' => 420, 'y' => 255, 'width' => 70, 'height' => 35],
        ];

        // Area VIP: Conference Table & Sofa
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'dining_table',
            'sort_order' => $order++,
            'data'       => ['x' => 580, 'y' => 155, 'width' => 120, 'height' => 60],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'sofa',
            'sort_order' => $order++,
            'data'       => ['x' => 730, 'y' => 155, 'width' => 100, 'height' => 55],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'table',
            'sort_order' => $order++,
            'data'       => ['x' => 755, 'y' => 220, 'width' => 50, 'height' => 30],
        ];

        // Trees & Landscaping
        $trees = [
            ['x' => 520, 'y' => 210, 'radius' => 24], // Area 5 garden
            ['x' => 460, 'y' => 620, 'radius' => 25], // Teras planter 1
            ['x' => 510, 'y' => 620, 'radius' => 22], // Teras planter 2
            ['x' => 140, 'y' => 740, 'radius' => 30], // Parkir tree left
            ['x' => 580, 'y' => 740, 'radius' => 30], // Parkir tree center
            ['x' => 660, 'y' => 740, 'radius' => 28], // Parkir tree right
        ];

        foreach ($trees as $trData) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'tree',
                'sort_order' => $order++,
                'data'       => $trData,
            ];
        }

        // Area Parkir: Pavement & Cars
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'rect',
            'sort_order' => $order++,
            'data'       => ['x' => 180, 'y' => 695, 'width' => 550, 'height' => 85, 'stroke' => '#475569', 'strokeWidth' => 1.5, 'fill' => 'rgba(51, 65, 85, 0.08)'],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'car',
            'sort_order' => $order++,
            'data'       => ['x' => 210, 'y' => 705, 'width' => 130, 'height' => 65],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'car',
            'sort_order' => $order++,
            'data'       => ['x' => 360, 'y' => 705, 'width' => 130, 'height' => 65],
        ];

        // ── 7. STRUCTURAL GRID LINES (GARIS AS BANGUNAN DENGAN BUBBLE) ────────
        // Horizontal Grids
        $hGrids = [
            ['x1' => 140, 'y1' => 130, 'x2' => 920, 'y2' => 130, 'code' => '1'],
            ['x1' => 140, 'y1' => 260, 'x2' => 920, 'y2' => 260, 'code' => '2'],
            ['x1' => 140, 'y1' => 400, 'x2' => 750, 'y2' => 400, 'code' => '3'],
            ['x1' => 140, 'y1' => 540, 'x2' => 750, 'y2' => 540, 'code' => '4'],
            ['x1' => 140, 'y1' => 670, 'x2' => 580, 'y2' => 670, 'code' => '5'],
        ];
        foreach ($hGrids as $g) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'grid_line',
                'sort_order' => $order++,
                'data'       => $g,
            ];
        }

        // Vertical Grids
        $vGrids = [
            ['x1' => 200, 'y1' => 90, 'x2' => 200, 'y2' => 710, 'code' => 'A'],
            ['x1' => 370, 'y1' => 90, 'x2' => 370, 'y2' => 710, 'code' => 'B'],
            ['x1' => 540, 'y1' => 90, 'x2' => 540, 'y2' => 710, 'code' => 'C'],
            ['x1' => 710, 'y1' => 90, 'x2' => 710, 'y2' => 580, 'code' => 'D'],
            ['x1' => 880, 'y1' => 90, 'x2' => 880, 'y2' => 300, 'code' => 'E'],
        ];
        foreach ($vGrids as $g) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'grid_line',
                'sort_order' => $order++,
                'data'       => $g,
            ];
        }

        // ── 8. DIMENSION LINES (GARIS UKURAN ARSITEKTUR TERUKUR) ──────────────
        $dims = [
            // Top Horizontal Dimensions (Bay Spacing)
            ['x1' => 200, 'y1' => 65, 'x2' => 370, 'y2' => 65, 'text' => '4.50 m'],
            ['x1' => 370, 'y1' => 65, 'x2' => 540, 'y2' => 65, 'text' => '4.50 m'],
            ['x1' => 540, 'y1' => 65, 'x2' => 710, 'y2' => 65, 'text' => '4.25 m'],
            ['x1' => 710, 'y1' => 65, 'x2' => 880, 'y2' => 65, 'text' => '4.25 m'],
            ['x1' => 200, 'y1' => 40, 'x2' => 880, 'y2' => 40, 'text' => '17.50 m'],
            // Left Vertical Dimensions (Room Depths)
            ['x1' => 110, 'y1' => 130, 'x2' => 110, 'y2' => 260, 'text' => '3.50 m'],
            ['x1' => 110, 'y1' => 260, 'x2' => 110, 'y2' => 400, 'text' => '3.75 m'],
            ['x1' => 110, 'y1' => 400, 'x2' => 110, 'y2' => 540, 'text' => '3.50 m'],
            ['x1' => 110, 'y1' => 540, 'x2' => 110, 'y2' => 670, 'text' => '3.25 m'],
            ['x1' => 80,  'y1' => 130, 'x2' => 80,  'y2' => 670, 'text' => '14.00 m'],
            ['x1' => 80,  'y1' => 670, 'x2' => 80,  'y2' => 780, 'text' => '3.00 m'],
        ];
        foreach ($dims as $d) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'dimension',
                'sort_order' => $order++,
                'data'       => $d,
            ];
        }

        // ── 9. NORTH ARROW (ARAH UTARA) & SECTION MARKER (POTONGAN A-A) ───────
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'north_arrow',
            'sort_order' => $order++,
            'data'       => ['x' => 960, 'y' => 100],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'section_marker',
            'sort_order' => $order++,
            'data'       => ['x1' => 90, 'y1' => 360, 'x2' => 910, 'y2' => 360, 'text' => 'A'],
        ];

        // ── 10. ELEVATION MARKERS (PEIL KETINGGIAN LANTAI) ───────────────────
        $elevs = [
            ['x' => 280, 'y' => 500, 'text' => '+0.05'], // Dining Area 1
            ['x' => 690, 'y' => 220, 'text' => '+0.10'], // VIP Room
            ['x' => 585, 'y' => 320, 'text' => '-0.05'], // KM
            ['x' => 290, 'y' => 640, 'text' => '±0.00'], // Teras Depan
            ['x' => 280, 'y' => 755, 'text' => '-0.20'], // Carport Parkir
        ];
        foreach ($elevs as $eData) {
            $elements[] = [
                'id'         => (string) Str::uuid(),
                'type'       => 'elevation_marker',
                'sort_order' => $order++,
                'data'       => $eData,
            ];
        }

        // ── 11. LEADER SPECIFICATION NOTES ───────────────────────────────────
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'leader_note',
            'sort_order' => $order++,
            'data'       => ['x1' => 330, 'y1' => 470, 'x2' => 430, 'y2' => 430, 'text' => 'Granit Tile 60×60 cm Matte'],
        ];
        $elements[] = [
            'id'         => (string) Str::uuid(),
            'type'       => 'leader_note',
            'sort_order' => $order++,
            'data'       => ['x1' => 720, 'y1' => 130, 'x2' => 800, 'y2' => 95, 'text' => 'Dinding Akustik Wood Panel'],
        ];

        // ── 12. ROOM LABELS (IDENTITAS & ZONASI RUANGAN) ───────────────────────
        $rooms = [
            ['id' => '01a1733f-0001-7000-8000-000000000001', 'x' => 285, 'y' => 575, 'name' => 'TERAS', 'text' => "TERAS\n±0.00 (3.25 × 8.50)", 'code' => 'R-01'],
            ['id' => '01a1733f-0002-7000-8000-000000000002', 'x' => 625, 'y' => 505, 'name' => 'KASIR', 'text' => "KASIR\n+0.05 (Meja Order)", 'code' => 'R-02'],
            ['id' => '01a1733f-0003-7000-8000-000000000003', 'x' => 285, 'y' => 475, 'name' => 'AREA 1', 'text' => "AREA 1\n+0.05 (Dining Hall)", 'code' => 'R-03'],
            ['id' => '01a1733f-0004-7000-8000-000000000004', 'x' => 320, 'y' => 345, 'name' => 'AREA 2 & 3', 'text' => "AREA 2 & 3\n+0.05 (Main Dining)", 'code' => 'R-04'],
            ['id' => '01a1733f-0006-7000-8000-000000000006', 'x' => 445, 'y' => 215, 'name' => 'AREA 4', 'text' => "AREA 4\n+0.05 (Lounge)", 'code' => 'R-05'],
            ['id' => '01a1733f-0007-7000-8000-000000000007', 'x' => 450, 'y' => 140, 'name' => 'AREA 5', 'text' => "AREA 5\n±0.00 (Semi-Terbuka)", 'code' => 'R-06'],
            ['id' => '01a1733f-0008-7000-8000-000000000008', 'x' => 675, 'y' => 165, 'name' => 'AREA VIP', 'text' => "AREA VIP\n+0.10 (Private / Meeting)", 'code' => 'R-07'],
            ['id' => '01a1733f-0009-7000-8000-000000000009', 'x' => 285, 'y' => 175, 'name' => 'DAPUR', 'text' => "DAPUR\n±0.00 (Kitchen & Barista)", 'code' => null],
            ['id' => '01a1733f-0010-7000-8000-000000000010', 'x' => 585, 'y' => 375, 'name' => 'AREA KM', 'text' => "KM\n-0.05 (Toilet & Sink)", 'code' => 'R-09'],
            ['id' => '01a1733f-0011-7000-8000-000000000011', 'x' => 665, 'y' => 365, 'name' => 'GUDANG', 'text' => "GUDANG\n+0.05 (Storage)", 'code' => null],
            ['id' => '01a1733f-0012-7000-8000-000000000012', 'x' => 300, 'y' => 735, 'name' => 'AREA PARKIR', 'text' => "AREA PARKIR\n-0.20 (Kapasitas 6 Mobil)", 'code' => null],
        ];

        foreach ($rooms as $r) {
            $elements[] = [
                'id'         => $r['id'],
                'type'       => 'room_label',
                'sort_order' => $order++,
                'data'       => [
                    'x'            => $r['x'],
                    'y'            => $r['y'],
                    'text'         => $r['text'],
                    'label'        => $r['name'],
                    'capture_code' => $r['code'],
                ],
            ];
        }

        // Sync all elements to sheet with standard A3 landscape dimensions (1260 × 891)
        $syncAction->execute($user, $sheet, $elements, [
            'width'       => 1260,
            'height'      => 891,
            'paper_size'  => 'A3',
            'orientation' => 'landscape',
        ]);

        $this->info("Successfully synced {$order} CAD elements into DrawingSheet {$sheet->id}!");
        return 0;
    }
}

