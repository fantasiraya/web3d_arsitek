<?php

namespace App\Domains\Project\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'                 => ['required', 'string'],
            'room_name'             => ['required', 'string', 'max:100'],
            'captured_hour'         => ['required', 'string', 'max:10'],
            'title'                 => ['nullable', 'string', 'max:255'],
            'width'                 => ['nullable', 'integer', 'min:100', 'max:7680'],
            'height'                => ['nullable', 'integer', 'min:100', 'max:4320'],
            'is_visible_to_clients' => ['nullable', 'boolean'],
            'prompt'                => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required'         => 'Data gambar capture wajib ada.',
            'room_name.required'     => 'Nama ruangan/preset wajib diisi.',
            'captured_hour.required' => 'Jam capture wajib diisi.',
        ];
    }
}

