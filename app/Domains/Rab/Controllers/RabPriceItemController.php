<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Rab\Actions\SavePriceItemAction;
use App\Domains\Rab\Models\RabPriceItem;
use App\Domains\Rab\Requests\StorePriceItemRequest;
use App\Domains\Rab\Services\RabAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RabPriceItemController extends Controller
{
    public function __construct(
        protected RabAccessService  $access,
        protected SavePriceItemAction $save,
    ) {}

    /**
     * Daftar harga satuan milik user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Gating: hanya user dengan plan RAB yang bisa mengelola harga satuan
        abort_unless($this->access->hasPlanAccess($user), 403, 'Paket langganan Anda tidak mendukung fitur RAB.');

        $priceItems = RabPriceItem::where('user_id', $user->id)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Kelompokkan berdasarkan category
        $grouped = $priceItems->groupBy(fn ($item) => $item->category ?? 'Umum');

        return Inertia::render('Rab/PriceItems', [
            'price_items' => $priceItems,
            'grouped'     => $grouped,
        ]);
    }

    /**
     * Buat harga satuan baru.
     */
    public function store(StorePriceItemRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403);

        $this->save->execute($user, $request->validated());

        return back()->with('success', 'Harga satuan berhasil ditambahkan.');
    }

    /**
     * Update harga satuan.
     */
    public function update(StorePriceItemRequest $request, RabPriceItem $priceItem): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403);
        abort_if($priceItem->user_id !== $user->id, 403, 'Anda tidak memiliki hak untuk mengubah harga satuan ini.');

        $this->save->execute($user, $request->validated(), $priceItem);

        return back()->with('success', 'Harga satuan berhasil diperbarui.');
    }

    /**
     * Hapus harga satuan.
     * Harga satuan yang sudah dipakai di RAB item tidak bisa dihapus
     * (akan menjadi null reference — dijaga oleh FK nullOnDelete di migration).
     */
    public function destroy(Request $request, RabPriceItem $priceItem): RedirectResponse
    {
        $user = $request->user();
        abort_if($priceItem->user_id !== $user->id, 403);

        $priceItem->delete();

        return back()->with('success', 'Harga satuan berhasil dihapus.');
    }
}
