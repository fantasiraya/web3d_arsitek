<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import KeyPlanCanvas from '@/components/drawing/KeyPlanCanvas.vue';
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
    Camera,
    BookOpen,
    Box,
    LayoutGrid,
    Settings,
    X,
    Loader2,
} from '@lucide/vue';

defineOptions({ layout: UnifiedLayout });

interface ProjectOption {
    id: string;
    title: string;
    client_name?: string | null;
    client_address?: string | null;
}

interface ProjectCaptureItem {
    id: string;
    project_id: string;
    drawing_sheet_id?: string | null;
    room_element_id?: string | null;
    code?: string | null;
    room_name: string;
    captured_hour?: string | null;
    file_path: string;
    ai_rendered_path?: string | null;
    url?: string;
    ai_rendered_url?: string | null;
}

interface DrawingSheetItem {
    id: string;
    project_id: string;
    user_id: string;
    title: string;
    type: string;
    scale: string;
    paper_size: string;
    orientation: string;
    grid_spacing: number;
    unit: string;
    image_path?: string | null;
    image_url?: string | null;
    sort_order: number;
    captures_count?: number;
    captures?: ProjectCaptureItem[];
    elements?: any[];
    created_at: string;
    updated_at: string;
    project?: {
        id: string;
        title: string;
        description?: string;
        client_name?: string | null;
        client_address?: string | null;
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
    allProjectCaptures?: ProjectCaptureItem[];
    selectedProjectId?: string | null;
    hasAccess: boolean;
}>();

// Mode tampilan preview per kartu (apakah mode 'vector' atau 'snapshot')
const cardViewModes = ref<Record<string, 'snapshot' | 'vector'>>({});

function toggleCardViewMode(sheetId: string) {
    if (cardViewModes.value[sheetId] === 'vector') {
        cardViewModes.value[sheetId] = 'snapshot';
    } else {
        cardViewModes.value[sheetId] = 'vector';
    }
}

function isVectorMode(sheetId: string): boolean {
    return cardViewModes.value[sheetId] === 'vector';
}

function getSheetSnapshot(sheet: DrawingSheetItem): string | null {
    // Hanya tampilkan snapshot kanvas lembar kerja 2D yang tersimpan (bukan visual 3D project)
    if (sheet.image_url && sheet.image_url.trim()) {
        return sheet.image_url;
    }
    if (sheet.image_path && sheet.image_path.trim()) {
        const p = sheet.image_path.trim();
        return p.startsWith('http') ? p : `/storage/${p.replace(/^\//, '')}`;
    }
    return null;
}

function getSnapshotBadge(sheet: DrawingSheetItem): { label: string; icon: any; color: string } | null {
    if (sheet.image_url || sheet.image_path) {
        return { label: 'Snapshot CAD 2D', icon: Camera, color: 'bg-violet-600/90 text-white shadow-violet-600/30' };
    }
    return null;
}

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
    client_name: '',
    client_address: '',
});

// Auto-fill nama dan alamat klien saat proyek dipilih di modal buat baru
watch(() => form.project_id, (newId) => {
    const sel = props.projects.find(p => p.id === newId);
    if (sel) {
        form.client_name = sel.client_name || '';
        form.client_address = sel.client_address || '';
    }
}, { immediate: true });

// Modal Edit Pengaturan Lembar Kerja
const showEditModal = ref(false);
const editingSheet = ref<DrawingSheetItem | null>(null);
const editForm = useForm({
    project_id: '',
    title: '',
    type: 'plan' as 'plan' | 'elevation' | 'section',
    scale: '1:100',
    unit: 'm',
    paper_size: 'A3',
    client_name: '',
    client_address: '',
});

function openEditSheetModal(sheet: DrawingSheetItem) {
    editingSheet.value = sheet;
    editForm.project_id = sheet.project_id;
    editForm.title = sheet.title;
    editForm.type = sheet.type;
    editForm.scale = sheet.scale || '1:100';
    editForm.unit = sheet.unit || 'm';
    editForm.paper_size = sheet.paper_size || 'A3';
    editForm.client_name = sheet.project?.client_name || '';
    editForm.client_address = sheet.project?.client_address || '';
    showEditModal.value = true;
}

// Update client info saat project diganti di modal edit
watch(() => editForm.project_id, (newId) => {
    if (!editingSheet.value) return;
    if (newId === editingSheet.value.project_id) {
        editForm.client_name = editingSheet.value.project?.client_name || '';
        editForm.client_address = editingSheet.value.project?.client_address || '';
    } else {
        const sel = props.projects.find(p => p.id === newId);
        if (sel) {
            editForm.client_name = sel.client_name || '';
            editForm.client_address = sel.client_address || '';
        }
    }
});

function submitEditSheet() {
    if (!editingSheet.value) return;
    editForm.patch(`/drawings/${editingSheet.value.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            editingSheet.value = null;
        },
    });
}

// Modal Hubungkan Gambar 3D ke Lembar Kerja
const showLinkCapturesModal = ref(false);
const selectedSheetForLinking = ref<DrawingSheetItem | null>(null);
const selectedCaptureIds = ref<string[]>([]);
const isSavingLinkedCaptures = ref(false);

const availableCapturesForSelectedSheet = computed(() => {
    if (!selectedSheetForLinking.value) return [];
    const projId = selectedSheetForLinking.value.project_id;
    return (props.allProjectCaptures || []).filter(c => c.project_id === projId);
});

function openLinkCapturesModal(sheet: DrawingSheetItem) {
    selectedSheetForLinking.value = sheet;
    selectedCaptureIds.value = (sheet.captures || []).map(c => c.id);
    showLinkCapturesModal.value = true;
}

function toggleCaptureSelection(capId: string) {
    const idx = selectedCaptureIds.value.indexOf(capId);
    if (idx > -1) {
        selectedCaptureIds.value.splice(idx, 1);
    } else {
        selectedCaptureIds.value.push(capId);
    }
}

async function saveLinkedCaptures() {
    if (!selectedSheetForLinking.value || isSavingLinkedCaptures.value) return;
    isSavingLinkedCaptures.value = true;
    try {
        const response = await fetch(`/drawings/${selectedSheetForLinking.value.id}/sync-captures`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                capture_ids: selectedCaptureIds.value,
            }),
        });

        if (response.ok) {
            showLinkCapturesModal.value = false;
            router.reload();
        }
    } catch (err) {
        console.error('Failed to link captures:', err);
    } finally {
        isSavingLinkedCaptures.value = false;
    }
}

const searchQuery = ref('');
const filterType = ref<'all' | 'plan' | 'elevation' | 'section'>('all');
const groupByProject = ref(false);

const filteredSheets = computed(() => {
    return props.sheets.data.filter((sheet) => {
        const matchTitle = sheet.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                           (sheet.project?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ?? false);
        const matchType = filterType.value === 'all' || sheet.type === filterType.value;
        return matchTitle && matchType;
    });
});

function getSheetRooms(sheet: DrawingSheetItem): string[] {
    if (!sheet.elements) return [];
    const rooms = sheet.elements
        .filter(e => e.type === 'room_label' && e.data?.text)
        .map(e => (e.data.text || '').split('\n')[0].trim())
        .filter(Boolean);
    return Array.from(new Set(rooms)).slice(0, 4);
}

const sheetsByProject = computed(() => {
    const map = new Map<string, { projectTitle: string; clientName?: string; clientAddress?: string; sheets: DrawingSheetItem[] }>();
    for (const sheet of filteredSheets.value) {
        const pId = sheet.project_id || 'unassigned';
        const pTitle = sheet.project?.title || 'Proyek Tanpa Judul';
        const cName = sheet.project?.client_name || undefined;
        const cAddress = sheet.project?.client_address || undefined;
        if (!map.has(pId)) {
            map.set(pId, { projectTitle: pTitle, clientName: cName, clientAddress: cAddress, sheets: [] });
        }
        map.get(pId)!.sheets.push(sheet);
    }
    return Array.from(map.entries()).map(([projectId, data]) => ({
        projectId,
        projectTitle: data.projectTitle,
        clientName: data.clientName,
        clientAddress: data.clientAddress,
        sheets: data.sheets,
    }));
});


const submitCreate = () => {
    if (!props.hasAccess) {
        if (confirm('Fitur Lembar Kerja 2D (Drawing CAD) eksklusif untuk paket Enterprise.\n\nLihat opsi paket sekarang?')) {
            router.visit('/plans');
        }
        return;
    }

    const createdTitle = form.title;
    form.post('/drawings', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
            toast.success(`Lembar kerja 2D "${createdTitle || 'Baru'}" berhasil dibuat!`);
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
    <Head title="Denah CAD 2D — Lembar Kerja Arsitektur" />

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
                                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Denah CAD 2D</h1>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-violet-100 dark:bg-violet-400/15 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-400/30">
                                    ENTERPRISE CAD
                                </span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Gambar teknik denah arsitektur, tampak, dan potongan 2D terintegrasi dengan ekspor AutoCAD (.DXF) &amp; vektor (.SVG).
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
            <div class="flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-3.5 bg-white dark:bg-[#12131a] p-4 rounded-2xl border border-slate-200 dark:border-white/5 shadow-sm">
                <!-- Search Input: Stretches full width on smaller/medium screens and stays comfortable on large screens -->
                <div class="relative w-full xl:max-w-md flex-1">
                    <Search class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari lembar kerja atau proyek..."
                        class="w-full h-10 pl-10 pr-9 text-sm rounded-xl bg-slate-50 dark:bg-white/[0.04] border border-slate-200 dark:border-white/10 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/50 transition-all"
                    />
                    <button
                        v-if="searchQuery"
                        @click="searchQuery = ''"
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-md transition-colors"
                        title="Hapus pencarian"
                    >
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Filters Group: Wraps naturally on small screens without ugly scrollbars -->
                <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                    <!-- Project Selector Filter -->
                    <select
                        :value="selectedProjectId || ''"
                        @change="filterByProject(($event.target as HTMLSelectElement).value)"
                        class="h-10 text-xs rounded-xl bg-white dark:bg-[#1a1b23] border border-slate-200 dark:border-white/10 px-3 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer font-medium shadow-sm transition shrink-0 dark:[color-scheme:dark] [&>option]:bg-white [&>option]:text-slate-800 dark:[&>option]:bg-[#1a1b23] dark:[&>option]:text-slate-200"
                    >
                        <option value="" class="bg-white dark:bg-[#1a1b23] text-slate-800 dark:text-slate-200 font-medium">Semua Proyek</option>
                        <option
                            v-for="p in projects"
                            :key="p.id"
                            :value="p.id"
                            class="bg-white dark:bg-[#1a1b23] text-slate-800 dark:text-slate-200 font-medium"
                        >
                            {{ p.title }}
                        </option>
                    </select>

                    <!-- Toggle Mode Grup Proyek -->
                    <button
                        @click="groupByProject = !groupByProject"
                        :class="[
                            'h-10 px-3 rounded-xl border text-xs font-semibold flex items-center gap-1.5 transition cursor-pointer shrink-0 shadow-sm',
                            groupByProject
                                ? 'bg-orange-500/15 border-orange-500/40 text-orange-600 dark:text-orange-400'
                                : 'bg-slate-50 dark:bg-white/[0.04] border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300'
                        ]"
                        title="Kelompokkan lembar kerja berdasarkan proyek"
                    >
                        <FolderKanban class="w-3.5 h-3.5" />
                        <span>{{ groupByProject ? 'Per Proyek' : 'Semua Lembar' }}</span>
                    </button>

                    <!-- Type Filter Tabs -->
                    <div class="h-10 inline-flex items-center rounded-xl p-1 bg-slate-100 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-xs shrink-0 shadow-sm">
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
                                'h-8 px-3 rounded-lg font-medium transition-all cursor-pointer flex items-center justify-center',
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

            <!-- TAMPILAN 1: KELOMPOK PER PROYEK -->
            <div v-if="filteredSheets.length > 0 && groupByProject" class="space-y-10">
                <div
                    v-for="grp in sheetsByProject"
                    :key="grp.projectId"
                    class="space-y-4"
                >
                    <!-- Project Section Header Banner -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-2xl bg-white dark:bg-[#12131a] border border-slate-200 dark:border-white/5 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-orange-500">
                                <FolderKanban class="w-5 h-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 flex-wrap">
                                    <span class="break-words leading-tight">{{ grp.projectTitle }}</span>
                                    <span class="text-xs font-mono font-normal px-2 py-0.5 rounded-full bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 shrink-0">
                                        {{ grp.sheets.length }} Lembar Kerja
                                    </span>
                                </h2>
                                <p v-if="grp.clientName" class="text-xs text-orange-500 font-medium mt-1 flex items-center gap-2 flex-wrap">
                                    <span>👤 Klien: <strong class="break-words font-semibold">{{ grp.clientName }}</strong></span>
                                    <span v-if="grp.clientAddress" class="text-slate-400 font-normal break-words text-[11px]">📍 {{ grp.clientAddress }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="`/projects/${grp.projectId}/viewer`"
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/15 text-slate-700 dark:text-slate-200 transition flex items-center gap-1.5"
                            >
                                <Box class="w-3.5 h-3.5" />
                                <span>3D Viewer</span>
                            </Link>
                            <Link
                                :href="`/projects/${grp.projectId}/booklet`"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-orange-600 hover:bg-orange-500 text-white transition flex items-center gap-1.5 shadow-sm"
                            >
                                <BookOpen class="w-3.5 h-3.5" />
                                <span>Buka Booklet PDF</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Grid Lembar Kerja di Bawah Proyek Ini -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 sm:gap-6">
                        <div
                            v-for="sheet in grp.sheets"
                            :key="sheet.id"
                            class="group relative bg-white dark:bg-[#12131a] rounded-2xl border border-slate-200 dark:border-white/5 overflow-hidden shadow-sm hover:border-violet-500/40 hover:shadow-lg transition-all flex flex-col justify-between"
                        >
                            <!-- Mini KeyPlan Canvas Preview / Snapshot Thumbnail -->
                            <div class="h-48 bg-slate-100 dark:bg-[#0a0b10] border-b border-slate-200 dark:border-white/5 flex flex-col justify-between relative overflow-hidden group/thumb">
                                <!-- Background Image Snapshot jika tersedia dan bukan vector mode -->
                                <template v-if="getSheetSnapshot(sheet) && !isVectorMode(sheet.id)">
                                    <div class="w-full h-full relative overflow-hidden bg-slate-950">
                                        <img
                                            :src="getSheetSnapshot(sheet)!"
                                            :alt="sheet.title"
                                            class="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform duration-500 ease-out"
                                            loading="lazy"
                                        />
                                        <!-- Gradient overlay -->
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-slate-950/40 pointer-events-none"></div>

                                        <!-- Badge Sumber Snapshot -->
                                        <div v-if="getSnapshotBadge(sheet)" class="absolute bottom-2.5 left-2.5 z-20">
                                            <span :class="['px-2 py-0.5 rounded-md text-[10px] font-semibold backdrop-blur-md shadow-md flex items-center gap-1', getSnapshotBadge(sheet)!.color]">
                                                <component :is="getSnapshotBadge(sheet)!.icon" class="w-3 h-3" />
                                                <span>{{ getSnapshotBadge(sheet)!.label }}</span>
                                            </span>
                                        </div>

                                        <!-- Toggle to Vector if sheet has elements -->
                                        <div v-if="sheet.elements && sheet.elements.length > 0" class="absolute bottom-2.5 right-2.5 z-20">
                                            <button
                                                type="button"
                                                @click.stop.prevent="toggleCardViewMode(sheet.id)"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/85 hover:bg-slate-900 text-slate-200 border border-white/20 backdrop-blur-md shadow transition-all cursor-pointer flex items-center gap-1"
                                                title="Lihat Gambar Denah Vektor"
                                            >
                                                <DraftingCompass class="w-3 h-3 text-violet-400" />
                                                <span>Denah 2D</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Vector KeyPlan Canvas jika mode vector atau tidak ada snapshot -->
                                <template v-else-if="sheet.elements && sheet.elements.length > 0">
                                    <div class="w-full h-full relative flex items-center justify-center p-2">
                                        <KeyPlanCanvas
                                            :elements="sheet.elements"
                                            :show-footer="false"
                                            width="100%"
                                            height="100%"
                                            className="w-full h-full border-none shadow-none bg-transparent"
                                        />

                                        <!-- Toggle back to snapshot if snapshot available -->
                                        <div v-if="getSheetSnapshot(sheet)" class="absolute bottom-2.5 right-2.5 z-20">
                                            <button
                                                type="button"
                                                @click.stop.prevent="toggleCardViewMode(sheet.id)"
                                                class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/85 hover:bg-slate-900 text-slate-200 border border-white/20 backdrop-blur-md shadow transition-all cursor-pointer flex items-center gap-1"
                                                title="Lihat Snapshot Gambar"
                                            >
                                                <Camera class="w-3 h-3 text-amber-400" />
                                                <span>Snapshot</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>

                                <!-- Fallback jika belum ada gambar & belum ada snapshot -->
                                <template v-else>
                                    <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900/40 p-4 text-center">
                                        <div class="w-10 h-10 rounded-xl bg-violet-500/10 border border-dashed border-violet-400/40 flex items-center justify-center text-violet-400 mb-1.5">
                                            <DraftingCompass class="w-5 h-5" />
                                        </div>
                                        <span class="text-xs font-medium text-slate-400">Belum ada snapshot</span>
                                        <span class="text-[10px] text-slate-500">Buka editor untuk mulai menggambar</span>
                                    </div>
                                </template>

                                <!-- Floating Top Badges -->
                                <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between pointer-events-none z-20">
                                    <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-semibold border backdrop-blur-md shadow-sm', getTypeColor(sheet.type)]">
                                        {{ getTypeLabel(sheet.type) }}
                                    </span>
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-900/90 text-white border border-white/10 backdrop-blur-md shadow-sm">
                                        {{ sheet.scale }} • {{ sheet.paper_size }}
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between space-y-3">
                                <div>
                                    <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base line-clamp-2 min-h-[2.5rem] group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors leading-snug" :title="sheet.title">
                                        {{ sheet.title }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
                                        <Calendar class="w-3.5 h-3.5" />
                                        Diperbarui {{ new Date(sheet.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                    </p>
                                </div>

                                <!-- Room Tags -->
                                <div v-if="getSheetRooms(sheet).length > 0" class="space-y-1">
                                    <span class="text-[10px] font-mono text-slate-400 uppercase tracking-wider block">Ruangan:</span>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="r in getSheetRooms(sheet)"
                                            :key="r"
                                            class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-white/10"
                                        >
                                            🏷️ {{ r }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Connected 3D Visual Thumbnails & Action -->
                                <div class="pt-2">
                                    <template v-if="sheet.captures && sheet.captures.length > 0">
                                        <div class="flex items-center justify-between text-xs mb-1.5">
                                            <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                                <Camera class="w-3.5 h-3.5 text-amber-500" />
                                                <span>Visual 3D</span>
                                                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 font-bold">
                                                    {{ sheet.captures.length }}
                                                </span>
                                            </span>
                                            <button
                                                type="button"
                                                @click="openLinkCapturesModal(sheet)"
                                                class="text-[10px] text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-semibold cursor-pointer"
                                            >
                                                + Kelola
                                            </button>
                                        </div>

                                        <!-- Thumbnail bar -->
                                        <div class="flex items-center gap-1.5 overflow-x-auto py-1 scrollbar-none">
                                            <div
                                                v-for="cap in sheet.captures.slice(0, 4)"
                                                :key="cap.id"
                                                class="relative group/thumb shrink-0 rounded-lg overflow-hidden border border-amber-500/30 bg-black/40 w-14 h-10 cursor-pointer"
                                                :title="`${cap.code ? '[' + cap.code + '] ' : ''}${cap.room_name}`"
                                                @click="openLinkCapturesModal(sheet)"
                                            >
                                                <img
                                                    :src="cap.ai_rendered_url || cap.url"
                                                    :alt="cap.room_name"
                                                    class="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform"
                                                />
                                                <span v-if="cap.code" class="absolute bottom-0 left-0 right-0 bg-black/75 text-[8px] font-mono font-bold text-amber-300 text-center truncate px-0.5">
                                                    {{ cap.code }}
                                                </span>
                                            </div>
                                            <button
                                                v-if="sheet.captures.length > 4"
                                                type="button"
                                                @click="openLinkCapturesModal(sheet)"
                                                class="w-8 h-10 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-[10px] font-bold text-slate-400 hover:text-white shrink-0 cursor-pointer"
                                            >
                                                +{{ sheet.captures.length - 4 }}
                                            </button>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <button
                                            type="button"
                                            @click="openLinkCapturesModal(sheet)"
                                            class="w-full py-2 px-3 rounded-xl border border-dashed border-slate-200 dark:border-white/10 hover:border-violet-500/40 bg-slate-50/50 dark:bg-white/[0.02] hover:bg-violet-500/5 text-slate-500 dark:text-slate-400 text-xs flex items-center justify-center gap-1.5 transition-all group/btn cursor-pointer"
                                        >
                                            <Camera class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-violet-500 transition-colors" />
                                            <span class="text-[11px] font-medium">+ Tautkan Visual 3D</span>
                                        </button>
                                    </template>
                                </div>

                                <!-- Action Bar -->
                                <div class="pt-3 border-t border-slate-100 dark:border-white/5 flex items-center justify-between gap-1.5 flex-wrap">
                                    <Link
                                        :href="`/drawings/${sheet.id}`"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white transition-colors whitespace-nowrap shrink-0 shadow-sm"
                                    >
                                        <DraftingCompass class="w-3.5 h-3.5 shrink-0" />
                                        <span>Buka Denah CAD</span>
                                    </Link>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <Link
                                            :href="`/projects/${sheet.project_id}/booklet?sheet_id=${sheet.id}`"
                                            title="Lihat Lembar Kerja & Booklet PDF"
                                            class="w-8 h-8 rounded-xl text-amber-600 dark:text-amber-400 bg-amber-500/10 border border-amber-500/20 hover:bg-amber-500/20 transition-colors flex items-center justify-center cursor-pointer"
                                        >
                                            <BookOpen class="w-3.5 h-3.5" />
                                        </Link>
                                        <Link
                                            :href="`/projects/${sheet.project_id}/viewer?sheet_id=${sheet.id}`"
                                            title="Buka Model 3D di Viewer"
                                            class="w-8 h-8 rounded-xl text-slate-500 hover:text-orange-500 dark:hover:text-orange-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors flex items-center justify-center"
                                        >
                                            <Box class="w-4 h-4" />
                                        </Link>
                                        <a
                                            :href="`/drawings/${sheet.id}/export-dxf`"
                                            title="Unduh AutoCAD DXF"
                                            class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors flex items-center justify-center"
                                        >
                                            <Download class="w-4 h-4" />
                                        </a>
                                        <button
                                            type="button"
                                            @click="openEditSheetModal(sheet)"
                                            title="Pengaturan Lembar Kerja"
                                            class="w-8 h-8 rounded-xl text-slate-400 hover:text-violet-600 dark:hover:text-violet-300 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors flex items-center justify-center cursor-pointer"
                                        >
                                            <Settings class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteSheet(sheet)"
                                            title="Hapus Lembar Kerja"
                                            class="w-8 h-8 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors flex items-center justify-center cursor-pointer"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAMPILAN 2: GRID REGULER (DENGAN BANNER PROYEK JELAS) -->
            <div v-else-if="filteredSheets.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 sm:gap-6">
                <div
                    v-for="sheet in filteredSheets"
                    :key="sheet.id"
                    class="group relative bg-white dark:bg-[#12131a] rounded-2xl border border-slate-200 dark:border-white/5 overflow-hidden shadow-sm hover:border-violet-500/40 hover:shadow-lg transition-all flex flex-col justify-between"
                >
                    <!-- Header Banner: Nama Proyek & Klien -->
                    <div class="px-3.5 py-2.5 bg-slate-900 border-b border-slate-800 text-white flex flex-col gap-1 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
                                <span class="font-bold text-slate-100 truncate text-xs leading-tight" :title="sheet.project?.title">
                                    {{ sheet.project?.title || 'Proyek Tanpa Judul' }}
                                </span>
                            </div>
                            <span v-if="sheet.project?.client_name" class="text-[10px] text-orange-400 font-mono font-medium shrink-0 ml-auto flex items-center gap-1 bg-orange-500/10 px-2 py-0.5 rounded border border-orange-500/20 max-w-[130px] truncate" :title="sheet.project.client_name">
                                👤 <span class="truncate">{{ sheet.project.client_name }}</span>
                            </span>
                        </div>
                        <p v-if="sheet.project?.client_address" class="text-[10px] text-slate-400 pl-4 truncate">
                            📍 {{ sheet.project.client_address }}
                        </p>
                    </div>

                    <!-- Preview Canvas Area (Live KeyPlan Canvas & Snapshot Thumbnail) -->
                    <div class="h-48 bg-slate-100 dark:bg-[#0a0b10] border-b border-slate-200 dark:border-white/5 flex flex-col justify-between relative overflow-hidden group/thumb">
                        <!-- Background Image Snapshot jika tersedia dan bukan vector mode -->
                        <template v-if="getSheetSnapshot(sheet) && !isVectorMode(sheet.id)">
                            <div class="w-full h-full relative overflow-hidden bg-slate-950">
                                <img
                                    :src="getSheetSnapshot(sheet)!"
                                    :alt="sheet.title"
                                    class="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform duration-500 ease-out"
                                    loading="lazy"
                                />
                                <!-- Gradient overlay -->
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-slate-950/40 pointer-events-none"></div>

                                <!-- Badge Sumber Snapshot -->
                                <div v-if="getSnapshotBadge(sheet)" class="absolute bottom-2.5 left-2.5 z-20">
                                    <span :class="['px-2 py-0.5 rounded-md text-[10px] font-semibold backdrop-blur-md shadow-md flex items-center gap-1', getSnapshotBadge(sheet)!.color]">
                                        <component :is="getSnapshotBadge(sheet)!.icon" class="w-3 h-3" />
                                        <span>{{ getSnapshotBadge(sheet)!.label }}</span>
                                    </span>
                                </div>

                                <!-- Toggle to Vector if sheet has elements -->
                                <div v-if="sheet.elements && sheet.elements.length > 0" class="absolute bottom-2.5 right-2.5 z-20">
                                    <button
                                        type="button"
                                        @click.stop.prevent="toggleCardViewMode(sheet.id)"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/85 hover:bg-slate-900 text-slate-200 border border-white/20 backdrop-blur-md shadow transition-all cursor-pointer flex items-center gap-1"
                                        title="Lihat Gambar Denah Vektor"
                                    >
                                        <DraftingCompass class="w-3 h-3 text-violet-400" />
                                        <span>Denah 2D</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Vector KeyPlan Canvas jika mode vector atau tidak ada snapshot -->
                        <template v-else-if="sheet.elements && sheet.elements.length > 0">
                            <div class="w-full h-full relative flex items-center justify-center p-2">
                                <KeyPlanCanvas
                                    :elements="sheet.elements"
                                    :show-footer="false"
                                    width="100%"
                                    height="100%"
                                    className="w-full h-full border-none shadow-none bg-transparent"
                                />

                                <!-- Toggle back to snapshot if snapshot available -->
                                <div v-if="getSheetSnapshot(sheet)" class="absolute bottom-2.5 right-2.5 z-20">
                                    <button
                                        type="button"
                                        @click.stop.prevent="toggleCardViewMode(sheet.id)"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/85 hover:bg-slate-900 text-slate-200 border border-white/20 backdrop-blur-md shadow transition-all cursor-pointer flex items-center gap-1"
                                        title="Lihat Snapshot Gambar"
                                    >
                                        <Camera class="w-3 h-3 text-amber-400" />
                                        <span>Snapshot</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Fallback jika belum ada gambar & belum ada snapshot -->
                        <template v-else>
                            <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900/40 p-4 text-center">
                                <div class="w-10 h-10 rounded-xl bg-violet-500/10 border border-dashed border-violet-400/40 flex items-center justify-center text-violet-400 mb-1.5">
                                    <DraftingCompass class="w-5 h-5" />
                                </div>
                                <span class="text-xs font-medium text-slate-400">Belum ada snapshot</span>
                                <span class="text-[10px] text-slate-500">Buka editor untuk mulai menggambar</span>
                            </div>
                        </template>

                        <!-- Floating Top Badges -->
                        <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between pointer-events-none z-20">
                            <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-semibold border backdrop-blur-md shadow-sm', getTypeColor(sheet.type)]">
                                {{ getTypeLabel(sheet.type) }}
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-900/90 text-white border border-white/10 backdrop-blur-md shadow-sm">
                                {{ sheet.scale }} • {{ sheet.paper_size }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4 sm:p-5 flex-1 flex flex-col justify-between space-y-3">
                        <div>
                            <h3 class="font-bold text-slate-900 dark:text-white text-sm sm:text-base line-clamp-2 min-h-[2.5rem] group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors leading-snug" :title="sheet.title">
                                {{ sheet.title }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1.5">
                                <Calendar class="w-3.5 h-3.5" />
                                Diperbarui {{ new Date(sheet.updated_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                            </p>
                        </div>

                        <!-- Room Badges Included in Sheet -->
                        <div v-if="getSheetRooms(sheet).length > 0" class="space-y-1">
                            <span class="text-[10px] font-mono text-slate-400 uppercase tracking-wider block">Ruangan:</span>
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="r in getSheetRooms(sheet)"
                                    :key="r"
                                    class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-white/10"
                                >
                                    🏷️ {{ r }}
                                </span>
                            </div>
                        </div>

                        <!-- Connected 3D Visual Thumbnails & Action -->
                        <div class="pt-2">
                            <template v-if="sheet.captures && sheet.captures.length > 0">
                                <div class="flex items-center justify-between text-xs mb-1.5">
                                    <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                        <Camera class="w-3.5 h-3.5 text-amber-500" />
                                        <span>Visual 3D</span>
                                        <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 font-bold">
                                            {{ sheet.captures.length }}
                                        </span>
                                    </span>
                                    <button
                                        type="button"
                                        @click="openLinkCapturesModal(sheet)"
                                        class="text-[10px] text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 font-semibold cursor-pointer"
                                    >
                                        + Kelola
                                    </button>
                                </div>

                                <!-- Thumbnail bar -->
                                <div class="flex items-center gap-1.5 overflow-x-auto py-1 scrollbar-none">
                                    <div
                                        v-for="cap in sheet.captures.slice(0, 4)"
                                        :key="cap.id"
                                        class="relative group/thumb shrink-0 rounded-lg overflow-hidden border border-amber-500/30 bg-black/40 w-14 h-10 cursor-pointer"
                                        :title="`${cap.code ? '[' + cap.code + '] ' : ''}${cap.room_name}`"
                                        @click="openLinkCapturesModal(sheet)"
                                    >
                                        <img
                                            :src="cap.ai_rendered_url || cap.url"
                                            :alt="cap.room_name"
                                            class="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform"
                                        />
                                        <span v-if="cap.code" class="absolute bottom-0 left-0 right-0 bg-black/75 text-[8px] font-mono font-bold text-amber-300 text-center truncate px-0.5">
                                            {{ cap.code }}
                                        </span>
                                    </div>
                                    <button
                                        v-if="sheet.captures.length > 4"
                                        type="button"
                                        @click="openLinkCapturesModal(sheet)"
                                        class="w-8 h-10 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-[10px] font-bold text-slate-400 hover:text-white shrink-0 cursor-pointer"
                                    >
                                        +{{ sheet.captures.length - 4 }}
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <button
                                    type="button"
                                    @click="openLinkCapturesModal(sheet)"
                                    class="w-full py-2 px-3 rounded-xl border border-dashed border-slate-200 dark:border-white/10 hover:border-violet-500/40 bg-slate-50/50 dark:bg-white/[0.02] hover:bg-violet-500/5 text-slate-500 dark:text-slate-400 text-xs flex items-center justify-center gap-1.5 transition-all group/btn cursor-pointer"
                                >
                                    <Camera class="w-3.5 h-3.5 text-slate-400 group-hover/btn:text-violet-500 transition-colors" />
                                    <span class="text-[11px] font-medium">+ Tautkan Visual 3D</span>
                                </button>
                            </template>
                        </div>

                        <!-- Action Bar -->
                        <div class="pt-3 border-t border-slate-100 dark:border-white/5 flex items-center justify-between gap-1.5 flex-wrap">
                            <Link
                                :href="`/drawings/${sheet.id}`"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white transition-colors whitespace-nowrap shrink-0 shadow-sm"
                            >
                                <DraftingCompass class="w-3.5 h-3.5 shrink-0" />
                                <span>Buka Denah CAD</span>
                            </Link>

                            <div class="flex items-center gap-1 shrink-0">
                                <Link
                                    :href="`/projects/${sheet.project_id}/booklet?sheet_id=${sheet.id}`"
                                    title="Lihat Lembar Kerja & Booklet PDF"
                                    class="w-8 h-8 rounded-xl text-amber-600 dark:text-amber-400 bg-amber-500/10 border border-amber-500/20 hover:bg-amber-500/20 transition-colors flex items-center justify-center cursor-pointer"
                                >
                                    <BookOpen class="w-3.5 h-3.5" />
                                </Link>
                                <Link
                                    :href="`/projects/${sheet.project_id}/viewer?sheet_id=${sheet.id}`"
                                    title="Buka Model 3D di Viewer"
                                    class="w-8 h-8 rounded-xl text-slate-500 hover:text-orange-500 dark:hover:text-orange-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors flex items-center justify-center"
                                >
                                    <Box class="w-4 h-4" />
                                </Link>
                                <a
                                    :href="`/drawings/${sheet.id}/export-dxf`"
                                    title="Unduh AutoCAD DXF"
                                    class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors flex items-center justify-center"
                                >
                                    <Download class="w-4 h-4" />
                                </a>
                                <button
                                    type="button"
                                    @click="openEditSheetModal(sheet)"
                                    title="Pengaturan Lembar Kerja"
                                    class="w-8 h-8 rounded-xl text-slate-400 hover:text-violet-600 dark:hover:text-violet-300 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors flex items-center justify-center cursor-pointer"
                                >
                                    <Settings class="w-4 h-4" />
                                </button>
                                <button
                                    type="button"
                                    @click="deleteSheet(sheet)"
                                    title="Hapus Lembar Kerja"
                                    class="w-8 h-8 rounded-xl text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors flex items-center justify-center cursor-pointer"
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
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white font-medium"
                        >
                            <option v-for="p in projects" :key="p.id" :value="p.id" class="bg-slate-900 text-white">
                                {{ p.title }}
                            </option>
                        </select>
                        <p v-if="form.errors.project_id" class="text-xs text-red-500 mt-1">{{ form.errors.project_id }}</p>
                    </div>

                    <!-- Client Information (Name & Address) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Nama Klien
                            </label>
                            <input
                                v-model="form.client_name"
                                type="text"
                                placeholder="Contoh: Bpk. Hendra, Mr. Jerry"
                                class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Alamat Proyek / Klien
                            </label>
                            <input
                                v-model="form.client_address"
                                type="text"
                                placeholder="Contoh: Jl. Sentot Prawirodirdjo, Jember"
                                class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            />
                        </div>
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
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white font-mono"
                            >
                                <option value="1:50" class="bg-slate-900 text-white">1:50 (Detail)</option>
                                <option value="1:100" class="bg-slate-900 text-white">1:100 (Standar)</option>
                                <option value="1:200" class="bg-slate-900 text-white">1:200 (Site Plan)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Ukuran Kertas
                            </label>
                            <select
                                v-model="form.paper_size"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white font-mono"
                            >
                                <option value="A1" class="bg-slate-900 text-white">A1 (841 x 594 mm)</option>
                                <option value="A2" class="bg-slate-900 text-white">A2 (594 x 420 mm)</option>
                                <option value="A3" class="bg-slate-900 text-white">A3 (420 x 297 mm)</option>
                                <option value="A4" class="bg-slate-900 text-white">A4 (297 x 210 mm)</option>
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
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white font-mono"
                            >
                                <option value="landscape" class="bg-slate-900 text-white">Landscape</option>
                                <option value="portrait" class="bg-slate-900 text-white">Portrait</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Satuan Ukuran
                            </label>
                            <select
                                v-model="form.unit"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-amber-400 font-bold focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="m" class="bg-slate-900 text-white">Meter (m)</option>
                                <option value="cm" class="bg-slate-900 text-white">Sentimeter (cm)</option>
                                <option value="mm" class="bg-slate-900 text-white">Milimeter (mm)</option>
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

        <!-- MODAL EDIT PENGATURAN LEMBAR KERJA -->
        <div
            v-if="showEditModal && editingSheet"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        >
            <div class="w-full max-w-lg bg-white dark:bg-[#12131a] rounded-3xl border border-slate-200 dark:border-white/10 p-6 shadow-2xl">
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-white/10">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Settings class="w-4 h-4 text-violet-500" />
                            Pengaturan Lembar Kerja CAD
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Proyek: {{ editingSheet.project?.title || 'Proyek' }}
                        </p>
                    </div>
                    <button
                        @click="showEditModal = false; editingSheet = null"
                        class="p-1 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditSheet" class="mt-4 space-y-4">
                    <!-- Project Selection in Edit Modal -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Pilih Proyek
                        </label>
                        <select
                            v-model="editForm.project_id"
                            required
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white font-medium"
                        >
                            <option v-for="p in projects" :key="p.id" :value="p.id" class="bg-slate-900 text-white">
                                {{ p.title }}
                            </option>
                        </select>
                        <p v-if="editForm.errors.project_id" class="text-xs text-red-500 mt-1">{{ editForm.errors.project_id }}</p>
                    </div>

                    <!-- Client Information (Name & Address) in Edit Modal -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/5">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Nama Klien
                            </label>
                            <input
                                v-model="editForm.client_name"
                                type="text"
                                placeholder="Contoh: Bpk. Hendra, Mr. Jerry"
                                class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Alamat Proyek / Klien
                            </label>
                            <input
                                v-model="editForm.client_address"
                                type="text"
                                placeholder="Contoh: Jl. Sentot Prawirodirdjo, Jember"
                                class="w-full px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Judul Lembar Kerja
                        </label>
                        <input
                            v-model="editForm.title"
                            type="text"
                            required
                            placeholder="Contoh: DENAH LANTAI 1"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-white/[0.05] border border-slate-200 dark:border-white/10 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Kategori Gambar
                            </label>
                            <select
                                v-model="editForm.type"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="plan" class="bg-slate-900 text-white">Denah (Floor Plan)</option>
                                <option value="elevation" class="bg-slate-900 text-white">Tampak (Elevation)</option>
                                <option value="section" class="bg-slate-900 text-white">Potongan (Section)</option>
                            </select>
                            <span class="text-[10px] text-slate-400 mt-1 block">Denah otomatis menjadi acuan Booklet</span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Satuan Ukuran (Unit)
                            </label>
                            <select
                                v-model="editForm.unit"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-amber-500/30 text-sm text-amber-400 font-bold focus:outline-none focus:ring-2 focus:ring-violet-500/40 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="m" class="bg-slate-900 text-white">Meter (m) - Standar Arsitek</option>
                                <option value="cm" class="bg-slate-900 text-white">Sentimeter (cm) - Standar Sipil</option>
                                <option value="mm" class="bg-slate-900 text-white">Milimeter (mm) - Detail Teknis</option>
                            </select>
                            <span class="text-[10px] text-amber-500/80 mt-1 block">Satuan penggaris pengukur</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Skala Gambar
                            </label>
                            <select
                                v-model="editForm.scale"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 font-mono cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="1:20" class="bg-slate-900 text-white">1:20 (Detail Khusus)</option>
                                <option value="1:50" class="bg-slate-900 text-white">1:50 (Detail Ruang)</option>
                                <option value="1:100" class="bg-slate-900 text-white">1:100 (Standar Denah)</option>
                                <option value="1:200" class="bg-slate-900 text-white">1:200 (Site Plan)</option>
                                <option value="1:500" class="bg-slate-900 text-white">1:500 (Master Plan)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                                Ukuran Kertas
                            </label>
                            <select
                                v-model="editForm.paper_size"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-900 border border-slate-700 dark:border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40 font-mono cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="A4" class="bg-slate-900 text-white">A4 (297 × 210 mm)</option>
                                <option value="A3" class="bg-slate-900 text-white">A3 (420 × 297 mm)</option>
                                <option value="A2" class="bg-slate-900 text-white">A2 (594 × 420 mm)</option>
                                <option value="A1" class="bg-slate-900 text-white">A1 (841 × 594 mm)</option>
                                <option value="A0" class="bg-slate-900 text-white">A0 (1189 × 841 mm)</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-white/10 flex items-center justify-between">
                        <span class="text-xs text-slate-400">Data otomatis tersimpan ke server</span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showEditModal = false; editingSheet = null"
                                class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-50 transition-all cursor-pointer shadow-md shadow-violet-600/20"
                            >
                                <span v-if="editForm.processing">Menyimpan...</span>
                                <span v-else>Simpan Perubahan</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL HUBUNGKAN GAMBAR 3D KE LEMBAR KERJA -->
        <div
            v-if="showLinkCapturesModal && selectedSheetForLinking"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
        >
            <div class="w-full max-w-2xl bg-white dark:bg-[#12131a] rounded-3xl border border-slate-200 dark:border-white/10 p-6 shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-white/10 shrink-0">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Camera class="w-4 h-4 text-amber-500" />
                            Hubungkan Visual 3D ke Lembar Kerja
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Lembar: <strong class="text-violet-600 dark:text-violet-400">{{ selectedSheetForLinking.title }}</strong> ({{ selectedSheetForLinking.project?.title }})
                        </p>
                    </div>
                    <button
                        @click="showLinkCapturesModal = false; selectedSheetForLinking = null"
                        class="p-1 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="py-3 text-xs text-slate-500 dark:text-slate-400 shrink-0">
                    Pilih gambar hasil ambil 3D yang mewakili atau berkaitan dengan lembar kerja ini. Gambar yang dipilih akan otomatis terkoneksi ke denah dan booklet PDF.
                </div>

                <!-- Daftar Galeri Gambar 3D Proyek -->
                <div class="flex-1 overflow-y-auto space-y-3 pr-1 py-1">
                    <div
                        v-if="availableCapturesForSelectedSheet.length === 0"
                        class="p-8 text-center text-xs text-slate-400 bg-slate-50 dark:bg-white/[0.02] rounded-2xl border border-dashed border-slate-200 dark:border-white/10"
                    >
                        Belum ada gambar yang diambil dari 3D Viewer untuk proyek ini.
                    </div>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div
                            v-for="cap in availableCapturesForSelectedSheet"
                            :key="cap.id"
                            @click="toggleCaptureSelection(cap.id)"
                            :class="[
                                'relative rounded-2xl border p-2 cursor-pointer transition-all flex flex-col justify-between overflow-hidden group',
                                selectedCaptureIds.includes(cap.id)
                                    ? 'bg-amber-500/10 border-amber-500/50 shadow-md ring-2 ring-amber-500/30'
                                    : 'bg-slate-50 dark:bg-white/[0.03] border-slate-200 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/20'
                            ]"
                        >
                            <!-- Thumbnail Gambar -->
                            <div class="aspect-video w-full rounded-xl overflow-hidden bg-black/40 relative mb-2">
                                <img
                                    :src="cap.ai_rendered_url || cap.url"
                                    :alt="cap.room_name"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                />
                                <span
                                    v-if="selectedCaptureIds.includes(cap.id)"
                                    class="absolute top-1.5 right-1.5 w-5 h-5 rounded-full bg-amber-500 text-black flex items-center justify-center font-bold text-xs shadow-md"
                                >
                                    ✓
                                </span>
                                <span v-if="cap.code" class="absolute bottom-1.5 left-1.5 px-1.5 py-0.5 rounded bg-black/80 font-mono text-[9px] font-bold text-amber-300">
                                    {{ cap.code }}
                                </span>
                            </div>

                            <div>
                                <div class="font-semibold text-xs text-slate-800 dark:text-white truncate" :title="cap.room_name">
                                    {{ cap.room_name }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5 flex items-center justify-between">
                                    <span>{{ cap.captured_hour || '12:00' }}</span>
                                    <span v-if="cap.drawing_sheet_id && cap.drawing_sheet_id !== selectedSheetForLinking.id" class="text-orange-400 text-[9px]">
                                        (Pindah Tautan)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="pt-4 border-t border-slate-200 dark:border-white/10 flex items-center justify-between shrink-0">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ selectedCaptureIds.length }} gambar 3D dipilih
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showLinkCapturesModal = false; selectedSheetForLinking = null"
                            class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="saveLinkedCaptures"
                            :disabled="isSavingLinkedCaptures"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-500 disabled:opacity-50 transition-all cursor-pointer shadow-md shadow-amber-600/20 flex items-center gap-1.5"
                        >
                            <Loader2 v-if="isSavingLinkedCaptures" class="w-3.5 h-3.5 animate-spin" />
                            <span>{{ isSavingLinkedCaptures ? 'Menyimpan...' : 'Simpan Koneksi 3D' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

