<?php

namespace App\Domains\Rab\Requests;

use App\Domains\Rab\Services\TakeoffParserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportTakeoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via RabAccessService
    }

    public function rules(): array
    {
        $supported = TakeoffParserService::SUPPORTED;
        $mimes     = 'csv,xlsx,xls';

        return [
            // Step 1: Upload
            'file'         => ['required', 'file', 'max:10240', "mimes:{$mimes}"],
            'header_row'   => ['sometimes', 'integer', 'min:1', 'max:10'],

            // Step 2: Pilih kolom
            'name_col'     => ['required', 'string', 'max:100'],
            'qty_col'      => ['required', 'string', 'max:100'],
            'unit_col'     => ['nullable', 'string', 'max:100'],
            'material_col' => ['nullable', 'string', 'max:100'],
            'section_col'  => ['nullable', 'string', 'max:100'],

            // Step 3: Manual price overrides untuk item yang tidak ter-map
            'manual_prices'                    => ['sometimes', 'array'],
            'manual_prices.*.row_index'        => ['required', 'integer'],
            'manual_prices.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'manual_prices.*.price_item_id'    => ['nullable', 'uuid', 'exists:rab_price_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required'   => 'File wajib diupload.',
            'file.mimes'      => 'Format file tidak didukung. Gunakan CSV, XLSX, atau XLS.',
            'file.max'        => 'Ukuran file maksimal 10 MB.',
            'name_col.required' => 'Kolom nama/uraian pekerjaan wajib dipilih.',
            'qty_col.required'  => 'Kolom kuantitas/volume wajib dipilih.',
        ];
    }
}
