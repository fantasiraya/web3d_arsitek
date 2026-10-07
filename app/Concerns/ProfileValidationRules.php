<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(int|string|null $userId = null): array
    {
        return [
            'name'         => $this->nameRules(),
            'email'        => $this->emailRules($userId),
            // Badan usaha
            'company_type' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            // Wilayah Indonesia
            'province_id'   => ['nullable', 'integer'],
            'city_id'       => ['nullable', 'integer'],
            'district_id'   => ['nullable', 'integer'],
            'village_id'    => ['nullable', 'integer'],
            'province_name' => ['nullable', 'string', 'max:100'],
            'city_name'     => ['nullable', 'string', 'max:100'],
            'district_name' => ['nullable', 'string', 'max:100'],
            'village_name'  => ['nullable', 'string', 'max:100'],
            // Alamat
            'address'      => ['nullable', 'string', 'max:1000'],
            'postal_code'  => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(int|string|null $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
