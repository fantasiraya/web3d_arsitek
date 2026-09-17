<?php

namespace Database\Factories;

use App\Domains\SystemConfig\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => (string) fake()->numberBetween(1, 100),
            'type' => 'integer',
            'description' => fake()->sentence(),
        ];
    }
}
