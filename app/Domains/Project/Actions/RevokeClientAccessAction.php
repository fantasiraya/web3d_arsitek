<?php

namespace App\Domains\Project\Actions;

use App\Domains\Project\Models\ProjectClient;

class RevokeClientAccessAction
{
    public function execute(ProjectClient $projectClient): void
    {
        $projectClient->update([
            'status' => ProjectClient::STATUS_REVOKED,
        ]);

        // Alternatively, we could delete it completely
        // $projectClient->delete();
    }
}
