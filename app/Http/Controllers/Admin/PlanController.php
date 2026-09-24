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
        $plans = Plan::withCount('subscriptions')->get();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
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
