<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LandingLayout from '@/layouts/LandingLayout.vue';

defineOptions({ layout: LandingLayout });

const props = defineProps<{
    status: number;
}>();

const config = computed(() => {
    switch (props.status) {
        case 404:
            return {
                code: '404',
                tag: 'ERR_SPATIAL_COORDINATE_UNDEFINED [404]',
                subtitle: '/ Viewport Desynchronized',
                title: 'Koordinat Spatial CAD Tidak Ditemukan.',
                description: 'Objek 3D, tautan sesi spatial review klien, atau viewport model CAD arsitektural yang Anda tuju tidak tersedia di memori cloud, telah dicabut izin aksesnya, atau koordinat mesh telah dipindahkan ke cluster lain.',
            };
        case 403:
            return {
                code: '403',
                tag: 'ERR_ACCESS_DENIED [403]',
                subtitle: '/ Permission Revoked',
                title: 'Akses ke Resource Ini Ditolak.',
                description: 'Anda tidak memiliki izin untuk mengakses objek 3D, proyek, atau sesi review ini. Pastikan email Anda sudah diundang oleh arsitek pemilik proyek.',
            };
        case 500:
            return {
                code: '500',
                tag: 'ERR_INTERNAL_ENGINE_FAULT [500]',
                subtitle: '/ Engine Fault',
                title: 'Spatial Engine Mengalami Kesalahan.',
                description: 'Terjadi kesalahan internal pada Spatial CAD Engine. Tim teknis kami telah diberitahu secara otomatis. Silakan coba kembali dalam beberapa saat.',
            };
        default:
            return {
                code: String(props.status),
                tag: `ERR_UNKNOWN [${props.status}]`,
                subtitle: '/ Unknown Error',
                title: 'Terjadi Kesalahan Tak Terduga.',
                description: 'Terjadi kesalahan yang tidak dikenali. Silakan kembali ke dashboard atau hubungi support kami.',
            };
    }
});
</script>

<template>
    <!-- Ambient glow -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden z-0">
        <div class="absolute -top-[30%] left-1/2 -translate-x-1/2 w-[850px] h-[550px] bg-gradient-to-b from-indigo-600/10 via-blue-600/5 to-transparent blur-[120px] rounded-full"></div>
        <div class="absolute bottom-10 -right-20 w-[420px] h-[420px] bg-rose-600/10 blur-[140px] rounded-full"></div>
    </div>

    <div class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 py-16 flex flex-col justify-center min-h-[calc(100vh-160px)]">

        <!-- Main error card -->
        <div class="relative w-full rounded-2xl bg-white/[0.03] border border-white/10 backdrop-blur-2xl p-6 sm:p-10 lg:p-12 overflow-hidden mb-8 shadow-2xl">

            <!-- Wireframe grid background -->
            <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
                 style="background-image: linear-gradient(rgba(99,102,241,0.4) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.4) 1px, transparent 1px); background-size: 48px 48px;">
            </div>

            <!-- HUD header strip -->
            <div class="flex flex-wrap items-center justify-between gap-4 pb-6 mb-8 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-mono bg-rose-500/10 text-rose-400 border border-rose-500/20 tracking-wider">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                        {{ config.tag }}
                    </span>
                    <span class="hidden sm:inline-block text-white/20">•</span>
                    <span class="text-[11px] font-mono text-neutral-500">VIEWPORT ENGINE: V4.19-RTX</span>
                </div>
                <div class="flex items-center gap-4 text-[11px] font-mono text-neutral-500">
                    <span>X: NaN | Y: NaN | Z: 0.00</span>
                    <span class="h-3 w-px bg-white/10 hidden sm:block"></span>
                    <span class="text-indigo-400">FOI: ORPHAN_PTR</span>
                </div>
            </div>

            <!-- Main content grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">

                <!-- Left: Typography & CTAs -->
                <div class="lg:col-span-7 flex flex-col space-y-6">
                    <div class="space-y-2">
                        <div class="flex items-baseline gap-4">
                            <span class="text-[96px] sm:text-[120px] font-bold tracking-tighter text-white leading-none select-none">
                                {{ config.code }}
                            </span>
                            <span class="text-xl text-neutral-500 font-normal">
                                {{ config.subtitle }}
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl text-indigo-400 font-medium tracking-tight">
                            {{ config.title }}
                        </h1>
                    </div>

                    <p class="text-base text-neutral-400 max-w-xl leading-relaxed">
                        {{ config.description }}
                    </p>

                    <!-- CTA Buttons -->
                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-white text-black font-semibold text-sm tracking-wide hover:bg-neutral-100 hover:shadow-[0_0_20px_rgba(99,102,241,0.35)] transition-all duration-150 active:scale-[0.98]"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            <span>Kembali ke Dashboard</span>
                        </Link>

                        <Link
                            href="/"
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-white/5 border border-white/10 text-white text-sm hover:bg-white/10 hover:border-white/20 transition-all duration-150 active:scale-[0.98]"
                        >
                            <svg class="h-5 w-5 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            <span>Kembali ke Beranda</span>
                        </Link>
                    </div>

                    <!-- Tertiary support link -->
                    <div class="pt-2 flex items-center gap-2 text-sm text-neutral-500">
                        <svg class="h-4 w-4 text-neutral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Mengalami kendala sinkronisasi model?</span>
                        <Link href="/" class="text-indigo-400 hover:underline underline-offset-4 font-medium transition-colors">
                            Laporkan Masalah →
                        </Link>
                    </div>
                </div>

                <!-- Right: 3D Wireframe visual -->
                <div class="lg:col-span-5 relative">
                    <div class="relative w-full aspect-square rounded-xl bg-black/40 border border-white/8 overflow-hidden flex flex-col justify-between p-4">

                        <!-- SVG Wireframe -->
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-80">
                            <svg class="w-full h-full p-4" viewBox="0 0 400 400" fill="none" stroke="currentColor" stroke-width="1.2">
                                <!-- Ground grid lines -->
                                <line x1="50" y1="320" x2="350" y2="320" stroke="rgba(255,255,255,0.1)" stroke-dasharray="4 4"/>
                                <line x1="80" y1="280" x2="320" y2="280" stroke="rgba(255,255,255,0.08)"/>
                                <line x1="120" y1="240" x2="280" y2="240" stroke="rgba(255,255,255,0.05)"/>
                                <line x1="200" y1="80" x2="200" y2="340" stroke="rgba(99,102,241,0.25)" stroke-dasharray="2 2"/>
                                <!-- Ghost bounding box -->
                                <rect x="130" y="130" width="140" height="140" rx="4" stroke="rgba(99,102,241,0.4)" stroke-dasharray="6 6"/>
                                <rect x="150" y="110" width="140" height="140" rx="4" stroke="rgba(255,255,255,0.15)" stroke-dasharray="6 6"/>
                                <line x1="130" y1="130" x2="150" y2="110" stroke="rgba(255,255,255,0.2)"/>
                                <line x1="270" y1="130" x2="290" y2="110" stroke="rgba(255,255,255,0.2)"/>
                                <line x1="130" y1="270" x2="150" y2="250" stroke="rgba(255,255,255,0.2)"/>
                                <line x1="270" y1="270" x2="290" y2="250" stroke="rgba(255,255,255,0.2)"/>
                                <!-- Reticle crosshairs -->
                                <circle cx="200" cy="200" r="32" stroke="rgba(248,113,113,0.4)" stroke-dasharray="3 3" stroke-width="1.5"/>
                                <line x1="190" y1="200" x2="210" y2="200" stroke="#f87171" stroke-width="1.5"/>
                                <line x1="200" y1="190" x2="200" y2="210" stroke="#f87171" stroke-width="1.5"/>
                            </svg>
                        </div>

                        <!-- Top HUD -->
                        <div class="relative z-10 flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded bg-black/60 border border-white/10 text-[10px] font-mono text-neutral-400 flex items-center gap-1.5 backdrop-blur-md">
                                <svg class="h-3.5 w-3.5 text-rose-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                ORBIT_CAM: NULL_POINTER
                            </span>
                            <span class="px-2 py-0.5 rounded bg-white/5 text-[10px] font-mono text-neutral-500">
                                RAY_DEPTH: 0
                            </span>
                        </div>

                        <!-- Center callout -->
                        <div class="relative z-10 text-center my-auto px-4 py-3 rounded-lg bg-black/70 border border-white/10 backdrop-blur-xl shadow-lg max-w-[260px] mx-auto">
                            <svg class="h-7 w-7 text-indigo-400 mx-auto mb-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                            <p class="text-xs font-medium text-white">GlTF / Draco Vertex Null</p>
                            <p class="text-[10px] text-neutral-500 mt-0.5">Hash mismatch on spatial coordinate</p>
                        </div>

                        <!-- Bottom HUD -->
                        <div class="relative z-10 flex items-center justify-between text-[10px] font-mono text-neutral-500">
                            <span class="flex items-center gap-1 bg-black/40 px-2 py-1 rounded border border-white/8">
                                <span class="text-neutral-600">SCENE:</span>
                                <span class="text-neutral-400">_UNMOUNTED_</span>
                            </span>
                            <span class="flex items-center gap-1 px-2 py-1 rounded bg-rose-500/10 border border-rose-500/20 text-rose-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                                MESH: VOID
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom diagnostic panel -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl bg-white/[0.02] border border-white/8 p-4 flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 border border-indigo-500/20">
                    <svg class="h-4 w-4 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-white mb-0.5">Status Engine</p>
                    <p class="text-[11px] text-neutral-500 leading-tight">Spatial CAD Engine beroperasi normal. Hanya koordinat ini yang tidak ditemukan.</p>
                </div>
            </div>

            <div class="rounded-xl bg-white/[0.02] border border-white/8 p-4 flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 border border-amber-500/20">
                    <svg class="h-4 w-4 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-white mb-0.5">Error Code {{ status }}</p>
                    <p class="text-[11px] text-neutral-500 leading-tight">Halaman atau resource yang diminta tidak dapat ditemukan di server.</p>
                </div>
            </div>

            <div class="rounded-xl bg-white/[0.02] border border-white/8 p-4 flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                    <svg class="h-4 w-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-white mb-0.5">Sesi Aman</p>
                    <p class="text-[11px] text-neutral-500 leading-tight">Autentikasi dan data proyek Anda tetap aman dan tidak terpengaruh.</p>
                </div>
            </div>
        </div>
    </div>
</template>
