<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { 
    UploadCloud, 
    ShieldCheck, 
    MapPin, 
    Lock, 
    CheckCircle2, 
    ChevronDown, 
    FileCode, 
    Sparkles,
    Sliders,
    Layers,
    ArrowRight
} from '@lucide/vue';

const containerRef = ref<HTMLElement | null>(null);
const scrollProgress = ref(0);
const activeStep = ref(0);

const steps = [
    {
        id: 'compression',
        badge: 'Langkah 01',
        title: 'Lebih Ringan',
        subtitle: 'Upload Model dari CAD/BIM, Optimasi Sekali Klik',
        description: 'Ekspor model dari Revit, ArchiCAD, SketchUp, atau Rhino lalu unggah. Pipeline server otomatis mengompresi geometri 3D hingga 85% lebih ringan tanpa merusak detail material PBR, sehingga model bangunan langsung terbuka cepat di browser klien.',
        icon: UploadCloud,
        accent: 'from-blue-500 to-indigo-500',
        borderColor: 'border-blue-500/30',
        glowColor: 'rgba(59, 130, 246, 0.15)',
        stats: [
            { label: 'Reduksi Ukuran', val: '84.6%' },
            { label: 'Waktu Muat Model', val: '~1.2 dtk' },
            { label: 'Target Performa', val: '60 FPS' }
        ]
    },
    {
        id: 'security',
        badge: 'Langkah 02',
        title: 'Akses Klien via Email',
        subtitle: 'Tanpa Link Bocor & Identitas Terverifikasi',
        description: 'Lindungi hak cipta dan kerahasiaan desain Anda dari link publik yang tersebar tanpa izin. Model hanya bisa dibuka oleh email klien, investor, atau konsultan yang Anda undang, dengan autentikasi Google OAuth atau email terverifikasi.',
        icon: ShieldCheck,
        accent: 'from-emerald-500 to-teal-500',
        borderColor: 'border-emerald-500/30',
        glowColor: 'rgba(16, 185, 129, 0.15)',
        stats: [
            { label: 'Model Akses', val: 'Undangan Email' },
            { label: 'Proteksi URL', val: 'Tokenized' },
            { label: 'Identitas', val: 'OAuth Verified' }
        ]
    },
    {
        id: 'spatial',
        badge: 'Langkah 03',
        title: 'Titik Desain (X, Y, Z)',
        subtitle: 'Anotasi Presisi di Fasad, Ruang, dan Struktur',
        description: 'Klien cukup mengklik bagian mana pun pada model bangunan, misalnya fasad, dek, atau plafon. Raycaster Three.js menghitung koordinat spasial seketika dan menghubungkan komentar revisi dengan garis penunjuk SVG, sehingga tidak ada lagi masukan ambigu seperti "yang di sebelah sana".',
        icon: MapPin,
        accent: 'from-indigo-500 to-purple-500',
        borderColor: 'border-indigo-500/30',
        glowColor: 'rgba(99, 102, 241, 0.18)',
        stats: [
            { label: 'Presisi Posisi', val: 'Vector 3D' },
            { label: 'Garis Penunjuk', val: 'SVG Real-time' },
            { label: 'Layar Sentuh', val: 'Mendukung iPad' }
        ]
    },
    {
        id: 'gatekeeper',
        badge: 'Langkah 04',
        title: 'Batas Revisi',
        subtitle: 'Kunci Kuota Revisi & Kesepakatan Akhir',
        description: 'Lindungi tim Anda dari revisi tanpa akhir. Kuota revisi 3x per tahap desain terpantau transparan; setelah kuota habis, form revisi terkunci otomatis dan klien dapat menyetujui desain akhir via chat terintegrasi atau mengajukan addendum.',
        icon: Sliders,
        accent: 'from-amber-500 to-orange-500',
        borderColor: 'border-amber-500/30',
        glowColor: 'rgba(245, 158, 11, 0.15)',
        stats: [
            { label: 'Batas Revisi', val: '3x Terkunci' },
            { label: 'Status Proteksi', val: 'Form Terkunci' },
            { label: 'Output Akhir', val: 'Persetujuan Final' }
        ]
    }
];

const updateScroll = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    const totalDist = rect.height - windowHeight;

    if (totalDist <= 0) return;

    // Hitung progress scroll di dalam container
    const scrolled = -rect.top;
    const progress = Math.min(Math.max(scrolled / totalDist, 0), 1);
    scrollProgress.value = progress;

    // Tentukan langkah aktif (0, 1, 2, 3)
    const stepCount = steps.length;
    let step = Math.floor(progress * stepCount);
    if (step >= stepCount) step = stepCount - 1;
    activeStep.value = step;
};

const jumpToStep = (index: number) => {
    if (!containerRef.value) return;
    const containerTop = containerRef.value.getBoundingClientRect().top + window.scrollY;
    const totalDist = containerRef.value.offsetHeight - window.innerHeight;
    const targetY = containerTop + (index / (steps.length - 1)) * totalDist;
    window.scrollTo({ top: targetY, behavior: 'smooth' });
};

onMounted(() => {
    window.addEventListener('scroll', updateScroll, { passive: true });
    window.addEventListener('resize', updateScroll, { passive: true });
    updateScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', updateScroll);
    window.removeEventListener('resize', updateScroll);
});
</script>

<template>
    <!-- Outer Scroll Track Container (360vh memberikan durasi scroll yang nyaman & responsif) -->
    <section
        id="experience"
        ref="containerRef"
        class="relative h-[360vh] bg-[#07080c] border-t border-white/10"
    >
        <!-- Inner Container 100vh Sticky Viewport: Diam di tengah layar saat di-scroll -->
        <div class="sticky top-0 h-screen w-full flex flex-col justify-between py-6 sm:py-8 lg:py-10 px-4 sm:px-8 max-w-7xl mx-auto z-10 overflow-hidden">
            
            <!-- Dynamic Ambient Backlight -->
            <div
                class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-full max-w-5xl rounded-full blur-[180px] transition-all duration-700 opacity-60"
                :style="{ backgroundColor: steps[activeStep].glowColor }"
            ></div>

            <!-- Top Header & Scrollytelling Step Scrubber -->
            <div class="relative z-20 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-indigo-300">
                            <Sparkles class="h-3 w-3 text-indigo-400" />
                            <span>Alur Kerja Spasial Scrollytelling</span>
                        </div>
                        <span class="rounded bg-white/10 px-2 py-0.5 text-xs font-mono text-neutral-300">
                            Langkah {{ activeStep + 1 }} dari {{ steps.length }}
                        </span>
                    </div>

                    <!-- Scroll Telemetry Readout -->
                    <div class="flex items-center gap-3 text-xs text-neutral-400 font-mono">
                        <span class="h-2 w-2 rounded-full bg-indigo-400 animate-pulse"></span>
                        <span class="text-neutral-200">Scroll Duration: {{ Math.round(scrollProgress * 100) }}%</span>
                    </div>
                </div>

                <!-- 4 Step Interactive Progress Bar -->
                <div class="grid grid-cols-4 gap-2 sm:gap-3">
                    <button
                        v-for="(st, idx) in steps"
                        :key="st.id"
                        type="button"
                        @click="jumpToStep(idx)"
                        class="group text-left transition-all duration-200"
                    >
                        <div class="h-1.5 sm:h-2 w-full rounded-full bg-white/10 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-200"
                                :class="{
                                    'bg-gradient-to-r from-indigo-500 to-white': idx === activeStep,
                                    'bg-white/80': idx < activeStep,
                                    'bg-transparent': idx > activeStep
                                }"
                                :style="{
                                    width: idx === activeStep ? '100%' : (idx < activeStep ? '100%' : '0%')
                                }"
                            ></div>
                        </div>
                        <div class="mt-2 hidden sm:flex items-center gap-1.5">
                            <span
                                class="text-[11px] font-mono tracking-wider transition-colors duration-200"
                                :class="activeStep === idx ? 'text-white font-bold' : 'text-neutral-500 group-hover:text-neutral-300'"
                            >
                                {{ st.badge }}
                            </span>
                            <span class="text-neutral-600">•</span>
                            <span
                                class="text-[11px] font-medium truncate transition-colors duration-200"
                                :class="activeStep === idx ? 'text-neutral-200 font-semibold' : 'text-neutral-500 group-hover:text-neutral-300'"
                            >
                                {{ st.title }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Central Content Display: Absolute Cross-Fade Transitions -->
            <div class="relative z-20 my-auto grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 items-center">
                
                <!-- Left Column: Step Text Explanations (5 cols) -->
                <div class="lg:col-span-5 relative min-h-[300px] sm:min-h-[340px] flex items-center">
                    <div
                        v-for="(st, idx) in steps"
                        :key="st.id"
                        class="absolute inset-0 flex flex-col justify-center space-y-4 sm:space-y-5 transition-all duration-500 ease-out"
                        :class="activeStep === idx 
                            ? 'opacity-100 translate-y-0 pointer-events-auto' 
                            : 'opacity-0 translate-y-6 pointer-events-none'"
                    >
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-neutral-300 w-fit">
                            <component :is="st.icon" class="h-3.5 w-3.5 text-indigo-400" />
                            <span>{{ st.subtitle }}</span>
                        </div>

                        <h3 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight font-sans">
                            {{ st.title }}
                        </h3>

                        <p class="text-xs sm:text-sm lg:text-base leading-relaxed text-neutral-300 max-w-lg">
                            {{ st.description }}
                        </p>

                        <!-- Telemetry Stats Grid -->
                        <div class="grid grid-cols-3 gap-2 sm:gap-3 pt-1">
                            <div
                                v-for="stat in st.stats"
                                :key="stat.label"
                                class="rounded-2xl border border-white/10 bg-white/[0.04] p-2.5 sm:p-3 backdrop-blur-xl text-center shadow-lg"
                            >
                                <div class="text-xs sm:text-sm font-bold text-white font-mono">{{ stat.val }}</div>
                                <div class="mt-1 text-[9px] sm:text-[10px] text-neutral-400 leading-tight">{{ stat.label }}</div>
                            </div>
                        </div>

                        <!-- Scroll Hint -->
                        <div class="pt-2 flex items-center gap-2 text-xs text-neutral-400 font-mono">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full border border-white/15 bg-white/5 animate-bounce">
                                <ChevronDown class="h-3 w-3 text-indigo-400" />
                            </div>
                            <span>Scroll mouse / layar untuk langkah selanjutnya</span>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Visual Stage Simulations (7 cols) -->
                <div class="lg:col-span-7 relative min-h-[340px] sm:min-h-[400px]">
                    <div
                        v-for="(st, idx) in steps"
                        :key="st.id"
                        class="absolute inset-0 rounded-3xl border border-white/15 bg-gradient-to-br from-[#0f1118] via-[#090b10] to-black p-5 sm:p-7 shadow-2xl backdrop-blur-2xl transition-all duration-500 ease-out flex flex-col justify-between"
                        :class="activeStep === idx 
                            ? 'opacity-100 scale-100 pointer-events-auto' 
                            : 'opacity-0 scale-95 pointer-events-none'"
                    >
                        <!-- Step 0: Draco Compression Visual Simulation -->
                        <template v-if="idx === 0">
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-500/20 border border-blue-500/30">
                                        <FileCode class="h-5 w-5 text-blue-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-white font-mono">residence_cantilever.glb</div>
                                        <div class="text-xs text-neutral-400">Draco Geometry Compression Pipeline</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-500/20 border border-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-400 font-mono">
                                    84.6% Disimpan
                                </span>
                            </div>

                            <div class="space-y-4 my-auto">
                                <div>
                                    <div class="flex justify-between text-xs text-neutral-400 mb-1.5 font-mono">
                                        <span>Ukuran File Asli Mentah</span>
                                        <span class="text-neutral-300">89.4 MB</span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-white/10 overflow-hidden">
                                        <div class="h-full bg-neutral-500 rounded-full w-full"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs text-emerald-400 mb-1.5 font-mono">
                                        <span>Hasil Kompresi Draco (Siap Browser)</span>
                                        <span class="font-bold">14.3 MB (Loading ~1.2 dtk)</span>
                                    </div>
                                    <div class="h-2.5 w-full rounded-full bg-white/10 overflow-hidden p-0.5">
                                        <div class="h-full bg-gradient-to-r from-emerald-500 to-indigo-500 rounded-full w-[16%]"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-xs text-neutral-300 space-y-1 font-mono">
                                    <div class="text-indigo-400 font-semibold flex items-center gap-1.5">
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        <span>Mesh Quantization</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-400">Vertex buffer dipadatkan tanpa degradasi garis fasad arsitektur.</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-xs text-neutral-300 space-y-1 font-mono">
                                    <div class="text-emerald-400 font-semibold flex items-center gap-1.5">
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        <span>Material PBR Intact</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-400">Tekstur 4K roughness, metalness, dan kaca dipertahankan 100%.</p>
                                </div>
                            </div>
                        </template>

                        <!-- Step 1: Client Email Access Simulation -->
                        <template v-else-if="idx === 1">
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/20 border border-emerald-500/30">
                                        <ShieldCheck class="h-5 w-5 text-emerald-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-white">Daftar Undangan Klien Proyek</div>
                                        <div class="text-xs text-neutral-400">ProjectClientAccess Guard</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-indigo-500/20 border border-indigo-500/30 px-3 py-1 text-xs font-semibold text-indigo-300 font-mono">
                                    Zero Link Leak
                                </span>
                            </div>

                            <div class="space-y-3 my-auto">
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/40 p-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white font-mono">BP</div>
                                        <div>
                                            <div class="text-xs font-semibold text-white">budi.prasetyo@client.id</div>
                                            <div class="text-[10px] text-neutral-400">Role: Klien Terdaftar • Google OAuth</div>
                                        </div>
                                    </div>
                                    <span class="rounded bg-emerald-500/20 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-400">
                                        Terverifikasi
                                    </span>
                                </div>

                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/40 p-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-xs font-bold text-white font-mono">NW</div>
                                        <div>
                                            <div class="text-xs font-semibold text-white">nadia.wijaya@investor.co.id</div>
                                            <div class="text-[10px] text-neutral-400">Role: Co-Reviewer • Email Terproteksi</div>
                                        </div>
                                    </div>
                                    <span class="rounded bg-amber-500/20 px-2.5 py-0.5 text-[10px] font-semibold text-amber-400">
                                        Undangan Aktif
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-950/20 p-3 text-xs text-neutral-300 flex items-center gap-3 font-mono">
                                <Lock class="h-4 w-4 text-emerald-400 shrink-0" />
                                <span>Pencocokan email ketat: Akses ditolak otomatis (HTTP 403) jika login dengan email tidak terdaftar.</span>
                            </div>
                        </template>

                        <!-- Step 2: Spatial Pin Annotation Simulation -->
                        <template v-else-if="idx === 2">
                            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-blue-500 animate-ping"></span>
                                    <span class="text-xs font-semibold text-white font-mono">Raycaster Hit: Fasad Kantilever Depan</span>
                                </div>
                                <span class="text-[11px] font-mono text-neutral-400">X: -4.82m &nbsp; Y: +12.40m &nbsp; Z: +8.15m</span>
                            </div>

                            <div class="relative h-48 sm:h-56 w-full rounded-2xl overflow-hidden border border-white/10 bg-neutral-900 my-auto group">
                                <img
                                    src="/images/villa_cantilever_deck.jpg"
                                    alt="3D Spatial Pin Raycast"
                                    class="h-full w-full object-cover brightness-90 group-hover:scale-105 transition-transform duration-700"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30"></div>

                                <!-- Raycasting Pin Target -->
                                <div class="absolute top-[48%] left-[42%] -translate-x-1/2 -translate-y-1/2 z-20">
                                    <div class="relative flex items-center justify-center">
                                        <div class="absolute h-10 w-10 rounded-full border border-blue-400/80 animate-ping"></div>
                                        <div class="h-6 w-6 rounded-full bg-blue-600/90 text-white font-mono text-[10px] font-bold flex items-center justify-center shadow-lg border border-white">
                                            1
                                        </div>
                                    </div>
                                </div>

                                <!-- SVG Leader Line -->
                                <svg class="absolute inset-0 pointer-events-none z-10 h-full w-full">
                                    <line x1="42%" y1="48%" x2="62%" y2="28%" stroke="#60a5fa" stroke-width="1.5" stroke-dasharray="3 3" />
                                    <circle cx="62%" cy="28%" r="3" fill="#60a5fa" />
                                </svg>

                                <!-- Floating Popover Bubble -->
                                <div class="absolute top-[16%] left-[58%] z-20 w-52 rounded-xl border border-white/20 bg-black/85 p-3 shadow-2xl backdrop-blur-xl">
                                    <div class="flex items-center justify-between text-[10px] text-neutral-400 font-mono">
                                        <span class="text-blue-400 font-semibold">Revisi #01</span>
                                        <span>Budi (Klien)</span>
                                    </div>
                                    <p class="mt-1 text-xs text-white leading-snug">
                                        "Tolong tambahkan recessed LED strip di sepanjang soffit beton kantilever ini."
                                    </p>
                                </div>
                            </div>

                            <p class="text-[11px] text-neutral-400 font-mono text-center">
                                *Koordinat 3D dihitung otomatis dari Three.js Raycaster terhadap normal mesh bangunan.
                            </p>
                        </template>

                        <!-- Step 3: Revision Gatekeeper Simulation -->
                        <template v-else>
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-500/20 border border-amber-500/30">
                                        <Sliders class="h-5 w-5 text-amber-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-white">Revision Gatekeeper Protocol</div>
                                        <div class="text-xs text-neutral-400">Proteksi Kelelahan Revisi Arsitek</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-amber-500/20 border border-amber-500/30 px-3 py-1 text-xs font-semibold text-amber-400 font-mono">
                                    Batas Kuota: 3 / 3
                                </span>
                            </div>

                            <div class="rounded-2xl border border-amber-500/30 bg-amber-950/20 p-4 space-y-3 my-auto">
                                <div class="flex justify-between items-center text-xs font-mono">
                                    <span class="text-neutral-300">Penggunaan Kuota Revisi</span>
                                    <span class="font-bold text-amber-400">3 dari 3 Telah Digunakan</span>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="h-2 rounded-full bg-amber-500"></div>
                                    <div class="h-2 rounded-full bg-amber-500"></div>
                                    <div class="h-2 rounded-full bg-amber-500"></div>
                                </div>
                                <div class="rounded-xl bg-black/60 p-3 text-xs text-amber-200/90 border border-amber-500/20 flex items-start gap-2.5">
                                    <Lock class="h-4 w-4 text-amber-400 shrink-0 mt-0.5" />
                                    <span>Form pin revisi dinonaktifkan otomatis. Klien diarahkan untuk menyetujui desain atau mengajukan addendum.</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-950/30 p-3 text-xs text-emerald-300 font-mono">
                                <span class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 text-emerald-400" />
                                    <span>Status Desain: Disetujui & Siap Eksekusi</span>
                                </span>
                                <span class="text-white font-semibold">Finalized</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Bottom Indicator Bar: Current Step & Jump Controls -->
            <div class="relative z-20 flex items-center justify-between border-t border-white/10 pt-3 text-xs text-neutral-400">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-neutral-300 uppercase tracking-wider">{{ steps[activeStep].badge }}</span>
                    <span>—</span>
                    <span class="text-white font-medium">{{ steps[activeStep].title }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-for="(_, dotIdx) in steps"
                        :key="dotIdx"
                        type="button"
                        @click="jumpToStep(dotIdx)"
                        class="h-2 rounded-full transition-all duration-300"
                        :class="activeStep === dotIdx ? 'w-8 bg-indigo-500' : 'w-2 bg-white/20 hover:bg-white/50'"
                        :aria-label="`Lompat ke langkah ${dotIdx + 1}`"
                    ></button>
                </div>
            </div>
        </div>
    </section>
</template>
