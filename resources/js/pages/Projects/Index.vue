<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle, Box, CheckCircle2, Clock, FolderOpen,
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

// ─── Create project modal ────────────────────────────────
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    description: '',
    max_revisions_allowed: 3,
    file: null as File | null,
});
const selectedFileName = ref('');
const fileInputRef = ref<HTMLInputElement | null>(null);

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    createForm.file = file;
    selectedFileName.value = file.name;
}

function submitCreate() {
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
            toast.success('Proyek berhasil dibuat!');
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
            <AppHeader />

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
                    <div
                        v-for="project in projects"
                        :key="project.id"
                        class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] shadow-sm dark:shadow-lg transition-all hover:shadow-md dark:hover:shadow-[0_0_45px_rgba(99,102,241,0.25)] hover:border-slate-300 dark:hover:border-white/30 group"
                    >
                        <!-- Thumbnail banner -->
                        <div class="relative flex h-32 w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-950 overflow-hidden">
                            <Box class="h-14 w-14 opacity-20 group-hover:opacity-30 group-hover:scale-110 transition-all duration-500" />
                            <div class="absolute top-3 left-3 flex gap-1.5">
                                <Badge class="bg-black/60 text-white text-[11px] border border-white/20">3D GLB</Badge>
                                <Badge v-if="project.is_draco_compressed" class="bg-emerald-500/80 text-white text-[10px]">Draco</Badge>
                            </div>
                            <div class="absolute top-3 right-3 flex gap-1.5">
                                <button @click="deleteProject(project)" class="rounded-full bg-black/50 border border-white/20 p-1.5 text-slate-300 hover:bg-rose-600 hover:text-white transition-all">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            <div class="absolute bottom-2 right-3 text-[11px] text-slate-300 font-mono">{{ formatBytes(project.file_size_bytes) }}</div>
                        </div>

                        <!-- Card content -->
                        <div class="p-5 flex-1">
                            <h3 class="font-bold text-base text-slate-900 dark:text-neutral-100 line-clamp-1">{{ project.title }}</h3>
                            <p class="mt-1 text-xs text-slate-400 dark:text-neutral-400 line-clamp-2 min-h-[32px]">{{ project.description || 'Tidak ada deskripsi.' }}</p>

                            <!-- Revision progress -->
                            <div class="mt-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-slate-500 dark:text-neutral-400">Kuota Revisi:</span>
                                    <span class="font-bold text-slate-900 dark:text-neutral-100">
                                        {{ project.current_revision_count }} / {{ project.max_revisions_allowed }}
                                    </span>
                                </div>
                                <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="project.has_reached_revision_limit ? 'bg-gradient-to-r from-rose-500 to-rose-600' : 'bg-gradient-to-r from-indigo-500 to-purple-500'"
                                        :style="{ width: `${Math.min(100, (project.current_revision_count / project.max_revisions_allowed) * 100)}%` }"
                                    ></div>
                                </div>
                                <div class="mt-1.5 flex items-center justify-between text-[11px]">
                                    <span v-if="project.has_reached_revision_limit" class="font-semibold text-rose-500 dark:text-rose-400 flex items-center gap-1">
                                        <AlertTriangle class="h-3 w-3" /> Batas habis
                                    </span>
                                    <span v-else class="text-emerald-600 dark:text-emerald-400">Terbuka</span>
                                    <span class="text-slate-400">{{ project.created_at }}</span>
                                </div>
                            </div>

                            <!-- Klien -->
                            <div class="mt-3 flex items-center justify-between text-xs">
                                <span class="text-slate-400 dark:text-neutral-400">Klien Kolaborator:</span>
                                <button @click="openClientModal(project)" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                                    <UserCheck class="h-3.5 w-3.5" />
                                    {{ project.invited_clients.length }} Klien
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-slate-100 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] p-4 flex flex-col items-center gap-2">
                            <Link :href="`/projects/${project.id}/viewer`" class="flex-1">
                                <Button class="w-full text-xs font-semibold bg-indigo-500 hover:bg-indigo-600 text-white" size="sm">
                                    <Box class="mr-1.5 h-3.5 w-3.5" /> Buka 3D Viewer
                                </Button>
                            </Link>
                            <Button @click="openClientModal(project)" variant="outline" size="sm" class="text-xs border-slate-200 dark:border-white/15" title="Kelola Klien">
                                <UserPlus class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
                </template><!-- end v-else -->
            </main>
        </div>
    </div>

    <!-- ── MODAL: Buat Proyek ── -->
    <Dialog :open="isCreateModalOpen" @update:open="isCreateModalOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2"><Box class="h-5 w-5 text-primary" /> Buat Proyek 3D Baru</DialogTitle>
                <DialogDescription>Unggah model 3D berformat <code>.glb</code> atau <code>.gltf</code>.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                <div class="space-y-1.5">
                    <Label for="title">Judul Proyek <span class="text-rose-500">*</span></Label>
                    <Input id="title" v-model="createForm.title" placeholder="Contoh: Desain Villa Modern" required />
                    <p v-if="createForm.errors.title" class="text-xs text-rose-500">{{ createForm.errors.title }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label for="desc">Deskripsi (Opsional)</Label>
                    <textarea id="desc" v-model="createForm.description" rows="2" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:border-ring focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="Catatan konsep atau arahan khusus..."></textarea>
                </div>
                <div class="space-y-1.5">
                    <Label for="max_rev">Batas Revisi Klien</Label>
                    <Input id="max_rev" type="number" v-model.number="createForm.max_revisions_allowed" min="1" max="10" required />
                </div>
                <div class="space-y-1.5">
                    <Label>File Model 3D (.glb / .gltf) <span class="text-rose-500">*</span></Label>
                    <div
                        class="cursor-pointer rounded-xl border-2 border-dashed border-slate-200 dark:border-white/15 p-6 text-center hover:border-indigo-400 transition-colors"
                        @click="fileInputRef?.click()"
                    >
                        <Box class="mx-auto h-8 w-8 text-slate-400 mb-2" />
                        <p class="text-sm text-slate-500 dark:text-neutral-400">
                            {{ selectedFileName || 'Klik untuk pilih file .glb atau .gltf' }}
                        </p>
                        <input ref="fileInputRef" type="file" accept=".glb,.gltf" class="hidden" @change="onFileChange" />
                    </div>
                    <p v-if="createForm.errors.file" class="text-xs text-rose-500">{{ createForm.errors.file }}</p>
                </div>
                <DialogFooter>
                    <DialogClose as-child><Button type="button" variant="outline">Batal</Button></DialogClose>
                    <Button type="submit" :disabled="createForm.processing || !createForm.file" class="bg-indigo-600 hover:bg-indigo-700 text-white">
                        {{ createForm.processing ? 'Mengunggah...' : 'Buat Proyek' }}
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
