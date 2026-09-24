<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property int|null $custom_project_limit
 * @property bool $is_unlimited
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserPlanOverride extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_plan_overrides';

    protected $fillable = [
        'user_id',
        'custom_project_limit',
        'is_unlimited',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'custom_project_limit' => 'integer',
            'is_unlimited' => 'boolean',
        ];
    }

    /**
     * The user this override belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
