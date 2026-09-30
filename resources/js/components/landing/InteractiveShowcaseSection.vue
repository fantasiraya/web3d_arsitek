<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { 
    Eye, 
    Layers, 
    Compass, 
    Plus, 
    Sliders, 
    MessageSquare, 
    Trash2, 
    CheckCircle2, 
    RotateCw, 
    ChevronDown, 
    Sparkles, 
    MapPin,
    Target,
    RotateCcw,
    Maximize2
} from '@lucide/vue';

interface DemoPin {
    id: number;
    title: string;
    comment: string;
    author: string;
    time: string;
    x: number;
    y: number;
    resolved: boolean;
}

const containerRef = ref<HTMLElement | null>(null);
const scrollProgress = ref(0);
const activeTab = ref(0);

const tabs = [
    {
        id: 'orbit',
        badge: 'Langkah 01',
        title: 'Eksplorasi Sudut Pandang Bebas 360°',
        subtitle: 'Kamera Orbit & Perspektif 360 Derajat',
        description: 'Klien dapat memutar sudut pandang bangunan 360° secara bebas melingkari fondasi air, memeriksa skala geometri dari perspektif pulau, dan meninjau hubungan ruang secara menyeluruh.',
        presetName: 'Fasad Utama',
        is360: true,
        image: '/images/villa_orbit_360.jpg',
        telemetry: 'Azimuth: 145° • Elevation: 24° • 360° Orbit Panoramic View',
        statusText: 'Mode Putar 360° Aktif'
    },
    {
        id: 'raycast',
        badge: 'Langkah 02',
        title: 'Penempatan Pin Spasial Tiga Dimensi',
        subtitle: 'Dek Cantilever & Deteksi Permukaan Presisi',
        description: 'Raycaster Three.js membaca permukaan struktur kantilever beton dan kolam infinity. Anda dapat mengklik di mana saja tanpa batas (Unlimited Pin) untuk menancapkan revisi tepat pada titik desain.',
        presetName: 'Dek Cantilever',
        is360: false,
        image: '/images/villa_cantilever_deck.jpg',
        telemetry: 'X: -4.82m • Y: +12.40m • Z: +8.15m • Hit: Dek_Cantilever_Pool',
        statusText: 'Mode Tambah Pin Unlimited Aktif'
    },
    {
        id: 'resolve',
        badge: 'Langkah 03',
        title: 'Validasi Revisi & Pencahayaan Interior',
        subtitle: 'Interior Ambien 2800K & Status Selesai',
        description: 'Inspeksi pencahayaan interior hangat 2800K melalui kaca setinggi dua lantai saat senja. Arsitek meninjau dan menyelesaikan seluruh masukan spasial dengan status validasi "Resolved".',
        presetName: 'Pencahayaan Interior',
        is360: false,
        image: '/images/villa_interior_lighting.jpg',
        telemetry: 'Kelvin: 2800K • Status: 100% Selesai • Final Verification',
        statusText: 'Semua Revisi Telah Disetujui'
    }
];

const initialPins: DemoPin[] = [
    {
        id: 1,
        title: 'Material Kaca Fasad',
        comment: 'Tolong pastikan kaca menggunakan double-pane low-e untuk isolasi termal matahari sore.',
        author: 'Budi Prasetyo (Klien)',
        time: '1 jam lalu',
        x: 48,
        y: 35,
        resolved: false
    },
    {
        id: 2,
        title: 'Struktur Cantilever',
        comment: 'Dek kolam renang cantilever tolong diperkuat profil baja titanium agar tahan gempa.',
        author: 'Arsitek Tim',
        time: '30 menit lalu',
        x: 68,
        y: 45,
        resolved: true
    }
];

const pins = ref<DemoPin[]>([...initialPins]);
const activePinId = ref<number | null>(1);
const showAnnotations = ref(true);
const currentPreset = ref('Fasad Utama');
const activeMode = ref<'rotate' | 'pin' | 'resolved'>('rotate');
const is360Active = ref(true);

const presets = [
    { name: 'Fasad Utama', angle: '0° Sudut Depan', image: '/images/aether_villa_hero.jpg' },
    { name: 'Dek Cantilever', angle: '45° Elevated', image: '/images/villa_cantilever_deck.jpg' },
    { name: 'Pencahayaan Interior', angle: 'Close-up Warm', image: '/images/villa_interior_lighting.jpg' }
];

// Computed active image representing the current tab/preset/360 state
const currentObjectImage = computed(() => {
    if (is360Active.value) {
        return '/images/villa_orbit_360.jpg';
    }
    const foundPreset = presets.find(p => p.name === currentPreset.value);
    return foundPreset ? foundPreset.image : '/images/aether_villa_hero.jpg';
});

const selectPreset = (presetName: string) => {
    currentPreset.value = presetName;
    is360Active.value = false;
    activeMode.value = 'rotate';
};

const toggle360 = () => {
    is360Active.value = !is360Active.value;
    if (is360Active.value) {
        activeMode.value = 'rotate';
    }
};

const togglePinMode = () => {
    activeMode.value = activeMode.value === 'pin' ? 'rotate' : 'pin';
    if (activeMode.value === 'pin') {
        is360Active.value = false;
        if (currentPreset.value !== 'Dek Cantilever') {
            currentPreset.value = 'Dek Cantilever';
        }
    }
};

const syncStateWithTab = (tabIndex: number) => {
    const tab = tabs[tabIndex];
    currentPreset.value = tab.presetName;
    is360Active.value = tab.is360;

    if (tabIndex === 0) {
        activeMode.value = 'rotate';
        activePinId.value = null;
    } else if (tabIndex === 1) {
        activeMode.value = 'pin';
        activePinId.value = 1;
    } else if (tabIndex === 2) {
        activeMode.value = 'resolved';
        activePinId.value = 2;
    }
};

const handleScroll = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    const totalDist = rect.height - windowHeight;

    if (totalDist <= 0) return;

    const scrolled = -rect.top;
    const progress = Math.min(Math.max(scrolled / totalDist, 0), 1);
    scrollProgress.value = progress;

    const tabCount = tabs.length;
    let tab = Math.floor(progress * tabCount);
    if (tab >= tabCount) tab = tabCount - 1;

    if (activeTab.value !== tab) {
        activeTab.value = tab;
        syncStateWithTab(tab);
    }
};

const jumpToTab = (index: number) => {
    if (!containerRef.value) return;
    const containerTop = containerRef.value.getBoundingClientRect().top + window.scrollY;
    const totalDist = containerRef.value.offsetHeight - window.innerHeight;
    const targetY = containerTop + (index / (tabs.length - 1)) * totalDist;
    window.scrollTo({ top: targetY, behavior: 'smooth' });
};

// Unlimited Pin Placement Handler
const handleCanvasClick = (e: MouseEvent) => {
    if (activeMode.value === 'pin') {
        const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
        const x = Math.round(((e.clientX - rect.left) / rect.width) * 100);
        const y = Math.round(((e.clientY - rect.top) / rect.height) * 100);

        const newId = pins.value.length + 1;
        const suggestedTopics = [
            'Penyesuaian Kaca Low-E',
            'Penguatan Struktur Baja Kantilever',
            'Pencahayaan Recessed LED Plafon',
            'Insulasi Akustik Fasad',
            'Finishing Beton Obsidian',
            'Drainase Dek Kolam Renang',
            'Sensitivitas Suhu Termal'
        ];
        const topic = suggestedTopics[(newId - 1) % suggestedTopics.length];

        pins.value.push({
            id: newId,
            title: `${topic} #${newId}`,
            comment: `Catatan revisi spasial #${newId} pada koordinat X: ${((x - 50) / 10).toFixed(2)}m, Y: ${((50 - y) / 10).toFixed(2)}m.`,
            author: 'Klien (Reviewer)',
            time: 'Baru saja',
            x,
            y,
            resolved: false
        });
        activePinId.value = newId;
    }
};

const selectPin = (id: number) => {
    activePinId.value = activePinId.value === id ? null : id;
};

const deletePin = (id: number) => {
    pins.value = pins.value.filter(p => p.id !== id);
    if (activePinId.value === id) {
        activePinId.value = pins.value[0]?.id ?? null;
    }
};

const resetPins = () => {
    pins.value = [...initialPins];
    activePinId.value = 1;
};

const resolvePin = (id: number) => {
    const pin = pins.value.find(p => p.id === id);
    if (pin) {
        pin.resolved = !pin.resolved;
    }
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', handleScroll, { passive: true });
    handleScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('resize', handleScroll);
});
</script>

<template>
    <!-- Outer Scroll Track Container (300vh memberikan durasi scroll 3 tab tersinkronisasi) -->
    <section
        id="showcase"
        ref="containerRef"
        class="relative h-[300vh] bg-[#06070a] border-t border-white/10"
    >
        <!-- Inner Container 100vh Sticky Viewport -->
        <div class="sticky top-0 h-screen w-full flex flex-col justify-between py-4 sm:py-6 lg:py-8 px-4 sm:px-8 max-w-7xl mx-auto z-10 overflow-hidden">
            
            <!-- Dynamic Background Glow -->
            <div
                class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-full max-w-5xl rounded-full blur-[180px] transition-all duration-700 opacity-50"
                :class="{
                    'bg-indigo-600/20': activeTab === 0,
                    'bg-blue-600/20': activeTab === 1,
                    'bg-amber-600/20': activeTab === 2
                }"
            ></div>

            <!-- Top Header & Synchronized 3-Tab Scroller -->
            <div class="relative z-20 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                    <div class="flex items-center gap-2">
                        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-indigo-300">
                            <Compass class="h-3.5 w-3.5 text-indigo-400" />
                            <span>Simulasi Interaktif Scrollytelling</span>
                        </div>
                        <span class="rounded bg-white/10 px-2 py-0.5 text-xs font-mono text-neutral-300">
                            Tab {{ activeTab + 1 }} dari {{ tabs.length }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-neutral-400 font-mono">
                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-neutral-200">Sinkronisasi Scroll: {{ Math.round(scrollProgress * 100) }}%</span>
                    </div>
                </div>

                <!-- 3 Interactive Synchronized Tabs -->
                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                    <button
                        v-for="(tb, idx) in tabs"
                        :key="tb.id"
                        type="button"
                        @click="jumpToTab(idx)"
                        class="group text-left transition-all duration-200"
                    >
                        <div class="h-1.5 sm:h-2 w-full rounded-full bg-white/10 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-200"
                                :class="{
                                    'bg-gradient-to-r from-indigo-500 via-blue-400 to-white': idx === activeTab,
                                    'bg-white/80': idx < activeTab,
                                    'bg-transparent': idx > activeTab
                                }"
                                :style="{
                                    width: idx === activeTab ? '100%' : (idx < activeTab ? '100%' : '0%')
                                }"
                            ></div>
                        </div>
                        <div class="mt-2 hidden sm:flex items-center gap-1.5">
                            <span
                                class="text-[11px] font-mono tracking-wider transition-colors duration-200"
                                :class="activeTab === idx ? 'text-white font-bold' : 'text-neutral-500 group-hover:text-neutral-300'"
                            >
                                {{ tb.badge }}
                            </span>
                            <span class="text-neutral-600">•</span>
                            <span
                                class="text-[11px] font-medium truncate transition-colors duration-200"
                                :class="activeTab === idx ? 'text-neutral-200 font-semibold' : 'text-neutral-500 group-hover:text-neutral-300'"
                            >
                                {{ tb.title }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Central Content Display: Interactive Simulator Frame Synchronized with Active Tab -->
            <div class="relative z-20 my-auto grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-8 items-center">
                
                <!-- Left: Tab Text Narrative (4 cols) -->
                <div class="lg:col-span-4 relative min-h-[200px] sm:min-h-[240px] flex items-center">
                    <div
                        v-for="(tb, idx) in tabs"
                        :key="tb.id"
                        class="absolute inset-0 flex flex-col justify-center space-y-3 transition-all duration-500 ease-out"
                        :class="activeTab === idx 
                            ? 'opacity-100 translate-y-0 pointer-events-auto' 
                            : 'opacity-0 translate-y-6 pointer-events-none'"
                    >
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-neutral-300 w-fit">
                            <Sparkles class="h-3.5 w-3.5 text-indigo-400" />
                            <span>{{ tb.subtitle }}</span>
                        </div>

                        <h3 class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-white leading-tight font-sans">
                            {{ tb.title }}
                        </h3>

                        <p class="text-xs sm:text-sm leading-relaxed text-neutral-300">
                            {{ tb.description }}
                        </p>

                        <!-- Live Telemetry Box -->
                        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-3 backdrop-blur-xl font-mono text-xs space-y-1">
                            <div class="flex items-center justify-between text-[10px] text-neutral-400 uppercase tracking-wider">
                                <span>Objek Render Aktif:</span>
                                <span class="text-indigo-400 font-bold">{{ is360Active ? 'Perspektif 360°' : currentPreset }}</span>
                            </div>
                            <div class="text-white font-semibold truncate">{{ tb.telemetry }}</div>
                        </div>

                        <!-- Scroll Hint -->
                        <div class="flex items-center gap-2 text-xs text-neutral-400 font-mono pt-1">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full border border-white/15 bg-white/5 animate-bounce">
                                <ChevronDown class="h-3 w-3 text-indigo-400" />
                            </div>
                            <span>Scroll untuk mengganti objek & tab berikutnya</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Interactive 3D Showcase Simulator Window (8 cols) -->
                <div class="lg:col-span-8 overflow-hidden rounded-[28px] border border-white/15 bg-black shadow-[0_0_80px_rgba(0,0,0,0.8)]">
                    
                    <!-- Toolbar Header -->
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 bg-[#0e1017] px-5 py-3">
                        <!-- Presets Switcher (Objek Berubah Sesuai Pilihan) -->
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <span class="text-xs text-neutral-300 mr-1 hidden sm:inline">Objek Fasad:</span>
                            <button
                                v-for="preset in presets"
                                :key="preset.name"
                                type="button"
                                @click="selectPreset(preset.name)"
                                class="rounded-full px-3 py-1 text-xs font-medium transition-all"
                                :class="!is360Active && currentPreset === preset.name 
                                    ? 'bg-white text-black shadow-md font-semibold' 
                                    : 'bg-white/5 text-neutral-300 hover:text-white hover:bg-white/10'"
                            >
                                {{ preset.name }}
                            </button>
                        </div>

                        <!-- Mode Toggles (Rotate / Putar 360 / Tambah Pin Unlimited) -->
                        <div class="flex items-center gap-2">
                            <!-- Tombol 360 Derajat: Mengubah Objek ke Perspektif 360 Rotasi -->
                            <button
                                type="button"
                                @click="toggle360"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-all shadow-md"
                                :class="is360Active 
                                    ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white ring-2 ring-indigo-400/80 shadow-[0_0_15px_rgba(99,102,241,0.5)]' 
                                    : 'bg-white/10 text-neutral-200 hover:text-white hover:bg-white/20'"
                            >
                                <RotateCw class="h-3.5 w-3.5 transition-transform duration-700" :class="is360Active ? 'rotate-180 text-white' : ''" />
                                <span>{{ is360Active ? '360° Aktif' : 'Putar 360°' }}</span>
                            </button>

                            <!-- Tombol Tambah Pin Unlimited -->
                            <button
                                type="button"
                                @click="togglePinMode"
                                class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold transition-all shadow-md"
                                :class="activeMode === 'pin' 
                                    ? 'bg-emerald-500 text-black ring-2 ring-emerald-400' 
                                    : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40'"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <span>{{ activeMode === 'pin' ? 'Klik di Mana Saja!' : 'Tambah Pin' }}</span>
                            </button>

                            <button
                                type="button"
                                @click="showAnnotations = !showAnnotations"
                                class="rounded-full p-1.5 text-xs font-medium text-neutral-300 hover:text-white bg-white/5 hover:bg-white/10"
                                :title="showAnnotations ? 'Sembunyikan Pin' : 'Tampilkan Pin'"
                            >
                                <Eye class="h-4 w-4" :class="showAnnotations ? 'text-indigo-400' : 'text-neutral-500'" />
                            </button>
                        </div>
                    </div>

                    <!-- Main Viewport + Sidebar Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-12 min-h-[350px] sm:min-h-[380px]">
                        <!-- Viewport Canvas (8 cols) -->
                        <div
                            class="relative md:col-span-8 overflow-hidden bg-black select-none"
                            :class="activeMode === 'pin' ? 'cursor-crosshair' : 'cursor-grab'"
                            @click="handleCanvasClick"
                        >
                            <!-- Visual Objek Gambar Berganti Sesuai Tab & Pilihan (Fasad Utama / Dek Cantilever / Pencahayaan Interior / 360) -->
                            <div class="relative h-full w-full overflow-hidden">
                                <img
                                    :src="currentObjectImage"
                                    :alt="currentPreset"
                                    class="h-full w-full object-cover min-h-[290px] transition-all duration-700 ease-out"
                                    :style="{
                                        transform: is360Active ? 'scale(1.02)' : (currentPreset === 'Dek Cantilever' ? 'scale(1.05)' : 'scale(1.0)'),
                                        filter: currentPreset === 'Pencahayaan Interior' ? 'contrast(115%) brightness(105%)' : 'none'
                                    }"
                                />

                                <!-- Ambient Gradient Scrim -->
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/20"></div>

                                <!-- Current Object Watermark Badge -->
                                <div class="absolute bottom-3 left-3 z-20 flex items-center gap-2 rounded-lg border border-white/15 bg-black/70 px-2.5 py-1 text-[10px] font-mono text-neutral-200 backdrop-blur-xl">
                                    <span class="h-2 w-2 rounded-full" :class="is360Active ? 'bg-purple-400 animate-spin' : 'bg-emerald-400'"></span>
                                    <span>OBJEK: {{ is360Active ? 'PERSPEKTIF 360° WATER ROTATION' : currentPreset.toUpperCase() }}</span>
                                </div>
                            </div>

                            <!-- Mode Tambah Pin Unlimited Banner -->
                            <div
                                v-if="activeMode === 'pin'"
                                class="absolute top-3 left-3 z-30 flex items-center gap-2 rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-black shadow-lg animate-pulse"
                            >
                                <span>Mode Pin Unlimited Aktif — Klik titik mana saja untuk menambah pin!</span>
                            </div>

                            <!-- Indikator Rotasi 360 Derajat -->
                            <div
                                v-if="is360Active"
                                class="absolute top-3 right-3 z-30 flex items-center gap-2 rounded-full border border-purple-500/40 bg-purple-950/80 px-3 py-1 text-xs font-mono text-purple-300 shadow-xl backdrop-blur-xl"
                            >
                                <RotateCw class="h-3 w-3 animate-spin text-purple-400" style="animation-duration: 12s;" />
                                <span>360° Orbit Perspective</span>
                            </div>

                            <!-- Target Crosshair on Step 1 (Raycasting simulation) -->
                            <div v-if="activeTab === 1 && currentPreset === 'Dek Cantilever'" class="absolute top-[35%] left-[48%] -translate-x-1/2 -translate-y-1/2 z-20 pointer-events-none">
                                <div class="relative flex items-center justify-center">
                                    <div class="absolute h-14 w-14 rounded-full border border-blue-400 animate-ping"></div>
                                    <div class="h-8 w-8 rounded-full border border-white/60 flex items-center justify-center">
                                        <Target class="h-4 w-4 text-blue-400" />
                                    </div>
                                </div>
                            </div>

                            <!-- Interactive Pins Layer (Unlimited Pins) -->
                            <template v-if="showAnnotations">
                                <div
                                    v-for="pin in pins"
                                    :key="pin.id"
                                    class="absolute z-20 transition-all duration-300"
                                    :style="{ left: `${pin.x}%`, top: `${pin.y}%` }"
                                >
                                    <!-- Pin Marker Button -->
                                    <button
                                        type="button"
                                        @click.stop="selectPin(pin.id)"
                                        class="relative -translate-x-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white shadow-xl transition-transform hover:scale-125 focus:outline-none"
                                        :class="pin.resolved 
                                            ? 'bg-emerald-600 border border-emerald-300 ring-2 ring-emerald-500/50' 
                                            : 'bg-indigo-600 border-2 border-white shadow-[0_0_20px_rgba(99,102,241,1)]'"
                                    >
                                        {{ pin.id }}
                                    </button>

                                    <!-- Floating Popover Bubble -->
                                    <div
                                        v-if="activePinId === pin.id"
                                        class="absolute left-6 -top-6 w-56 sm:w-64 rounded-2xl border border-white/20 bg-black/90 p-3 shadow-2xl backdrop-blur-2xl z-30 animate-fadeIn"
                                        @click.stop
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-semibold text-white truncate max-w-[150px]">{{ pin.title }}</span>
                                            <button
                                                type="button"
                                                @click.stop="deletePin(pin.id)"
                                                class="text-neutral-500 hover:text-red-400 p-1"
                                                title="Hapus Pin Ini"
                                            >
                                                <Trash2 class="h-3 w-3" />
                                            </button>
                                        </div>
                                        <p class="mt-1 text-xs text-neutral-300 leading-snug">
                                            {{ pin.comment }}
                                        </p>
                                        <div class="mt-2 flex items-center justify-between text-[10px] text-neutral-500 pt-1.5 border-t border-white/10">
                                            <button
                                                type="button"
                                                @click.stop="resolvePin(pin.id)"
                                                class="text-xs font-medium transition-colors"
                                                :class="pin.resolved ? 'text-emerald-400 hover:text-emerald-300' : 'text-amber-400 hover:text-amber-300'"
                                            >
                                                {{ pin.resolved ? '✓ Selesai (Resolved)' : '○ Tandai Selesai' }}
                                            </button>
                                            <span class="text-neutral-400">{{ pin.author }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Side Annotation List Panel (4 cols) -->
                        <div class="md:col-span-4 border-t md:border-t-0 md:border-l border-white/10 bg-[#0a0c10] p-4 sm:p-5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
                                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-neutral-200">
                                        Catatan Revisi ({{ pins.length }})
                                    </h4>
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded bg-indigo-500/20 px-2 py-0.5 text-[10px] font-mono text-indigo-300">
                                            {{ pins.filter(p => p.resolved).length }} Resolved
                                        </span>
                                        <button
                                            v-if="pins.length > 2"
                                            type="button"
                                            @click="resetPins"
                                            class="text-[10px] text-neutral-400 hover:text-white transition-colors"
                                            title="Reset ke pin awal"
                                        >
                                            Reset
                                        </button>
                                    </div>
                                </div>

                                <!-- Dynamic Unlimited Pin Scroll List -->
                                <div class="mt-3 space-y-2 max-h-52 overflow-y-auto pr-1">
                                    <div
                                        v-for="pin in pins"
                                        :key="pin.id"
                                        @click="selectPin(pin.id)"
                                        class="rounded-xl border p-2.5 cursor-pointer transition-all duration-200"
                                        :class="activePinId === pin.id 
                                            ? 'border-indigo-500/50 bg-indigo-500/10' 
                                            : 'border-white/10 bg-white/[0.02] hover:bg-white/5'"
                                    >
                                        <div class="flex items-center justify-between text-xs">
                                            <div class="flex items-center gap-1.5">
                                                <span class="flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold"
                                                    :class="pin.resolved ? 'bg-emerald-600 text-white' : 'bg-indigo-600 text-white'"
                                                >
                                                    {{ pin.id }}
                                                </span>
                                                <span class="font-semibold text-white truncate max-w-[110px]">{{ pin.title }}</span>
                                            </div>
                                            <span class="text-[9px]" :class="pin.resolved ? 'text-emerald-400' : 'text-amber-400'">
                                                {{ pin.resolved ? 'Resolved' : 'Pending' }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-neutral-300 line-clamp-2">
                                            {{ pin.comment }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Panel Bottom Helper -->
                            <div class="mt-3 rounded-xl border border-white/10 bg-black/40 p-2.5 text-xs text-neutral-400">
                                <div class="flex items-center justify-between text-indigo-400 font-medium text-[11px]">
                                    <div class="flex items-center gap-1.5">
                                        <CheckCircle2 class="h-3.5 w-3.5" />
                                        <span>Pin Unlimited Didukung</span>
                                    </div>
                                    <span class="font-mono text-neutral-300 text-[10px]">{{ pins.length }} Pin Aktif</span>
                                </div>
                                <p class="mt-1 text-[10px] text-neutral-400 leading-tight">
                                    Klik tombol "+ Tambah Pin" lalu klik titik mana saja di fasad vila untuk menambahkan pin tanpa batas.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Progress Indicator -->
            <div class="relative z-20 flex items-center justify-between border-t border-white/10 pt-2.5 text-xs text-neutral-400 font-mono">
                <div class="flex items-center gap-2">
                    <span class="text-white font-semibold uppercase">{{ tabs[activeTab].badge }}</span>
                    <span>—</span>
                    <span class="text-neutral-300">{{ tabs[activeTab].title }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-for="(_, idx) in tabs"
                        :key="idx"
                        type="button"
                        @click="jumpToTab(idx)"
                        class="h-2 rounded-full transition-all duration-300"
                        :class="activeTab === idx ? 'w-8 bg-indigo-500' : 'w-2 bg-white/20 hover:bg-white/50'"
                        :aria-label="`Pindah ke tab ${idx + 1}`"
                    ></button>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
