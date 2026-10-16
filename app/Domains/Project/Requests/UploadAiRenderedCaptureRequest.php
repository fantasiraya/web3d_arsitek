<?php

namespace App\Domains\Project\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAiRenderedCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'File gambar wajib diunggah.',
            'image.image'    => 'File harus berupa gambar.',
            'image.mimes'    => 'Format gambar harus JPG, JPEG, PNG, atau WebP.',
            'image.max'      => 'Ukuran gambar maksimal 10MB.',
        ];
    }
}

