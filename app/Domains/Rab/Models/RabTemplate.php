<?php

namespace App\Domains\Rab\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $user_id
 * @property string      $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rab_templates';

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    /** Pemilik template. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Item-item di dalam template ini. */
    public function items(): HasMany
    {
        return $this->hasMany(RabTemplateItem::class, 'rab_template_id')->orderBy('sort_order');
    }
}
