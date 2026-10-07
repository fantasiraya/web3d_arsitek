<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Project\Models\Project;
use App\Domains\Rab\Actions\DeleteRabItemAction;
use App\Domains\Rab\Actions\UpsertRabItemAction;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Requests\UpsertRabItemRequest;
use App\Domains\Rab\Services\RabAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RabItemController extends Controller
{
    public function __construct(
        protected RabAccessService   $access,
        protected UpsertRabItemAction $upsert,
        protected DeleteRabItemAction $delete,
    ) {}

    /**
     * Tambah item baru ke dokumen RAB.
     */
    public function store(UpsertRabItemRequest $request, Project $project, RabDocument $rab): RedirectResponse
    {
        $this->access->authorizeEdit($request->user(), $project);

        $this->upsert->execute($rab, $request->validated());

        return back()->with('success', 'Item berhasil ditambahkan.');
    }

    /**
     * Update item existing di dokumen RAB.
     */
    public function update(UpsertRabItemRequest $request, Project $project, RabDocument $rab, RabItem $item): RedirectResponse
    {
        $this->access->authorizeEdit($request->user(), $project);
        abort_if($item->rab_document_id !== $rab->id, 404);

        $this->upsert->execute($rab, $request->validated(), $item);

        return back()->with('success', 'Item berhasil diperbarui.');
    }

    /**
     * Hapus item dari dokumen RAB.
     */
    public function destroy(Request $request, Project $project, RabDocument $rab, RabItem $item): RedirectResponse
    {
        $this->access->authorizeEdit($request->user(), $project);

        $this->delete->execute($rab, $item);

        return back()->with('success', 'Item berhasil dihapus.');
    }
}
