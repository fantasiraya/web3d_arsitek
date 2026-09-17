<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $order_id
 * @property string $amount
 * @property string|null $payment_type
 * @property string $status
 * @property array<string, mixed>|null $snap_response
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLEMENT = 'settlement';

    public const STATUS_EXPIRE = 'expire';

    public const STATUS_CANCEL = 'cancel';

    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'order_id',
        'amount',
        'payment_type',
        'status',
        'snap_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'snap_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The user making the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Subscription activated by this transaction.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'transaction_id');
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_SETTLEMENT;
    }
}
