<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Sparkles, ArrowRight, ShieldCheck, Zap, Layers, Eye, Compass, MessageSquare, Timer } from '@lucide/vue';
import { register } from '@/routes';

// ─── Pin hover ───────────────────────────────────────────────────────────────
const activePinHover = ref(true);

// ─── Countdown / count-up timer ──────────────────────────────────────────────
// Counts UP from 0 → MAX_SECONDS while page is visible.
// When page goes hidden, counts back DOWN to 0 at the same rate.
const MAX_SECONDS = 30;        // max value displayed before it loops / holds
const TICK_MS     = 50;        // how often we update (ms) — smooth enough

const elapsed     = ref(0);    // current displayed value (0 .. MAX_SECONDS)
const direction   = ref<1 | -1>(1);   // 1 = counting up, -1 = counting down
let   rafId: number | null = null;
let   lastTs: number | null = null;
let   accumulator = 0;         // fractional ms accumulator for smooth ticking

/** Step function called on every animation frame */
function tick(ts: number) {
    if (lastTs !== null) {
        accumulator += ts - lastTs;
    }
    lastTs = ts;

    // advance every TICK_MS ms
    while (accumulator >= TICK_MS) {
        accumulator -= TICK_MS;
        elapsed.value = Math.max(0, Math.min(MAX_SECONDS, elapsed.value + direction.value));
    }

    rafId = requestAnimationFrame(tick);
}

function startLoop() {
    if (rafId !== null) return;
    lastTs = null;
    accumulator = 0;
    rafId = requestAnimationFrame(tick);
}

function stopLoop() {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
}

function onVisibilityChange() {
    if (document.hidden) {
        direction.value = -1;  // start counting DOWN
    } else {
        direction.value = 1;   // start counting UP
    }
}

onMounted(() => {
    direction.value = document.hidden ? -1 : 1;
    startLoop();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onUnmounted(() => {
    stopLoop();
    document.removeEventListener('visibilitychange', onVisibilityChange);
});

/** Format seconds as MM:SS */
const formattedTime = computed(() => {
    const s = elapsed.value;
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
});

/** Percentage for the arc progress indicator */
const progressPct = computed(() => (elapsed.value / MAX_SECONDS) * 100);

// SVG circle arc helpers
const RADIUS = 18;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const strokeDash = computed(() => {
    const filled = (progressPct.value / 100) * CIRCUMFERENCE;
    return `${filled} ${CIRCUMFERENCE}`;
});

// ─── Count-up stats (IntersectionObserver, fires once) ────────────────────────
const statsRef   = ref<HTMLElement | null>(null);
const stat85     = ref(0);
const stat100    = ref(0);

let statsObserver: IntersectionObserver | null = null;

function animateCount(target: { value: number }, to: number, durationMs = 1400) {
    const start     = performance.now();
    const from      = target.value;
    function step(now: number) {
        const progress = Math.min((now - start) / durationMs, 1);
        const ease     = 1 - Math.pow(1 - progress, 4); // easeOutQuart
        target.value   = Math.round(from + (to - from) * ease);
        if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
}

onMounted(() => {
    statsObserver = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) {
                animateCount(stat85,  85,  1600);
                animateCount(stat100, 100, 1800);
                statsObserver?.disconnect();
            }
        },
        { threshold: 0.4 }
    );
    if (statsRef.value) statsObserver.observe(statsRef.value);
});

onUnmounted(() => {
    statsObserver?.disconnect();
});
</script>

<template>
    <section class="relative overflow-hidden pt-32 pb-24 sm:pt-40 sm:pb-32">
        <!-- Radial Ambient Glows -->
        <div class="pointer-events-none absolute -top-40 left-1/2 -translate-x-1/2 h-[600px] w-full max-w-6xl rounded-full bg-gradient-to-b from-indigo-600/15 via-purple-600/5 to-transparent blur-[140px]"></div>
        <div class="pointer-events-none absolute top-1/2 -left-48 h-96 w-96 rounded-full bg-blue-600/10 blur-[120px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Header Text Block -->
            <div class="mx-auto max-w-3xl text-center">
                <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-white sm:text-6xl md:text-7xl font-sans leading-[1.08]">
                    Desain Anda, <br />
                    <span class="bg-gradient-to-r from-white via-neutral-300 to-neutral-400 bg-clip-text text-transparent">
                        Dipahami Klien.
                    </span>
                </h1>

                <p class="mt-6 text-base sm:text-lg leading-relaxed text-neutral-300 max-w-2xl mx-auto">
                    Tak perlu lagi meminta klien menginstal software CAD atau BIM yang berat. Presentasikan model 3D bangunan secara fotorealistik langsung di browser, kumpulkan masukan klien tepat di titik fasad, ruang, atau struktur yang dimaksud, dan kunci revisi desain dengan kuota yang transparan.
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        :href="register()"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 rounded-full bg-white px-7 py-3 text-sm font-semibold text-black shadow-[0_0_35px_rgba(255,255,255,0.3)] transition-all duration-300 hover:scale-[1.02] hover:bg-neutral-100 hover:shadow-[0_0_45px_rgba(255,255,255,0.45)]"
                    >
                        <span>Presentasikan Desain Gratis</span>
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

                <div class="mt-5 flex items-center justify-center gap-6 text-xs text-neutral-300">
                    <div class="flex items-center gap-1.5">
                        <ShieldCheck class="h-3.5 w-3.5 text-emerald-400" />
                        <span>Karya Terlindungi: Akses Klien via Undangan Email</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Zap class="h-3.5 w-3.5 text-indigo-400" />
                        <span>Revisi Desain & Proyek Lebih Tertata</span>
                    </div>
                </div>
            </div>

            <!-- ─── Hero Showcase Frame ──────────────────────────────────────── -->
            <div id="showcase" class="mt-14 sm:mt-20">
                <div class="relative mx-auto max-w-5xl rounded-[32px] border border-white/15 bg-gradient-to-b from-white/10 to-transparent p-2 sm:p-3 shadow-[0_0_90px_rgba(99,102,241,0.2)] backdrop-blur-3xl">
                    <div class="relative overflow-hidden rounded-[26px] bg-[#0c0d12] border border-white/10">
                        <!-- Window Header -->
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
                                <!-- ── Live Session Timer ──────────────────── -->
                                <div
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-mono transition-all duration-500"
                                    :class="direction === 1
                                        ? 'border-indigo-500/30 bg-indigo-500/10 text-indigo-300'
                                        : 'border-amber-500/30 bg-amber-500/10 text-amber-300'"
                                    :title="direction === 1 ? 'Tab aktif — sesi berjalan' : 'Tab tidak aktif — sesi dijeda'"
                                >
                                    <!-- SVG Arc Progress Ring -->
                                    <svg class="shrink-0" width="20" height="20" viewBox="0 0 44 44">
                                        <!-- Track -->
                                        <circle
                                            cx="22" cy="22" :r="RADIUS"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-opacity="0.15"
                                            stroke-width="4"
                                        />
                                        <!-- Filled arc -->
                                        <circle
                                            cx="22" cy="22" :r="RADIUS"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="4"
                                            stroke-linecap="round"
                                            :stroke-dasharray="strokeDash"
                                            stroke-dashoffset="0"
                                            transform="rotate(-90 22 22)"
                                            class="transition-[stroke-dasharray] duration-75"
                                        />
                                    </svg>

                                    <span class="tabular-nums tracking-wider">{{ formattedTime }}</span>

                                    <span
                                        class="rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-widest"
                                        :class="direction === 1
                                            ? 'bg-indigo-500/20 text-indigo-300'
                                            : 'bg-amber-500/20 text-amber-300'"
                                    >
                                        {{ direction === 1 ? 'LIVE' : 'JEDA' }}
                                    </span>
                                </div>

                                <span class="rounded bg-white/5 border border-white/10 px-2 py-0.5 text-[11px] font-mono text-neutral-300">
                                    60 FPS • WebGL
                                </span>
                            </div>
                        </div>

                        <!-- Showcase Stage -->
                        <div class="relative aspect-[16/9] w-full overflow-hidden bg-black group">
                            <img
                                src="/images/aether_villa_hero.jpg"
                                alt="Hyper-realistic architectural luxury minimalist villa exterior cantilevered over water at dusk"
                                class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.02]"
                            />

                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30"></div>

                            <!-- Spatial Pin #01 -->
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

                                <div
                                    v-if="activePinHover"
                                    class="absolute left-7 -top-4 w-64 sm:w-72 rounded-2xl border border-white/20 bg-black/85 p-3.5 shadow-2xl backdrop-blur-2xl animate-fadeIn"
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
                                        <span class="text-emerald-400">{{ elapsed > 0 ? `${elapsed}d lalu` : 'baru saja' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Badges Bar -->
                            <div class="absolute bottom-4 left-4 right-4 z-20 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 rounded-full border border-white/15 bg-black/70 px-4 py-2 text-xs backdrop-blur-xl shadow-lg">
                                    <div class="flex h-2.5 w-2.5 rounded-full bg-indigo-500"></div>
                                    <span class="font-medium text-white">Kuota Revisi Desain:</span>
                                    <span class="rounded-full bg-indigo-500/20 px-2 py-0.5 text-[11px] font-semibold text-indigo-300 border border-indigo-500/30">
                                        2 dari 3 Revisi Terpakai
                                    </span>
                                </div>

                                <div class="hidden sm:flex items-center gap-2 rounded-full border border-white/15 bg-black/70 px-3 py-1.5 text-xs text-neutral-300 backdrop-blur-xl">
                                    <span class="flex items-center gap-1 text-[11px]">
                                        <Eye class="h-3.5 w-3.5 text-indigo-400" />
                                        Tampak Fasad (Dusk)
                                    </span>
                                    <span class="text-neutral-600">|</span>
                                    <span class="flex items-center gap-1 text-[11px]">
                                        <Layers class="h-3.5 w-3.5 text-emerald-400" />
                                        Model 84% Lebih Ringan
                                    </span>
                                    <span class="text-neutral-600">|</span>
                                    <span class="flex items-center gap-1 text-[11px]">
                                        <MessageSquare class="h-3.5 w-3.5 text-blue-400" />
                                        Diskusi Klien Terhubung
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Stats Row ──────────────────────────────────────────── -->
                <div
                    ref="statsRef"
                    class="mt-12 grid grid-cols-2 gap-6 sm:grid-cols-4 border-y border-white/10 py-8"
                >
                    <!-- Stat 1: Live Countdown Timer -->
                    <div class="text-center group">
                        <div class="inline-flex items-end gap-1.5 justify-center">
                            <!-- Arc ring (small) -->
                            <svg
                                class="mb-1 transition-all duration-300"
                                :class="direction === 1 ? 'text-indigo-400' : 'text-amber-400'"
                                width="28" height="28" viewBox="0 0 44 44"
                            >
                                <circle cx="22" cy="22" :r="RADIUS" fill="none" stroke="currentColor" stroke-opacity="0.15" stroke-width="5" />
                                <circle
                                    cx="22" cy="22" :r="RADIUS" fill="none" stroke="currentColor"
                                    stroke-width="5" stroke-linecap="round"
                                    :stroke-dasharray="strokeDash"
                                    transform="rotate(-90 22 22)"
                                    class="transition-[stroke-dasharray] duration-75"
                                />
                            </svg>
                            <span
                                class="text-3xl font-extrabold tracking-tight tabular-nums transition-colors duration-500"
                                :class="direction === 1 ? 'text-indigo-400' : 'text-amber-400'"
                            >
                                {{ formattedTime }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-neutral-400">
                            Sesi Viewer Aktif
                            <span
                                class="ml-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase"
                                :class="direction === 1
                                    ? 'bg-indigo-500/15 text-indigo-300'
                                    : 'bg-amber-500/15 text-amber-300'"
                            >
                                {{ direction === 1 ? '▲' : '▼' }}
                            </span>
                        </div>
                    </div>

                    <!-- Stat 2 -->
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-indigo-400 tracking-tight tabular-nums">
                            {{ stat85 }}<span class="text-xl">%</span>
                        </div>
                        <div class="mt-1 text-xs text-neutral-400">Ukuran Model 3D Lebih Ringan (Draco)</div>
                    </div>

                    <!-- Stat 3 -->
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-emerald-400 tracking-tight tabular-nums">
                            {{ stat100 }}<span class="text-xl">%</span>
                        </div>
                        <div class="mt-1 text-xs text-neutral-400">Desain Aman via Undangan Email</div>
                    </div>

                    <!-- Stat 4 -->
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-white tracking-tight">3×</div>
                        <div class="mt-1 text-xs text-neutral-400">Batas Revisi per Tahap Desain</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
