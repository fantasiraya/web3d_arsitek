<?php

namespace App\Domains\Comment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by middleware (project.access)
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'position_x' => ['required', 'numeric'],
            'position_y' => ['required', 'numeric'],
            'position_z' => ['required', 'numeric'],
            'normal_x' => ['nullable', 'numeric'],
            'normal_y' => ['nullable', 'numeric'],
            'normal_z' => ['nullable', 'numeric'],
        ];
    }
}
