<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $planFilter = $request->query('plan');
        $userFilter = $request->query('user_id');
        $sortOrder = $request->query('sort', 'latest');

        $query = Project::with(['user', 'clients'])
            ->withCount(['versions', 'comments']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($planFilter && in_array($planFilter, ['free', 'pro', 'enterprise'])) {
            $query->whereHas('user', function ($uq) use ($planFilter) {
                $uq->where('subscription_status', $planFilter);
            });
        }

        if ($userFilter) {
            $query->where('user_id', $userFilter);
        }

        if ($sortOrder === 'oldest') {
            $query->oldest();
        } else {
            $query->latest();
        }

        $projects = $query->paginate(15)->withQueryString();

        $projects->getCollection()->transform(function (Project $project) {
            return [
                'id' => $project->id,
                'title' => $project->title,
                'slug' => $project->slug,
                'file_size_bytes' => $project->file_size_bytes,
                'current_revision_count' => $project->current_revision_count,
                'max_revisions_allowed' => $project->max_revisions_allowed,
                'versions_count' => $project->versions_count,
                'comments_count' => $project->comments_count,
                'clients_count' => $project->clients->count(),
                'owner' => [
                    'id' => $project->user?->id,
                    'name' => $project->user?->name ?? 'Unknown',
                    'email' => $project->user?->email ?? '-',
                    'subscription_status' => $project->user?->subscription_status ?? 'free',
                ],
                'created_at' => $project->created_at?->format('d M Y, H:i'),
                'updated_at' => $project->updated_at?->format('d M Y, H:i'),
            ];
        });

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
            'filters' => [
                'search' => $search ?? '',
                'plan' => $planFilter ?? '',
                'user_id' => $userFilter ?? '',
                'sort' => $sortOrder,
            ],
            'users' => User::select(['id', 'name', 'email'])->orderBy('name')->take(100)->get(),
        ]);
    }
}
