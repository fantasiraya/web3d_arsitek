<?php

namespace Database\Factories;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_id' => Transaction::factory(),
            'plan' => 'pro',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
            'expires_at' => now()->addMonth(),
        ];
    }

    /**
     * Mark subscription as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }
}
