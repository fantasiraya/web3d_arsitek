<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { 
    Layers, 
    Sun, 
    Maximize2, 
    Sparkles, 
    Cpu, 
    Compass, 
    ChevronDown, 
    Eye, 
    Check,
    SlidersHorizontal
} from '@lucide/vue';

const containerRef = ref<HTMLElement | null>(null);
const scrollProgress = ref(0);
const activeLayer = ref(0);

const layers = [
    {
        id: 'wireframe',
        title: 'Analisis Geometri & Rangka Kantilever',
        badge: 'Layer 01',
        subtitle: 'Presisi Struktur Ekstrim',
        description: 'Kantilever beton bertulang sepanjang 8.5 meter di atas permukaan air. Geometri poligon dianalisis dan direduksi hingga rasio optimal 1:12 tanpa kehilangan ketegasan garis tepi fasad brutalist kontemporer.',
        specs: [
            { label: 'Panjang Kantilever', value: '8.50 m' },
            { label: 'Densitas Mesh', value: '184k Tri' },
            { label: 'LOD Buffer', value: '3 Tingkat' }
        ],
        hudColor: 'border-cyan-500/30 text-cyan-400 bg-cyan-500/10',
        filterStyle: 'contrast(115%) saturate(85%) hue-rotate(190deg)'
    },
    {
        id: 'glass',
        title: 'Fasad Kaca Akustik & Low-E Glazing',
        badge: 'Layer 02',
        subtitle: 'Transparansi Tanpa Refleksi Berlebih',
        description: 'Panel kaca floor-to-ceiling setinggi 4.2 meter dengan indeks bias fotometrik 1.52. Mensimulasikan pantulan cahaya air dan transmisi cahaya interior secara akurat di sudut pandang manapun.',
        specs: [
            { label: 'Tinggi Panel', value: '4.20 m' },
            { label: 'Refraction Index', value: '1.52 IOR' },
            { label: 'Insulasi Akustik', value: 'Rw 42 dB' }
        ],
        hudColor: 'border-blue-500/30 text-blue-400 bg-blue-500/10',
        filterStyle: 'contrast(125%) brightness(105%) hue-rotate(210deg)'
    },
    {
        id: 'lighting',
        title: 'Suhu Warna Fotometrik & Pencahayaan Senja',
        badge: 'Layer 03',
        subtitle: 'Kontras Golden Hour & Twilight',
        description: 'Pencahayaan ambien interior 2800K warm ambient berpadu dengan cahaya langit twilight 6500K. Menghadirkan kedalaman visual dramatis yang memukau klien saat memutar sudut kamera secara bebas.',
        specs: [
            { label: 'Suhu Interior', value: '2800 Kelvin' },
            { label: 'Langit Twilight', value: '6500 Kelvin' },
            { label: 'Volumetric Bloom', value: 'Enabled' }
        ],
        hudColor: 'border-amber-500/30 text-amber-400 bg-amber-500/10',
        filterStyle: 'contrast(120%) brightness(110%) sepia(20%)'
    },
    {
        id: 'materials',
        title: 'Material Obsidian, Marmer & Air Pantulan',
        badge: 'Layer 04',
        subtitle: 'PBR Shaders Generasi Baru',
        description: 'Tekstur micro-roughness beton cair obsidian dipadukan aksen titanium anodized. Permukaan air kolam memantulkan pilar-pilar arsitektur dengan ripple displacement real-time berlatensi ultra-rendah.',
        specs: [
            { label: 'Tekstur PBR', value: '4K Micro-Map' },
            { label: 'Roughness Map', value: '0.12 - 0.78' },
            { label: 'Frame Rendering', value: '60 FPS Stabil' }
        ],
        hudColor: 'border-purple-500/30 text-purple-400 bg-purple-500/10',
        filterStyle: 'contrast(130%) saturate(110%)'
    }
];

const handleScroll = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    const windowHeight = window.innerHeight;
    const totalDist = rect.height - windowHeight;

    if (totalDist <= 0) return;

    const scrolled = -rect.top;
    const progress = Math.min(Math.max(scrolled / totalDist, 0), 1);
    scrollProgress.value = progress;

    const layerIdx = Math.min(Math.floor(progress * layers.length), layers.length - 1);
    activeLayer.value = layerIdx;
};

const scrollToLayer = (index: number) => {
    if (!containerRef.value) return;
    const containerTop = containerRef.value.getBoundingClientRect().top + window.scrollY;
    const totalDist = containerRef.value.offsetHeight - window.innerHeight;
    const targetScrollY = containerTop + (index / layers.length) * totalDist + 20;
    window.scrollTo({ top: targetScrollY, behavior: 'smooth' });
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

const layerProgress = computed(() => {
    const span = 1 / layers.length;
    const start = activeLayer.value * span;
    const rel = (scrollProgress.value - start) / span;
    return Math.min(Math.max(rel, 0), 1);
});
</script>

<template>
    <section
        id="anatomy"
        ref="containerRef"
        class="relative h-[340vh] sm:h-[400vh] bg-[#050608] border-t border-white/10"
    >
        <!-- Sticky Viewport Canvas -->
        <div class="sticky top-0 h-screen w-full overflow-hidden flex flex-col justify-between py-6 sm:py-8 lg:py-10 px-4 sm:px-8 max-w-7xl mx-auto z-10">
            <!-- Top Controls & Layer Navigation -->
            <div class="relative z-20 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <div class="inline-flex items-center gap-2 rounded-full border border-cyan-500/30 bg-cyan-500/10 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-cyan-300">
                            <Layers class="h-3 w-3 text-cyan-400" />
                            <span>Anatomi Spasial Digital</span>
                        </div>
                        <span class="text-xs font-mono text-neutral-400">
                            Lapisan {{ activeLayer + 1 }} dari {{ layers.length }}
                        </span>
                    </div>

                    <div class="hidden sm:flex items-center gap-3 text-xs font-mono text-neutral-400">
                        <span class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        <span>Scroll-Driven Telemetry: {{ Math.round(scrollProgress * 100) }}%</span>
                    </div>
                </div>

                <!-- 4-Layer Progress Track -->
                <div class="grid grid-cols-4 gap-2">
                    <button
                        v-for="(layer, idx) in layers"
                        :key="layer.id"
                        type="button"
                        @click="scrollToLayer(idx)"
                        class="group text-left"
                    >
                        <div class="h-1.5 sm:h-2 w-full rounded-full bg-white/10 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-150"
                                :class="{
                                    'bg-gradient-to-r from-cyan-400 to-indigo-400': idx === activeLayer,
                                    'bg-white/80': idx < activeLayer,
                                    'bg-transparent': idx > activeLayer
                                }"
                                :style="{
                                    width: idx === activeLayer
                                        ? `${Math.round(layerProgress * 100)}%`
                                        : (idx < activeLayer ? '100%' : '0%')
                                }"
                            ></div>
                        </div>
                        <span
                            class="mt-1.5 hidden md:block text-[11px] font-medium tracking-tight truncate transition-colors"
                            :class="activeLayer === idx ? 'text-white font-semibold' : 'text-neutral-500 group-hover:text-neutral-300'"
                        >
                            {{ layer.badge }} • {{ layer.title }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Central Split: Interactive Cinematic Canvas & Architectural Layer Inspection -->
            <div class="relative z-20 my-auto grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 items-center">
                
                <!-- Left: Full Architectural Viewport with Live HUD Overlay (7 cols) -->
                <div class="lg:col-span-7">
                    <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-neutral-950 p-2 sm:p-3 shadow-2xl backdrop-blur-2xl">
                        
                        <!-- Visual Image Container with Parallax Scale & Dynamic Filter -->
                        <div class="relative h-64 sm:h-80 lg:h-96 w-full rounded-2xl overflow-hidden bg-black">
                            <img
                                src="/images/aether_villa_hero.jpg"
                                alt="Architectural Anatomy Visualization"
                                class="h-full w-full object-cover transition-all duration-700 ease-out"
                                :style="{
                                    transform: `scale(${1 + activeLayer * 0.04}) rotate(${activeLayer * 0.5 - 0.75}deg)`,
                                    filter: layers[activeLayer].filterStyle
                                }"
                            />

                            <!-- Gradient Vignette -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/30"></div>

                            <!-- Live Telemetry Corner Badges -->
                            <div class="absolute top-4 left-4 z-20 flex flex-col gap-1.5">
                                <div class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[10px] font-mono backdrop-blur-xl"
                                    :class="layers[activeLayer].hudColor"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-current animate-ping"></span>
                                    <span>ACTIVE: {{ layers[activeLayer].badge }}</span>
                                </div>
                                <div class="rounded-lg border border-white/10 bg-black/60 px-2.5 py-0.5 text-[10px] font-mono text-neutral-300 backdrop-blur-xl">
                                    PBR PASS: DIRECT_DIFFUSE + SPECULAR
                                </div>
                            </div>

                            <div class="absolute top-4 right-4 z-20 rounded-lg border border-white/10 bg-black/60 px-2.5 py-1 text-[10px] font-mono text-neutral-300 backdrop-blur-xl">
                                60.0 FPS • 14.3 MB GLB
                            </div>

                            <!-- Layer HUD Wireframe/Grid Target Crosshair -->
                            <div class="absolute inset-0 pointer-events-none z-10 flex items-center justify-center">
                                <div class="relative h-44 w-44 rounded-full border border-white/10 flex items-center justify-center animate-spin" style="animation-duration: 25s;">
                                    <div class="absolute top-0 h-2 w-2 rounded-full bg-cyan-400"></div>
                                    <div class="absolute bottom-0 h-2 w-2 rounded-full bg-indigo-400"></div>
                                </div>
                                <div class="absolute h-24 w-24 border border-white/20"></div>
                            </div>

                            <!-- Bottom Floating Metric bar -->
                            <div class="absolute bottom-4 left-4 right-4 z-20 flex items-center justify-between rounded-xl border border-white/15 bg-black/75 px-4 py-2 text-xs font-mono backdrop-blur-xl">
                                <span class="text-neutral-300 truncate">VILLA CANTILEVER // MODEL V3.8</span>
                                <span class="text-cyan-400 font-semibold shrink-0">THREE.JS DRIFT 0.00°</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Layer Specifications & Engineering Narrative (5 cols) -->
                <div class="lg:col-span-5 space-y-4 sm:space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-medium text-neutral-300">
                        <span class="h-2 w-2 rounded-full bg-cyan-400"></span>
                        <span>{{ layers[activeLayer].subtitle }}</span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight font-sans">
                        {{ layers[activeLayer].title }}
                    </h3>

                    <p class="text-xs sm:text-sm lg:text-base leading-relaxed text-neutral-300">
                        {{ layers[activeLayer].description }}
                    </p>

                    <!-- Technical Specs Grid -->
                    <div class="grid grid-cols-3 gap-2.5 sm:gap-3 pt-2">
                        <div
                            v-for="spec in layers[activeLayer].specs"
                            :key="spec.label"
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 backdrop-blur-xl text-center shadow-lg"
                        >
                            <div class="text-xs sm:text-sm font-bold text-white font-mono">{{ spec.value }}</div>
                            <div class="mt-1 text-[9px] sm:text-[10px] text-neutral-400 leading-tight">{{ spec.label }}</div>
                        </div>
                    </div>

                    <!-- Scroll Hint -->
                    <div class="pt-2 flex items-center gap-2 text-xs text-neutral-400 font-mono">
                        <div class="flex h-5 w-5 items-center justify-center rounded-full border border-white/15 bg-white/5 animate-bounce">
                            <ChevronDown class="h-3 w-3 text-cyan-400" />
                        </div>
                        <span>Scroll terus untuk menjelajah lapisan arsitektur</span>
                    </div>
                </div>
            </div>

            <!-- Bottom Progress Bar Indicator -->
            <div class="relative z-20 flex items-center justify-between border-t border-white/10 pt-3 text-xs text-neutral-400 font-mono">
                <div class="flex items-center gap-2">
                    <span class="text-white font-semibold">{{ layers[activeLayer].badge }}</span>
                    <span>/</span>
                    <span>{{ layers[activeLayer].title }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-for="(_, idx) in layers"
                        :key="idx"
                        type="button"
                        @click="scrollToLayer(idx)"
                        class="h-2 rounded-full transition-all duration-300"
                        :class="activeLayer === idx ? 'w-8 bg-cyan-400' : 'w-2 bg-white/20 hover:bg-white/50'"
                        :aria-label="`Pindah ke layer ${idx + 1}`"
                    ></button>
                </div>
            </div>
        </div>
    </section>
</template>
