<?php

namespace Database\Factories;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(5),
            'description' => fake()->paragraph(),
            'file_path' => 'projects/models/'.Str::uuid().'.glb',
            'file_size_bytes' => fake()->numberBetween(1_000_000, 15_000_000),
            'is_draco_compressed' => false,
            'share_token' => Str::random(32),
            'max_revisions_allowed' => 3,
            'current_revision_count' => 0,
        ];
    }

    /**
     * Mark project as draco compressed.
     */
    public function compressed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draco_compressed' => true,
        ]);
    }
}
