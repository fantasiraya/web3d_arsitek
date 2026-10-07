<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Rab\Models\RabMapping;
use App\Domains\Rab\Models\RabPriceItem;
use App\Domains\Rab\Services\RabAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mengelola aturan pencocokan nama objek .glb / baris CSV → RabPriceItem.
 * Mapping ini dipakai oleh RabMappingService di Fase B (CSV) dan Fase C (.glb).
 */
class RabMappingController extends Controller
{
    public function __construct(
        protected RabAccessService $access,
    ) {}

    /** Daftar semua mapping milik user. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403, 'Paket langganan Anda tidak mendukung fitur RAB.');

        $mappings = RabMapping::where('user_id', $user->id)
            ->with('priceItem:id,name,unit,unit_price,category')
            ->orderBy('match_type')
            ->orderBy('pattern')
            ->get();

        $priceItems = RabPriceItem::where('user_id', $user->id)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'unit_price', 'category']);

        return Inertia::render('Rab/Mappings', [
            'mappings'   => $mappings,
            'priceItems' => $priceItems,
        ]);
    }

    /** Buat mapping baru. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403);

        $data = $request->validate([
            'match_type'        => ['required', Rule::in(['exact', 'name_pattern', 'material'])],
            'pattern'           => ['required', 'string', 'max:255'],
            'rab_price_item_id' => ['required', 'uuid', 'exists:rab_price_items,id'],
            'quantity_basis'    => ['required', Rule::in(['area', 'volume', 'count', 'length'])],
        ]);

        // Pastikan price item milik user
        $priceItem = RabPriceItem::where('id', $data['rab_price_item_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        RabMapping::updateOrCreate(
            [
                'user_id'    => $user->id,
                'match_type' => $data['match_type'],
                'pattern'    => $data['pattern'],
            ],
            [
                'rab_price_item_id' => $priceItem->id,
                'quantity_basis'    => $data['quantity_basis'],
            ],
        );

        return back()->with('success', "Mapping \"{$data['pattern']}\" berhasil disimpan.");
    }

    /** Update mapping existing. */
    public function update(Request $request, RabMapping $mapping): RedirectResponse
    {
        abort_if($mapping->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'match_type'        => ['required', Rule::in(['exact', 'name_pattern', 'material'])],
            'pattern'           => ['required', 'string', 'max:255'],
            'rab_price_item_id' => ['required', 'uuid', 'exists:rab_price_items,id'],
            'quantity_basis'    => ['required', Rule::in(['area', 'volume', 'count', 'length'])],
        ]);

        $priceItem = RabPriceItem::where('id', $data['rab_price_item_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $mapping->update([
            'match_type'        => $data['match_type'],
            'pattern'           => $data['pattern'],
            'rab_price_item_id' => $priceItem->id,
            'quantity_basis'    => $data['quantity_basis'],
        ]);

        return back()->with('success', "Mapping diperbarui.");
    }

    /** Hapus mapping. */
    public function destroy(Request $request, RabMapping $mapping): RedirectResponse
    {
        abort_if($mapping->user_id !== $request->user()->id, 403);
        $mapping->delete();
        return back()->with('success', 'Mapping dihapus.');
    }
}
