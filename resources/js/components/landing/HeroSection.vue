<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Sparkles, ArrowRight, ShieldCheck, Zap, Layers, Eye, Compass, MessageSquare } from '@lucide/vue';
import { register } from '@/routes';

const activePinHover = ref(true);
</script>

<template>
    <section class="relative overflow-hidden pt-32 pb-24 sm:pt-40 sm:pb-32">
        <!-- Radial Ambient Glows -->
        <div class="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 h-[600px] w-full max-w-6xl rounded-full bg-gradient-to-b from-indigo-600/15 via-purple-600/5 to-transparent blur-[140px]"></div>
        <div class="pointer-events-none absolute top-1/2 -left-48 h-96 w-96 rounded-full bg-blue-600/10 blur-[120px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Header Text Block -->
            <div class="mx-auto max-w-3xl text-center">
                <!-- Pill Badge -->
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/[0.04] px-4 py-1.5 text-[11px] font-medium tracking-wide text-neutral-300 shadow-inner shadow-white/5 backdrop-blur-xl transition-all duration-300 hover:border-white/30">
                    <span class="flex h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></span>
                    <span class="text-neutral-400">AETHER 3D</span>
                    <span class="text-neutral-600">•</span>
                    <span class="text-white">Generasi Baru Presentasi Arsitektur</span>
                </div>

                <!-- Monumental Headline -->
                <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-white sm:text-6xl md:text-7xl lg:text-7xl font-sans leading-[1.08]">
                    Ruang Nyata. <br />
                    <span class="bg-gradient-to-r from-white via-neutral-300 to-neutral-400 bg-clip-text text-transparent">
                        Presisi Digital.
                    </span>
                </h1>

                <!-- Subheadline -->
                <p class="mt-6 text-base sm:text-lg leading-relaxed text-neutral-300 max-w-2xl mx-auto">
                    Bebaskan klien Anda dari instalasi software berat. Presentasikan model 3D secara fotorealistik langsung di browser, kumpulkan anotasi koordinat presisi, dan kunci revisi dengan kuota transparan.
                </p>

                <!-- Action CTA Buttons -->
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        :href="register()"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 rounded-full bg-white px-7 py-3 text-sm font-semibold text-black shadow-[0_0_35px_rgba(255,255,255,0.3)] transition-all duration-300 hover:scale-[1.02] hover:bg-neutral-100 hover:shadow-[0_0_45px_rgba(255,255,255,0.45)]"
                    >
                        <span>Mulai Presentasi Gratis</span>
                        <ArrowRight class="h-4 w-4 text-black" />
                    </Link>

                    <a
                        href="#experience"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full border border-white/15 bg-white/[0.05] px-6 py-3 text-sm font-medium text-neutral-200 backdrop-blur-xl transition-all duration-200 hover:bg-white/10 hover:border-white/30"
                    >
                        <Compass class="h-4 w-4 text-indigo-400" />
                        <span>Lihat Alur Kerja</span>
                    </a>
                </div>

                <!-- Security & Access Note -->
                <div class="mt-5 flex items-center justify-center gap-6 text-xs text-neutral-300">
                    <div class="flex items-center gap-1.5">
                        <ShieldCheck class="h-3.5 w-3.5 text-emerald-400" />
                        <span>Undangan Klien via Email (Anti Bocor)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Zap class="h-3.5 w-3.5 text-indigo-400" />
                        <span>Kompresi Otomatis Draco</span>
                    </div>
                </div>
            </div>

            <!-- Monumental Hero Showcase Frame (Screen 1 & 2 Integration) -->
            <div id="showcase" class="mt-14 sm:mt-20">
                <div class="relative mx-auto max-w-5xl rounded-[32px] border border-white/15 bg-gradient-to-b from-white/10 to-transparent p-2 sm:p-3 shadow-[0_0_90px_rgba(99,102,241,0.2)] backdrop-blur-3xl">
                    <!-- Window Shell -->
                    <div class="relative overflow-hidden rounded-[26px] bg-[#0c0d12] border border-white/10">
                        <!-- macOS-Style Window Header -->
                        <div class="flex items-center justify-between border-b border-white/10 bg-black/60 px-5 py-3.5 backdrop-blur-xl">
                            <div class="flex items-center gap-2">
                                <div class="h-3 w-3 rounded-full bg-red-500/80"></div>
                                <div class="h-3 w-3 rounded-full bg-yellow-500/80"></div>
                                <div class="h-3 w-3 rounded-full bg-emerald-500/80"></div>
                                <span class="ml-3 text-xs font-mono text-neutral-300 tracking-wide">
                                    AETHER_SHOWCASE_VILLA_CANTILEVER.GLB
                                </span>
                            </div>

                            <div class="hidden sm:flex items-center gap-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-0.5 text-[11px] font-medium text-emerald-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                    Viewer 3D Aktif
                                </span>
                                <span class="rounded bg-white/5 border border-white/10 px-2 py-0.5 text-[11px] font-mono text-neutral-300">
                                    60 FPS • WebGL
                                </span>
                            </div>
                        </div>

                        <!-- Main Showcase Stage with 8K Villa Image -->
                        <div class="relative aspect-[16/9] w-full overflow-hidden bg-black group">
                            <img
                                src="/images/aether_villa_hero.jpg"
                                alt="Hyper-realistic architectural luxury minimalist villa exterior cantilevered over water at dusk"
                                class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.02]"
                            />

                            <!-- Subtle Vignette & Gradient Overlays -->
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30"></div>

                            <!-- Floating Spatial Pin Annotation #01 -->
                            <div
                                class="absolute top-[28%] left-[45%] z-20 cursor-pointer transition-all duration-300"
                                @mouseenter="activePinHover = true"
                                @click="activePinHover = !activePinHover"
                            >
                                <div class="relative flex items-center justify-center">
                                    <span class="absolute h-8 w-8 rounded-full bg-indigo-500/40 animate-ping"></span>
                                    <span class="relative flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-[10px] font-bold text-white shadow-[0_0_20px_rgba(99,102,241,0.8)] border border-white">
                                        1
                                    </span>
                                </div>

                                <!-- Dynamic Pin Popover Card -->
                                <div
                                    v-if="activePinHover"
                                    class="absolute left-7 -top-4 w-64 sm:w-72 rounded-2xl border border-white/20 bg-black/85 p-3.5 shadow-2xl backdrop-blur-2xl transition-all duration-200 animate-fadeIn"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span class="h-2 w-2 rounded-full bg-indigo-400"></span>
                                            <span class="text-[11px] font-mono text-indigo-300">X: 18.42 Y: 4.10 Z: -12.05</span>
                                        </div>
                                        <span class="rounded bg-yellow-500/20 px-1.5 py-0.5 text-[9px] font-semibold text-yellow-300">
                                            Revisi #2
                                        </span>
                                    </div>
                                    <p class="mt-2 text-xs font-medium text-neutral-200 leading-snug">
                                        "Lantai dek cantilever tolong perpanjang 50cm ke arah air dan tambahkan ambient strip light di bawah struktur."
                                    </p>
                                    <div class="mt-2.5 flex items-center justify-between border-t border-white/10 pt-2 text-[10px] text-neutral-300">
                                        <span>Budi Prasetyo (Klien)</span>
                                        <span class="text-emerald-400">12 menit lalu</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Floating Badges Bar inside Viewer -->
                            <div class="absolute bottom-4 left-4 right-4 z-20 flex flex-wrap items-center justify-between gap-3">
                                <!-- Revision Counter Glass Badge -->
                                <div class="flex items-center gap-2.5 rounded-full border border-white/15 bg-black/70 px-4 py-2 text-xs backdrop-blur-xl shadow-lg">
                                    <div class="flex h-2.5 w-2.5 rounded-full bg-indigo-500"></div>
                                    <span class="font-medium text-white">Status Revisi Klien:</span>
                                    <span class="rounded-full bg-indigo-500/20 px-2 py-0.5 text-[11px] font-semibold text-indigo-300 border border-indigo-500/30">
                                        2 dari 3 Kuota Digunakan
                                    </span>
                                </div>

                                <!-- Quick Tools Indicator -->
                                <div class="hidden sm:flex items-center gap-2 rounded-full border border-white/15 bg-black/70 px-3 py-1.5 text-xs text-neutral-300 backdrop-blur-xl">
                                    <span class="flex items-center gap-1 text-[11px]">
                                        <Eye class="h-3.5 w-3.5 text-indigo-400" />
                                        Fasad Dusk
                                    </span>
                                    <span class="text-neutral-600">|</span>
                                    <span class="flex items-center gap-1 text-[11px]">
                                        <Layers class="h-3.5 w-3.5 text-emerald-400" />
                                        Draco 84% Saved
                                    </span>
                                    <span class="text-neutral-600">|</span>
                                    <span class="flex items-center gap-1 text-[11px]">
                                        <MessageSquare class="h-3.5 w-3.5 text-blue-400" />
                                        Chat Terhubung
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Row Beneath Showcase -->
                <div class="mt-12 grid grid-cols-2 gap-6 sm:grid-cols-4 border-y border-white/10 py-8">
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-white tracking-tight">0 Detik</div>
                        <div class="mt-1 text-xs text-neutral-400">Instalasi Software Klien</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-indigo-400 tracking-tight">85%</div>
                        <div class="mt-1 text-xs text-neutral-400">Kompresi Draco Otomatis</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-emerald-400 tracking-tight">100%</div>
                        <div class="mt-1 text-xs text-neutral-400">Aman via Undangan Email</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-white tracking-tight">3x</div>
                        <div class="mt-1 text-xs text-neutral-400">Gatekeeper Batas Revisi</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
