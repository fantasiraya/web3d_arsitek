<?php

namespace App\Domains\Drawing\Requests;

use App\Domains\Drawing\Models\DrawingSheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateDrawingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id'   => ['required', 'uuid', 'exists:projects,id'],
            'title'        => ['required', 'string', 'max:255'],
            'type'         => ['required', 'string', Rule::in(DrawingSheet::TYPES)],
            'scale'        => ['nullable', 'string', 'max:20'],
            'paper_size'   => ['nullable', 'string', 'max:20'],
            'orientation'  => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'width'        => ['nullable', 'integer', 'min:100', 'max:20000'],
            'height'       => ['nullable', 'integer', 'min:100', 'max:20000'],
            'grid_spacing'   => ['nullable', 'integer', 'min:5', 'max:200'],
            'unit'           => ['nullable', 'string', 'max:10'],
            'client_name'    => ['nullable', 'string', 'max:255'],
            'client_address' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

