<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;

class DeleteRabItemAction
{
    public function __construct(
        protected RecalculateRabAction $recalculate
    ) {}

    /**
     * Hapus item dari dokumen RAB lalu recalculate total.
     */
    public function execute(RabDocument $document, RabItem $item): void
    {
        abort_if($document->isFinal(), 403, 'Dokumen RAB sudah final. Lakukan reopen untuk menghapus item.');
        abort_if($item->rab_document_id !== $document->id, 422, 'Item tidak ditemukan di dokumen ini.');

        $item->delete();

        $this->recalculate->execute($document);
    }
}
