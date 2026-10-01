<?php

namespace App\Http\Controllers;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamsPageController extends Controller
{
    /**
     * GET /teams
     * Halaman Tim & Klien — semua klien yang diundang ke proyek milik arsitek.
     * Support search by email / nama proyek.
     */
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = trim($request->query('search', ''));

        // Ambil semua project milik user + klien mereka
        $projectsQuery = Project::where('user_id', $user->id)
            ->with(['invitedClients.user']);

        $projects = $projectsQuery->latest()->get();

        // Flatten klien + filter
        $clients = $projects->flatMap(function (Project $project) use ($query) {
            return $project->invitedClients
                ->filter(function (ProjectClient $client) use ($query) {
                    if ($query === '') return true;
                    $q = strtolower($query);
                    return str_contains(strtolower($client->email), $q)
                        || str_contains(strtolower($project->title), $q)
                        || str_contains(strtolower($client->user?->name ?? ''), $q);
                })
                ->map(fn (ProjectClient $client) => [
                    'id'            => $client->id,
                    'email'         => $client->email,
                    'status'        => $client->status,
                    'invited_at'    => $client->invited_at?->diffForHumans(),
                    'accepted_at'   => $client->accepted_at?->diffForHumans(),
                    'user_name'     => $client->user?->name,
                    'user_id'       => $client->user_id,
                    'project_id'    => $project->id,
                    'project_title' => $project->title,
                ]);
        })->values();

        // Ringkasan per project untuk sidebar mini-stats
        $projectSummaries = $projects->map(fn (Project $p) => [
            'id'             => $p->id,
            'title'          => $p->title,
            'total_clients'  => $p->invitedClients->count(),
            'accepted'       => $p->invitedClients->where('status', ProjectClient::STATUS_ACCEPTED)->count(),
            'pending'        => $p->invitedClients->where('status', ProjectClient::STATUS_PENDING)->count(),
        ])->values();

        return Inertia::render('Teams/Index', [
            'clients'          => $clients,
            'projectSummaries' => $projectSummaries,
            'search'           => $query,
            'stats'            => [
                'total_clients'   => $clients->count(),
                'accepted'        => $clients->where('status', ProjectClient::STATUS_ACCEPTED)->count(),
                'pending'         => $clients->where('status', ProjectClient::STATUS_PENDING)->count(),
                'total_projects'  => $projects->count(),
            ],
        ]);
    }
}
