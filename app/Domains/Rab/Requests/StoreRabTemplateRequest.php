<?php

namespace App\Domains\Rab\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRabTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                        => ['required', 'string', 'max:255'],
            'description'                 => ['nullable', 'string', 'max:1000'],
            // Item-item awal opsional saat membuat template
            'items'                       => ['sometimes', 'array'],
            'items.*.rab_price_item_id'   => ['required', 'uuid', 'exists:rab_price_items,id'],
            'items.*.section'             => ['required', 'string', 'max:100'],
            'items.*.sort_order'          => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                      => 'Nama template wajib diisi.',
            'items.*.rab_price_item_id.required' => 'Setiap item template wajib punya harga satuan.',
            'items.*.rab_price_item_id.exists'   => 'Harga satuan tidak ditemukan.',
            'items.*.section.required'           => 'Bagian pekerjaan item template wajib diisi.',
        ];
    }
}
