<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Rab\Models\RabPriceItem;

class SavePriceItemAction
{
    /**
     * Buat atau update harga satuan milik user.
     *
     * Aturan snapshot (Section M.4):
     *  Mengubah unit_price di sini TIDAK mengubah rab_items.unit_price yang sudah ada.
     *  Snapshot hanya diambil saat item RAB pertama kali dibuat (UpsertRabItemAction).
     */
    public function execute(User $user, array $data, ?RabPriceItem $existing = null): RabPriceItem
    {
        if ($existing === null) {
            return RabPriceItem::create([
                'user_id'    => $user->id,
                'code'       => $data['code'] ?? null,
                'name'       => $data['name'],
                'unit'       => $data['unit'],
                'unit_price' => $data['unit_price'],
                'category'   => $data['category'] ?? null,
            ]);
        }

        // Pastikan hanya pemilik yang bisa update
        abort_if($existing->user_id !== $user->id, 403, 'Anda tidak memiliki hak untuk mengubah harga satuan ini.');

        $existing->fill([
            'code'       => $data['code'] ?? $existing->code,
            'name'       => $data['name'],
            'unit'       => $data['unit'],
            'unit_price' => $data['unit_price'],
            'category'   => $data['category'] ?? $existing->category,
        ]);
        $existing->save();

        return $existing->fresh();
    }
}
