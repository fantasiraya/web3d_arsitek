<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;
use Illuminate\Support\Facades\DB;

class FinalizeRabDocumentAction
{
    public function __construct(
        protected RecalculateRabAction $recalculate
    ) {}

    /**
     * Finalisasi dokumen RAB → status 'final', terkunci dari edit.
     * Hanya pemilik yang bisa melakukan ini (dicek di controller via RabAccessService).
     */
    public function finalize(RabDocument $document): RabDocument
    {
        abort_if($document->isFinal(), 422, 'Dokumen RAB sudah dalam status final.');

        return DB::transaction(function () use ($document) {
            // Pastikan kalkulasi terbaru sebelum finalize
            $document = $this->recalculate->execute($document);

            $document->status       = RabDocument::STATUS_FINAL;
            $document->finalized_at = now();
            $document->save();

            return $document->fresh();
        });
    }

    /**
     * Reopen dokumen RAB final → kembali ke status 'draft'.
     * Hanya pemilik yang bisa reopen (dicek di controller).
     */
    public function reopen(RabDocument $document): RabDocument
    {
        abort_if($document->isDraft(), 422, 'Dokumen RAB masih dalam status draft.');

        $document->status       = RabDocument::STATUS_DRAFT;
        $document->finalized_at = null;
        $document->save();

        return $document->fresh();
    }
}
