<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int|null $project_limit
 * @property bool $can_create_project
 * @property bool $can_edit_project
 * @property bool $can_delete_project
 * @property bool $can_export
 * @property int $max_team_members
 * @property bool $api_access
 * @property bool $audit_log
 * @property bool $advanced_analytics
 * @property bool $sso
 * @property bool $custom_branding
 * @property bool $priority_support
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    use HasFactory, HasUuids;

    public const SLUG_FREE = 'free';

    public const SLUG_PRO = 'pro';

    public const SLUG_ENTERPRISE = 'enterprise';

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'display_name',
        'tagline',
        'badge_text',
        'cta_text',
        'cta_url',
        'is_featured',
        'price_monthly',
        'price_annual',
        'period_label',
        'benefits',
        'sort_order',
        'description',
        'project_limit',
        'can_create_project',
        'can_edit_project',
        'can_delete_project',
        'can_export',
        'max_team_members',
        'api_access',
        'audit_log',
        'advanced_analytics',
        'sso',
        'custom_branding',
        'priority_support',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'project_limit'        => 'integer',
            'can_create_project'   => 'boolean',
            'can_edit_project'     => 'boolean',
            'can_delete_project'   => 'boolean',
            'can_export'           => 'boolean',
            'max_team_members'     => 'integer',
            'api_access'           => 'boolean',
            'audit_log'            => 'boolean',
            'advanced_analytics'   => 'boolean',
            'sso'                  => 'boolean',
            'custom_branding'      => 'boolean',
            'priority_support'     => 'boolean',
            'is_featured'          => 'boolean',
            'benefits'             => 'array',
            'sort_order'           => 'integer',
        ];
    }

    /**
     * Subscriptions associated with this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Whether this plan has unlimited projects.
     */
    public function isUnlimitedProjects(): bool
    {
        return $this->project_limit === null;
    }

    /**
     * Get active plans formatted for frontend display, cached in Redis with tags.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getActivePlansForDisplay(): array
    {
        return \App\Support\CacheKeys::tags([\App\Support\CacheKeys::TAG_PLANS])->remember(
            \App\Support\CacheKeys::KEY_PLANS_ACTIVE,
            \App\Support\CacheKeys::TTL_MEDIUM,
            fn () => static::where('status', 'active')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan) => [
                    'id'            => $plan->id,
                    'slug'          => $plan->slug,
                    'display_name'  => $plan->display_name ?? $plan->name,
                    'tagline'       => $plan->tagline ?? '',
                    'badge_text'    => $plan->badge_text ?? '',
                    'cta_text'      => $plan->cta_text ?? 'Mulai Sekarang',
                    'cta_url'       => $plan->cta_url ?? '/register',
                    'is_featured'   => (bool) $plan->is_featured,
                    'price_monthly' => $plan->price_monthly ?? 'Rp 0',
                    'price_annual'  => $plan->price_annual  ?? 'Rp 0',
                    'period_label'  => $plan->period_label  ?? 'per bulan',
                    'benefits'      => $plan->benefits ?? [],
                ])
                ->all()
        );
    }
}
