<?php

namespace Database\Factories;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectClient>
 */
class ProjectClientFactory extends Factory
{
    protected $model = ProjectClient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'email' => fake()->safeEmail(),
            'user_id' => null,
            'invited_by' => User::factory(),
            'status' => ProjectClient::STATUS_PENDING,
            'invited_at' => now(),
            'accepted_at' => null,
        ];
    }

    /**
     * State for accepted invitation.
     */
    public function accepted(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectClient::STATUS_ACCEPTED,
            'user_id' => $user?->id ?? User::factory(),
            'accepted_at' => now(),
        ]);
    }

    /**
     * State for revoked invitation.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectClient::STATUS_REVOKED,
        ]);
    }
}
