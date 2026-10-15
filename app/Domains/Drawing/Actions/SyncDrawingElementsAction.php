<?php

namespace App\Domains\Drawing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingElement;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Services\DrawingAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncDrawingElementsAction
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    /**
     * Sinkronisasi seluruh elemen kanvas 2D untuk sebuah lembar kerja.
     *
     * @param  array<int, array<string, mixed>>  $elements
     * @param  array<string, mixed>|null         $sheetData
     * @return \Illuminate\Database\Eloquent\Collection<int, DrawingElement>
     */
    public function execute(User $user, DrawingSheet $sheet, array $elements, ?array $sheetData = null)
    {
        $this->accessService->authorizeEdit($user, $sheet->project);

        return DB::transaction(function () use ($sheet, $elements, $sheetData) {
            // Perbarui dimensi atau ukuran kertas jika ada
            if (! empty($sheetData)) {
                $sheet->update(array_filter([
                    'width'       => isset($sheetData['width']) ? (int) $sheetData['width'] : null,
                    'height'      => isset($sheetData['height']) ? (int) $sheetData['height'] : null,
                    'paper_size'  => $sheetData['paper_size'] ?? null,
                    'orientation' => $sheetData['orientation'] ?? null,
                ], fn ($val) => ! is_null($val)));
            }

            // Hapus elemen lama
            DrawingElement::where('drawing_sheet_id', $sheet->id)->delete();

            $insertData = [];
            $now = now();

            foreach ($elements as $index => $el) {
                $id = ! empty($el['id']) && Str::isUuid($el['id']) ? $el['id'] : (string) Str::uuid();

                $insertData[] = [
                    'id'               => $id,
                    'drawing_sheet_id' => $sheet->id,
                    'type'             => $el['type'] ?? 'line',
                    'data'             => json_encode($el['data'] ?? []),
                    'sort_order'       => $el['sort_order'] ?? $index,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            if (! empty($insertData)) {
                DrawingElement::insert($insertData);
            }

            return $sheet->elements()->get();
        });
    }
}

