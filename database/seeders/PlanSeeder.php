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
                'display_name' => 'Free Tier',
                'tagline' => 'Ideal untuk arsitek individual yang baru mencoba platform',
                'badge_text' => 'Coba Gratis',
                'cta_text' => 'Mulai Sekarang',
                'cta_url' => '/register',
                'is_featured' => false,
                'price_monthly' => 'Rp 0',
                'price_annual' => 'Rp 0',
                'period_label' => 'selamanya',
                'sort_order' => 1,
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
                'benefits' => [
                    'Batas maksimal 1 proyek aktif',
                    'Batas kuota 3x revisi klien per proyek',
                    'Undangan klien via email terenkripsi',
                    '3D WebGL Viewer & Pin Komentar Spasial',
                    'Simulasi Matahari & Arah Bayangan (SunCalc)',
                    'Maksimal ukuran file 25 MB',
                    'Google OAuth One-Tap Login',
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'display_name' => 'Pro Architect',
                'tagline' => 'Untuk studio arsitektur aktif & konsultan profesional',
                'badge_text' => 'Paling Populer',
                'cta_text' => 'Upgrade ke Pro',
                'cta_url' => '/register?plan=pro',
                'is_featured' => true,
                'price_monthly' => 'Rp 149.000',
                'price_annual' => 'Rp 119.000',
                'period_label' => 'per bulan',
                'sort_order' => 2,
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
                'benefits' => [
                    'Batas kuota hingga 20 proyek aktif',
                    'Akses Penuh AI Vision Studio (Image-to-Prompt & Video AI)',
                    'Estimator RAB (Volume GLB 3D & Master SNI AHSP)',
                    'Ekspor Dokumen RAB ke Excel (.xlsx)',
                    'Kustomisasi batas revisi klien (hingga unlimited)',
                    'Fitur In-App Real-time Chat (Laravel Reverb)',
                    'Prioritas pemrosesan kompresi Draco 3D (hingga 100 MB)',
                    'Penyimpanan berkecepatan tinggi',
                    'Pembayaran otomatis (QRIS, VA, Kartu via Midtrans)',
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'display_name' => 'Enterprise Studio',
                'tagline' => 'Untuk biro konsultan arsitektur berskala besar',
                'badge_text' => 'Custom Team',
                'cta_text' => 'Konsultasi Tim',
                'cta_url' => '/register?plan=enterprise',
                'is_featured' => false,
                'price_monthly' => 'Hubungi Kami',
                'price_annual' => 'Hubungi Kami',
                'period_label' => 'kebutuhan tim',
                'sort_order' => 3,
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
                'benefits' => [
                    'Kuota proyek tanpa batas (Unlimited)',
                    'Lembar Kerja CAD 2D Interaktif (Konva.js)',
                    'Ekspor AutoCAD DXF (Layer terpisah) & SVG',
                    'Booklet Presentasi A3 Landscape (1-Klik PDF)',
                    'Shop Drawing & Cutting List Produksi',
                    'Direct AI Architectural Render (Gemini API)',
                    'Akses Prioritas AI Vision Studio & Galeri Unlimited',
                    'Custom Domain & Whitelabel Branding',
                    'Single Sign-On (SSO) & Audit Logs',
                    'Dukungan dedicated SLA & prioritas teknis',
                ],
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
