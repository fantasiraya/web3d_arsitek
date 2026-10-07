<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Rab\Actions\SaveRabTemplateAction;
use App\Domains\Rab\Models\RabPriceItem;
use App\Domains\Rab\Models\RabTemplate;
use App\Domains\Rab\Models\RabTemplateItem;
use App\Domains\Rab\Requests\StoreRabTemplateRequest;
use App\Domains\Rab\Services\RabAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RabTemplateController extends Controller
{
    public function __construct(
        protected RabAccessService    $access,
        protected SaveRabTemplateAction $save,
    ) {}

    /**
     * Daftar template RAB milik user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403, 'Paket langganan Anda tidak mendukung fitur RAB.');

        $templates = RabTemplate::where('user_id', $user->id)
            ->withCount('items')
            ->latest()
            ->get();

        return Inertia::render('Rab/Templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Buat template RAB baru.
     */
    public function store(StoreRabTemplateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403);

        $this->save->execute($user, $request->validated());

        return back()->with('success', 'Template RAB berhasil dibuat.');
    }

    /**
     * Detail template dengan items-nya — Inertia page untuk kelola item.
     */
    public function show(Request $request, RabTemplate $template): \Inertia\Response
    {
        abort_if($template->user_id !== $request->user()->id, 403);

        $template->load(['items' => fn ($q) => $q->with('priceItem')->orderBy('sort_order')]);

        // Semua harga satuan milik user — untuk dropdown pilih item
        $priceItems = \App\Domains\Rab\Models\RabPriceItem::where('user_id', $request->user()->id)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'unit_price', 'category']);

        return \Inertia\Inertia::render('Rab/TemplateShow', [
            'template'   => $template,
            'priceItems' => $priceItems,
        ]);
    }

    /**
     * Update template RAB.
     */
    public function update(StoreRabTemplateRequest $request, RabTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->access->hasPlanAccess($user), 403);
        abort_if($template->user_id !== $user->id, 403);

        $this->save->execute($user, $request->validated(), $template);

        return back()->with('success', 'Template RAB berhasil diperbarui.');
    }

    /**
     * Hapus template RAB.
     */
    public function destroy(Request $request, RabTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_if($template->user_id !== $user->id, 403);

        $template->delete();

        return back()->with('success', 'Template RAB berhasil dihapus.');
    }

    // ── Item management ──────────────────────────────────────────────────────

    /**
     * Tambah item harga satuan ke template.
     */
    public function addItem(Request $request, RabTemplate $template): RedirectResponse
    {
        $user = $request->user();
        abort_if($template->user_id !== $user->id, 403);
        abort_unless($this->access->hasPlanAccess($user), 403);

        $request->validate([
            'rab_price_item_id' => ['required', 'uuid', 'exists:rab_price_items,id'],
            'section'           => ['required', 'string', 'max:100'],
        ]);

        // Pastikan price item milik user ini
        $priceItem = RabPriceItem::where('id', $request->rab_price_item_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $maxOrder = $template->items()->max('sort_order') ?? -1;

        RabTemplateItem::create([
            'rab_template_id'   => $template->id,
            'rab_price_item_id' => $priceItem->id,
            'section'           => $request->section,
            'sort_order'        => $maxOrder + 1,
        ]);

        return back()->with('success', "Item \"{$priceItem->name}\" ditambahkan ke template.");
    }

    /**
     * Hapus item dari template.
     */
    public function removeItem(Request $request, RabTemplate $template, RabTemplateItem $item): RedirectResponse
    {
        abort_if($template->user_id !== $request->user()->id, 403);
        abort_if($item->rab_template_id !== $template->id, 404);

        $item->delete();

        return back()->with('success', 'Item dihapus dari template.');
    }

    /**
     * Reorder items template (drag-and-drop).
     */
    public function reorderItems(Request $request, RabTemplate $template): RedirectResponse
    {
        abort_if($template->user_id !== $request->user()->id, 403);

        $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['uuid'],
        ]);

        foreach ($request->order as $index => $itemId) {
            RabTemplateItem::where('id', $itemId)
                ->where('rab_template_id', $template->id)
                ->update(['sort_order' => $index]);
        }

        return back()->with('success', 'Urutan item diperbarui.');
    }
}
