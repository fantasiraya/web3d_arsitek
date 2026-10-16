<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Models\RabPriceItem;
use Illuminate\Support\Facades\DB;

class GenerateRabFromWorksheetAction
{
    public function __construct(
        protected RecalculateRabAction $recalculateRab
    ) {}

    public function execute(User $user, Project $project, array $data): RabDocument
    {
        return DB::transaction(function () use ($user, $project, $data) {
            $docTitle = ! empty($data['title'])
                ? $data['title']
                : 'RAB Lembar Kerja - ' . ($project->title ?? 'Proyek');

            $document = RabDocument::create([
                'project_id'            => $project->id,
                'user_id'               => $user->id,
                'title'                 => $docTitle,
                'source'                => RabDocument::SOURCE_WORKSHEET,
                'status'                => RabDocument::STATUS_DRAFT,
                'is_visible_to_clients' => false,
                'overhead_percent'      => 10.0,
                'ppn_percent'           => 11.0,
                'subtotal'              => 0,
                'overhead_amount'       => 0,
                'ppn_amount'            => 0,
                'total'                 => 0,
            ]);

            // Ambil daftar master harga satuan milik user untuk pencocokan otomatis
            $priceItems = RabPriceItem::where('user_id', $user->id)->get();

            $sortOrder = 1;

            // 1. Pekerjaan Umum & Persiapan
            RabItem::create([
                'rab_document_id' => $document->id,
                'section'         => 'PEKERJAAN PERSIAPAN',
                'description'     => 'Pembersihan Lokasi & Pengukuran (Uitzet)',
                'unit'            => 'ls',
                'quantity'        => 1.0,
                'waste_percent'   => 0,
                'unit_price'      => 1500000,
                'subtotal'        => 1500000,
                'source_ref'      => 'worksheet_general',
                'is_mapped'       => false,
                'is_estimate'     => true,
                'sort_order'      => $sortOrder++,
            ]);

            $rooms = $data['rooms'] ?? [];
            foreach ($rooms as $room) {
                $roomName = strtoupper(trim($room['name'] ?? 'RUANGAN'));
                $area = (float) ($room['area_sqm'] ?? 12.0);
                if ($area <= 0) {
                    $area = 12.0;
                }
                $perimeter = (float) ($room['wall_perimeter_m'] ?? ($area > 0 ? round(sqrt($area) * 4, 2) : 14.0));
                if ($perimeter <= 0) {
                    $perimeter = 14.0;
                }
                $wallHeight = 3.5;
                $wallArea = round($perimeter * $wallHeight, 2);

                // Helper pencocokan harga satuan arsitek
                $findPrice = function (string $keyword, float $fallback) use ($priceItems) {
                    $found = $priceItems->first(fn ($p) => str_contains(strtolower($p->name), strtolower($keyword)));
                    return $found ? (float) $found->unit_price : $fallback;
                };

                // Item 1: Pekerjaan Lantai
                $floorPrice = $findPrice('lantai', 250000);
                RabItem::create([
                    'rab_document_id' => $document->id,
                    'section'         => $roomName,
                    'description'     => "Pekerjaan Penutup Lantai {$roomName} (Homogeneous Tile / Granit)",
                    'unit'            => 'm2',
                    'quantity'        => $area,
                    'waste_percent'   => 5.0,
                    'unit_price'      => $floorPrice,
                    'subtotal'        => round($area * 1.05 * $floorPrice, 2),
                    'source_ref'      => "worksheet_{$roomName}_floor",
                    'is_mapped'       => false,
                    'is_estimate'     => true,
                    'sort_order'      => $sortOrder++,
                ]);

                // Item 2: Pekerjaan Dinding & Cat
                $wallPrice = $findPrice('cat dinding', 65000);
                RabItem::create([
                    'rab_document_id' => $document->id,
                    'section'         => $roomName,
                    'description'     => "Pekerjaan Pengecatan Dinding Interior {$roomName}",
                    'unit'            => 'm2',
                    'quantity'        => $wallArea,
                    'waste_percent'   => 3.0,
                    'unit_price'      => $wallPrice,
                    'subtotal'        => round($wallArea * 1.03 * $wallPrice, 2),
                    'source_ref'      => "worksheet_{$roomName}_wall",
                    'is_mapped'       => false,
                    'is_estimate'     => true,
                    'sort_order'      => $sortOrder++,
                ]);

                // Item 3: Pekerjaan Plafond & Rangka
                $ceilingPrice = $findPrice('plafon', 165000);
                RabItem::create([
                    'rab_document_id' => $document->id,
                    'section'         => $roomName,
                    'description'     => "Pekerjaan Plafond Gypsum & Rangka {$roomName}",
                    'unit'            => 'm2',
                    'quantity'        => $area,
                    'waste_percent'   => 5.0,
                    'unit_price'      => $ceilingPrice,
                    'subtotal'        => round($area * 1.05 * $ceilingPrice, 2),
                    'source_ref'      => "worksheet_{$roomName}_ceiling",
                    'is_mapped'       => false,
                    'is_estimate'     => true,
                    'sort_order'      => $sortOrder++,
                ]);

                // Item 4: Titik Lampu / Elektrikal
                $lampCount = max(2, (int) round($area / 6));
                $elecPrice = $findPrice('titik lampu', 125000);
                RabItem::create([
                    'rab_document_id' => $document->id,
                    'section'         => $roomName,
                    'description'     => "Instalasi Titik Lampu & Downlight {$roomName}",
                    'unit'            => 'titik',
                    'quantity'        => $lampCount,
                    'waste_percent'   => 0,
                    'unit_price'      => $elecPrice,
                    'subtotal'        => round($lampCount * $elecPrice, 2),
                    'source_ref'      => "worksheet_{$roomName}_electric",
                    'is_mapped'       => false,
                    'is_estimate'     => true,
                    'sort_order'      => $sortOrder++,
                ]);
            }

            // Hitung ulang semua subtotal, overhead, PPN, dan total sesuai formula baku
            return $this->recalculateRab->execute($document);
        });
    }
}

