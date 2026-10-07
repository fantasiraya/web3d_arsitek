<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Rab\Models\RabTemplate;
use App\Domains\Rab\Models\RabTemplateItem;
use Illuminate\Support\Facades\DB;

class SaveRabTemplateAction
{
    /**
     * Buat atau update template RAB beserta item-itemnya.
     *
     * Jika $existing diisi, update nama/deskripsi dan sync items.
     * Items dikirim sebagai array: [['rab_price_item_id', 'section', 'sort_order'], ...]
     */
    public function execute(User $user, array $data, ?RabTemplate $existing = null): RabTemplate
    {
        return DB::transaction(function () use ($user, $data, $existing) {
            if ($existing === null) {
                $template = RabTemplate::create([
                    'user_id'     => $user->id,
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            } else {
                abort_if($existing->user_id !== $user->id, 403, 'Anda tidak memiliki hak untuk mengubah template ini.');

                $existing->fill([
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? $existing->description,
                ]);
                $existing->save();
                $template = $existing;
            }

            // Sync items jika disupply
            if (isset($data['items'])) {
                // Hapus items lama, ganti dengan yang baru
                $template->items()->delete();

                foreach ($data['items'] as $index => $itemData) {
                    RabTemplateItem::create([
                        'rab_template_id'   => $template->id,
                        'rab_price_item_id' => $itemData['rab_price_item_id'],
                        'section'           => $itemData['section'] ?? 'Umum',
                        'sort_order'        => $itemData['sort_order'] ?? $index,
                    ]);
                }
            }

            return $template->load('items.priceItem');
        });
    }
}
