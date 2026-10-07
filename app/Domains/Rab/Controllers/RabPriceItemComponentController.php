<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Rab\Actions\RecalculatePriceItemAction;
use App\Domains\Rab\Models\RabPriceItem;
use App\Domains\Rab\Models\RabPriceItemComponent;
use App\Domains\Rab\Services\RabAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RabPriceItemComponentController extends Controller
{
    public function __construct(
        protected RabAccessService          $access,
        protected RecalculatePriceItemAction $recalc,
    ) {}

    /**
     * Halaman AHSP untuk satu harga satuan.
     */
    public function show(Request $request, RabPriceItem $priceItem): Response
    {
        $user = $request->user();
        abort_if($priceItem->user_id !== $user->id, 403);
        abort_unless($this->access->hasPlanAccess($user), 403);

        $priceItem->load([
            'components' => fn ($q) => $q->orderBy('component_type')->orderBy('sort_order'),
        ]);

        return Inertia::render('Rab/PriceItemComponents', [
            'price_item' => $priceItem,
            'components' => $priceItem->components,
        ]);
    }

    /**
     * Simpan komponen baru.
     */
    public function store(Request $request, RabPriceItem $priceItem): RedirectResponse
    {
        $user = $request->user();
        abort_if($priceItem->user_id !== $user->id, 403);

        $data = $this->validateComponent($request);
        $this->recalc->saveComponent($priceItem, $data);

        return back()->with('success', 'Komponen ditambahkan.');
    }

    /**
     * Update komponen existing.
     */
    public function update(Request $request, RabPriceItem $priceItem, RabPriceItemComponent $component): RedirectResponse
    {
        abort_if($priceItem->user_id !== $request->user()->id, 403);
        abort_if($component->rab_price_item_id !== $priceItem->id, 404);

        $data = $this->validateComponent($request);
        $this->recalc->saveComponent($priceItem, $data, $component);

        return back()->with('success', 'Komponen diperbarui.');
    }

    /**
     * Hapus komponen dan recalculate.
     */
    public function destroy(Request $request, RabPriceItem $priceItem, RabPriceItemComponent $component): RedirectResponse
    {
        abort_if($priceItem->user_id !== $request->user()->id, 403);
        abort_if($component->rab_price_item_id !== $priceItem->id, 404);

        $component->delete();

        // Jika tidak ada komponen lagi, reset has_components ke false
        if ($priceItem->components()->count() === 0) {
            $priceItem->has_components = false;
            $priceItem->save();
        } else {
            $this->recalc->execute($priceItem);
        }

        return back()->with('success', 'Komponen dihapus.');
    }

    /**
     * Update overhead_percent dan recalculate.
     */
    public function updateOverhead(Request $request, RabPriceItem $priceItem): RedirectResponse
    {
        abort_if($priceItem->user_id !== $request->user()->id, 403);

        $request->validate([
            'overhead_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $priceItem->overhead_percent = $request->overhead_percent;
        $priceItem->save();

        if ($priceItem->has_components) {
            $this->recalc->execute($priceItem);
        }

        return back()->with('success', 'Overhead diperbarui.');
    }

    private function validateComponent(Request $request): array
    {
        return $request->validate([
            'component_type' => ['required', Rule::in(RabPriceItemComponent::TYPES)],
            'name'           => ['required', 'string', 'max:255'],
            'code'           => ['nullable', 'string', 'max:50'],
            'unit'           => ['required', 'string', 'max:20'],
            'coefficient'    => ['required', 'numeric', 'min:0'],
            'unit_price'     => ['required', 'numeric', 'min:0'],
            'sort_order'     => ['sometimes', 'integer', 'min:0'],
        ]);
    }
}
