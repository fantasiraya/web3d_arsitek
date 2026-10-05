<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
{
    public function __invoke(): Response
    {
        $plans = Plan::where('status', 'active')
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
            ]);

        return Inertia::render('Plans', [
            'plans' => $plans,
        ]);
    }
}
