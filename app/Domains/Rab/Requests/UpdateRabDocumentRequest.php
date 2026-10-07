<?php

namespace App\Domains\Rab\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRabDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'            => ['sometimes', 'string', 'max:255'],
            'overhead_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'ppn_percent'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.max'              => 'Judul RAB maksimal 255 karakter.',
            'overhead_percent.min'   => 'Overhead tidak boleh negatif.',
            'overhead_percent.max'   => 'Overhead maksimal 100%.',
            'ppn_percent.min'        => 'PPN tidak boleh negatif.',
            'ppn_percent.max'        => 'PPN maksimal 100%.',
        ];
    }
}
