<?php

namespace Database\Seeders;

use App\Domains\SystemConfig\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'free_tier_max_projects',
                'value' => '2',
                'type' => 'integer',
                'description' => 'Jumlah maksimal project yang bisa dibuat oleh user Free Tier.',
            ],
            [
                'key' => 'free_tier_max_file_size_mb',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Batas ukuran maksimal per file 3D untuk user Free Tier (dalam MB).',
            ],
            [
                'key' => 'free_tier_default_client_revisions',
                'value' => '3',
                'type' => 'integer',
                'description' => 'Batas default kuota revisi client per project untuk paket Free Tier.',
            ],
            [
                'key' => 'free_tier_trial_days',
                'value' => '14',
                'type' => 'integer',
                'description' => 'Durasi masa uji coba gratis sebelum diminta upgrade.',
            ],
            [
                'key' => 'pro_tier_max_projects',
                'value' => '100',
                'type' => 'integer',
                'description' => 'Jumlah maksimal project yang bisa dibuat oleh user Pro Subscriber.',
            ],
            [
                'key' => 'pro_tier_max_file_size_mb',
                'value' => '100',
                'type' => 'integer',
                'description' => 'Batas ukuran maksimal per file 3D untuk user Pro Subscriber (dalam MB).',
            ],
            [
                'key' => 'free_tier_max_invited_clients',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Batas jumlah email Klien yang boleh diundang per project untuk Free Tier.',
            ],
            [
                'key' => 'pro_tier_max_invited_clients',
                'value' => 'unlimited',
                'type' => 'string',
                'description' => 'Batas jumlah email Klien yang boleh diundang per project untuk Pro Tier.',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
