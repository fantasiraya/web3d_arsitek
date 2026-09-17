<?php

namespace Database\Factories;

use App\Domains\Auth\Models\User;
use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'content' => fake()->paragraph(),
            'position_x' => fake()->randomFloat(6, -10, 10),
            'position_y' => fake()->randomFloat(6, 0, 10),
            'position_z' => fake()->randomFloat(6, -10, 10),
            'normal_x' => fake()->randomFloat(6, -1, 1),
            'normal_y' => fake()->randomFloat(6, -1, 1),
            'normal_z' => fake()->randomFloat(6, -1, 1),
            'status' => Comment::STATUS_OPEN,
        ];
    }
}
