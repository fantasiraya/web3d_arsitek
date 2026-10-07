<?php

namespace App\Domains\Rab\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyGlbQuantitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via RabAccessService
    }

    public function rules(): array
    {
        return [
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.name'                   => ['required', 'string', 'max:255'],
            'items.*.quantity'               => ['required', 'numeric', 'min:0'],
            'items.*.quantity_basis'         => ['required', Rule::in(['area', 'volume', 'count', 'length'])],
            'items.*.unit'                   => ['required', 'string', 'max:20'],
            'items.*.section'                => ['required', 'string', 'max:100'],
            'items.*.rab_price_item_id'      => ['nullable', 'uuid', 'exists:rab_price_items,id'],
            'items.*.unit_price'             => ['required', 'numeric', 'min:0'],
            'project_version_id'             => ['nullable', 'uuid', 'exists:project_versions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                    => 'Minimal satu item estimasi diperlukan.',
            'items.min'                         => 'Minimal satu item estimasi diperlukan.',
            'items.*.name.required'             => 'Nama objek wajib diisi.',
            'items.*.quantity.required'         => 'Kuantitas wajib diisi.',
            'items.*.quantity.min'              => 'Kuantitas tidak boleh negatif.',
            'items.*.quantity_basis.in'         => 'Basis kuantitas harus: area, volume, count, atau length.',
            'items.*.unit.required'             => 'Satuan wajib diisi.',
            'items.*.section.required'          => 'Bagian pekerjaan wajib diisi.',
            'items.*.rab_price_item_id.exists'  => 'Harga satuan tidak ditemukan.',
            'items.*.unit_price.min'            => 'Harga satuan tidak boleh negatif.',
        ];
    }
}
