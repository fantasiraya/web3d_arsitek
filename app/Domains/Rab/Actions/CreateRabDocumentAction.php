<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Models\RabTemplate;
use Illuminate\Support\Facades\DB;

class CreateRabDocumentAction
{
    public function __construct(
        protected RecalculateRabAction $recalculate
    ) {}

    /**
     * Buat dokumen RAB baru untuk sebuah project.
     * Jika rab_template_id diisi, item-item dari template disalin + snapshot harga satuan.
     */
    public function execute(User $user, Project $project, array $data): RabDocument
    {
        return DB::transaction(function () use ($user, $project, $data) {
            $document = RabDocument::create([
                'project_id'            => $project->id,
                'user_id'               => $user->id,
                'rab_template_id'       => $data['rab_template_id'] ?? null,
                'title'                 => $data['title'],
                'source'                => $data['source'] ?? RabDocument::SOURCE_MANUAL,
                'status'                => RabDocument::STATUS_DRAFT,
                'is_visible_to_clients' => false,
                'overhead_percent'      => $data['overhead_percent'] ?? 0,
                'ppn_percent'           => $data['ppn_percent'] ?? 0,
                'subtotal'              => 0,
                'overhead_amount'       => 0,
                'ppn_amount'            => 0,
                'total'                 => 0,
            ]);

            // Salin item dari template jika dipilih
            if (! empty($data['rab_template_id'])) {
                $template = RabTemplate::with('items.priceItem')
                    ->where('id', $data['rab_template_id'])
                    ->where('user_id', $user->id) // pastikan template milik user ini
                    ->first();

                if ($template) {
                    foreach ($template->items as $templateItem) {
                        $priceItem = $templateItem->priceItem;
                        if (! $priceItem) {
                            continue;
                        }

                        RabItem::create([
                            'rab_document_id'   => $document->id,
                            'rab_price_item_id' => $priceItem->id,
                            'section'           => $templateItem->section,
                            'description'       => $priceItem->name,
                            'unit'              => $priceItem->unit,
                            'quantity'          => 0,
                            'waste_percent'     => 0,
                            'unit_price'        => $priceItem->unit_price, // snapshot harga
                            'subtotal'          => 0,
                            'is_mapped'         => true,
                            'is_estimate'       => false,
                            'sort_order'        => $templateItem->sort_order,
                        ]);
                    }

                    // Hitung ulang setelah salin item template
                    $document = $this->recalculate->execute($document);
                }
            }

            return $document->loadMissing(['items', 'template']);
        });
    }
}
