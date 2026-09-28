<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles, ShieldCheck } from '@lucide/vue';
import { register } from '@/routes';
</script>

<template>
    <section class="relative py-28 sm:py-36 overflow-hidden bg-[#050608] text-white border-t border-white/10">
        <!-- Intense Radial Glow behind CTA -->
        <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[450px] w-full max-w-4xl rounded-full bg-gradient-to-r from-indigo-600/20 via-purple-600/20 to-blue-600/20 blur-[140px]"></div>

        <div class="relative mx-auto max-w-4xl px-6 text-center">
            <!-- Badge -->
            <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-[11px] font-medium tracking-wide text-neutral-300 backdrop-blur-xl">
                <Sparkles class="h-3.5 w-3.5 text-indigo-400" />
                <span>Mulai Dalam Hitungan Detik</span>
            </div>

            <!-- Massive Headline -->
            <h2 class="mt-6 text-3xl font-extrabold tracking-tight sm:text-5xl md:text-6xl font-sans leading-tight">
                Mulai Presentasikan Visi <br class="hidden sm:inline" />
                <span class="bg-gradient-to-r from-white via-neutral-200 to-neutral-500 bg-clip-text text-transparent">
                    Arsitektur Anda Hari Ini.
                </span>
            </h2>

            <p class="mt-6 text-base sm:text-lg text-neutral-300 max-w-xl mx-auto leading-relaxed">
                Tinggalkan file berat yang rumit. Berikan pengalaman spasial 3D fotorealistik yang membuat klien Anda jatuh hati pada pandangan pertama.
            </p>

            <!-- Large Pill Action Button -->
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link
                    :href="register()"
                    class="group relative inline-flex items-center gap-2.5 rounded-full bg-white px-8 py-4 text-sm font-bold text-black shadow-[0_0_50px_rgba(255,255,255,0.4)] transition-all duration-300 hover:scale-[1.03] hover:bg-neutral-100 hover:shadow-[0_0_70px_rgba(255,255,255,0.6)]"
                >
                    <span>Daftar Akun Gratis Sekarang</span>
                    <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-1" />
                </Link>
            </div>

            <!-- Guarantees Row -->
            <div class="mt-8 flex flex-wrap items-center justify-center gap-6 text-xs text-neutral-300">
                <span class="flex items-center gap-1.5">
                    <ShieldCheck class="h-4 w-4 text-emerald-400" />
                    Tanpa Kartu Kredit
                </span>
                <span class="text-neutral-500">•</span>
                <span>1 Proyek Gratis Selamanya</span>
                <span class="text-neutral-500">•</span>
                <span>Dukungan Google OAuth One-Tap</span>
            </div>
        </div>
    </section>
</template>
