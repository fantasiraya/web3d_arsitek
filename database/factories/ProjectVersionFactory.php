<?php

namespace Database\Factories;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectVersion>
 */
class ProjectVersionFactory extends Factory
{
    protected $model = ProjectVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version_number' => 1,
            'file_path' => 'projects/versions/'.Str::uuid().'.glb',
            'file_size_bytes' => fake()->numberBetween(1_000_000, 15_000_000),
            'changelog' => fake()->sentence(),
            'is_draco_compressed' => false,
        ];
    }
}
