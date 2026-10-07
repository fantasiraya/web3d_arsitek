<?php

namespace App\Domains\Rab\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId    = $this->user()->id;
        $itemId    = $this->route('priceItem')?->id;

        return [
            'code'       => [
                'nullable',
                'string',
                'max:50',
                // Kode unik per user, kecuali untuk update item yang sama
                Rule::unique('rab_price_items')->where('user_id', $userId)->ignore($itemId),
            ],
            'name'       => ['required', 'string', 'max:255'],
            'unit'       => ['required', 'string', 'max:20'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'category'   => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'Nama pekerjaan wajib diisi.',
            'unit.required'       => 'Satuan wajib diisi.',
            'unit_price.required' => 'Harga satuan wajib diisi.',
            'unit_price.min'      => 'Harga satuan tidak boleh negatif.',
            'code.unique'         => 'Kode item sudah digunakan. Gunakan kode yang berbeda.',
        ];
    }
}
