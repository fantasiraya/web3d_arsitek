<?php

namespace App\Domains\Rab\Requests;

use App\Domains\Rab\Models\RabDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRabDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via RabAccessService
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:255'],
            'source'          => ['sometimes', Rule::in([RabDocument::SOURCE_MANUAL, RabDocument::SOURCE_CSV, RabDocument::SOURCE_GLB])],
            'rab_template_id' => ['nullable', 'uuid', 'exists:rab_templates,id'],
            'overhead_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'ppn_percent'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'       => 'Judul RAB wajib diisi.',
            'title.max'            => 'Judul RAB maksimal 255 karakter.',
            'source.in'            => 'Sumber RAB tidak valid.',
            'rab_template_id.exists' => 'Template RAB tidak ditemukan.',
        ];
    }
}
