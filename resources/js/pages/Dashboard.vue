<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Box,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileText,
    FileUp,
    FolderGit2,
    FolderOpen,
    Layers,
    Pencil,
    Plus,
    Send,
    ShieldCheck,
    Sparkles,
    Trash2,
    UserCheck,
    UserPlus,
    Users,
    X,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import AppSwissLayout from '@/layouts/AppSwissLayout.vue';

interface ProjectClientItem {
    id: string;
    email: string;
    status: 'pending' | 'accepted' | 'revoked';
    invited_at: string;
    accepted_at: string | null;
}

interface OwnedProject {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    file_path: string;
    file_size_bytes: number;
    is_draco_compressed: boolean;
    max_revisions_allowed: number;
    current_revision_count: number;
    has_reached_revision_limit: boolean;
    created_at: string;
    versions_count: number;
    invited_clients: ProjectClientItem[];
}

interface ClientProject {
    invitation_id: string;
    invitation_status: 'pending' | 'accepted';
    invited_at: string;
    accepted_at: string | null;
    id: string;
    title: string;
    description: string | null;
    architect_name: string;
    architect_email: string;
    max_revisions_allowed: number;
    current_revision_count: number;
    has_reached_revision_limit: boolean;
    created_at: string;
}

interface Stats {
    owned_count: number;
    client_count: number;
    max_projects: number;
    can_create_project: boolean;
    subscription_status: 'free' | 'pro';
}

const props = defineProps<{
    auth?: {
        user?: {
            id: string;
            name: string;
            email: string;
            subscription_status: string;
            is_pro: boolean;
        };
    };
    ownedProjects?: OwnedProject[];
    clientProjects?: ClientProject[];
    stats?: Stats;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);

// Active Tab: 'architect' or 'client'
const activeTab = ref<'architect' | 'client'>('architect');
const searchQuery = ref('');

// Filtered lists
const filteredOwnedProjects = computed(() => {
    const list = props.ownedProjects ?? [];
    if (!searchQuery.value.trim()) return list;
    const query = searchQuery.value.toLowerCase();
    return list.filter(
        (p) =>
            p.title.toLowerCase().includes(query) ||
            (p.description && p.description.toLowerCase().includes(query))
    );
});

const filteredClientProjects = computed(() => {
    const list = props.clientProjects ?? [];
    if (!searchQuery.value.trim()) return list;
    const query = searchQuery.value.toLowerCase();
    return list.filter(
        (p) =>
            p.title.toLowerCase().includes(query) ||
            p.architect_name.toLowerCase().includes(query) ||
            p.architect_email.toLowerCase().includes(query)
    );
});

// Modal: Create Project
const isCreateModalOpen = ref(false);
const createForm = useForm({
    title: '',
    description: '',
    max_revisions_allowed: 3,
    file: null as File | null,
});

const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFileName = ref('');
const selectedFileSize = ref('');
const fileError = ref('');

function onFileSelected(e: Event) {
    fileError.value = '';
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        const sizeMb = file.size / (1024 * 1024);
        selectedFileName.value = file.name;
        selectedFileSize.value = sizeMb.toFixed(2) + ' MB';

        const ext = file.name.split('.').pop()?.toLowerCase();
        if (ext !== 'glb' && ext !== 'gltf') {
            fileError.value = 'Format file tidak didukung! Mohon pilih file 3D (.glb atau .gltf).';
            createForm.file = null;
            return;
        }

        if (sizeMb > 100) {
            fileError.value = `Ukuran file (${sizeMb.toFixed(1)} MB) terlalu besar! Maksimal 100 MB.`;
            createForm.file = null;
            return;
        }

        createForm.file = file;
    }
}

function submitCreateProject() {
    if (fileError.value || !createForm.file) return;

    createForm.post('/projects', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            createForm.reset();
            selectedFileName.value = '';
            selectedFileSize.value = '';
            fileError.value = '';
        },
    });
}

// Modal: Edit Project
const isEditModalOpen = ref(false);
const editingProject = ref<OwnedProject | null>(null);
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

function openEditModal(project: OwnedProject) {
    editingProject.value = project;
    editForm.title = project.title;
    editForm.description = project.description || '';
    editForm.max_revisions_allowed = project.max_revisions_allowed;
    editForm.file = null;
    editSelectedFileName.value = '';
    editSelectedFileSize.value = '';
    editFileError.value = '';
    editForm.clearErrors();
    isEditModalOpen.value = true;
}

function onEditFileSelected(e: Event) {
    editFileError.value = '';
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        const sizeMb = file.size / (1024 * 1024);
        editSelectedFileName.value = file.name;
        editSelectedFileSize.value = sizeMb.toFixed(2) + ' MB';

        const ext = file.name.split('.').pop()?.toLowerCase();
        if (ext !== 'glb' && ext !== 'gltf') {
            editFileError.value = 'Format file tidak didukung! Mohon pilih file 3D (.glb atau .gltf).';
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
        },
    });
}

// Modal: Manage Clients
const isClientModalOpen = ref(false);
const activeProjectForClients = ref<OwnedProject | null>(null);
const inviteForm = useForm({
    email: '',
});

function openClientModal(project: OwnedProject) {
    activeProjectForClients.value = project;
    inviteForm.reset();
    isClientModalOpen.value = true;
}

function submitInviteClient() {
    if (!activeProjectForClients.value) return;
    inviteForm.post(`/projects/${activeProjectForClients.value.id}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            // Refresh local reference from updated props
            const updated = props.ownedProjects?.find((p) => p.id === activeProjectForClients.value?.id);
            if (updated) {
                activeProjectForClients.value = updated;
            }
        },
    });
}

function revokeClient(client: ProjectClientItem) {
    if (!activeProjectForClients.value) return;
    if (!confirm(`Cabut akses untuk klien ${client.email}?`)) return;

    router.delete(`/projects/${activeProjectForClients.value.id}/clients/${client.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            const updated = props.ownedProjects?.find((p) => p.id === activeProjectForClients.value?.id);
            if (updated) {
                activeProjectForClients.value = updated;
            }
        },
    });
}

// Action: Accept Client Invitation
function acceptInvitation(project: ClientProject) {
    router.post(`/projects/${project.id}/accept-invitation`, {}, {
        preserveScroll: true,
    });
}

// Action: Delete Project
function deleteProject(project: OwnedProject) {
    if (!confirm(`Apakah Anda yakin ingin menghapus proyek "${project.title}"?`)) return;
    router.delete(`/projects/${project.id}`, {
        preserveScroll: true,
    });
}

function formatBytes(bytes: number): string {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}
</script>

<template>
    <AppSwissLayout>
        <div class="min-h-screen bg-red-50 dark:bg-red-950/20">
            <Head title="Dashboard Kolaborasi 3D" />

            <div class="space-y-6 md:p-8 bg-blue-50 dark:bg-blue-950/20">
                <!-- Flash Message -->
                <div
                    v-if="flashSuccess"
                    class="flex items-center gap-3 rounded-lg border border-emerald-500/30 bg-emerald-50/50 p-4 text-emerald-800 shadow-xs dark:bg-emerald-950/20 dark:text-emerald-300"
                >
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <p class="text-sm font-medium">{{ flashSuccess }}</p>
                </div>

                <!-- Limit Warning Banner -->
                <div
                    v-if="stats?.limit_warning"
                    class="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-50/50 p-4 text-amber-900 shadow-xs dark:bg-amber-950/20 dark:text-amber-300"
                >
                    <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold mb-1">Project Limit Exceeded</p>
                        <p class="text-sm">{{ stats.limit_warning.message }}</p>
                        <p class="text-xs mt-2 text-amber-700 dark:text-amber-400">
                            You cannot create new projects until you upgrade your plan or delete existing projects.
                        </p>
                    </div>
                </div>

                <!-- Welcome & Header Section -->
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                                Selamat Datang, {{ auth?.user?.name ?? 'Pengguna' }}
                            </h1>
                            <Badge
                                v-if="stats?.subscription_status === 'pro'"
                                class="bg-linear-to-r from-amber-500 to-orange-600 text-white font-semibold text-xs"
                            >
                                <Sparkles class="mr-1 h-3.5 w-3.5" /> PRO TIER
                            </Badge>
                            <Badge
                                v-else
                                variant="secondary"
                                class="text-xs font-medium border"
                            >
                                FREE TIER ({{ stats?.owned_count ?? 0 }}/{{ stats?.max_projects ?? 3 }} Proyek)
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Kelola proyek arsitektur 3D Anda dan berikan feedback revisi secara interaktif dengan Klien.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            @click="isCreateModalOpen = true"
                            class="bg-primary hover:bg-primary/90 text-primary-foreground font-medium shadow-xs px-12"
                            :disabled="stats ? !stats.can_create_project : false"
                        >
                            <Plus class="mr-1.5 h-4 w-4" />
                            Buat Proyek 3D
                        </Button>
                    </div>
                </div>

                <!-- Summary Stat Cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Card 1: Proyek Arsitek -->
                    <div class="rounded-xl border bg-card p-5 shadow-xs transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Proyek Arsitek (Milik Saya)
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <Box class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-bold">{{ stats?.owned_count ?? 0 }}</span>
                            <span class="text-xs text-muted-foreground">/ {{ stats?.max_projects ?? 3 }} kuota</span>
                        </div>
                        <!-- Quota progress -->
                        <div class="mt-3 h-1.5 w-full rounded-full bg-secondary overflow-hidden">
                            <div
                                class="h-full bg-primary rounded-full transition-all"
                                :style="{
                                    width: `${Math.min(100, (((stats?.owned_count ?? 0) / (stats?.max_projects ?? 3)) * 100))}%`,
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- Card 2: Proyek Klien -->
                    <div class="rounded-xl border bg-card p-5 shadow-xs transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Proyek Klien (Reviewer)
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Users class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-bold">{{ stats?.client_count ?? 0 }}</span>
                            <span class="text-xs text-muted-foreground">proyek kolaborasi</span>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Diundang oleh arsitek untuk meninjau model
                        </p>
                    </div>

                    <!-- Card 3: Proteksi Revisi -->
                    <div class="rounded-xl border bg-card p-5 shadow-xs transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Batas Revisi Klien
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                <Clock class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-bold">Maks. 3x</span>
                            <span class="text-xs text-muted-foreground">default per proyek</span>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Proteksi sistem otomatis dari revisi berlebihan
                        </p>
                    </div>

                    <!-- Card 4: Model Dual-Capacity -->
                    <div class="rounded-xl border bg-card p-5 shadow-xs transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Kapabilitas Akun
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">Dual-Capacity</span>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Bisa jadi Arsitek & Klien tanpa switch akun
                        </p>
                    </div>
                </div>

                <!-- Tab Navigation (Architect vs Client) -->
                <div class="flex flex-col gap-4 border-b pb-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col md:flex-row items-center gap-2">
                        <button
                            class="border border-primary"
                            @click="activeTab = 'architect'"
                            :class="[
                                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition',
                                activeTab === 'architect'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <Box class="h-4 w-4" />
                            <span>Proyek Arsitek (Milik Saya)</span>
                            <span
                                :class="[
                                    'ml-1 rounded-full px-2 py-0.5 text-xs',
                                    activeTab === 'architect'
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                {{ ownedProjects?.length ?? 0 }}
                            </span>
                        </button>

                        <button
                            @click="activeTab = 'client'"
                            class="border border-primary"
                            :class="[
                                'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium transition',
                                activeTab === 'client'
                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <Users class="h-4 w-4" />
                            <span>Proyek Kolaborasi (Sebagai Klien)</span>
                            <span
                                :class="[
                                    'ml-1 rounded-full px-2 py-0.5 text-xs',
                                    activeTab === 'client'
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                ]"
                            >
                                {{ clientProjects?.length ?? 0 }}
                            </span>
                        </button>
                    </div>

                    <!-- Search box -->
                    <div class="w-full sm:w-64">
                        <Input
                            v-model="searchQuery"
                            placeholder="Cari judul proyek..."
                            class="h-9 text-xs"
                        />
                    </div>
                </div>

                <!-- TAB 1: ARCHITECT PROJECTS CONTENT -->
                <div v-if="activeTab === 'architect'">
                    <!-- Empty State -->
                    <div
                        v-if="filteredOwnedProjects.length === 0"
                        class="flex min-h-[340px] flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center"
                    >
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <FolderOpen class="h-8 w-8" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Belum Ada Proyek 3D</h3>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            Unggah model 3D pertama Anda (.glb) untuk memvisualisasikan arsitektur dan undang Klien untuk memberikan pin komentar revisi.
                        </p>
                        <Button
                            @click="isCreateModalOpen = true"
                            class="mt-5"
                            :disabled="stats ? !stats.can_create_project : false"
                        >
                            <Plus class="mr-1.5 h-4 w-4" /> Unggah Proyek Pertama
                        </Button>
                    </div>

                    <!-- Projects Grid -->
                    <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="project in filteredOwnedProjects"
                            :key="project.id"
                            class="flex flex-col justify-between overflow-hidden rounded-xl border bg-card shadow-xs transition hover:shadow-md"
                        >
                            <!-- Card Header & 3D Thumbnail Banner -->
                            <div>
                                <div class="relative flex h-36 w-full items-center justify-center bg-linear-to-br from-slate-800 to-slate-950 text-white overflow-hidden">
                                    <Box class="h-16 w-16 opacity-30 transition-transform group-hover:scale-110" />
                                    
                                    <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                        <Badge variant="secondary" class="bg-black/60 text-white backdrop-blur-xs text-[11px]">
                                            3D GLB
                                        </Badge>
                                        <Badge
                                            v-if="project.is_draco_compressed"
                                            class="bg-emerald-500/80 text-white text-[10px]"
                                        >
                                            Draco
                                        </Badge>
                                    </div>

                                    <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                        <button
                                            @click="openEditModal(project)"
                                            title="Edit Data Proyek"
                                            class="rounded-full bg-black/50 p-1.5 text-slate-300 hover:bg-primary hover:text-white transition"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            @click="deleteProject(project)"
                                            title="Hapus Proyek"
                                            class="rounded-full bg-black/50 p-1.5 text-slate-300 hover:bg-rose-600 hover:text-white transition"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>

                                    <div class="absolute bottom-2 right-3 text-[11px] text-slate-300">
                                        {{ formatBytes(project.file_size_bytes) }}
                                    </div>
                                </div>

                                <!-- Card Content -->
                                <div class="p-5">
                                    <h3 class="font-bold text-base line-clamp-1" :title="project.title">
                                        {{ project.title }}
                                    </h3>
                                    <p class="mt-1 text-xs text-muted-foreground line-clamp-2 min-h-[32px]">
                                        {{ project.description || 'Tidak ada deskripsi proyek.' }}
                                    </p>

                                    <!-- Revision Limit Tracker -->
                                    <div class="mt-4 rounded-lg bg-muted/60 p-3">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-medium text-muted-foreground">Kuota Revisi Klien:</span>
                                            <span class="font-bold">
                                                {{ project.current_revision_count }} / {{ project.max_revisions_allowed }}
                                            </span>
                                        </div>
                                        <div class="mt-2 h-1.5 w-full rounded-full bg-secondary overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="[
                                                    project.has_reached_revision_limit
                                                        ? 'bg-rose-500'
                                                        : project.current_revision_count > 0
                                                        ? 'bg-amber-500'
                                                        : 'bg-emerald-500',
                                                ]"
                                                :style="{
                                                    width: `${Math.min(100, (project.current_revision_count / project.max_revisions_allowed) * 100)}%`,
                                                }"
                                            ></div>
                                        </div>
                                        <div class="mt-1.5 flex items-center justify-between text-[11px]">
                                            <span
                                                v-if="project.has_reached_revision_limit"
                                                class="font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1"
                                            >
                                                <AlertTriangle class="h-3 w-3" /> Batas revisi habis
                                            </span>
                                            <span v-else class="text-emerald-600 dark:text-emerald-400">
                                                Revisi terbuka
                                            </span>
                                            <span class="text-muted-foreground">{{ project.created_at }}</span>
                                        </div>
                                    </div>

                                    <!-- Collaborators pill -->
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground">Klien Kolaborator:</span>
                                        <button
                                            @click="openClientModal(project)"
                                            class="font-medium text-primary hover:underline flex items-center gap-1"
                                        >
                                            <UserCheck class="h-3.5 w-3.5" />
                                            {{ project.invited_clients.length }} Klien
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="border-t bg-muted/20 p-4 flex items-center gap-2">
                                <Link
                                    :href="`/projects/${project.id}/viewer`"
                                    class="flex-1"
                                >
                                    <Button class="w-full text-xs font-semibold" size="sm">
                                        <Box class="mr-1.5 h-3.5 w-3.5" /> Buka 3D Viewer
                                    </Button>
                                </Link>
                                <Button
                                    @click="openEditModal(project)"
                                    variant="outline"
                                    size="sm"
                                    class="text-xs"
                                    title="Edit Data Proyek"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    @click="openClientModal(project)"
                                    variant="outline"
                                    size="sm"
                                    class="text-xs"
                                    title="Undang / Kelola Klien"
                                >
                                    <UserPlus class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: CLIENT PROJECTS CONTENT -->
                <div v-if="activeTab === 'client'">
                    <!-- Empty State -->
                    <div
                        v-if="filteredClientProjects.length === 0"
                        class="flex min-h-[340px] flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center"
                    >
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <Users class="h-8 w-8" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Belum Ada Proyek Kolaborasi</h3>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            Ketika seorang arsitek mengundang email Anda (<strong>{{ auth?.user?.email }}</strong>) ke proyek mereka, proyek tersebut akan langsung muncul di sini secara otomatis.
                        </p>
                    </div>

                    <!-- Client Projects Grid -->
                    <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="project in filteredClientProjects"
                            :key="project.invitation_id"
                            class="flex flex-col justify-between overflow-hidden rounded-xl border bg-card shadow-xs transition hover:shadow-md"
                        >
                            <div>
                                <!-- Header Status -->
                                <div class="flex items-center justify-between border-b p-4 bg-muted/40">
                                    <div class="flex items-center gap-1.5">
                                        <Badge
                                            v-if="project.invitation_status === 'accepted'"
                                            class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[11px] border border-emerald-500/30 font-medium"
                                        >
                                            <CheckCircle2 class="mr-1 h-3 w-3" /> Akses Aktif (Reviewer)
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[11px] border border-amber-500/30 font-medium"
                                        >
                                            <Clock class="mr-1 h-3 w-3" /> Menunggu Konfirmasi
                                        </Badge>
                                    </div>
                                    <span class="text-[11px] text-muted-foreground">{{ project.invited_at }}</span>
                                </div>

                                <!-- Body -->
                                <div class="p-5">
                                    <h3 class="font-bold text-base line-clamp-1" :title="project.title">
                                        {{ project.title }}
                                    </h3>
                                    <p class="mt-1 text-xs text-muted-foreground line-clamp-2 min-h-[32px]">
                                        {{ project.description || 'Proyek visualisasi arsitektur 3D dari klien.' }}
                                    </p>

                                    <!-- Architect information -->
                                    <div class="mt-4 rounded-lg bg-muted/60 p-3 text-xs space-y-1">
                                        <div class="text-muted-foreground">Arsitek Pengundang:</div>
                                        <div class="font-semibold text-foreground">
                                            {{ project.architect_name }}
                                        </div>
                                        <div class="text-muted-foreground text-[11px]">
                                            {{ project.architect_email }}
                                        </div>
                                    </div>

                                    <!-- Revision status -->
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground">Revisi Terpakai:</span>
                                        <span class="font-semibold">
                                            {{ project.current_revision_count }} / {{ project.max_revisions_allowed }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="border-t bg-muted/20 p-4">
                                <div v-if="project.invitation_status === 'pending'">
                                    <Button
                                        @click="acceptInvitation(project)"
                                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold"
                                        size="sm"
                                    >
                                        <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" /> Terima Undangan & Buka Viewer
                                    </Button>
                                </div>
                                <div v-else>
                                    <Link :href="`/projects/${project.id}/viewer`">
                                        <Button class="w-full text-xs font-semibold" size="sm">
                                            <Box class="mr-1.5 h-3.5 w-3.5" /> Buka 3D Viewer & Beri Feedback
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: BUAT PROYEK BARU -->
            <Dialog :open="isCreateModalOpen" @update:open="isCreateModalOpen = $event">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Box class="h-5 w-5 text-primary" /> Buat Proyek 3D Baru
                        </DialogTitle>
                        <DialogDescription>
                            Unggah model 3D berformat <code>.glb</code> atau <code>.gltf</code> untuk mulai berkolaborasi dengan Klien Anda.
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitCreateProject" class="space-y-4 py-2">
                        <!-- Judul Proyek -->
                        <div class="space-y-1.5">
                            <Label for="title">Judul Proyek <span class="text-rose-500">*</span></Label>
                            <Input
                                id="title"
                                v-model="createForm.title"
                                placeholder="Contoh: Desain Villa Modern Canggu"
                                required
                            />
                            <p v-if="createForm.errors.title" class="text-xs text-rose-500">
                                {{ createForm.errors.title }}
                            </p>
                        </div>

                        <!-- Deskripsi -->
                        <div class="space-y-1.5">
                            <Label for="description">Deskripsi Proyek (Opsional)</Label>
                            <textarea
                                id="description"
                                v-model="createForm.description"
                                placeholder="Catatan konsep arsitektur, lokasi, atau arahan khusus klien..."
                                rows="2"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            ></textarea>
                        </div>

                        <!-- Batas Maksimal Revisi Klien -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <Label for="max_revisions">Batas Revisi Klien</Label>
                                <span class="text-xs text-muted-foreground">Default: 3x</span>
                            </div>
                            <Input
                                id="max_revisions"
                                type="number"
                                v-model.number="createForm.max_revisions_allowed"
                                min="1"
                                max="10"
                                required
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Klien akan dibatasi hanya dapat membuat thread revisi baru hingga batas ini tercapai.
                            </p>
                        </div>

                        <!-- File Upload Input -->
                        <div class="space-y-1.5">
                            <Label>File Model 3D (.glb / .gltf) <span class="text-rose-500">*</span></Label>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".glb,.gltf"
                                @change="onFileSelected"
                                class="hidden"
                                required
                            />
                            <div
                                @click="fileInputRef?.click()"
                                class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-input p-6 text-center cursor-pointer hover:border-primary/60 hover:bg-primary/5 transition"
                            >
                                <FileUp class="h-8 w-8 text-muted-foreground mb-2" />
                                <div v-if="selectedFileName" class="text-sm font-semibold text-primary">
                                    {{ selectedFileName }} ({{ selectedFileSize }})
                                </div>
                                <div v-else class="text-sm font-medium">
                                    Klik untuk memilih file 3D (.glb)
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-1">
                                    Mendukung model hingga 100 MB
                                </p>
                            </div>

                            <p v-if="fileError" class="text-xs font-semibold text-rose-500 mt-1">
                                {{ fileError }}
                            </p>
                            <p v-if="createForm.errors.file" class="text-xs font-semibold text-rose-500 mt-1">
                                {{ createForm.errors.file }}
                            </p>
                        </div>

                        <DialogFooter class="gap-2 sm:gap-0 pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="isCreateModalOpen = false"
                            >
                                Batal
                            </Button>
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

            <!-- MODAL: EDIT PROYEK -->
            <Dialog :open="isEditModalOpen" @update:open="isEditModalOpen = $event">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Pencil class="h-5 w-5 text-primary" /> Edit Data Proyek 3D
                        </DialogTitle>
                        <DialogDescription>
                            Perbarui informasi proyek atau ganti file model 3D (.glb / .gltf) yang telah diunggah.
                        </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="submitEditProject" class="space-y-4 py-2">
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
                                <Label>Ganti File Model 3D (.glb / .gltf)</Label>
                                <span class="text-xs text-muted-foreground">Opsional</span>
                            </div>
                            <input
                                ref="editFileInputRef"
                                type="file"
                                accept=".glb,.gltf"
                                @change="onEditFileSelected"
                                class="hidden"
                            />
                            <div
                                @click="editFileInputRef?.click()"
                                class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-input p-6 text-center cursor-pointer hover:border-primary/60 hover:bg-primary/5 transition"
                            >
                                <FileUp class="h-8 w-8 text-muted-foreground mb-2" />
                                <div v-if="editSelectedFileName" class="text-sm font-semibold text-primary">
                                    {{ editSelectedFileName }} ({{ editSelectedFileSize }})
                                </div>
                                <div v-else class="text-sm font-medium">
                                    Klik jika ingin mengganti file model 3D saat ini ({{ formatBytes(editingProject?.file_size_bytes ?? 0) }})
                                </div>
                                <p class="text-[11px] text-muted-foreground mt-1">
                                    Biarkan kosong jika tidak ingin mengubah file 3D
                                </p>
                            </div>

                            <p v-if="editFileError" class="text-xs font-semibold text-rose-500 mt-1">
                                {{ editFileError }}
                            </p>
                            <p v-if="editForm.errors.file" class="text-xs font-semibold text-rose-500 mt-1">
                                {{ editForm.errors.file }}
                            </p>
                        </div>

                        <DialogFooter class="gap-2 sm:gap-0 pt-3">
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

            <!-- MODAL: KELOLA AKSES KLIEN -->
            <Dialog :open="isClientModalOpen" @update:open="isClientModalOpen = $event">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5 text-primary" /> Kelola Klien Reviewer
                        </DialogTitle>
                        <DialogDescription>
                            Undang Klien via email untuk meninjau model 3D dan memberikan pin anotasi pada
                            <strong>{{ activeProjectForClients?.title }}</strong>.
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Invite Form -->
                    <form @submit.prevent="submitInviteClient" class="space-y-3 pt-2">
                        <div class="space-y-1.5">
                            <Label for="client_email">Email Klien</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="client_email"
                                    type="email"
                                    v-model="inviteForm.email"
                                    placeholder="klien@gmail.com"
                                    required
                                    class="text-sm"
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    :disabled="inviteForm.processing || !inviteForm.email"
                                >
                                    <Send class="h-3.5 w-3.5 mr-1" /> Undang
                                </Button>
                            </div>
                            <p v-if="inviteForm.errors.email" class="text-xs text-rose-500">
                                {{ inviteForm.errors.email }}
                            </p>
                        </div>
                    </form>

                    <!-- Invited Clients List -->
                    <div class="mt-4 space-y-2">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Daftar Klien Diundang ({{ activeProjectForClients?.invited_clients.length ?? 0 }})
                        </h4>

                        <div
                            v-if="!activeProjectForClients?.invited_clients.length"
                            class="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground"
                        >
                            Belum ada klien yang diundang untuk proyek ini.
                        </div>

                        <div v-else class="max-h-48 overflow-y-auto space-y-2 pr-1">
                            <div
                                v-for="client in activeProjectForClients.invited_clients"
                                :key="client.id"
                                class="flex items-center justify-between rounded-lg border p-2.5 text-xs bg-muted/30"
                            >
                                <div>
                                    <div class="font-medium text-foreground">{{ client.email }}</div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-0.5">
                                        <Badge
                                            v-if="client.status === 'accepted'"
                                            class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] py-0"
                                        >
                                            Accepted
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] py-0"
                                        >
                                            Pending
                                        </Badge>
                                        <span>{{ client.invited_at }}</span>
                                    </div>
                                </div>

                                <button
                                    @click="revokeClient(client)"
                                    title="Cabut Akses Klien"
                                    class="rounded p-1 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-600 transition"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="pt-3">
                        <DialogClose as-child>
                            <Button variant="outline" size="sm">Tutup</Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppSwissLayout>
</template>
