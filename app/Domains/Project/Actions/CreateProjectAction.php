<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Support\Str;

class CreateProjectAction
{
    public function __construct(
        protected SystemSettingRepository $settings,
        protected SubscriptionLimitService $limitService
    ) {}

    public function execute(User $user, array $data): Project
    {
        $check = $this->limitService->canCreateProject($user);

        if (! $check['allowed']) {
            abort(403, $check['reason'] ?? 'Project limit reached for your current subscription.');
        }

        $maxRevisions = $this->settings->get("quota.{$user->subscription_status}.max_revisions_per_project", 3);
        $title = $data['title'] ?? $data['name'] ?? 'Untitled Project';

        return Project::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => $data['description'] ?? null,
            'file_path' => $data['file_path'] ?? 'projects/models/'.Str::uuid().'.glb',
            'file_size_bytes' => $data['file_size_bytes'] ?? 0,
            'is_draco_compressed' => $data['is_draco_compressed'] ?? false,
            'share_token' => Str::random(32),
            'max_revisions_allowed' => (int) ($data['max_revisions_allowed'] ?? $maxRevisions),
            'current_revision_count' => 0,
        ]);
    }
}
