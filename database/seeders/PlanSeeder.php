<?php

namespace Database\Seeders;

use App\Domains\Billing\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Paket gratis untuk pengguna yang ingin mencoba aplikasi. Maksimal 1 project, dapat membuat dan mengedit.',
                'project_limit' => 1,
                'can_create_project' => true,
                'can_edit_project' => true,
                'can_delete_project' => true,
                'can_export' => false,
                'max_team_members' => 1,
                'api_access' => false,
                'audit_log' => false,
                'advanced_analytics' => false,
                'sso' => false,
                'custom_branding' => false,
                'priority_support' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Paket profesional untuk arsitek individu atau tim kecil dengan kebutuhan hingga 20 project.',
                'project_limit' => 20,
                'can_create_project' => true,
                'can_edit_project' => true,
                'can_delete_project' => true,
                'can_export' => true,
                'max_team_members' => 5,
                'api_access' => false,
                'audit_log' => false,
                'advanced_analytics' => true,
                'sso' => false,
                'custom_branding' => false,
                'priority_support' => false,
                'status' => 'active',
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solusi lengkap untuk organisasi dan perusahaan arsitektur besar tanpa batasan project.',
                'project_limit' => null, // Unlimited
                'can_create_project' => true,
                'can_edit_project' => true,
                'can_delete_project' => true,
                'can_export' => true,
                'max_team_members' => 50,
                'api_access' => true,
                'audit_log' => true,
                'advanced_analytics' => true,
                'sso' => true,
                'custom_branding' => true,
                'priority_support' => true,
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
