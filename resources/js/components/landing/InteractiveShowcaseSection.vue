<script setup lang="ts">
import { ref } from 'vue';
import { Eye, Layers, Compass, Plus, Sliders, MessageSquare, Trash2, CheckCircle2, RotateCw } from '@lucide/vue';

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

const pins = ref<DemoPin[]>([
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
]);

const activePinId = ref<number | null>(1);
const showAnnotations = ref(true);
const currentPreset = ref('Fasad Utama');
const activeMode = ref<'rotate' | 'pan' | 'pin'>('rotate');

const presets = [
    { name: 'Fasad Utama', angle: '0° Sudut Depan' },
    { name: 'Dek Cantilever', angle: '45° Elevated' },
    { name: 'Pencahayaan Interior', angle: 'Close-up Warm' }
];

const handleCanvasClick = (e: MouseEvent) => {
    if (activeMode.value === 'pin' && pins.value.length < 4) {
        const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
        const x = Math.round(((e.clientX - rect.left) / rect.width) * 100);
        const y = Math.round(((e.clientY - rect.top) / rect.height) * 100);

        const newId = pins.value.length + 1;
        pins.value.push({
            id: newId,
            title: `Anotasi Catatan #${newId}`,
            comment: 'Catatan spasial baru berhasil ditambahkan pada permukaan model.',
            author: 'Klien Reviewer',
            time: 'Baru saja',
            x,
            y,
            resolved: false
        });
        activePinId.value = newId;
        activeMode.value = 'rotate';
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
</script>

<template>
    <section class="relative py-28 sm:py-36 bg-[#07080b] border-t border-white/10">
        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Section Header -->
            <div class="mx-auto max-w-2xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300">
                    <Compass class="h-3 w-3 text-indigo-400" />
                    <span>Simulasi Interaktif</span>
                </div>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-white sm:text-5xl font-sans">
                    Coba Pengalaman <br />
                    <span class="bg-gradient-to-r from-white via-neutral-300 to-neutral-400 bg-clip-text text-transparent">
                        3D Spatial Annotation
                    </span>
                </h2>
                <p class="mt-4 text-sm sm:text-base text-neutral-300">
                    Klik tombol "Tambah Pin" di toolbar, lalu klik titik mana pun pada fasad vila untuk mensimulasikan bagaimana klien memberikan masukan secara spasial.
                </p>
            </div>

            <!-- Interactive Showcase Container -->
            <div class="mt-14 overflow-hidden rounded-[32px] border border-white/15 bg-black shadow-[0_0_80px_rgba(0,0,0,0.8)]">
                <!-- Toolbar Header -->
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 bg-[#0e1017] px-6 py-4">
                    <!-- Presets Switcher -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-neutral-300 mr-1 hidden sm:inline">Kamera:</span>
                        <button
                            v-for="preset in presets"
                            :key="preset.name"
                            type="button"
                            @click="currentPreset = preset.name"
                            class="rounded-full px-3 py-1 text-xs font-medium transition-all"
                            :class="currentPreset === preset.name ? 'bg-white text-black shadow-sm' : 'bg-white/5 text-neutral-300 hover:text-white hover:bg-white/10'"
                        >
                            {{ preset.name }}
                        </button>
                    </div>

                    <!-- Mode Toggles (Rotate / Pan / Add Pin) -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="activeMode = 'rotate'"
                            class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                            :class="activeMode === 'rotate' ? 'bg-indigo-600 text-white' : 'bg-white/5 text-neutral-300 hover:text-white'"
                        >
                            <RotateCw class="h-3.5 w-3.5" />
                            <span>Putar 360°</span>
                        </button>

                        <button
                            type="button"
                            @click="activeMode = 'pin'"
                            class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-semibold transition-all shadow-md"
                            :class="activeMode === 'pin' ? 'bg-emerald-500 text-black ring-2 ring-emerald-400' : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40'"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            <span>{{ activeMode === 'pin' ? 'Klik pada Vila!' : '+ Tambah Pin' }}</span>
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
                <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[460px]">
                    <!-- Viewport Canvas (8 cols) -->
                    <div
                        class="relative lg:col-span-8 overflow-hidden bg-black select-none"
                        :class="activeMode === 'pin' ? 'cursor-crosshair' : 'cursor-grab'"
                        @click="handleCanvasClick"
                    >
                        <!-- Villa Render Image -->
                        <img
                            src="/images/aether_villa_hero.jpg"
                            alt="Interactive Architectural Canvas"
                            class="h-full w-full object-cover min-h-[360px]"
                        />

                        <!-- Gradient Scrim -->
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>

                        <!-- Active Pin Placement Mode Indicator -->
                        <div
                            v-if="activeMode === 'pin'"
                            class="absolute top-4 left-4 z-30 flex items-center gap-2 rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-black shadow-lg animate-pulse"
                        >
                            <span>Mode Anotasi Aktif — Silakan klik pada dinding atau kaca</span>
                        </div>

                        <!-- Interactive Pins Layer -->
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
                                        ? 'bg-emerald-600 border border-emerald-300' 
                                        : 'bg-indigo-600 border-2 border-white shadow-[0_0_20px_rgba(99,102,241,1)]'"
                                >
                                    {{ pin.id }}
                                </button>

                                <!-- Floating Popover Bubble -->
                                <div
                                    v-if="activePinId === pin.id"
                                    class="absolute left-6 -top-6 w-60 sm:w-68 rounded-2xl border border-white/20 bg-black/90 p-3 shadow-2xl backdrop-blur-2xl z-30 animate-fadeIn"
                                    @click.stop
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-white">{{ pin.title }}</span>
                                        <button
                                            type="button"
                                            @click.stop="deletePin(pin.id)"
                                            class="text-neutral-500 hover:text-red-400 p-1"
                                            title="Hapus Pin"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-neutral-300 leading-snug">
                                        {{ pin.comment }}
                                    </p>
                                    <div class="mt-2 flex items-center justify-between text-[10px] text-neutral-500 pt-1.5 border-t border-white/10">
                                        <span>{{ pin.author }}</span>
                                        <span :class="pin.resolved ? 'text-emerald-400' : 'text-amber-400'">
                                            {{ pin.resolved ? 'Selesai' : 'Pending Revisi' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Side Anotation List Panel (4 cols) -->
                    <div class="lg:col-span-4 border-t lg:border-t-0 lg:border-l border-white/10 bg-[#0a0c10] p-6 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-neutral-200">
                                    Daftar Catatan Revisi ({{ pins.length }})
                                </h3>
                                <span class="rounded bg-indigo-500/20 px-2 py-0.5 text-[10px] font-mono text-indigo-300">
                                    {{ pins.filter(p => p.resolved).length }} Resolved
                                </span>
                            </div>

                            <div class="mt-4 space-y-3 max-h-72 overflow-y-auto pr-1">
                                <div
                                    v-for="pin in pins"
                                    :key="pin.id"
                                    @click="selectPin(pin.id)"
                                    class="rounded-2xl border p-3 cursor-pointer transition-all duration-200"
                                    :class="activePinId === pin.id 
                                        ? 'border-indigo-500/50 bg-indigo-500/10' 
                                        : 'border-white/10 bg-white/[0.02] hover:bg-white/5'"
                                >
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                                                :class="pin.resolved ? 'bg-emerald-600 text-white' : 'bg-indigo-600 text-white'"
                                            >
                                                {{ pin.id }}
                                            </span>
                                            <span class="font-semibold text-white truncate max-w-[140px]">{{ pin.title }}</span>
                                        </div>
                                        <span class="text-[10px] text-neutral-300">{{ pin.time }}</span>
                                    </div>
                                    <p class="mt-1.5 text-xs text-neutral-300 line-clamp-2">
                                        {{ pin.comment }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Panel Bottom Helper -->
                        <div class="mt-6 rounded-2xl border border-white/10 bg-black/40 p-3.5 text-xs text-neutral-400">
                            <div class="flex items-center gap-2 text-indigo-400 font-medium">
                                <CheckCircle2 class="h-4 w-4" />
                                <span>Persistensi LocalStorage & DB</span>
                            </div>
                            <p class="mt-1 text-[11px] text-neutral-400">
                                Pada aplikasi nyata, posisi pin dan thread komentar tersimpan permanen di database PostgreSQL dan disinkronkan secara instan.
                            </p>
                        </div>
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
