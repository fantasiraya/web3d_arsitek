<?php

namespace App\Domains\Drawing\Requests;

use App\Domains\Drawing\Models\DrawingSheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDrawingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'        => ['sometimes', 'required', 'string', 'max:255'],
            'type'         => ['sometimes', 'required', 'string', Rule::in(DrawingSheet::TYPES)],
            'scale'        => ['nullable', 'string', 'max:20'],
            'paper_size'   => ['nullable', 'string', 'max:20'],
            'orientation'  => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'width'        => ['nullable', 'integer', 'min:100', 'max:20000'],
            'height'       => ['nullable', 'integer', 'min:100', 'max:20000'],
            'grid_spacing' => ['nullable', 'integer', 'min:5', 'max:200'],
            'unit'         => ['nullable', 'string', 'max:10'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ];
    }
}

