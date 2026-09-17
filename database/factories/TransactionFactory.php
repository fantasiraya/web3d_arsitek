<?php

namespace Database\Factories;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => 'TRX-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'amount' => 150000.00,
            'payment_type' => 'qris',
            'status' => Transaction::STATUS_PENDING,
            'snap_response' => null,
            'paid_at' => null,
        ];
    }

    /**
     * Mark transaction as settled/paid.
     */
    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::STATUS_SETTLEMENT,
            'paid_at' => now(),
        ]);
    }
}
