<?php

namespace App\Domains\Drawing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncDrawingElementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'elements'              => ['present', 'array'],
            'elements.*.id'         => ['nullable', 'string'],
            'elements.*.type'       => ['required', 'string', 'max:30'],
            'elements.*.data'       => ['required', 'array'],
            'elements.*.sort_order' => ['nullable', 'integer'],
            'sheet'                 => ['nullable', 'array'],
            'sheet.width'           => ['nullable', 'integer', 'min:100', 'max:20000'],
            'sheet.height'          => ['nullable', 'integer', 'min:100', 'max:20000'],
            'sheet.paper_size'      => ['nullable', 'string', 'max:30'],
            'sheet.orientation'     => ['nullable', 'string', 'max:20'],
        ];
    }
}

