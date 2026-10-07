<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;

class ToggleRabClientVisibilityAction
{
    /**
     * Toggle visibilitas RAB ke Klien.
     * Switch boleh diubah kapan saja — draft maupun final.
     * Hanya pemilik project yang bisa toggle (dicek di controller).
     *
     * @param  bool|null $forceValue  Jika diisi, paksa ke nilai ini; jika null, toggle otomatis.
     */
    public function execute(RabDocument $document, ?bool $forceValue = null): RabDocument
    {
        $document->is_visible_to_clients = $forceValue ?? (! $document->is_visible_to_clients);
        $document->save();

        return $document->fresh();
    }
}
