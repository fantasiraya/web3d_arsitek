<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Rab\Actions\SaveRabTemplateAction;
use App\Domains\Rab\Models\RabTemplate;
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
     * Detail template dengan items-nya (untuk modal edit).
     */
    public function show(Request $request, RabTemplate $template): \Illuminate\Http\JsonResponse
    {
        abort_if($template->user_id !== $request->user()->id, 403);

        $template->load('items.priceItem');

        return response()->json($template);
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
}
