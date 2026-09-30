<?php

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private channel untuk realtime chat per project.
 * Hanya Arsitek pemilik project DAN Klien berstatus 'accepted' yang bisa join.
 *
 * Channel name: project.{projectId}.chat
 */
Broadcast::channel('project.{projectId}.chat', function ($user, string $projectId) {
    $project = Project::find($projectId);

    if (! $project) {
        return false;
    }

    // Arsitek pemilik selalu boleh
    if ($project->user_id === $user->id) {
        return [
            'id'   => $user->id,
            'name' => $user->name,
            'role' => 'architect',
        ];
    }

    // Klien yang sudah accepted
    $isAccepted = ProjectClient::where('project_id', $projectId)
        ->where('user_id', $user->id)
        ->where('status', ProjectClient::STATUS_ACCEPTED)
        ->exists();

    if ($isAccepted) {
        return [
            'id'   => $user->id,
            'name' => $user->name,
            'role' => 'client',
        ];
    }

    return false;
});
