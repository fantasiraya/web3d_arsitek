<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle, Box, CheckCircle2, ClipboardList, Clock, FileUp, FolderOpen,
    Pencil, Plus, Search, Send, Sparkles, Trash2,
    UserCheck, UserPlus, Users, X,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog, DialogClose, DialogContent, DialogDescription,
    DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppHeader from '@/components/app/AppHeader.vue';
import { useSidebar } from '@/composables/useSidebar';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import { useConfirm } from '@/composables/useConfirm';
import { usePageLoading } from '@/composables/usePageLoading';
import SkeletonProjectGrid from '@/components/skeletons/SkeletonProjectGrid.vue';
import { Skeleton } from '@/components/ui/skeleton';
import ProjectCard from '@/components/ProjectCard.vue';

// ─── Types ───────────────────────────────────────────────
interface ProjectClientItem {
    id: string;
    email: string;
    status: 'pending' | 'accepted' | 'revoked';
    invited_at: string;
    accepted_at: string | null;
}

interface Project {
    id: string;
    title: string;
    description: string | null;
    file_path?: string;
    thumbnail_url?: string | null;
    file_size_bytes: number;
    is_draco_compressed: boolean;
    max_revisions_allowed: number;
    current_revision_count: number;
    has_reached_revision_limit: boolean;
    created_at: string;
    invited_clients: ProjectClientItem[];
}

// ─── Props ───────────────────────────────────────────────
const props = defineProps<{
    projects: Project[];
    search: string;
    canCreate: boolean;
    stats: { total: number; max_projects: number };
}>();

// ─── Layout ──────────────────────────────────────────────
const { isSidebarOpen, isMobile } = useSidebar();
const { confirm } = useConfirm();
const { isLoading } = usePageLoading(80);
const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);

// ─── Search ──────────────────────────────────────────────
const searchInput = ref(props.search);

function doSearch() {
    router.get('/projects', { search: searchInput.value }, { preserveState: true, preserveScroll: true });
}

function clearSearch() {
    searchInput.value = '';
    router.get('/projects', {}, { preserveState: true });
}

// ─── Helpers ─────────────────────────────────────────────
function formatBytes(bytes: number): string {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);
    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
}

// ─── Delete project ──────────────────────────────────────
async function deleteProject(project: Project) {
    const ok = await confirm({
        title: 'Hapus Proyek?',
        description: `Proyek "${project.title}" dan semua data terkait akan dihapus permanen.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'destructive',
        icon: 'trash',
    });
    if (!ok) return;

    router.delete(`/projects/${project.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Proyek berhasil dihapus.'),
        onError: () => toast.error('Gagal menghapus proyek.'),
    });
}

const iconButtonClass =
    'border-slate-200 bg-white text-xs text-slate-500 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-neutral-300 dark:hover:border-white/30 dark:hover:bg-white/10 dark:hover:text-white';

// ─── Create project modal ────────────────────────────────
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    description: '',
    max_revisions_allowed: 3,
    file: null as File | null,
});
const selectedFileName = ref('');
const selectedFileSize = ref('');
const fileError = ref('');
const isDraggingCreate = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

function handleFileSelection(file: File | undefined, mode: 'create' | 'edit') {
    if (!file) return;

    const sizeMb = file.size / (1024 * 1024);
    const ext = file.name.split('.').pop()?.toLowerCase();

    if (mode === 'create') {
        fileError.value = '';
        selectedFileName.value = file.name;
        selectedFileSize.value = sizeMb.toFixed(2) + ' MB';

        if (ext !== 'glb' && ext !== 'skp' && ext !== 'gltf') {
            fileError.value = 'Format file tidak didukung! Mohon pilih file 3D (.glb atau .skp).';
            createForm.file = null;
            return;
        }

        if (sizeMb > 100) {
            fileError.value = `Ukuran file (${sizeMb.toFixed(1)} MB) terlalu besar! Maksimal 100 MB.`;
            createForm.file = null;
            return;
        }

        createForm.file = file;
    } else {
        editFileError.value = '';
        editSelectedFileName.value = file.name;
        editSelectedFileSize.value = sizeMb.toFixed(2) + ' MB';

        if (ext !== 'glb' && ext !== 'skp' && ext !== 'gltf') {
            editFileError.value = 'Format file tidak didukung! Mohon pilih file 3D (.glb atau .skp).';
            editForm.file = null;
            return;
        }

        if (sizeMb > 100) {
            editFileError.value = `Ukuran file (${sizeMb.toFixed(1)} MB) terlalu besar! Maksimal 100 MB.`;
            editForm.file = null;
            return;
        }

        editForm.file = file;
    }
}

function onFileChange(e: Event) {
    const target = e.target as HTMLInputElement;
    handleFileSelection(target.files?.[0], 'create');
    target.value = '';
}

function onFileDropCreate(e: DragEvent) {
    isDraggingCreate.value = false;
    const file = e.dataTransfer?.files?.[0];
    handleFileSelection(file, 'create');
}

function submitCreate() {
    if (fileError.value || !createForm.file) return;

    createForm.transform((d) => ({
        title: d.title,
        description: d.description,
        max_revisions_allowed: d.max_revisions_allowed,
        file: d.file,
    })).post('/projects', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            createForm.reset();
            selectedFileName.value = '';
            selectedFileSize.value = '';
            fileError.value = '';
            toast.success('Proyek 3D berhasil dibuat!');
        },
        onError: (err: any) => {
            toast.error(err.file || err.title || 'Gagal membuat proyek. Periksa ukuran file dan data Anda.');
        },
    });
}

// ─── Edit project modal ──────────────────────────────────
const isEditModalOpen = ref(false);
const editingProject = ref<Project | null>(null);
const editForm = useForm({
    title: '',
    description: '',
    max_revisions_allowed: 3,
    file: null as File | null,
});

const editFileInputRef = ref<HTMLInputElement | null>(null);
const editSelectedFileName = ref('');
const editSelectedFileSize = ref('');
const editFileError = ref('');
const isDraggingEdit = ref(false);

function openEditModal(project: Project) {
    editingProject.value = project;
    editForm.title = project.title;
    editForm.description = project.description || '';
    editForm.max_revisions_allowed = project.max_revisions_allowed;
    editForm.file = null;
    editSelectedFileName.value = '';
    editSelectedFileSize.value = '';
    editFileError.value = '';
    isDraggingEdit.value = false;
    editForm.clearErrors();
    isEditModalOpen.value = true;
}

function onEditFileSelected(e: Event) {
    const target = e.target as HTMLInputElement;
    handleFileSelection(target.files?.[0], 'edit');
    target.value = '';
}

function onFileDropEdit(e: DragEvent) {
    isDraggingEdit.value = false;
    const file = e.dataTransfer?.files?.[0];
    handleFileSelection(file, 'edit');
}

function submitEditProject() {
    if (!editingProject.value || editFileError.value) return;

    editForm.transform((data) => {
        const payload: any = {
            title: data.title,
            description: data.description,
            max_revisions_allowed: data.max_revisions_allowed,
            _method: 'PATCH',
        };
        if (data.file) {
            payload.file = data.file;
        }
        return payload;
    }).post(`/projects/${editingProject.value.id}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            isEditModalOpen.value = false;
            editForm.reset();
            editSelectedFileName.value = '';
            editSelectedFileSize.value = '';
            editFileError.value = '';
            editingProject.value = null;
            toast.success('Proyek berhasil diperbarui.');
        },
        onError: (err: any) => {
            toast.error(err.file || err.title || 'Gagal memperbarui proyek.');
        },
    });
}

// ─── Client modal ────────────────────────────────────────
const isClientModalOpen = ref(false);
const activeProject = ref<Project | null>(null);
const inviteForm = useForm({ email: '' });

function openClientModal(project: Project) {
    activeProject.value = project;
    inviteForm.reset();
    isClientModalOpen.value = true;
}

function submitInvite() {
    if (!activeProject.value) return;
    inviteForm.post(`/projects/${activeProject.value.id}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            toast.success('Undangan berhasil dikirim.');
            router.reload({ only: ['projects'] });
        },
    });
}

async function revokeClient(client: ProjectClientItem) {
    if (!activeProject.value) return;
    const ok = await confirm({
        title: 'Cabut Akses Klien?',
        description: `Akses ${client.email} akan dicabut dari proyek ini.`,
        confirmText: 'Ya, Cabut',
        cancelText: 'Batal',
        variant: 'destructive',
        icon: 'trash',
    });
    if (!ok) return;

    router.delete(`/projects/${activeProject.value.id}/clients/${client.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Akses klien dicabut.');
            router.reload({ only: ['projects'] });
        },
    });
}
</script>

<template>
    <Head title="Proyek 3D" />

    <div class="min-h-screen bg-[#F8F9FA] dark:bg-[#0b0c10] text-slate-900 dark:text-[#f3f4f6] font-['Geist',sans-serif] antialiased relative flex">
        <AppSidebar />

        <div :class="['flex-1 flex flex-col min-w-0 transition-all duration-300', isSidebarOpen && !isMobile ? 'ml-64' : 'ml-0 lg:ml-16']">
            <AppHeader 
                :can-create="canCreate"
                @create-project="isCreateModalOpen = true"
            />

            <main class="flex-1 p-6 lg:p-8 space-y-6">

                <!-- ── SKELETON ── -->
                <template v-if="isLoading">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="space-y-2"><Skeleton class="h-8 w-40" /><Skeleton class="h-4 w-56" /></div>
                        <Skeleton class="h-10 w-36 rounded-full" />
                    </div>
                    <Skeleton class="h-10 w-80 max-w-full rounded-xl" />
                    <SkeletonProjectGrid :count="3" />
                </template>

                <!-- ── REAL CONTENT ── -->
                <template v-else>

                <!-- Page header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Proyek 3D</h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                            {{ stats.total }} proyek · kuota {{ stats.max_projects >= 999 ? 'tak terbatas' : stats.max_projects }}
                        </p>
                    </div>
                    <Button
                        @click="isCreateModalOpen = true"
                        :disabled="!canCreate"
                        class="bg-slate-900 dark:bg-white text-white dark:text-black hover:bg-slate-700 dark:hover:bg-neutral-100 font-semibold rounded-full px-6"
                    >
                        <Plus class="mr-1.5 h-4 w-4" /> Buat Proyek 3D
                    </Button>
                </div>

                <!-- Flash -->
                <div v-if="flashSuccess" class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-200">
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <p class="text-sm font-medium">{{ flashSuccess }}</p>
                </div>

                <!-- Search bar -->
                <div class="flex items-center gap-2 max-w-lg">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <Input
                            v-model="searchInput"
                            placeholder="Cari proyek berdasarkan judul..."
                            class="pl-9 pr-9"
                            @keydown.enter="doSearch"
                        />
                        <button v-if="searchInput" @click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-white">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <Button @click="doSearch" variant="outline" class="shrink-0">
                        <Search class="h-4 w-4 mr-1.5" /> Cari
                    </Button>
                </div>

                <!-- No results -->
                <div v-if="projects.length === 0" class="flex min-h-[320px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 dark:border-white/20 bg-white dark:bg-white/[0.02] p-8 text-center">
                    <FolderOpen class="h-12 w-12 text-slate-300 dark:text-neutral-600 mb-4" />
                    <h3 class="text-lg font-bold text-slate-900 dark:text-neutral-100">
                        {{ search ? 'Tidak ditemukan' : 'Belum Ada Proyek 3D' }}
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-neutral-400 max-w-md">
                        {{ search ? `Tidak ada proyek yang cocok dengan "${search}".` : 'Unggah model 3D pertama Anda untuk memulai.' }}
                    </p>
                    <Button v-if="!search && canCreate" @click="isCreateModalOpen = true" class="mt-5 rounded-full">
                        <Plus class="mr-1.5 h-4 w-4" /> Unggah Proyek Pertama
                    </Button>
                </div>

                <!-- Project grid -->
                <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <ProjectCard
                        v-for="project in projects"
                        :key="project.id"
                        :project="project"
                        @edit="openEditModal"
                        @delete="deleteProject"
                        @manage-clients="openClientModal"
                    />
                </div>
                </template><!-- end v-else -->
            </main>
        </div>
    </div>

    <!-- ── MODAL: Buat Proyek ── -->
    <Dialog :open="isCreateModalOpen" @update:open="isCreateModalOpen = $event">
        <DialogContent class="sm:max-w-xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
            <DialogHeader class="px-6 pt-6 pb-3 shrink-0 border-b border-border/40">
                <DialogTitle class="flex items-center gap-2 text-lg">
                    <Box class="h-5 w-5 text-primary" /> Buat Proyek 3D Baru
                </DialogTitle>
                <DialogDescription>
                    Unggah model 3D berformat <code>.glb</code> atau <code>.skp</code> (SketchUp) untuk mulai berkolaborasi dengan Klien Anda.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitCreate" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="space-y-4 px-6 py-4 overflow-y-auto flex-1">
                    <div class="space-y-1.5">
                        <Label for="title">Judul Proyek <span class="text-rose-500">*</span></Label>
                        <Input id="title" v-model="createForm.title" placeholder="Contoh: Desain Villa Modern Canggu" required />
                        <p v-if="createForm.errors.title" class="text-xs text-rose-500">{{ createForm.errors.title }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="desc">Deskripsi Proyek (Opsional)</Label>
                        <textarea
                            id="desc"
                            v-model="createForm.description"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Catatan konsep arsitektur, lokasi, atau arahan khusus klien..."
                        ></textarea>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="max_rev">Batas Revisi Klien</Label>
                            <span class="text-xs text-muted-foreground">Default: 3x</span>
                        </div>
                        <Input id="max_rev" type="number" v-model.number="createForm.max_revisions_allowed" min="1" max="10" required />
                        <p class="text-[11px] text-muted-foreground">
                            Klien akan dibatasi hanya dapat membuat thread revisi baru hingga batas ini tercapai.
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label>File Model 3D (.glb / .skp) <span class="text-rose-500">*</span></Label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".glb,.skp,.gltf,.GLB,.SKP,.GLTF"
                            class="hidden"
                            @change="onFileChange"
                        />
                        <div
                            @click="fileInputRef?.click()"
                            @dragover.prevent="isDraggingCreate = true"
                            @dragleave.prevent="isDraggingCreate = false"
                            @drop.prevent="onFileDropCreate"
                            :class="[
                                'flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 text-center cursor-pointer transition',
                                isDraggingCreate
                                    ? 'border-primary bg-primary/10 ring-2 ring-primary/30'
                                    : 'border-input hover:border-primary/60 hover:bg-primary/5'
                            ]"
                        >
                            <FileUp class="h-8 w-8 text-muted-foreground mb-2" />
                            <div v-if="selectedFileName" class="text-sm font-semibold text-primary">
                                {{ selectedFileName }} ({{ selectedFileSize }})
                            </div>
                            <div v-else class="text-sm font-medium">
                                Klik atau tarik (drag & drop) file 3D (.glb / .skp) ke sini
                            </div>
                            <p class="text-[11px] text-muted-foreground mt-1">
                                Mendukung model <strong>.glb</strong> dan SketchUp <strong>.skp</strong> hingga 100 MB
                            </p>
                        </div>
                        <p v-if="fileError" class="text-xs font-semibold text-rose-500 mt-1">{{ fileError }}</p>
                        <p v-if="createForm.errors.file" class="text-xs font-semibold text-rose-500 mt-1">{{ createForm.errors.file }}</p>
                    </div>
                </div>

                <DialogFooter class="px-6 py-4 border-t border-border/40 shrink-0 bg-background/80 backdrop-blur-sm gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="isCreateModalOpen = false">Batal</Button>
                    <Button
                        type="submit"
                        :disabled="createForm.processing || !createForm.title || !createForm.file || !!fileError"
                    >
                        <span v-if="createForm.processing">Mengunggah...</span>
                        <span v-else>Simpan & Buat Proyek</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── MODAL: Edit Proyek ── -->
    <Dialog :open="isEditModalOpen" @update:open="isEditModalOpen = $event">
        <DialogContent class="sm:max-w-xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
            <DialogHeader class="px-6 pt-6 pb-3 shrink-0 border-b border-border/40">
                <DialogTitle class="flex items-center gap-2 text-lg">
                    <Pencil class="h-5 w-5 text-primary" /> Edit Data Proyek 3D
                </DialogTitle>
                <DialogDescription>
                    Perbarui informasi proyek atau ganti file model 3D (format <code>.glb</code> atau <code>.skp</code>).
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitEditProject" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="space-y-4 px-6 py-4 overflow-y-auto flex-1">
                    <!-- Judul Proyek -->
                    <div class="space-y-1.5">
                        <Label for="edit_title">Judul Proyek <span class="text-rose-500">*</span></Label>
                        <Input
                            id="edit_title"
                            v-model="editForm.title"
                            placeholder="Contoh: Desain Villa Modern Canggu"
                            required
                        />
                        <p v-if="editForm.errors.title" class="text-xs text-rose-500">
                            {{ editForm.errors.title }}
                        </p>
                    </div>

                    <!-- Deskripsi -->
                    <div class="space-y-1.5">
                        <Label for="edit_description">Deskripsi Proyek (Opsional)</Label>
                        <textarea
                            id="edit_description"
                            v-model="editForm.description"
                            placeholder="Catatan konsep arsitektur, lokasi, atau arahan khusus klien..."
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <p v-if="editForm.errors.description" class="text-xs text-rose-500">
                            {{ editForm.errors.description }}
                        </p>
                    </div>

                    <!-- Batas Maksimal Revisi Klien -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label for="edit_max_revisions">Batas Revisi Klien</Label>
                            <span class="text-xs text-muted-foreground">Saat ini terpakai: {{ editingProject?.current_revision_count ?? 0 }}</span>
                        </div>
                        <Input
                            id="edit_max_revisions"
                            type="number"
                            v-model.number="editForm.max_revisions_allowed"
                            min="1"
                            max="50"
                            required
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Klien akan dibatasi hanya dapat membuat thread revisi baru hingga batas ini tercapai.
                        </p>
                        <p v-if="editForm.errors.max_revisions_allowed" class="text-xs text-rose-500">
                            {{ editForm.errors.max_revisions_allowed }}
                        </p>
                    </div>

                    <!-- File Upload Input (Opsional saat Edit) -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label>Ganti File Model 3D (.glb / .skp)</Label>
                            <span class="text-xs text-muted-foreground">Opsional</span>
                        </div>
                        <input
                            ref="editFileInputRef"
                            type="file"
                            accept=".glb,.skp,.gltf,.GLB,.SKP,.GLTF"
                            @change="onEditFileSelected"
                            class="hidden"
                        />
                        <div
                            @click="editFileInputRef?.click()"
                            @dragover.prevent="isDraggingEdit = true"
                            @dragleave.prevent="isDraggingEdit = false"
                            @drop.prevent="onFileDropEdit"
                            :class="[
                                'flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 text-center cursor-pointer transition',
                                isDraggingEdit
                                    ? 'border-primary bg-primary/10 ring-2 ring-primary/30'
                                    : 'border-input hover:border-primary/60 hover:bg-primary/5'
                            ]"
                        >
                            <FileUp class="h-8 w-8 text-muted-foreground mb-2" />
                            <div v-if="editSelectedFileName" class="text-sm font-semibold text-primary">
                                {{ editSelectedFileName }} ({{ editSelectedFileSize }})
                            </div>
                            <div v-else class="text-sm font-medium">
                                Klik atau tarik file untuk mengganti model 3D saat ini ({{ formatBytes(editingProject?.file_size_bytes ?? 0) }})
                            </div>
                            <p class="text-[11px] text-muted-foreground mt-1">
                                Biarkan kosong jika tidak ingin mengubah file (.glb dan .skp didukung hingga 100 MB)
                            </p>
                        </div>

                        <p v-if="editFileError" class="text-xs font-semibold text-rose-500 mt-1">
                            {{ editFileError }}
                        </p>
                        <p v-if="editForm.errors.file" class="text-xs font-semibold text-rose-500 mt-1">
                            {{ editForm.errors.file }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="px-6 py-4 border-t border-border/40 shrink-0 bg-background/80 backdrop-blur-sm gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="isEditModalOpen = false"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        :disabled="editForm.processing || !editForm.title || !!editFileError"
                    >
                        <span v-if="editForm.processing">Menyimpan...</span>
                        <span v-else>Simpan Perubahan</span>
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── MODAL: Kelola Klien ── -->
    <Dialog :open="isClientModalOpen" @update:open="isClientModalOpen = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><Users class="h-5 w-5 text-primary" /> Kelola Klien Reviewer</DialogTitle>
                <DialogDescription>Undang Klien via email untuk proyek <strong>{{ activeProject?.title }}</strong>.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitInvite" class="space-y-3 pt-2">
                <Label for="client_email">Email Klien</Label>
                <div class="flex gap-2">
                    <Input id="client_email" type="email" v-model="inviteForm.email" placeholder="klien@gmail.com" required />
                    <Button type="submit" size="sm" :disabled="inviteForm.processing || !inviteForm.email">
                        <Send class="h-3.5 w-3.5 mr-1" /> Undang
                    </Button>
                </div>
                <p v-if="inviteForm.errors.email" class="text-xs text-rose-500">{{ inviteForm.errors.email }}</p>
            </form>
            <div class="mt-4 space-y-2">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Klien Diundang ({{ activeProject?.invited_clients.length ?? 0 }})
                </h4>
                <div v-if="!activeProject?.invited_clients.length" class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                    Belum ada klien diundang.
                </div>
                <div v-else class="max-h-48 overflow-y-auto space-y-2 pr-1">
                    <div v-for="client in activeProject!.invited_clients" :key="client.id" class="flex items-center justify-between rounded-lg border p-2.5 text-xs bg-muted/30">
                        <div>
                            <div class="font-medium text-foreground">{{ client.email }}</div>
                            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-0.5">
                                <Badge v-if="client.status === 'accepted'" class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] py-0">✓ Accepted</Badge>
                                <Badge v-else class="bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] py-0 animate-pulse">⏳ Pending</Badge>
                                <span>{{ client.invited_at }}</span>
                            </div>
                        </div>
                        <button @click="revokeClient(client)" class="rounded p-1 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600 transition">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
            <DialogFooter class="pt-3">
                <DialogClose as-child><Button variant="outline" size="sm">Tutup</Button></DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <ConfirmDialog />
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
