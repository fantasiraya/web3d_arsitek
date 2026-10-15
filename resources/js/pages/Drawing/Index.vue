<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import {
    DraftingCompass,
    Plus,
    FileCode,
    Download,
    Trash2,
    Layers,
    Sliders,
    Search,
    ExternalLink,
    AlertCircle,
    CheckCircle2,
    Calendar,
    FolderKanban,
    Maximize2,
    Sparkles,
    Lock,
} from '@lucide/vue';

defineOptions({ layout: UnifiedLayout });

interface ProjectOption {
    id: string;
    title: string;
}

interface DrawingSheetItem {
    id: string;
    project_id: string;
    user_id: string;
    title: string;
    type: 'plan' | 'elevation' | 'section';
    scale: string;
    paper_size: string;
    orientation: string;
    grid_spacing: number;
    unit: string;
    sort_order: number;
    created_at: string;
    updated_at: string;
    project?: {
        id: string;
        title: string;
    };
}

const props = defineProps<{
    sheets: {
        data: DrawingSheetItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
    projects: ProjectOption[];
    selectedProjectId?: string | null;
    hasAccess: boolean;
}>();

// Modal Buat Lembar Kerja Baru
const showCreateModal = ref(false);

const form = useForm({
    project_id: props.selectedProjectId || (props.projects[0]?.id ?? ''),
    title: '',
    type: 'plan' as 'plan' | 'elevation' | 'section',
    scale: '1:100',
    paper_size: 'A3',
    orientation: 'landscape',
    grid_spacing: 20,
    unit: 'm',
});

const searchQuery = ref('');
const filterType = ref<'all' | 'plan' | 'elevation' | 'section'>('all');

const filteredSheets = computed(() => {
    return props.sheets.data.filter((sheet) => {
        const matchTitle = sheet.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                           (sheet.project?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ?? false);
        const matchType = filterType.value === 'all' || sheet.type === filterType.value;
        return matchTitle && matchType;
    });
});

const submitCreate = () => {
    if (!props.hasAccess) {
        if (confirm('Fitur Lembar Kerja 2D (Drawing CAD) eksklusif untuk paket Enterprise.\n\nLihat opsi paket sekarang?')) {
            router.visit('/plans');
        }
        return;
    }

    form.post('/drawings', {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};

const deleteSheet = (sheet: DrawingSheetItem) => {
    if (confirm(`Apakah Anda yakin ingin menghapus lembar kerja "${sheet.title}"? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(`/drawings/${sheet.id}`);
    }
};

const filterByProject = (projectId: string) => {
    router.get('/drawings', projectId ? { project_id: projectId } : {}, {
        preserveState: true,
        replace: true,
    });
};

const getTypeLabel = (type: string) => {
    switch (type) {
        case 'plan': return 'Denah (Plan)';
        case 'elevation': return 'Tampak (Elevation)';
        case 'section': return 'Potongan (Section)';
        default: return type;
    }
};

const getTypeColor = (type: string) => {
    switch (type) {
        case 'plan': return 'bg-sky-50 dark:bg-sky-400/10 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-400/20';
        case 'elevation': return 'bg-emerald-50 dark:bg-emerald-400/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-400/20';
        case 'section': return 'bg-purple-50 dark:bg-purple-400/10 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-400/20';
        default: return 'bg-slate-100 text-slate-700';
    }
};
</script>

<template>
    <Head title="Lembar Kerja 2D (Drawing CAD)" />

    <div class="min-h-screen bg-slate-50 dark:bg-[#090a0f] text-slate-800 dark:text-slate-100 py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-8">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 dark:border-white/10 pb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-600 dark:text-violet-400 shadow-sm">
                            <DraftingCompass class="w-5 h-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Lembar Kerja 2D</h1>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-violet-100 dark:bg-violet-400/15 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-400/30">
                                    ENTERPRISE CAD
                                </span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Gambar teknik denah, tampak, dan potongan 2D terintegrasi dengan ekspor AutoCAD (.DXF) & vektor (.SVG).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium text-sm text-white bg-violet-600 hover:bg-violet-700 active:scale-[0.98] transition-all shadow-md shadow-violet-600/20 cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        Buat Lembar Kerja Baru
                    </button>
                </div>
            </div>

            <!-- Gating Notice if not Enterprise -->
            <div v-if="!hasAccess" class="rounded-2xl p-5 bg-gradient-to-r from-violet-900/30 via-slate-900/40 to-slate-900/20 border border-violet-500/30 flex items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="p-2.5 rounded-xl bg-violet-500/20 text-violet-400 shrink-0">
                        <Lock class="w-5 h-5" />
                    </div>
                    <div>
                        <h4 class="font-semibold text-slate-900 dark:text-white text-sm">Fitur Lembar Kerja 2D Khusus Paket Enterprise</h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                            Paket aktif Anda saat ini belum mencakup editor CAD 2D. Upgrade ke Enterprise untuk membuka lembar kerja, grid snap, dan ekspor AutoCAD DXF.
                        </p>
                    </div>
                </div>
                <Link
                    href="/plans"
                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white shrink-0 shadow-sm transition-colors"
                >
                    Lihat Paket
                </Link>
            </div>

            <!-- Controls: Search & Filters -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white dark:bg-[#12131a] p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <div class="relative flex-1 max-w-md">
                    <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari lembar kerja atau proyek..."
                        class="w-full pl-9 pr-4 py-2 text-sm rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/10 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                    />
                </div>

                <div class="flex items-center gap-2 overflow-x-auto">
                    <!-- Project Selector Filter -->
                    <select
                        :value="selectedProjectId || ''"
                        @change="filterByProject(($event.target as HTMLSelectElement).value)"
                        class="text-xs rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/10 px-3 py-2 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                    >
                        <option value="">Semua Proyek</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.title }}
                        </option>
                    </select>

                    <!-- Type Filter Tabs -->
                    <div class="inline-flex rounded-xl p-1 bg-slate-100 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-xs">
                        <button
                            v-for="t in [
                                { key: 'all', label: 'Semua' },
                                { key: 'plan', label: 'Denah' },
                                { key: 'elevation', label: 'Tampak' },
                                { key: 'section', label: 'Potongan' },
                            ]"
                            :key="t.key"
                            @click="filterType = t.key as any"
                            :class="[
                                'px-3 py-1.5 rounded-lg font-medium transition-all cursor-pointer',
                                filterType === t.key
                                    ? 'bg-white dark:bg-[#1e2029] text-violet-600 dark:text-violet-300 shadow-sm'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white',
                            ]"
                        >
                            {{ t.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sheets Grid -->
            <div v-if="filteredSheets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="sheet in filteredSheets"
                    :key="sheet.id"
                    class="group relative bg-white dark:bg-[#12131a] rounded-2xl border border-slate-200 dark:border-white/5 overflow-hidden shadow-sm hover:border-violet-500/40 hover:shadow-lg transition-all flex flex-col justify-between"
                >
                    <!-- Preview Canvas Area -->
                    <div class="h-44 bg-slate-100 dark:bg-[#0a0b10] border-b border-slate-200 dark:border-white/5 p-4 flex flex-col justify-between relative overflow-hidden">
                        <!-- Grid visual pattern background -->
                        <div class="absolute inset-0 bg-[linear-gradient(to_right,#cbd5e115_1px,transparent_1px),linear-gradient(to_bottom,#cbd5e115_1px,transparent_1px)] bg-[size:16px_16px]"></div>
                        
                        <!-- Top badges -->
                        <div class="relative z-10 flex items-center justify-between">
                            <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-semibold border', getTypeColor(sheet.type)]">
                                {{ getTypeLabel(sheet.type) }}
                            </span>
                            <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-200/60 dark:bg-white/10 text-slate-700 dark:text-slate-300">
                                {{ sheet.scale }} • {{ sheet.paper_size }}
                            </span>
                        </div>

                        <!-- Miniature CAD vector preview simulation -->
                        <div class="relative z-10 flex items-center justify-center py-4">
                            <div class="w-32 h-20 rounded border border-dashed border-violet-400/40 flex items-center justify-center bg-violet-500/5 group-hover:scale-105 transition-transform">
                                <DraftingCompass class="w-8 h-8 text-violet-400/60" />
                            </div>
                        </div>

                        <!-- Footer metadata inside preview -->
                        <div class="relative z-10 flex items-center justify-between text-[11px] text-slate-400">
                            <span class="truncate max-w-[180px]">{{ sheet.project?.title || 'Proyek Tanpa Judul' }}</span>
                            <span class="uppercase font-mono">{{ sheet.orientation }}</span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-base group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors">
                                {{ sheet.title }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
                                <Calendar class="w-3.5 h-3.5" />
                                Diperbarui {{ new Date(sheet.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </p>
                        </div>

                        <!-- Action Bar -->
                        <div class="pt-3 border-t border-slate-100 dark:border-white/5 flex items-center justify-between gap-2">
                            <Link
                                :href="`/drawings/${sheet.id}`"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-violet-50 dark:bg-violet-400/10 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-400/20 hover:bg-violet-100 dark:hover:bg-violet-400/20 transition-colors"
                            >
                                <DraftingCompass class="w-3.5 h-3.5" />
                                Buka CAD Editor
                            </Link>

                            <div class="flex items-center gap-1">
                                <a
                                    :href="`/drawings/${sheet.id}/export-dxf`"
                                    title="Unduh AutoCAD DXF"
                                    class="p-2 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                                >
                                    <Download class="w-4 h-4" />
                                </a>
                                <button
                                    @click="deleteSheet(sheet)"
                                    title="Hapus Lembar Kerja"
                                    class="p-2 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors cursor-pointer"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="rounded-3xl border border-dashed border-slate-200 dark:border-white/10 p-12 text-center bg-white/50 dark:bg-white/[0.02]"
            >
                <div class="w-16 h-16 rounded-2xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center mx-auto text-violet-500 mb-4">
                    <DraftingCompass class="w-8 h-8" />
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Belum Ada Lembar Kerja 2D</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto mt-1 mb-6">
                    Mulai menggambar denah lantai, tampak bangunan, dan potongan arsitektur langsung pada browser Anda.
                </p>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-sm text-white bg-violet-600 hover:bg-violet-700 transition-all cursor-pointer shadow-md shadow-violet-600/20"
                >
                    <Plus class="w-4 h-4" />
                    Buat Lembar Kerja Pertama
                </button>
            </div>
        </div>

        <!-- MODAL BUAT LEMBAR KERJA BARU -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-[#14151f] w-full max-w-lg rounded-3xl border border-slate-200 dark:border-white/10 p-6 shadow-2xl space-y-6">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-violet-500/10 text-violet-500">
                            <DraftingCompass class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Lembar Kerja 2D Baru</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Atur parameter kertas dan jenis gambar arsitektur</p>
                        </div>
                    </div>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1">
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <!-- Project Selection -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Pilih Proyek
                        </label>
                        <select
                            v-model="form.project_id"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                        >
                            <option v-for="p in projects" :key="p.id" :value="p.id">
                                {{ p.title }}
                            </option>
                        </select>
                        <p v-if="form.errors.project_id" class="text-xs text-red-500 mt-1">{{ form.errors.project_id }}</p>
                    </div>

                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Judul Lembar Kerja
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            placeholder="Contoh: Denah Lantai 1, Tampak Depan, Potongan A-A"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                        />
                        <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- Type -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Tipe Gambar
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                v-for="t in [
                                    { key: 'plan', label: 'Denah' },
                                    { key: 'elevation', label: 'Tampak' },
                                    { key: 'section', label: 'Potongan' },
                                ]"
                                :key="t.key"
                                @click="form.type = t.key as any"
                                :class="[
                                    'py-2 px-3 rounded-xl text-xs font-medium border text-center transition-all cursor-pointer',
                                    form.type === t.key
                                        ? 'bg-violet-50 dark:bg-violet-500/20 text-violet-700 dark:text-violet-300 border-violet-500'
                                        : 'bg-slate-50 dark:bg-white/[0.03] text-slate-600 dark:text-slate-400 border-slate-200 dark:border-white/10',
                                ]"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Scale & Paper Size Grid -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Skala Gambar
                            </label>
                            <select
                                v-model="form.scale"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            >
                                <option value="1:50">1:50 (Detail)</option>
                                <option value="1:100">1:100 (Standar)</option>
                                <option value="1:200">1:200 (Site Plan)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Ukuran Kertas
                            </label>
                            <select
                                v-model="form.paper_size"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            >
                                <option value="A1">A1 (841 x 594 mm)</option>
                                <option value="A2">A2 (594 x 420 mm)</option>
                                <option value="A3">A3 (420 x 297 mm)</option>
                                <option value="A4">A4 (297 x 210 mm)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Orientation & Unit -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Orientasi
                            </label>
                            <select
                                v-model="form.orientation"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            >
                                <option value="landscape">Landscape</option>
                                <option value="portrait">Portrait</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Satuan Ukuran
                            </label>
                            <select
                                v-model="form.unit"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            >
                                <option value="m">Meter (m)</option>
                                <option value="cm">Sentimeter (cm)</option>
                                <option value="mm">Milimeter (mm)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-200 dark:border-white/10 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2.5 rounded-xl text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 transition-all cursor-pointer shadow-md shadow-violet-600/20"
                        >
                            <span v-if="form.processing">Membuat...</span>
                            <span v-else>Buat & Buka Editor</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

