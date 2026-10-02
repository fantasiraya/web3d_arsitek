<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom plans sudah ditambahkan — hanya jalankan data seeding
        if (!Schema::hasColumn('plans', 'display_name')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->string('display_name')->nullable()->after('name');
                $table->string('tagline')->nullable()->after('display_name');
                $table->string('badge_text')->nullable()->after('tagline');
                $table->string('cta_text')->default('Mulai Sekarang')->after('badge_text');
                $table->string('cta_url')->default('/register')->after('cta_text');
                $table->boolean('is_featured')->default(false)->after('cta_url');
                $table->string('price_monthly')->default('Rp 0')->after('is_featured');
                $table->string('price_annual')->default('Rp 0')->after('price_monthly');
                $table->string('period_label')->default('per bulan')->after('price_annual');
                $table->json('benefits')->nullable()->after('period_label');
                $table->unsignedTinyInteger('sort_order')->default(0)->after('benefits');
            });
        }

        // Seed kolom baru dengan data dari PricingSection yang sudah ada
        DB::table('plans')->where('slug', 'free')->update([
            'display_name' => 'Free Tier',
            'tagline'      => 'Ideal untuk arsitek individual yang baru mencoba platform',
            'badge_text'   => 'Coba Gratis',
            'cta_text'     => 'Mulai Sekarang',
            'cta_url'      => '/register',
            'is_featured'  => false,
            'price_monthly' => 'Rp 0',
            'price_annual'  => 'Rp 0',
            'period_label'  => 'selamanya',
            'sort_order'    => 1,
            'benefits'      => json_encode([
                'Batas maksimal 1 proyek aktif',
                'Batas kuota 3x revisi klien per proyek',
                'Undangan klien via email (terproteksi)',
                '3D WebGL Viewer & Spatial Pin Comment',
                'Maksimal ukuran file 25 MB',
                'Google OAuth One-Tap Login',
            ]),
        ]);

        DB::table('plans')->where('slug', 'pro')->update([
            'display_name' => 'Pro Architect',
            'tagline'      => 'Untuk studio arsitektur aktif & konsultan profesional',
            'badge_text'   => 'Paling Populer',
            'cta_text'     => 'Upgrade ke Pro',
            'cta_url'      => '/register?plan=pro',
            'is_featured'  => true,
            'price_monthly' => 'Rp 149.000',
            'price_annual'  => 'Rp 119.000',
            'period_label'  => 'per bulan',
            'sort_order'    => 2,
            'benefits'      => json_encode([
                'Batas kuota hingga 20 proyek aktif',
                'Kustomisasi batas revisi klien (hingga unlimited)',
                'Fitur In-App Real-time Chat Arsitek ↔ Klien',
                'Prioritas pemrosesan kompresi Draco 3D',
                'Ukuran file hingga 100 MB per proyek',
                'Penyimpanan berkecepatan tinggi',
                'Pembayaran otomatis (QRIS, VA, Kartu)',
            ]),
        ]);

        DB::table('plans')->where('slug', 'enterprise')->update([
            'display_name' => 'Enterprise Studio',
            'tagline'      => 'Untuk biro konsultan arsitektur berskala besar',
            'badge_text'   => 'Custom Team',
            'cta_text'     => 'Konsultasi Tim',
            'cta_url'      => '/register?plan=enterprise',
            'is_featured'  => false,
            'price_monthly' => 'Hubungi Kami',
            'price_annual'  => 'Hubungi Kami',
            'period_label'  => 'kebutuhan tim',
            'sort_order'    => 3,
            'benefits'      => json_encode([
                'Kuota proyek tanpa batas (Unlimited)',
                'Custom Domain & Whitelabel Branding',
                'Single Sign-On (SSO) & Audit Logs',
                'Manajemen izin peran tingkat lanjut',
                'Dukungan dedicated SLA & prioritas teknis',
            ]),
        ]);

        // Seed system_settings untuk payment gateway
        $paymentSettings = [
            ['key' => 'payment_gateway_enabled', 'value' => '0',      'type' => 'boolean', 'description' => 'Aktifkan payment gateway Midtrans. Jika nonaktif, pembayaran via transfer manual.'],
            ['key' => 'bank_name',               'value' => 'BCA',    'type' => 'string',  'description' => 'Nama bank untuk transfer manual'],
            ['key' => 'bank_account_number',     'value' => '',        'type' => 'string',  'description' => 'Nomor rekening bank untuk transfer manual'],
            ['key' => 'bank_account_holder',     'value' => '',        'type' => 'string',  'description' => 'Nama pemilik rekening bank'],
            ['key' => 'midtrans_server_key',     'value' => '',        'type' => 'string',  'description' => 'Midtrans Server Key (dari dashboard Midtrans)'],
            ['key' => 'midtrans_client_key',     'value' => '',        'type' => 'string',  'description' => 'Midtrans Client Key (dari dashboard Midtrans)'],
            ['key' => 'midtrans_is_production',  'value' => '0',      'type' => 'boolean', 'description' => 'Mode produksi Midtrans. Jika 0 = sandbox/testing.'],
        ];

        foreach ($paymentSettings as $setting) {
            $exists = DB::table('system_settings')->where('key', $setting['key'])->exists();
            if (!$exists) {
                DB::table('system_settings')->insert(array_merge($setting, [
                    'id'         => \Illuminate\Support\Str::uuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                DB::table('system_settings')->where('key', $setting['key'])->update([
                    'description' => $setting['description'],
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'display_name', 'tagline', 'badge_text', 'cta_text',
                'cta_url', 'is_featured', 'price_monthly', 'price_annual',
                'period_label', 'benefits', 'sort_order',
            ]);
        });
    }
};
