<?php

namespace App\Domains\Rab\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertRabItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section'           => ['required', 'string', 'max:100'],
            'description'       => ['required', 'string', 'max:255'],
            'unit'              => ['required', 'string', 'max:20'],
            'quantity'          => ['required', 'numeric', 'min:0'],
            'waste_percent'     => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'unit_price'        => ['required', 'numeric', 'min:0'],
            'rab_price_item_id' => ['nullable', 'uuid', 'exists:rab_price_items,id'],
            'sort_order'        => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'section.required'      => 'Bagian pekerjaan wajib diisi.',
            'description.required'  => 'Nama pekerjaan wajib diisi.',
            'unit.required'         => 'Satuan wajib diisi.',
            'quantity.required'     => 'Kuantitas wajib diisi.',
            'quantity.min'          => 'Kuantitas tidak boleh negatif.',
            'unit_price.required'   => 'Harga satuan wajib diisi.',
            'unit_price.min'        => 'Harga satuan tidak boleh negatif.',
            'waste_percent.max'     => 'Faktor sisa maksimal 100%.',
            'rab_price_item_id.exists' => 'Harga satuan tidak ditemukan.',
        ];
    }
}
