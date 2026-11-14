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
        'can_use_rab',
        'can_use_drawing',
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
            'can_use_rab'          => 'boolean',
            'can_use_drawing'      => 'boolean',
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
            function () {
                $canonicalPlans = [
                    'free' => [
                        'display_name'  => 'Free Tier',
                        'tagline'       => 'Ideal untuk arsitek individual yang baru mencoba platform',
                        'badge_text'    => 'Coba Gratis',
                        'cta_text'      => 'Mulai Sekarang',
                        'cta_url'       => '/register',
                        'is_featured'   => false,
                        'price_monthly' => 'Rp 0',
                        'price_annual'  => 'Rp 0',
                        'period_label'  => 'selamanya',
                        'benefits'      => [
                            'Batas maksimal 1 proyek aktif',
                            'Batas kuota 3x revisi klien per proyek',
                            'Undangan klien via email terenkripsi',
                            '3D WebGL Viewer & Pin Komentar Spasial',
                            'Simulasi Matahari & Arah Bayangan (SunCalc)',
                            'Maksimal ukuran file 25 MB',
                            'Google OAuth One-Tap Login',
                        ],
                    ],
                    'pro' => [
                        'display_name'  => 'Pro Architect',
                        'tagline'       => 'Untuk studio arsitektur aktif & konsultan profesional',
                        'badge_text'    => 'Paling Populer',
                        'cta_text'      => 'Upgrade ke Pro',
                        'cta_url'       => '/register?plan=pro',
                        'is_featured'   => true,
                        'price_monthly' => 'Rp 149.000',
                        'price_annual'  => 'Rp 119.000',
                        'period_label'  => 'per bulan',
                        'benefits'      => [
                            'Batas kuota hingga 20 proyek aktif',
                            'Akses Penuh AI Vision Studio (Image-to-Prompt & Video AI)',
                            'Estimator RAB (Volume GLB 3D & Master SNI AHSP)',
                            'Ekspor Dokumen RAB ke Excel (.xlsx)',
                            'Kustomisasi batas revisi klien (hingga unlimited)',
                            'Fitur In-App Real-time Chat (Laravel Reverb)',
                            'Prioritas pemrosesan kompresi Draco 3D (hingga 100 MB)',
                            'Penyimpanan berkecepatan tinggi',
                            'Pembayaran otomatis (QRIS, VA, Kartu via Midtrans)',
                        ],
                    ],
                    'enterprise' => [
                        'display_name'  => 'Enterprise Studio',
                        'tagline'       => 'Untuk biro konsultan arsitektur berskala besar',
                        'badge_text'    => 'Custom Team',
                        'cta_text'      => 'Konsultasi Tim',
                        'cta_url'       => '/register?plan=enterprise',
                        'is_featured'   => false,
                        'price_monthly' => 'Hubungi Kami',
                        'price_annual'  => 'Hubungi Kami',
                        'period_label'  => 'kebutuhan tim',
                        'benefits'      => [
                            'Kuota proyek tanpa batas (Unlimited)',
                            'Lembar Kerja CAD 2D Interaktif (Konva.js)',
                            'Ekspor AutoCAD DXF (Layer terpisah) & SVG',
                            'Booklet Presentasi A3 Landscape (1-Klik PDF)',
                            'Shop Drawing & Cutting List Produksi',
                            'Direct AI Architectural Render (Gemini API)',
                            'Akses Prioritas AI Vision Studio & Galeri Unlimited',
                            'Custom Domain & Whitelabel Branding',
                            'Single Sign-On (SSO) & Audit Logs',
                            'Dukungan dedicated SLA & prioritas teknis',
                        ],
                    ],
                ];

                return static::where('status', 'active')
                    ->orderBy('sort_order')
                    ->get()
                    ->map(function (Plan $plan) use ($canonicalPlans) {
                        $defaults = $canonicalPlans[$plan->slug] ?? [];
                        $hasPrice = $plan->slug === 'free' || ($plan->price_monthly && !in_array($plan->price_monthly, ['Rp 0', '0', '']));
                        $hasBenefits = is_array($plan->benefits) && count($plan->benefits) > 0;

                        return [
                            'id'            => $plan->id,
                            'slug'          => $plan->slug,
                            'display_name'  => ($plan->display_name && $plan->display_name !== $plan->slug) ? $plan->display_name : ($defaults['display_name'] ?? $plan->name),
                            'tagline'       => $plan->tagline ?: ($defaults['tagline'] ?? ''),
                            'badge_text'    => $plan->badge_text ?: ($defaults['badge_text'] ?? ''),
                            'cta_text'      => $plan->cta_text ?: ($defaults['cta_text'] ?? 'Mulai Sekarang'),
                            'cta_url'       => $plan->cta_url ?: ($defaults['cta_url'] ?? '/register'),
                            'is_featured'   => (bool) ($plan->is_featured ?? ($defaults['is_featured'] ?? false)),
                            'price_monthly' => $hasPrice ? $plan->price_monthly : ($defaults['price_monthly'] ?? 'Rp 0'),
                            'price_annual'  => $hasPrice ? $plan->price_annual : ($defaults['price_annual'] ?? 'Rp 0'),
                            'period_label'  => $plan->period_label ?: ($defaults['period_label'] ?? 'per bulan'),
                            'benefits'      => $hasBenefits ? $plan->benefits : ($defaults['benefits'] ?? []),
                        ];
                    })
                    ->all();
            }
        );
    }
}
