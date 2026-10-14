<?php

namespace App\Domains\Project\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectSunSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude'    => ['required', 'numeric', 'between:-90,90'],
            'longitude'   => ['required', 'numeric', 'between:-180,180'],
            'north_angle' => ['required', 'numeric', 'between:0,360'],
            'solar_date'  => ['nullable', 'date'],
        ];
    }
}

