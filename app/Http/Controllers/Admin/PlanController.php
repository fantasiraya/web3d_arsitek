<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Models\Plan;
use App\Domains\SystemConfig\Services\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function index(): Response
    {
        $plans = Plan::withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();

        // Payment settings
        $paymentSettings = \App\Domains\SystemConfig\Models\SystemSetting::whereIn('key', [
            'payment_gateway_enabled',
            'bank_name', 'bank_account_number', 'bank_account_holder',
            'midtrans_is_production', 'midtrans_server_key', 'midtrans_client_key',
            'admin_whatsapp', 'whatsapp_template',
            'payment_provider_primary', 'payment_provider_fallback', 'payment_provider_future',
            'tripay_api_key', 'tripay_private_key', 'tripay_merchant_code', 'tripay_is_sandbox',
            'xendit_secret_key', 'xendit_webhook_token',
            // Limit per tier
            'free_tier_max_file_size_mb', 'pro_tier_max_file_size_mb', 'enterprise_tier_max_file_size_mb',
            'free_tier_max_projects', 'pro_tier_max_projects',
        ])->pluck('value', 'key');

        return Inertia::render('Admin/Plans/Index', [
            'plans'           => $plans,
            'paymentSettings' => $paymentSettings,
        ]);
    }

    /**
     * Update tampilan/pricing plan (display_name, tagline, harga, benefits, dll)
     */
    public function updatePricing(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'tagline'      => ['nullable', 'string', 'max:255'],
            'badge_text'   => ['nullable', 'string', 'max:80'],
            'cta_text'     => ['required', 'string', 'max:80'],
            'cta_url'      => ['required', 'string', 'max:255'],
            'is_featured'  => ['required', 'boolean'],
            'price_monthly' => ['required', 'string', 'max:50'],
            'price_annual'  => ['required', 'string', 'max:50'],
            'period_label'  => ['required', 'string', 'max:50'],
            'benefits'      => ['required', 'array', 'min:1'],
            'benefits.*'    => ['required', 'string', 'max:255'],
            'sort_order'    => ['required', 'integer', 'min:0'],
        ]);

        $plan->update($validated);

        return back()->with('success', "Tampilan paket {$plan->display_name} berhasil diperbarui.");
    }

    /**
     * Update payment gateway settings
     */
    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_gateway_enabled'  => ['required', 'boolean'],
            'bank_name'                => ['nullable', 'string', 'max:100'],
            'bank_account_number'      => ['nullable', 'string', 'max:50'],
            'bank_account_holder'      => ['nullable', 'string', 'max:150'],
            'midtrans_is_production'   => ['required', 'boolean'],
            'midtrans_server_key'      => ['nullable', 'string', 'max:255'],
            'midtrans_client_key'      => ['nullable', 'string', 'max:255'],
            'admin_whatsapp'           => ['nullable', 'string', 'max:20'],
            'whatsapp_template'        => ['nullable', 'string', 'max:1000'],
            // Multi-provider
            'payment_provider_primary'  => ['nullable', 'string', 'in:midtrans,tripay,xendit'],
            'payment_provider_fallback' => ['nullable', 'string', 'in:midtrans,tripay,xendit,'],
            'payment_provider_future'   => ['nullable', 'string', 'in:midtrans,tripay,xendit,'],
            // Tripay
            'tripay_api_key'           => ['nullable', 'string', 'max:255'],
            'tripay_private_key'       => ['nullable', 'string', 'max:255'],
            'tripay_merchant_code'     => ['nullable', 'string', 'max:100'],
            'tripay_is_sandbox'        => ['required', 'boolean'],
            // Xendit
            'xendit_secret_key'        => ['nullable', 'string', 'max:255'],
            'xendit_webhook_token'     => ['nullable', 'string', 'max:255'],
            // Limit per tier
            'free_tier_max_file_size_mb'       => ['required', 'integer', 'min:1', 'max:500'],
            'pro_tier_max_file_size_mb'        => ['required', 'integer', 'min:1', 'max:500'],
            'enterprise_tier_max_file_size_mb' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        // Key-key yang tidak boleh di-overwrite jika dikirim kosong
        $secretKeys = [
            'midtrans_server_key', 'midtrans_client_key',
            'tripay_api_key', 'tripay_private_key',
            'xendit_secret_key', 'xendit_webhook_token',
        ];

        // Hapus cache SEBELUM update
        \Illuminate\Support\Facades\Cache::forget(
            \App\Domains\SystemConfig\Repositories\SystemSettingRepository::CACHE_KEY
        );

        foreach ($validated as $key => $value) {
            // Jangan overwrite secret key jika dikirim kosong
            if (in_array($key, $secretKeys) && empty($value)) {
                continue;
            }
            \App\Domains\SystemConfig\Models\SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) ($value ?? '')]
            );
        }

        $status = $validated['payment_gateway_enabled']
            ? 'Payment Gateway (Midtrans) diaktifkan.'
            : 'Mode pembayaran manual (transfer bank) diaktifkan.';

        return back()->with('success', $status);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_unlimited' => ['required', 'boolean'],
            'project_limit' => ['nullable', 'required_if:is_unlimited,false', 'integer', 'min:1'],
            'can_create_project' => ['required', 'boolean'],
            'can_edit_project' => ['required', 'boolean'],
            'can_delete_project' => ['required', 'boolean'],
            'can_export' => ['required', 'boolean'],
            'max_team_members' => ['required', 'integer', 'min:1'],
            'api_access' => ['required', 'boolean'],
            'audit_log' => ['required', 'boolean'],
            'advanced_analytics' => ['required', 'boolean'],
            'sso' => ['required', 'boolean'],
            'custom_branding' => ['required', 'boolean'],
            'priority_support' => ['required', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $oldValues = $plan->only([
            'name', 'project_limit', 'can_create_project', 'can_edit_project',
            'can_delete_project', 'can_export', 'max_team_members', 'api_access',
            'audit_log', 'advanced_analytics', 'sso', 'custom_branding', 'priority_support', 'status',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'project_limit' => $validated['is_unlimited'] ? null : (int) $validated['project_limit'],
            'can_create_project' => (bool) $validated['can_create_project'],
            'can_edit_project' => (bool) $validated['can_edit_project'],
            'can_delete_project' => (bool) $validated['can_delete_project'],
            'can_export' => (bool) $validated['can_export'],
            'max_team_members' => (int) $validated['max_team_members'],
            'api_access' => (bool) $validated['api_access'],
            'audit_log' => (bool) $validated['audit_log'],
            'advanced_analytics' => (bool) $validated['advanced_analytics'],
            'sso' => (bool) $validated['sso'],
            'custom_branding' => (bool) $validated['custom_branding'],
            'priority_support' => (bool) $validated['priority_support'],
            'status' => $validated['status'],
        ];

        $plan->update($updateData);

        $admin = auth()->user();
        $adminName = $admin?->name ?? 'Admin';
        $summary = "Admin {$adminName} updated configuration for plan {$plan->name}.";

        $this->auditLogger->log(
            action: 'plan.updated',
            targetUser: null,
            oldValue: $oldValues,
            newValue: $updateData,
            metadata: [
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'summary' => $summary,
            ]
        );

        return back()->with('success', "Konfigurasi paket {$plan->name} berhasil diperbarui.");
    }
}
