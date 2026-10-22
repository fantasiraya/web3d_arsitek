<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import {
    Layers,
    Plus,
    FileText,
    Download,
    Trash2,
    Sliders,
    Search,
    ExternalLink,
    AlertCircle,
    CheckCircle2,
    Calendar,
    FolderKanban,
    Sparkles,
    Lock,
    Camera,
    Box,
    LayoutGrid,
    Settings,
    X,
    Loader2,
    Package,
    Scissors,
    ArrowRight,
    Building2,
    Ruler,
    Wand2,
    Upload,
    RotateCcw,
} from '@lucide/vue';
import ShopDrawingThumbnail from '@/components/drawing/ShopDrawingThumbnail.vue';

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

// Modal Buat Gambar Kerja Baru
const showCreateModal = ref(false);

const form = useForm({
    project_id: props.selectedProjectId || (props.projects[0]?.id ?? ''),
    title: '',
    scale: '1:20',
    paper_size: 'A3',
    orientation: 'landscape',
    client_name: '',
    client_address: '',
    furniture_template: 'blank',
});

// Auto-fill nama dan alamat klien saat proyek 3D dipilih
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
    type: 'shop_drawing',
    scale: '1:20',
    unit: 'mm',
    paper_size: 'A3',
    client_name: '',
    client_address: '',
});

function openEditSheetModal(sheet: DrawingSheetItem) {
    editingSheet.value = sheet;
    editForm.project_id = sheet.project_id;
    editForm.title = sheet.title;
    editForm.type = sheet.type || 'shop_drawing';
    editForm.scale = sheet.scale || '1:20';
    editForm.unit = sheet.unit || 'mm';
    editForm.paper_size = sheet.paper_size || 'A3';
    editForm.client_name = sheet.project?.client_name || '';
    editForm.client_address = sheet.project?.client_address || '';
    showEditModal.value = true;
}

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
            toast.success('Pengaturan Gambar Kerja berhasil diperbarui.');
        },
    });
}

// Modal Hubungkan Visual 3D
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
            toast.success('Visual 3D berhasil dihubungkan ke gambar kerja.');
            router.reload();
        }
    } catch (err) {
        console.error('Failed to link captures:', err);
        toast.error('Gagal menghubungkan visual 3D.');
    } finally {
        isSavingLinkedCaptures.value = false;
    }
}

const searchQuery = ref('');
const selectedProjectFilter = ref<string>(props.selectedProjectId || 'all');

const filteredSheets = computed(() => {
    return props.sheets.data.filter((sheet) => {
        const matchTitle = sheet.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                           (sheet.project?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ?? false) ||
                           (sheet.project?.client_name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ?? false);
        const matchProj = selectedProjectFilter.value === 'all' || sheet.project_id === selectedProjectFilter.value;
        return matchTitle && matchProj;
    });
});

const submitCreate = () => {
    if (!props.hasAccess) {
        if (confirm('Fitur Gambar Kerja & Potong eksklusif untuk paket Enterprise.\n\nLihat opsi paket sekarang?')) {
            router.visit('/plans');
        }
        return;
    }

    const createdTitle = form.title;
    form.post('/shop-drawings', {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
            toast.success(`Gambar Kerja "${createdTitle || 'Baru'}" berhasil dibuat!`);
        },
    });
};

const deleteSheet = (sheet: DrawingSheetItem) => {
    if (confirm(`Apakah Anda yakin ingin menghapus gambar kerja "${sheet.title}"? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(`/shop-drawings/${sheet.id}`, {
            onSuccess: () => {
                toast.success(`Gambar kerja "${sheet.title}" berhasil dihapus.`);
            },
        });
    }
};

const filterByProject = (projectId: string) => {
    selectedProjectFilter.value = projectId;
    router.get('/shop-drawings', projectId !== 'all' ? { project_id: projectId } : {}, {
        preserveState: true,
        replace: true,
    });
};

// Mode tampilan kartu preview: 'snapshot' atau 'vector'
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
    // Hanya snapshot kanvas gambar kerja yang tersimpan langsung pada lembar kerja (bukan visual 3D project)
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
        return { label: 'Snapshot Gambar Kerja', icon: Camera, color: 'bg-amber-500 text-slate-950 font-bold shadow-amber-500/30' };
    }
    return null;
}

const uploadingSheetId = ref<string | null>(null);
const fileInputRefs = ref<Record<string, HTMLInputElement | null>>({});

function triggerFileInput(sheetId: string) {
    fileInputRefs.value[sheetId]?.click();
}

async function handleFileUpload(sheet: DrawingSheetItem, event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;
    const file = input.files[0];

    uploadingSheetId.value = sheet.id;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(`/shop-drawings/${sheet.id}/image`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
            body: formData,
        });

        if (res.ok) {
            const data = await res.json();
            if (data.sheet) {
                sheet.image_path = data.sheet.image_path;
                sheet.image_url = data.sheet.image_url;
            }
            cardViewModes.value[sheet.id] = 'snapshot';
            toast.success(`Snapshot gambar "${sheet.title}" berhasil diunggah!`);
        } else {
            toast.error('Gagal mengunggah snapshot gambar.');
        }
    } catch (err) {
        console.error('Error uploading snapshot:', err);
        toast.error('Gagal mengunggah snapshot.');
    } finally {
        uploadingSheetId.value = null;
        input.value = '';
    }
}

async function deleteSnapshotImage(sheet: DrawingSheetItem) {
    if (!confirm(`Hapus snapshot gambar untuk "${sheet.title}" dan kembali ke tampilan blueprint?`)) return;

    try {
        const res = await fetch(`/shop-drawings/${sheet.id}/image`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        });

        if (res.ok) {
            sheet.image_path = null;
            sheet.image_url = null;
            cardViewModes.value[sheet.id] = 'vector';
            toast.success('Snapshot gambar dihapus. Menampilkan blueprint elevasi vektor.');
        } else {
            toast.error('Gagal menghapus snapshot.');
        }
    } catch (err) {
        console.error('Error deleting snapshot:', err);
        toast.error('Gagal menghapus snapshot.');
    }
}
</script>

<template>
    <Head title="Gambar Kerja &amp; Daftar Potong — Shop Drawing" />

    <div class="space-y-6 max-w-7xl mx-auto pb-12">
            <!-- ── TOP HEADER & ACTIONS ──────────────────────────────────────── -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-white/10">
                <div class="space-y-1">
                    <div class="flex items-center gap-2.5">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/15 dark:bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-600 dark:text-amber-400 shadow-md shadow-amber-500/10">
                            <Layers class="w-5 h-5 stroke-[2.2]" />
                        </div>
                        <div>
                            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                                Gambar Kerja &amp; Daftar Potong
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-semibold bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-500/30">
                                    SHOP DRAWING
                                </span>
                            </h1>
                            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                                Shop drawing detail fabrikasi furnitur interior &amp; estimasi cutting list lembaran potong otomatis terhubung ke Proyek 3D.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- CTA Button -->
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 shadow-lg shadow-amber-500/20 transition-all cursor-pointer transform active:scale-95"
                    >
                        <Plus class="w-4 h-4 stroke-[2.5]" />
                        <span>Buat Gambar Kerja Baru</span>
                    </button>
                </div>
            </div>

            <!-- ── STATS CARDS ────────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <div class="bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 rounded-2xl p-4 flex items-center gap-3.5 shadow-sm backdrop-blur-sm">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                        <Layers class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Total Gambar Kerja</div>
                        <div class="text-lg font-bold text-slate-900 dark:text-white font-mono">{{ props.sheets.total || 0 }} <span class="text-xs text-slate-400 dark:text-slate-500 font-normal">lembar</span></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 rounded-2xl p-4 flex items-center gap-3.5 shadow-sm backdrop-blur-sm">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0">
                        <Box class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Terkoneksi Proyek 3D</div>
                        <div class="text-lg font-bold text-sky-600 dark:text-sky-300 font-mono">{{ props.projects.length }} <span class="text-xs text-slate-400 dark:text-slate-500 font-normal">proyek aktif</span></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-white/10 rounded-2xl p-4 flex items-center gap-3.5 shadow-sm backdrop-blur-sm">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <Scissors class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Daftar Potong Otomatis</div>
                        <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1 mt-0.5">
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            <span>P × L × T &amp; Edging Aktif</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── FILTER & SEARCH BAR ────────────────────────────────────────── -->
            <div class="bg-white dark:bg-slate-900/80 border border-slate-200 dark:border-white/10 rounded-2xl p-3 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-sm">
                <div class="flex items-center gap-2 w-full sm:w-auto flex-1">
                    <div class="relative flex-1 max-w-md">
                        <Search class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari judul gambar kerja, proyek 3D, atau klien..."
                            class="w-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 font-medium"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                    <label class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-1.5">
                        <Box class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" />
                        <span>Filter Proyek 3D:</span>
                    </label>
                    <select
                        :value="selectedProjectFilter"
                        @change="filterByProject(($event.target as HTMLSelectElement).value)"
                        class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-1.5 text-xs text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer max-w-xs truncate font-medium [&>option]:bg-white [&>option]:text-slate-800 dark:[&>option]:bg-slate-950 dark:[&>option]:text-white shadow-sm"
                    >
                        <option value="all">Semua Proyek 3D ({{ props.projects.length }})</option>
                        <option v-for="p in props.projects" :key="p.id" :value="p.id">
                            {{ p.title }} {{ p.client_name ? `• ${p.client_name}` : '' }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- ── EMPTY STATE ────────────────────────────────────────────────── -->
            <div v-if="filteredSheets.length === 0" class="bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-white/10 rounded-3xl p-12 text-center space-y-4 shadow-sm">
                <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center mx-auto shadow-inner">
                    <Layers class="w-8 h-8" />
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Belum Ada Gambar Kerja</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                        Mulai rancang gambar kerja interior &amp; furnitur fabrikasi untuk proyek 3D Anda. Data tampak, ukuran teknis, dan lembaran daftar potong terhitung otomatis.
                    </p>
                </div>
                <div>
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition-all cursor-pointer"
                    >
                        <Plus class="w-4 h-4 stroke-[2.5]" />
                        <span>Buat Gambar Kerja Pertama</span>
                    </button>
                </div>
            </div>

            <!-- ── GRID OF SHOP DRAWING CARDS ─────────────────────────────────── -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="sheet in filteredSheets"
                    :key="sheet.id"
                    class="group bg-white dark:bg-slate-900/70 hover:bg-slate-50/80 dark:hover:bg-slate-900 border border-slate-200 dark:border-white/10 hover:border-amber-500/40 dark:hover:border-amber-500/40 rounded-2xl flex flex-col justify-between transition-all duration-200 shadow-sm hover:shadow-xl hover:shadow-amber-500/5 relative overflow-hidden"
                >
                    <!-- Top Ribbon Indicator -->
                    <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 opacity-60 group-hover:opacity-100 transition-opacity z-20"></div>

                    <!-- Snapshot Thumbnail Preview Area -->
                    <div class="h-48 bg-slate-100 dark:bg-slate-950 border-b border-slate-200 dark:border-white/10 relative overflow-hidden group/thumb">
                        <!-- Mode Snapshot Gambar jika tersedia dan bukan mode vector -->
                        <template v-if="getSheetSnapshot(sheet) && !isVectorMode(sheet.id)">
                            <div class="w-full h-full relative overflow-hidden bg-slate-100 dark:bg-slate-950">
                                <img
                                    :src="getSheetSnapshot(sheet)!"
                                    :alt="sheet.title"
                                    class="w-full h-full object-cover group-hover/thumb:scale-105 transition-transform duration-500 ease-out"
                                    loading="lazy"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-transparent to-slate-950/30 pointer-events-none"></div>

                                <!-- Snapshot Source Badge (Bottom Left) -->
                                <div v-if="getSnapshotBadge(sheet)" class="absolute bottom-2.5 left-2.5 z-20">
                                    <span :class="['px-2 py-0.5 rounded-md text-[10px] font-semibold backdrop-blur-md shadow-md flex items-center gap-1', getSnapshotBadge(sheet)!.color]">
                                        <component :is="getSnapshotBadge(sheet)!.icon" class="w-3 h-3" />
                                        <span>{{ getSnapshotBadge(sheet)!.label }}</span>
                                    </span>
                                </div>

                                <!-- Toggle to Blueprint Vector Button (Bottom Right) -->
                                <div class="absolute bottom-2.5 right-2.5 z-20">
                                    <button
                                        type="button"
                                        @click.stop.prevent="toggleCardViewMode(sheet.id)"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white/90 hover:bg-white dark:bg-slate-900/85 dark:hover:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-white/20 backdrop-blur-md shadow transition-all cursor-pointer flex items-center gap-1"
                                        title="Lihat Blueprint Detail Elevasi Vektor"
                                    >
                                        <Layers class="w-3 h-3 text-amber-500 dark:text-amber-400" />
                                        <span>Blueprint Vektor</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Mode Blueprint Vektor (Jika tidak ada snapshot atau mode vector aktif) -->
                        <template v-else>
                            <div class="w-full h-full relative group/vector overflow-hidden">
                                <ShopDrawingThumbnail :sheet="sheet" />

                                <!-- Badge Blueprint Detail -->
                                <div class="absolute bottom-2.5 left-2.5 z-20 pointer-events-none">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-900/90 text-amber-300 border border-amber-500/30 backdrop-blur-md shadow-md flex items-center gap-1">
                                        <Layers class="w-3 h-3 text-amber-400" />
                                        <span>Elevasi Detail CAD</span>
                                    </span>
                                </div>

                                <!-- Toggle back to Snapshot jika file snapshot tersedia -->
                                <div v-if="getSheetSnapshot(sheet)" class="absolute bottom-2.5 right-2.5 z-20">
                                    <button
                                        type="button"
                                        @click.stop.prevent="toggleCardViewMode(sheet.id)"
                                        class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white/90 hover:bg-white dark:bg-slate-900/85 dark:hover:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-white/20 backdrop-blur-md shadow transition-all cursor-pointer flex items-center gap-1"
                                        title="Lihat Snapshot Foto / Render 3D"
                                    >
                                        <Camera class="w-3 h-3 text-sky-500 dark:text-sky-400" />
                                        <span>Snapshot Foto</span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Floating Top Badges -->
                        <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between pointer-events-none z-20">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30 backdrop-blur-md shadow-sm">
                                SHOP DRAWING
                            </span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-white/90 dark:bg-slate-900/90 text-slate-700 dark:text-white border border-slate-200 dark:border-white/10 backdrop-blur-md shadow-sm">
                                {{ sheet.scale || '1:20' }} • {{ sheet.paper_size || 'A3' }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 flex-1 flex flex-col justify-between space-y-3">
                        <div class="space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <!-- Connected 3D Project Badge -->
                                <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 text-sky-700 dark:text-sky-300 text-[11px] font-semibold max-w-[70%] truncate" :title="`Terkoneksi ke Proyek 3D: ${sheet.project?.title || 'Proyek 3D'}`">
                                    <Box class="w-3.5 h-3.5 shrink-0 text-sky-500 dark:text-sky-400" />
                                    <span class="truncate">{{ sheet.project?.title || 'Proyek 3D' }}</span>
                                </div>

                                <!-- Options Dropdown / Delete & Settings & Quick Snapshot Upload -->
                                <div class="flex items-center gap-1 shrink-0">
                                    <!-- Hidden File Input for Snapshot Upload -->
                                    <input
                                        type="file"
                                        :ref="(el) => { if (el) fileInputRefs[sheet.id] = el as HTMLInputElement; }"
                                        accept="image/*"
                                        class="hidden"
                                        @change="handleFileUpload(sheet, $event)"
                                    />

                                    <!-- Quick Upload Snapshot Button -->
                                    <button
                                        type="button"
                                        @click="triggerFileInput(sheet.id)"
                                        :disabled="uploadingSheetId === sheet.id"
                                        class="p-1 rounded-lg text-slate-400 hover:text-sky-600 dark:hover:text-sky-300 hover:bg-sky-50 dark:hover:bg-sky-500/10 transition-colors cursor-pointer"
                                        title="Unggah Snapshot Foto / Render untuk Lembar Ini"
                                    >
                                        <Loader2 v-if="uploadingSheetId === sheet.id" class="w-3.5 h-3.5 animate-spin text-amber-500" />
                                        <Camera v-else class="w-3.5 h-3.5" />
                                    </button>

                                    <!-- Delete Snapshot (if sheet has dedicated image) -->
                                    <button
                                        v-if="sheet.image_url || sheet.image_path"
                                        type="button"
                                        @click="deleteSnapshotImage(sheet)"
                                        class="p-1 rounded-lg text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors cursor-pointer"
                                        title="Hapus Snapshot Foto & Tampilkan Blueprint Vektor"
                                    >
                                        <RotateCcw class="w-3.5 h-3.5" />
                                    </button>

                                    <button
                                        type="button"
                                        @click="openEditSheetModal(sheet)"
                                        class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition-colors cursor-pointer"
                                        title="Edit Pengaturan Lembar Kerja"
                                    >
                                        <Settings class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="deleteSheet(sheet)"
                                        class="p-1 rounded-lg text-slate-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors cursor-pointer"
                                        title="Hapus Gambar Kerja"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Title & Description -->
                            <div>
                                <Link
                                    :href="`/shop-drawings/${sheet.id}`"
                                    class="text-base font-bold text-slate-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors line-clamp-1 block"
                                >
                                    {{ sheet.title }}
                                </Link>
                                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-1">
                                    <span v-if="sheet.project?.client_name" class="font-medium text-slate-700 dark:text-slate-300 truncate max-w-[150px]">
                                        Klien: {{ sheet.project.client_name }}
                                    </span>
                                    <span v-else class="text-slate-400 dark:text-slate-500 italic">Klien belum diisi</span>
                                    <span class="text-slate-300 dark:text-slate-600">•</span>
                                    <span class="text-[11px] font-mono text-slate-500 dark:text-slate-400">Skala {{ sheet.scale || '1:20' }}</span>
                                </div>
                            </div>

                            <!-- Technical Specs Tags -->
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-medium bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300">
                                    {{ sheet.paper_size || 'A3' }} ({{ sheet.orientation || 'landscape' }})
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-700 dark:text-emerald-300 flex items-center gap-1">
                                    <Scissors class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                                    <span>Cutting List Ready</span>
                                </span>
                                <span
                                    v-if="(sheet.captures_count ?? 0) > 0"
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 text-sky-700 dark:text-sky-300 flex items-center gap-1"
                                >
                                    <Camera class="w-3 h-3 text-sky-600 dark:text-sky-400" />
                                    <span>{{ sheet.captures_count }} Visual 3D</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer Actions -->
                    <div class="pt-4 mt-4 border-t border-slate-200 dark:border-white/10 flex items-center justify-between gap-2">
                        <button
                            type="button"
                            @click="openLinkCapturesModal(sheet)"
                            class="px-2.5 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-white/5 dark:hover:bg-white/10 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-white/10 transition-colors flex items-center gap-1.5 cursor-pointer shrink-0"
                            title="Tautkan Render &amp; Screenshot 3D ke Lembar Ini"
                        >
                            <Camera class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" />
                            <span>Visual 3D</span>
                        </button>

                        <Link
                            :href="`/shop-drawings/${sheet.id}`"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition-all shadow-md shadow-amber-500/10 group/btn cursor-pointer"
                        >
                            <span>Buka Gambar Kerja</span>
                            <ArrowRight class="w-3.5 h-3.5 group-hover/btn:translate-x-0.5 transition-transform" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MODAL BUAT GAMBAR KERJA BARU ───────────────────────────────── -->
            <div v-if="showCreateModal" class="fixed inset-0 z-50 bg-slate-950/70 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-white/15 rounded-3xl w-full max-w-lg p-6 shadow-2xl space-y-5 animate-in fade-in zoom-in-95 duration-150">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-white/10">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-500/15 dark:bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                                <Plus class="w-4 h-4 stroke-[2.5]" />
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Buat Gambar Kerja Baru</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Terhubung langsung ke Proyek 3D &amp; data kop gambar otomatis.</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" class="space-y-4 text-xs">
                        <!-- Koneksi ke Proyek 3D -->
                        <div>
                            <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1 flex items-center gap-1.5">
                                <Box class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" />
                                <span>PILIH PROYEK 3D YANG TERHUBUNG</span>
                                <span class="text-red-400">*</span>
                            </label>
                            <select
                                v-model="form.project_id"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-900 border border-sky-500/30 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-white"
                            >
                                <option v-for="p in props.projects" :key="p.id" :value="p.id">
                                    {{ p.title }} {{ p.client_name ? `(Klien: ${p.client_name})` : '' }}
                                </option>
                            </select>
                            <span class="text-[10px] text-sky-600 dark:text-sky-400/80 mt-1 block">
                                Proyek 3D ini akan otomatis mengisi Kop Gambar (Nama Proyek, Klien, &amp; Lokasi).
                            </span>
                        </div>

                        <!-- Judul Lembar Kerja -->
                        <div>
                            <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">
                                JUDUL GAMBAR KERJA
                                <span class="text-red-400">*</span>
                            </label>
                            <input
                                v-model="form.title"
                                type="text"
                                required
                                placeholder="Contoh: Backdrop TV & Floating Credenza, Kitchen Set Island, Wardrobe 3 Pintu"
                                class="w-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 font-medium"
                            />
                        </div>

                        <!-- Info Klien (Otomatis dari Proyek 3D, bisa diubah) -->
                        <div class="grid grid-cols-2 gap-3 bg-slate-50 dark:bg-white/[0.02] border border-slate-200 dark:border-white/5 rounded-xl p-3">
                            <div>
                                <label class="block text-slate-500 dark:text-slate-400 text-[11px] font-medium mb-1">Nama Klien</label>
                                <input
                                    v-model="form.client_name"
                                    type="text"
                                    placeholder="Contoh: Bpk. Hendra"
                                    class="w-full bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-lg px-2.5 py-1.5 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
                                />
                            </div>
                            <div>
                                <label class="block text-slate-500 dark:text-slate-400 text-[11px] font-medium mb-1">Alamat / Lokasi</label>
                                <input
                                    v-model="form.client_address"
                                    type="text"
                                    placeholder="Contoh: Cluster Lavender No. 12"
                                    class="w-full bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-lg px-2.5 py-1.5 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
                                />
                            </div>
                        </div>

                        <!-- Skala & Kertas -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">SKALA STANDAR</label>
                                <select
                                    v-model="form.scale"
                                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-white"
                                >
                                    <option value="1:20">1:20 (Standar Fabrikasi Mebel)</option>
                                    <option value="1:25">1:25 (Kabinet Detail)</option>
                                    <option value="1:50">1:50 (Denah Ruang)</option>
                                    <option value="1:100">1:100 (Skala Arsitektur)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">UKURAN KERTAS</label>
                                <select
                                    v-model="form.paper_size"
                                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-white"
                                >
                                    <option value="A3">A3 (Standar Gambar Kerja)</option>
                                    <option value="A4">A4 (Dokumen Portabel)</option>
                                    <option value="A2">A2 (Lembar Cetak Besar)</option>
                                    <option value="A1">A1 (Plotter)</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-3 flex items-center justify-between border-t border-slate-200 dark:border-white/10">
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Editor akan terbuka otomatis setelah disimpan</span>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="showCreateModal = false"
                                    class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white text-xs cursor-pointer hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl font-bold text-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-lg shadow-amber-500/20 transition-all"
                                >
                                    <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                                    <span>{{ form.processing ? 'Membuat...' : 'Buat & Buka Editor' }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── MODAL EDIT PENGATURAN LEMBAR KERJA ──────────────────────────── -->
            <div v-if="showEditModal" class="fixed inset-0 z-50 bg-slate-950/70 dark:bg-black/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-white/15 rounded-3xl w-full max-w-md p-6 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-white/10">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Settings class="w-4 h-4 text-amber-500 dark:text-amber-400" />
                            Pengaturan Gambar Kerja
                        </h3>
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form @submit.prevent="submitEditSheet" class="space-y-4 text-xs">
                        <div>
                            <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">PROYEK 3D TERHUBUNG</label>
                            <select
                                v-model="editForm.project_id"
                                required
                                class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-medium focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-white"
                            >
                                <option v-for="p in props.projects" :key="p.id" :value="p.id">
                                    {{ p.title }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">JUDUL GAMBAR KERJA</label>
                            <input
                                v-model="editForm.title"
                                type="text"
                                required
                                class="w-full bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-amber-500 font-medium"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">SKALA</label>
                                <select
                                    v-model="editForm.scale"
                                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-white"
                                >
                                    <option value="1:20">1:20</option>
                                    <option value="1:25">1:25</option>
                                    <option value="1:50">1:50</option>
                                    <option value="1:100">1:100</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-slate-700 dark:text-slate-300 font-semibold mb-1">UKURAN KERTAS</label>
                                <select
                                    v-model="editForm.paper_size"
                                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-900 dark:text-white font-mono focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer [&>option]:bg-white [&>option]:text-slate-900 dark:[&>option]:bg-slate-900 dark:[&>option]:text-white"
                                >
                                    <option value="A4">A4</option>
                                    <option value="A3">A3</option>
                                    <option value="A2">A2</option>
                                    <option value="A1">A1</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-200 dark:border-white/10">
                            <button
                                type="button"
                                @click="showEditModal = false"
                                class="px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white text-xs cursor-pointer hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="editForm.processing"
                                class="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl font-bold text-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-md shadow-amber-500/20 transition-all"
                            >
                                <Loader2 v-if="editForm.processing" class="w-3.5 h-3.5 animate-spin" />
                                <span>{{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── MODAL HUBUNGKAN VISUAL 3D (CAPTURES) ───────────────────────── -->
            <div v-if="showLinkCapturesModal" class="fixed inset-0 z-50 bg-slate-950/70 dark:bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-slate-950 border border-slate-200 dark:border-white/15 rounded-3xl w-full max-w-2xl p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-white/10 shrink-0">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-sky-500/15 dark:bg-sky-500/20 border border-sky-500/30 flex items-center justify-center text-sky-600 dark:text-sky-400">
                                <Camera class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Hubungkan Visual 3D Proyek</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Pilih screenshot atau render AI dari proyek <strong class="text-sky-600 dark:text-sky-300">{{ selectedSheetForLinking?.project?.title }}</strong>
                                </p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="showLinkCapturesModal = false"
                            class="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-white/5 transition-colors cursor-pointer"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Visual Grid Selection -->
                    <div class="overflow-y-auto flex-1 pr-1 space-y-3">
                        <div v-if="availableCapturesForSelectedSheet.length === 0" class="text-center py-10 space-y-2">
                            <Camera class="w-8 h-8 text-slate-300 dark:text-slate-600 mx-auto" />
                            <p class="text-xs text-slate-600 dark:text-slate-400">Belum ada screenshot atau render 3D pada proyek ini.</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">Ambil screenshot di 3D Viewer proyek terlebih dahulu.</p>
                        </div>

                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div
                                v-for="cap in availableCapturesForSelectedSheet"
                                :key="cap.id"
                                @click="toggleCaptureSelection(cap.id)"
                                :class="[
                                    'group relative rounded-xl border overflow-hidden cursor-pointer transition-all aspect-video bg-slate-100 dark:bg-slate-900',
                                    selectedCaptureIds.includes(cap.id)
                                        ? 'border-amber-500 ring-2 ring-amber-400/40 shadow-lg shadow-amber-500/10'
                                        : 'border-slate-200 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/30'
                                ]"
                            >
                                <img
                                    :src="cap.ai_rendered_url || cap.url || `/storage/${cap.ai_rendered_path || cap.file_path}`"
                                    :alt="cap.room_name"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />

                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex flex-col justify-between p-2">
                                    <div class="flex justify-end">
                                        <div
                                            :class="[
                                                'w-5 h-5 rounded-full flex items-center justify-center transition-colors',
                                                selectedCaptureIds.includes(cap.id)
                                                    ? 'bg-amber-400 text-slate-950'
                                                    : 'bg-black/50 text-white/50 border border-white/20'
                                            ]"
                                        >
                                            <CheckCircle2 v-if="selectedCaptureIds.includes(cap.id)" class="w-4 h-4" />
                                        </div>
                                    </div>
                                    <div class="text-[10px] font-medium text-white truncate">
                                        {{ cap.room_name || 'Visual 3D' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-3 border-t border-slate-200 dark:border-white/10 flex items-center justify-between shrink-0">
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ selectedCaptureIds.length }} visual 3D dipilih
                        </span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showLinkCapturesModal = false"
                                class="px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-white/10 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white text-xs cursor-pointer hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                @click="saveLinkedCaptures"
                                :disabled="isSavingLinkedCaptures"
                                class="px-4 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl font-bold text-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-md shadow-amber-500/20 transition-all"
                            >
                                <Loader2 v-if="isSavingLinkedCaptures" class="w-3.5 h-3.5 animate-spin" />
                                <span>{{ isSavingLinkedCaptures ? 'Menyimpan...' : 'Simpan Tautan Visual' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</template>

