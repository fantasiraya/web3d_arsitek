<script setup lang="ts">
import { ref, computed } from 'vue';
import { Check, Sparkles, ArrowRight } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';

const isAnnual = ref(true);

// ─── Props dari DB (Welcome.vue pass dari WelcomeController) ──
interface PlanItem {
    id: string; slug: string; display_name: string; tagline: string;
    badge_text: string; cta_text: string; cta_url: string;
    is_featured: boolean; price_monthly: string; price_annual: string;
    period_label: string; benefits: string[];
}

const props = defineProps<{ plans?: PlanItem[] }>();

// Fallback hardcoded jika DB belum ada data
const fallbackPlans = [
    {
        id: 'free', slug: 'free', display_name: 'Free Tier', is_featured: false,
        badge_text: 'Coba Gratis', cta_text: 'Mulai Sekarang', cta_url: '/register',
        tagline: 'Ideal untuk arsitek individual yang baru mencoba platform',
        price_monthly: 'Rp 0', price_annual: 'Rp 0', period_label: 'selamanya',
        benefits: ['Batas maksimal 1 proyek aktif','Batas kuota 3x revisi klien per proyek','Undangan klien via email (terproteksi)','3D WebGL Viewer & Spatial Pin Comment','Maksimal ukuran file 25 MB','Google OAuth One-Tap Login'],
    },
    {
        id: 'pro', slug: 'pro', display_name: 'Pro Architect', is_featured: true,
        badge_text: 'Paling Populer', cta_text: 'Upgrade ke Pro', cta_url: '/register?plan=pro',
        tagline: 'Untuk studio arsitektur aktif & konsultan profesional',
        price_monthly: 'Rp 149.000', price_annual: 'Rp 119.000', period_label: 'per bulan',
        benefits: ['Batas kuota hingga 20 proyek aktif','Kustomisasi batas revisi klien (hingga unlimited)','Fitur In-App Real-time Chat Arsitek ↔ Klien','Prioritas pemrosesan kompresi Draco 3D','Ukuran file hingga 100 MB per proyek','Penyimpanan berkecepatan tinggi','Pembayaran otomatis (QRIS, VA, Kartu)'],
    },
    {
        id: 'enterprise', slug: 'enterprise', display_name: 'Enterprise Studio', is_featured: false,
        badge_text: 'Custom Team', cta_text: 'Konsultasi Tim', cta_url: '/register?plan=enterprise',
        tagline: 'Untuk biro konsultan arsitektur berskala besar',
        price_monthly: 'Hubungi Kami', price_annual: 'Hubungi Kami', period_label: 'kebutuhan tim',
        benefits: ['Kuota proyek tanpa batas (Unlimited)','Custom Domain & Whitelabel Branding','Single Sign-On (SSO) & Audit Logs','Manajemen izin peran tingkat lanjut','Dukungan dedicated SLA & prioritas teknis'],
    },
];

const plans = computed(() =>
    (props.plans && props.plans.length > 0) ? props.plans : fallbackPlans
);
</script>

<template>
    <section id="pricing" class="relative py-2 bg-[#050608] text-white">
        <!-- Ambient lighting -->
        <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-full max-w-4xl bg-indigo-600/10 blur-[180px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Header Title -->
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl font-sans">
                    Pilihan Paket yang Jelas. <br />
                    <span class="bg-gradient-to-r from-neutral-200 via-neutral-400 to-neutral-500 bg-clip-text text-transparent">
                        Sesuai Skala Proyek Anda.
                    </span>
                </h2>
                <p class="mt-4 text-sm sm:text-base text-neutral-400">
                    Mulai gratis tanpa komitmen. Upgrade saat biro atau proyek arsitektur Anda berkembang.
                </p>

                <!-- Billing Cycle Toggle -->
                <div class="mt-8 inline-flex items-center rounded-full border border-white/15 bg-black/60 p-1 backdrop-blur-xl">
                    <button
                        type="button"
                        @click="isAnnual = false"
                        class="rounded-full px-5 py-2 text-xs font-semibold transition-all duration-200"
                        :class="!isAnnual ? 'bg-white text-black shadow-md' : 'text-neutral-400 hover:text-white'"
                    >
                        Bulanan
                    </button>
                    <button
                        type="button"
                        @click="isAnnual = true"
                        class="flex items-center gap-1.5 rounded-full px-5 py-2 text-xs font-semibold transition-all duration-200"
                        :class="isAnnual ? 'bg-white text-black shadow-md' : 'text-neutral-400 hover:text-white'"
                    >
                        <span>Tahunan</span>
                        <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-500/30">
                            Hemat 20%
                        </span>
                    </button>
                </div>
            </div>

            <!-- Pricing Cards Grid -->
            <div class="mt-14 grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
                <div
                    v-for="plan in plans"
                    :key="plan.slug"
                    class="relative flex flex-col justify-between rounded-[32px] p-8 transition-all duration-300 backdrop-blur-2xl"
                    :class="plan.is_featured
                        ? 'border-2 border-indigo-500/80 bg-gradient-to-b from-indigo-950/40 via-black/80 to-black shadow-[0_0_60px_rgba(99,102,241,0.25)] md:-translate-y-2'
                        : 'border border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.05]'"
                >
                    <!-- Featured Pill -->
                    <div v-if="plan.is_featured" class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 px-3.5 py-1 text-[11px] font-bold uppercase tracking-wider text-white shadow-lg shadow-indigo-500/40">
                            <Sparkles class="h-3 w-3" />
                            {{ plan.badge_text }}
                        </span>
                    </div>

                    <div>
                        <!-- Plan Header -->
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold tracking-tight text-white">{{ plan.display_name }}</h3>
                            <span v-if="!plan.is_featured" class="rounded-full border border-white/10 bg-white/5 px-2.5 py-0.5 text-[10px] font-medium text-neutral-400">
                                {{ plan.badge_text }}
                            </span>
                        </div>
                        <p class="mt-2 text-xs leading-relaxed text-neutral-400">{{ plan.tagline }}</p>

                        <!-- Price Tag -->
                        <div class="mt-6 flex items-baseline gap-1.5">
                            <span class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white font-mono">
                                {{ isAnnual ? plan.price_annual : plan.price_monthly }}
                            </span>
                            <span class="text-xs text-neutral-400">/ {{ plan.period_label }}</span>
                        </div>

                        <!-- Features Divider -->
                        <div class="my-6 h-px bg-white/10"></div>

                        <!-- Feature List -->
                        <ul class="space-y-3 text-xs text-neutral-300">
                            <li v-for="feature in (plan.benefits ?? [])" :key="feature" class="flex items-start gap-2.5">
                                <Check class="h-4 w-4 shrink-0 text-indigo-400 mt-0.5" />
                                <span class="leading-normal">{{ feature }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Action Button -->
                    <div class="mt-8 pt-4">
                        <Link
                            :href="plan.cta_url"
                            class="flex w-full items-center justify-center gap-2 rounded-full py-3 text-xs font-semibold transition-all duration-300"
                            :class="plan.is_featured
                                ? 'bg-white text-black shadow-lg shadow-white/20 hover:bg-neutral-100 hover:scale-[1.02]'
                                : 'border border-white/15 bg-white/5 text-white hover:bg-white/15 hover:border-white/30'"
                        >
                            <span>{{ plan.cta_text }}</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
