<?php

namespace Database\Factories;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectCapture>
 */
class ProjectCaptureFactory extends Factory
{
    protected $model = ProjectCapture::class;

    public function definition(): array
    {
        return [
            'project_id'            => Project::factory(),
            'user_id'               => User::factory(),
            'title'                 => fake()->words(3, true),
            'room_name'             => fake()->randomElement(['Ruang Tamu', 'Kamar Utama', 'Dapur', 'Fasad Depan']),
            'captured_hour'         => fake()->randomElement(['08:00', '12:00', '17:00', '20:00']),
            'file_path'             => 'projects/captures/'.fake()->uuid().'.png',
            'file_size_bytes'       => 1024 * 500, // 500 KB
            'width'                 => 1920,
            'height'                => 1080,
            'is_visible_to_clients' => false,
        ];
    }

    public function visibleToClients(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible_to_clients' => true,
        ]);
    }
}

