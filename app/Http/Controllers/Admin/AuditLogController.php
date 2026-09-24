<?php

namespace App\Http\Controllers\Admin;

use App\Domains\SystemConfig\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $actionFilter = $request->query('action');

        $query = AuditLog::with(['admin', 'targetUser']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('admin', fn ($aq) => $aq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('targetUser', fn ($tq) => $tq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($actionFilter) {
            $query->where('action', $actionFilter);
        }

        $auditLogs = $query->latest('created_at')->paginate(20)->withQueryString();

        $auditLogs->getCollection()->transform(function (AuditLog $log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'admin' => [
                    'id' => $log->admin?->id,
                    'name' => $log->admin?->name ?? 'System',
                    'email' => $log->admin?->email ?? '-',
                ],
                'target_user' => $log->targetUser ? [
                    'id' => $log->targetUser->id,
                    'name' => $log->targetUser->name,
                    'email' => $log->targetUser->email,
                ] : null,
                'summary' => $log->metadata['summary'] ?? $log->action,
                'reason' => $log->metadata['reason'] ?? null,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->format('d M Y, H:i:s'),
                'created_at_human' => $log->created_at->diffForHumans(),
            ];
        });

        // Distinct actions for dropdown
        $actions = AuditLog::select('action')->distinct()->pluck('action');

        return Inertia::render('Admin/AuditLogs/Index', [
            'audit_logs' => $auditLogs,
            'actions' => $actions,
            'filters' => [
                'search' => $search ?? '',
                'action' => $actionFilter ?? '',
            ],
        ]);
    }
}
