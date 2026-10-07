<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;
use Illuminate\Support\Facades\DB;

class UpdateRabDocumentAction
{
    public function __construct(
        protected RecalculateRabAction $recalculate
    ) {}

    /**
     * Update metadata dokumen RAB (judul, overhead, PPN).
     * Dokumen yang sudah final tidak bisa diedit — harus reopen dulu.
     */
    public function execute(RabDocument $document, array $data): RabDocument
    {
        abort_if($document->isFinal(), 403, 'Dokumen RAB sudah final dan tidak dapat diedit. Lakukan reopen terlebih dahulu.');

        return DB::transaction(function () use ($document, $data) {
            $needsRecalc = false;

            if (isset($data['title'])) {
                $document->title = $data['title'];
            }

            if (isset($data['overhead_percent']) && $data['overhead_percent'] != $document->overhead_percent) {
                $document->overhead_percent = $data['overhead_percent'];
                $needsRecalc = true;
            }

            if (isset($data['ppn_percent']) && $data['ppn_percent'] != $document->ppn_percent) {
                $document->ppn_percent = $data['ppn_percent'];
                $needsRecalc = true;
            }

            $document->save();

            if ($needsRecalc) {
                $document = $this->recalculate->execute($document);
            }

            return $document->fresh();
        });
    }
}
