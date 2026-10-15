<?php

namespace App\Domains\Drawing\Controllers;

use App\Domains\Drawing\Actions\SyncDrawingElementsAction;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Requests\SyncDrawingElementsRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DrawingElementController extends Controller
{
    /**
     * Simpan sinkronisasi seluruh elemen gambar pada kanvas 2D.
     */
    public function sync(SyncDrawingElementsRequest $request, DrawingSheet $sheet, SyncDrawingElementsAction $action): JsonResponse
    {
        $user = $request->user();
        $elements = $request->validated('elements', []);
        $sheetData = $request->validated('sheet', null);

        $saved = $action->execute($user, $sheet, $elements, $sheetData);

        return response()->json([
            'success' => true,
            'message' => 'Elemen gambar berhasil disimpan.',
            'count'   => $saved->count(),
        ]);
    }
}

