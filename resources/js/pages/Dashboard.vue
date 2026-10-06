<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Box,
    CheckCircle2,
    Clock,
    CreditCard,
    ExternalLink,
    FileText,
    FileUp,
    FolderGit2,
    FolderOpen,
    Layers,
    Pencil,
    Plus,
    Receipt,
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
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppHeader from '@/components/app/AppHeader.vue';
import { useSidebar } from '@/composables/useSidebar';
import { echo } from '@/lib/echo';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useConfirm } from '@/composables/useConfirm';
import { usePageLoading } from '@/composables/usePageLoading';
import SkeletonStatCards from '@/components/skeletons/SkeletonStatCards.vue';
import SkeletonProjectGrid from '@/components/skeletons/SkeletonProjectGrid.vue';
import { Skeleton } from '@/components/ui/skeleton';

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

interface Transaction {
    id: string;
    order_id: string;
    plan_name: string;
    billing_type: string;
    amount: string;
    payment_type: string;
    status: string;
    created_at: string;
    paid_at: string | null;
}

interface TransactionPage {
    data: Transaction[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
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
    transactions?: TransactionPage;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);

// ── Local copy of ownedProjects agar bisa di-mutate realtime ──────────────
const localOwnedProjects = ref<OwnedProject[]>([]);

// ── Local copy of clientProjects untuk realtime update tab Kolaborasi ──────
const localClientProjects = ref<ClientProject[]>([]);

// ── Realtime: update status klien saat event ClientStatusUpdated diterima ──
function handleClientStatusUpdated(payload: {
    project_id: string;
    client_id: string;
    email: string;
    status: string;
    accepted_at: string | null;
}) {
    // Update di localOwnedProjects
    const project = localOwnedProjects.value.find(p => p.id === payload.project_id);
    if (project) {
        const client = project.invited_clients.find(c => c.id === payload.client_id);
        if (client) {
            client.status = payload.status as 'pending' | 'accepted' | 'revoked';
            client.accepted_at = payload.accepted_at;
        }
    }

    // Juga update activeProjectForClients jika modal sedang terbuka untuk project itu
    if (activeProjectForClients.value?.id === payload.project_id) {
        const client = activeProjectForClients.value.invited_clients.find(c => c.id === payload.client_id);
        if (client) {
            client.status = payload.status as 'pending' | 'accepted' | 'revoked';
            client.accepted_at = payload.accepted_at;
        }
    }
}

// ── Realtime: proyek kolaborasi baru muncul saat arsitek mengundang ────────
function handleInvitationReceived(payload: ClientProject) {
    // Cegah duplikat jika sudah ada
    const alreadyExists = localClientProjects.value.some(
        p => p.invitation_id === payload.invitation_id
    );
    if (alreadyExists) return;

    // Prepend proyek baru ke atas list
    localClientProjects.value.unshift(payload);

    // Switch otomatis ke tab client agar user langsung melihat undangan baru
    activeTab.value = 'client';
}

// ── Realtime: proyek langsung hilang saat akses dicabut arsitek ────────────
function handleAccessRevoked(payload: { project_id: string; invitation_id: string }) {
    localClientProjects.value = localClientProjects.value.filter(
        p => p.invitation_id !== payload.invitation_id
    );
}

onMounted(() => {
    // Sync local copy dari props awal
    localOwnedProjects.value = [...(props.ownedProjects ?? [])];
    localClientProjects.value = [...(props.clientProjects ?? [])];

    // Subscribe ke private channel user — satu channel untuk semua event user ini
    const userId = (page.props.auth as any)?.user?.id;
    if (userId) {
        echo.private(`App.Models.User.${userId}`)
            .listen('.client.status.updated', handleClientStatusUpdated)
            .listen('.invitation.received', handleInvitationReceived)
            .listen('.access.revoked', handleAccessRevoked);
    }
});

onBeforeUnmount(() => {
    const userId = (page.props.auth as any)?.user?.id;
    if (userId) {
        echo.leave(`App.Models.User.${userId}`);
    }
});

// Sync local copy saat Inertia props diperbarui (invite/revoke/page reload)
watch(() => props.ownedProjects, (updated) => {
    if (!updated) return;
    // Merge: pertahankan status realtime yang sudah diupdate, update sisanya dari props
    localOwnedProjects.value = updated.map(proj => {
        const existing = localOwnedProjects.value.find(p => p.id === proj.id);
        if (!existing) return proj;
        // Gabungkan: clients dari props tapi status bisa sudah diupdate realtime
        return {
            ...proj,
            invited_clients: proj.invited_clients.map(c => {
                const existingClient = existing.invited_clients.find(ec => ec.id === c.id);
                // Pakai status realtime jika sudah accepted, karena props mungkin belum refresh
                if (existingClient && existingClient.status === 'accepted' && c.status === 'pending') {
                    return { ...c, status: 'accepted', accepted_at: existingClient.accepted_at };
                }
                return c;
            }),
        };
    });
}, { deep: true });

// Sync clientProjects dari props saat Inertia reload
watch(() => props.clientProjects, (updated) => {
    if (!updated) return;
    // Merge: tambahkan item baru dari props, pertahankan yang sudah ada via realtime
    const existingIds = new Set(localClientProjects.value.map(p => p.invitation_id));
    const newItems = updated.filter(p => !existingIds.has(p.invitation_id));
    if (newItems.length > 0) {
        localClientProjects.value.push(...newItems);
    }
    // Update status item yang sudah ada
    localClientProjects.value = localClientProjects.value.map(local => {
        const fresh = updated.find(p => p.invitation_id === local.invitation_id);
        return fresh ?? local;
    });
}, { deep: true });

// Active Tab: 'architect' | 'client' | 'billing'
const activeTab = ref<'architect' | 'client' | 'billing'>('architect');
const searchQuery = ref('');

// Filtered lists
const filteredOwnedProjects = computed(() => {
    const list = localOwnedProjects.value;
    if (!searchQuery.value.trim()) return list;
    const query = searchQuery.value.toLowerCase();
    return list.filter(
        (p) =>
            p.title.toLowerCase().includes(query) ||
            (p.description && p.description.toLowerCase().includes(query))
    );
});

const filteredClientProjects = computed(() => {
    const list = localClientProjects.value;
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

// ── Email suggest state ───────────────────────────────────
interface UserSuggestion {
    id: string;
    name: string;
    email: string;
    initial: string;
}
const suggestions = ref<UserSuggestion[]>([]);
const suggestLoading = ref(false);
const suggestEmpty = ref(false);      // true saat query ≥ 2 char tapi 0 hasil
const showSuggestions = ref(false);
let suggestTimer: ReturnType<typeof setTimeout> | null = null;

function onEmailInput() {
    const val = inviteForm.email.trim();
    showSuggestions.value = false;
    suggestEmpty.value = false;

    if (suggestTimer) clearTimeout(suggestTimer);

    if (val.length < 2) {
        suggestions.value = [];
        return;
    }

    suggestTimer = setTimeout(async () => {
        suggestLoading.value = true;
        try {
            const res = await fetch(`/users/search?email=${encodeURIComponent(val)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                const data = await res.json();
                suggestions.value = data.data ?? [];
                suggestEmpty.value = suggestions.value.length === 0;
                showSuggestions.value = true;
            }
        } catch {
            suggestions.value = [];
        } finally {
            suggestLoading.value = false;
        }
    }, 300);
}

function selectSuggestion(user: UserSuggestion) {
    inviteForm.email = user.email;
    showSuggestions.value = false;
    suggestions.value = [];
    suggestEmpty.value = false;
}

function closeSuggestions() {
    // Delay sedikit agar klik item suggestion sempat terpanggil
    setTimeout(() => { showSuggestions.value = false; }, 150);
}

function openClientModal(project: OwnedProject) {
    // Pakai dari localOwnedProjects agar status realtime sudah terupdate
    const localProject = localOwnedProjects.value.find(p => p.id === project.id) ?? project;
    activeProjectForClients.value = localProject;
    inviteForm.reset();
    suggestions.value = [];
    showSuggestions.value = false;
    suggestEmpty.value = false;
    isClientModalOpen.value = true;
}

function submitInviteClient() {
    if (!activeProjectForClients.value) return;
    inviteForm.post(`/projects/${activeProjectForClients.value.id}/invite`, {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            suggestions.value = [];
            showSuggestions.value = false;
            suggestEmpty.value = false;
            // Refresh dari localOwnedProjects dulu, fallback ke props
            const updated = localOwnedProjects.value.find((p) => p.id === activeProjectForClients.value?.id)
                ?? props.ownedProjects?.find((p) => p.id === activeProjectForClients.value?.id);
            if (updated) {
                activeProjectForClients.value = updated;
            }
        },
    });
}

async function revokeClient(client: ProjectClientItem) {
    if (!activeProjectForClients.value) return;

    const ok = await confirm({
        title: 'Cabut Akses Klien?',
        description: `Akses ${client.email} akan dicabut. Mereka tidak bisa lagi membuka viewer atau memberi pin komentar pada proyek ini.`,
        confirmText: 'Ya, Cabut Akses',
        cancelText: 'Batal',
        variant: 'destructive',
        icon: 'trash',
    });

    if (!ok) return;

    router.delete(`/projects/${activeProjectForClients.value.id}/clients/${client.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Akses klien ${client.email} berhasil dicabut.`);
            const updated = localOwnedProjects.value.find((p) => p.id === activeProjectForClients.value?.id)
                ?? props.ownedProjects?.find((p) => p.id === activeProjectForClients.value?.id);
            if (updated) {
                activeProjectForClients.value = updated;
            }
        },
        onError: () => {
            toast.error('Gagal mencabut akses klien. Coba lagi.');
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
async function deleteProject(project: OwnedProject) {
    const ok = await confirm({
        title: 'Hapus Proyek?',
        description: `Proyek "${project.title}" beserta semua file dan versinya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'destructive',
        icon: 'trash',
    });

    if (!ok) return;

    // Optimistic: hapus dari UI sebelum server merespons
    localOwnedProjects.value = localOwnedProjects.value.filter(p => p.id !== project.id);

    router.delete(`/projects/${project.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Proyek "${project.title}" berhasil dihapus.`);
        },
        onError: () => {
            // Rollback: kembalikan project ke list jika gagal
            localOwnedProjects.value = [...(props.ownedProjects ?? [])];
            toast.error('Gagal menghapus proyek. Coba lagi.');
        },
    });
}

function formatBytes(bytes: number): string {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// ── Billing helpers ───────────────────────────────────────
function txStatusColor(status: string) {
    const map: Record<string, string> = {
        pending:    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/25',
        settlement: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/25',
        cancel:     'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/25',
        expire:     'bg-neutral-100 text-neutral-500 dark:bg-neutral-500/15 dark:text-neutral-400 border-neutral-200 dark:border-neutral-500/25',
    };
    return map[status] ?? 'bg-muted text-muted-foreground border-border';
}

function txStatusLabel(status: string) {
    const map: Record<string, string> = {
        pending:    'Menunggu',
        settlement: 'Lunas',
        cancel:     'Dibatalkan',
        expire:     'Kadaluarsa',
    };
    return map[status] ?? status;
}

function txPaymentLabel(type: string) {
    const map: Record<string, string> = {
        bank_transfer: 'Transfer Bank',
        midtrans:      'Payment Gateway',
        manual:        'Aktivasi Manual',
    };
    return map[type] ?? type;
}

function goTxPage(page: number) {
    router.get('/dashboard', { tx_page: page }, { preserveState: true, preserveScroll: true });
}

// Sidebar state
const { isSidebarOpen, isMobile } = useSidebar();
const { confirm } = useConfirm();
const { isLoading } = usePageLoading(80);

</script>

<template>
    <Head title="Dashboard - PitchArch Spatial CAD" />

    <!-- Main Container with Sidebar -->
    <div class="min-h-screen bg-[#F8F9FA] dark:bg-[#0b0c10] text-slate-900 dark:text-[#f3f4f6] font-['Geist',sans-serif] antialiased selection:bg-sky-200 dark:selection:bg-[#38bdf8]/20 selection:text-slate-900 dark:selection:text-white relative overflow-x-hidden flex transition-colors duration-300">
        
        <!-- Ambient Background Lighting (Only in dark mode) -->
        <div class="hidden dark:block fixed top-0 left-64 w-[650px] h-[400px] bg-[#38bdf8]/10 blur-[150px] pointer-events-none rounded-full"></div>
        <div class="hidden dark:block fixed top-1/2 right-10 w-[500px] h-[500px] bg-[#6366f1]/10 blur-[170px] pointer-events-none rounded-full"></div>
        <div class="hidden dark:block fixed bottom-0 left-80 w-[550px] h-[350px] bg-[#2e303d]/20 blur-[130px] pointer-events-none rounded-full"></div>

        <!-- Sidebar Component -->
        <AppSidebar />

        <!-- Right Wrapper (Header + Main Content) -->
        <div 
            :class="[
                'flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out',
                isSidebarOpen && !isMobile ? 'ml-64' : 'ml-0 lg:ml-16'
            ]"
        >
            
            <!-- Header Component -->
            <AppHeader 
                @create-project="isCreateModalOpen = true"
            />

            <!-- Main Content Workspace -->
            <main class="flex-1 p-6 lg:p-8 space-y-6 w-full relative z-10">

                <!-- ── SKELETON STATE ── -->
                <template v-if="isLoading">
                    <!-- Header skeleton -->
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="space-y-2">
                            <Skeleton class="h-8 w-64" />
                            <Skeleton class="h-4 w-96 max-w-full" />
                        </div>
                        <Skeleton class="h-10 w-36 rounded-full" />
                    </div>
                    <!-- Stat cards skeleton -->
                    <SkeletonStatCards :count="4" />
                    <!-- Tabs skeleton -->
                    <div class="flex gap-3 border-b border-slate-200 dark:border-white/15 pb-2">
                        <Skeleton class="h-10 w-48 rounded-xl" />
                        <Skeleton class="h-10 w-52 rounded-xl" />
                        <div class="ml-auto"><Skeleton class="h-9 w-48 rounded-xl" /></div>
                    </div>
                    <!-- Project grid skeleton -->
                    <SkeletonProjectGrid :count="3" />
                </template>

                <!-- ── REAL CONTENT ── -->
                <template v-else>
                <!-- Flash Message -->
                <div
                    v-if="flashSuccess"
                    class="flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-200 shadow-sm"
                >
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <p class="text-sm font-medium">{{ flashSuccess }}</p>
                </div>

                <!-- Limit Warning Banner -->
                <div
                    v-if="stats?.limit_warning"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 dark:border-amber-500/20 bg-amber-50 dark:bg-amber-500/10 p-4 text-amber-800 dark:text-amber-200 shadow-sm"
                >
                    <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" />
                    <div class="flex-1">
                        <p class="text-sm font-semibold mb-1">Project Limit Exceeded</p>
                        <p class="text-sm">{{ stats.limit_warning.message }}</p>
                        <p class="text-xs mt-2 text-amber-600 dark:text-amber-300">
                            You cannot create new projects until you upgrade your plan or delete existing projects.
                        </p>
                    </div>
                </div>

                <!-- Welcome & Header Section -->
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-neutral-100 md:text-3xl">
                                Selamat Datang, {{ auth?.user?.name ?? 'Pengguna' }}
                            </h1>
                            <Badge
                                v-if="stats?.subscription_status === 'pro'"
                                class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-semibold text-xs shadow-[0_0_20px_rgba(245,158,11,0.3)]"
                            >
                                <Sparkles class="mr-1 h-3.5 w-3.5" /> PRO TIER
                            </Badge>
                            <Badge
                                v-else
                                class="bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-neutral-300 text-xs font-medium border border-slate-200 dark:border-white/15"
                            >
                                FREE TIER ({{ stats?.owned_count ?? 0 }}/{{ stats?.max_projects ?? 3 }} Proyek)
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-slate-500 dark:text-neutral-300 leading-relaxed">
                            Kelola proyek arsitektur 3D Anda dan berikan feedback revisi secara interaktif dengan Klien.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            @click="isCreateModalOpen = true"
                            class="bg-slate-900 dark:bg-white text-white dark:text-black hover:bg-slate-700 dark:hover:bg-neutral-100 font-semibold shadow-sm dark:shadow-[0_0_35px_rgba(255,255,255,0.25)] transition-all duration-300 hover:scale-[1.02] px-6 rounded-full"
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
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-5 shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_35px_rgba(99,102,241,0.2)] hover:border-slate-300 dark:hover:border-white/30 group">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-neutral-400">
                                Proyek Arsitek (Milik Saya)
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/30">
                                <Box class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ stats?.owned_count ?? 0 }}</span>
                            <span class="text-xs text-slate-400 dark:text-neutral-400">/ {{ stats?.max_projects ?? 3 }} kuota</span>
                        </div>
                        <!-- Quota progress -->
                        <div class="mt-3 h-1.5 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                            <div
                                class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full transition-all"
                                :style="{
                                    width: `${Math.min(100, (((stats?.owned_count ?? 0) / (stats?.max_projects ?? 3)) * 100))}%`,
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- Card 2: Proyek Klien -->
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-5 shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_35px_rgba(59,130,246,0.2)] hover:border-slate-300 dark:hover:border-white/30 group">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-neutral-400">
                                Proyek Klien (Reviewer)
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-500/30">
                                <Users class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ localClientProjects.length }}</span>
                            <span class="text-xs text-slate-400 dark:text-neutral-400">proyek kolaborasi</span>
                        </div>
                        <p class="mt-3 text-xs text-slate-400 dark:text-neutral-400">
                            Diundang oleh arsitek untuk meninjau model
                        </p>
                    </div>

                    <!-- Card 3: Proteksi Revisi -->
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-5 shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_35px_rgba(245,158,11,0.2)] hover:border-slate-300 dark:hover:border-white/30 group">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-neutral-400">
                                Batas Revisi Klien
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-500/30">
                                <Clock class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-2xl font-extrabold text-slate-900 dark:text-white">Maks. 3x</span>
                            <span class="text-xs text-slate-400 dark:text-neutral-400">default per proyek</span>
                        </div>
                        <p class="mt-3 text-xs text-slate-400 dark:text-neutral-400">
                            Proteksi sistem otomatis dari revisi berlebihan
                        </p>
                    </div>

                    <!-- Card 4: Model Dual-Capacity -->
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-5 shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_35px_rgba(168,85,247,0.2)] hover:border-slate-300 dark:hover:border-white/30 group">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-neutral-400">
                                Kapabilitas Akun
                            </span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-purple-50 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 border border-purple-100 dark:border-purple-500/30">
                                <ShieldCheck class="h-5 w-5" />
                            </div>
                        </div>
                        <div class="mt-3 flex items-baseline gap-2">
                            <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">Dual-Capacity</span>
                        </div>
                        <p class="mt-3 text-xs text-slate-400 dark:text-neutral-400">
                            Bisa jadi Arsitek & Klien tanpa switch akun
                        </p>
                    </div>
                </div>

                <!-- Tab Navigation (Architect vs Client) -->
                <div class="flex flex-col gap-4 border-b border-slate-200 dark:border-white/15 pb-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col md:flex-row items-center gap-2">
                        <button
                            @click="activeTab = 'architect'"
                            :class="[
                                'flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-medium transition-all duration-300 border',
                                activeTab === 'architect'
                                    ? 'bg-slate-900 dark:bg-white text-white dark:text-black shadow-sm dark:shadow-[0_0_25px_rgba(255,255,255,0.2)] border-slate-900 dark:border-white'
                                    : 'text-slate-500 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-neutral-100 border-slate-200 dark:border-white/15',
                            ]"
                        >
                            <Box class="h-4 w-4" />
                            <span>Proyek Arsitek (Milik Saya)</span>
                            <span
                                :class="[
                                    'ml-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                    activeTab === 'architect'
                                        ? 'bg-white/20 dark:bg-black/10 text-white dark:text-black'
                                        : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-neutral-300',
                                ]"
                            >
                                {{ localOwnedProjects.length }}
                            </span>
                        </button>

                        <button
                            @click="activeTab = 'client'"
                            :class="[
                                'flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-medium transition-all duration-300 border',
                                activeTab === 'client'
                                    ? 'bg-slate-900 dark:bg-white text-white dark:text-black shadow-sm dark:shadow-[0_0_25px_rgba(255,255,255,0.2)] border-slate-900 dark:border-white'
                                    : 'text-slate-500 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-neutral-100 border-slate-200 dark:border-white/15',
                            ]"
                        >
                            <Users class="h-4 w-4" />
                            <span>Proyek Kolaborasi (Sebagai Klien)</span>
                            <span
                                :class="[
                                    'ml-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                    activeTab === 'client'
                                        ? 'bg-white/20 dark:bg-black/10 text-white dark:text-black'
                                        : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-neutral-300',
                                ]"
                            >
                                {{ localClientProjects.length }}
                            </span>
                        </button>

                        <!-- Tab 3: Riwayat Pembelian -->
                        <button
                            @click="activeTab = 'billing'"
                            :class="[
                                'flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-medium transition-all duration-300 border',
                                activeTab === 'billing'
                                    ? 'bg-slate-900 dark:bg-white text-white dark:text-black shadow-sm dark:shadow-[0_0_25px_rgba(255,255,255,0.2)] border-slate-900 dark:border-white'
                                    : 'text-slate-500 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-white/5 hover:text-slate-900 dark:hover:text-neutral-100 border-slate-200 dark:border-white/15',
                            ]"
                        >
                            <Receipt class="h-4 w-4" />
                            <span>Riwayat Pembelian</span>
                            <span
                                :class="[
                                    'ml-1 rounded-full px-2 py-0.5 text-xs font-semibold',
                                    activeTab === 'billing'
                                        ? 'bg-white/20 dark:bg-black/10 text-white dark:text-black'
                                        : 'bg-slate-100 dark:bg-white/10 text-slate-500 dark:text-neutral-300',
                                ]"
                            >
                                {{ transactions?.total ?? 0 }}
                            </span>
                        </button>
                    </div>

                    <!-- Search box (sembunyikan di tab billing) -->
                    <div class="w-full sm:w-64" v-if="activeTab !== 'billing'">
                        <Input
                            v-model="searchQuery"
                            placeholder="Cari judul proyek..."
                            class="h-9 text-xs bg-white dark:bg-white/5 border-slate-200 dark:border-white/15 text-slate-900 dark:text-neutral-100 placeholder:text-slate-400 dark:placeholder:text-neutral-400"
                        />
                    </div>
                </div>

                <!-- TAB 1: ARCHITECT PROJECTS CONTENT -->
                <div v-if="activeTab === 'architect'">
                    <!-- Empty State -->
                    <div
                        v-if="filteredOwnedProjects.length === 0"
                        class="flex min-h-[340px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 dark:border-white/20 bg-white dark:bg-white/[0.02] p-8 text-center"
                    >
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/30">
                            <FolderOpen class="h-8 w-8" />
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-neutral-100">Belum Ada Proyek 3D</h3>
                        <p class="mt-2 max-w-md text-sm text-slate-500 dark:text-neutral-300 leading-relaxed">
                            Unggah model 3D pertama Anda (.glb) untuk memvisualisasikan arsitektur dan undang Klien untuk memberikan pin komentar revisi.
                        </p>
                        <Button
                            @click="isCreateModalOpen = true"
                            class="mt-5 bg-slate-900 dark:bg-white text-white dark:text-black hover:bg-slate-700 dark:hover:bg-neutral-100 font-semibold shadow-sm dark:shadow-[0_0_25px_rgba(255,255,255,0.2)] rounded-full"
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
                            class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_45px_rgba(99,102,241,0.25)] hover:border-slate-300 dark:hover:border-white/30 hover:scale-[1.02] group"
                        >
                            <!-- Card Header & 3D Thumbnail Banner -->
                            <div>
                                <div class="relative flex h-36 w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-950 text-white overflow-hidden">
                                    <Box class="h-16 w-16 opacity-20 transition-transform duration-500 group-hover:scale-110 group-hover:opacity-30" />
                                    
                                    <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                                        <Badge class="bg-black/60 text-white backdrop-blur-xl text-[11px] border border-white/20">
                                            3D GLB
                                        </Badge>
                                        <Badge
                                            v-if="project.is_draco_compressed"
                                            class="bg-emerald-500/80 text-white text-[10px] border border-emerald-400/50"
                                        >
                                            Draco
                                        </Badge>
                                    </div>

                                    <div class="absolute top-3 right-3 flex items-center gap-1.5">
                                        <button
                                            @click="openEditModal(project)"
                                            title="Edit Data Proyek"
                                            class="rounded-full bg-black/50 border border-white/20 backdrop-blur-xl p-1.5 text-slate-300 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all duration-300"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            @click="deleteProject(project)"
                                            title="Hapus Proyek"
                                            class="rounded-full bg-black/50 border border-white/20 backdrop-blur-xl p-1.5 text-slate-300 hover:bg-rose-600 hover:text-white hover:border-rose-400 transition-all duration-300"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>

                                    <div class="absolute bottom-2 right-3 text-[11px] text-slate-300 font-mono">
                                        {{ formatBytes(project.file_size_bytes) }}
                                    </div>
                                </div>

                                <!-- Card Content -->
                                <div class="p-5">
                                    <h3 class="font-bold text-base line-clamp-1 text-slate-900 dark:text-neutral-100" :title="project.title">
                                        {{ project.title }}
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-400 dark:text-neutral-400 line-clamp-2 min-h-[32px]">
                                        {{ project.description || 'Tidak ada deskripsi proyek.' }}
                                    </p>

                                    <!-- Revision Limit Tracker -->
                                    <div class="mt-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-3">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-medium text-slate-500 dark:text-neutral-400">Kuota Revisi Klien:</span>
                                            <span class="font-bold text-slate-900 dark:text-neutral-100">
                                                {{ project.current_revision_count }} / {{ project.max_revisions_allowed }}
                                            </span>
                                        </div>
                                        <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="[
                                                    project.has_reached_revision_limit
                                                        ? 'bg-gradient-to-r from-rose-500 to-rose-600'
                                                        : project.current_revision_count > 0
                                                        ? 'bg-gradient-to-r from-amber-500 to-orange-500'
                                                        : 'bg-gradient-to-r from-emerald-500 to-green-500',
                                                ]"
                                                :style="{
                                                    width: `${Math.min(100, (project.current_revision_count / project.max_revisions_allowed) * 100)}%`,
                                                }"
                                            ></div>
                                        </div>
                                        <div class="mt-1.5 flex items-center justify-between text-[11px]">
                                            <span
                                                v-if="project.has_reached_revision_limit"
                                                class="font-semibold text-rose-500 dark:text-rose-400 flex items-center gap-1"
                                            >
                                                <AlertTriangle class="h-3 w-3" /> Batas revisi habis
                                            </span>
                                            <span v-else class="text-emerald-600 dark:text-emerald-400">
                                                Revisi terbuka
                                            </span>
                                            <span class="text-slate-400 dark:text-neutral-400">{{ project.created_at }}</span>
                                        </div>
                                    </div>

                                    <!-- Collaborators pill -->
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="text-slate-400 dark:text-neutral-400">Klien Kolaborator:</span>
                                        <button
                                            @click="openClientModal(project)"
                                            class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 hover:underline flex items-center gap-1 transition-colors"
                                        >
                                            <UserCheck class="h-3.5 w-3.5" />
                                            {{ project.invited_clients.length }} Klien
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="border-t border-slate-100 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] p-4 flex items-center gap-2">
                                <Link
                                    :href="`/projects/${project.id}/viewer`"
                                    class="flex-1"
                                >
                                    <Button class="w-full text-xs font-semibold bg-indigo-500 hover:bg-indigo-600 text-white shadow-[0_0_20px_rgba(99,102,241,0.3)] transition-all duration-300" size="sm">
                                        <Box class="mr-1.5 h-3.5 w-3.5" /> Buka 3D Viewer
                                    </Button>
                                </Link>
                                <Button
                                    @click="openEditModal(project)"
                                    variant="outline"
                                    size="sm"
                                    class="text-xs border-slate-200 dark:border-white/15 bg-white dark:bg-white/5 text-slate-500 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-white/30"
                                    title="Edit Data Proyek"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    @click="openClientModal(project)"
                                    variant="outline"
                                    size="sm"
                                    class="text-xs border-slate-200 dark:border-white/15 bg-white dark:bg-white/5 text-slate-500 dark:text-neutral-300 hover:bg-slate-50 dark:hover:bg-white/10 hover:text-slate-900 dark:hover:text-white hover:border-slate-300 dark:hover:border-white/30"
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
                        class="flex min-h-[340px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 dark:border-white/20 bg-white dark:bg-white/[0.02] p-8 text-center"
                    >
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-100 dark:border-blue-500/30">
                            <Users class="h-8 w-8" />
                        </div>
                        <h3 class="mt-4 text-lg font-bold text-slate-900 dark:text-neutral-100">Belum Ada Proyek Kolaborasi</h3>
                        <p class="mt-2 max-w-md text-sm text-slate-500 dark:text-neutral-300 leading-relaxed">
                            Ketika seorang arsitek mengundang email Anda (<strong class="text-slate-700 dark:text-neutral-100">{{ auth?.user?.email }}</strong>) ke proyek mereka, proyek tersebut akan langsung muncul di sini secara otomatis.
                        </p>
                    </div>

                    <!-- Client Projects Grid -->
                    <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="project in filteredClientProjects"
                            :key="project.invitation_id"
                            class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_45px_rgba(59,130,246,0.25)] hover:border-slate-300 dark:hover:border-white/30 hover:scale-[1.02] group"
                        >
                            <div>
                                <!-- Header Status -->
                                <div class="flex items-center justify-between border-b border-slate-100 dark:border-white/10 p-4 bg-slate-50 dark:bg-white/[0.02]">
                                    <div class="flex items-center gap-1.5">
                                        <Badge
                                            v-if="project.invitation_status === 'accepted'"
                                            class="bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-[11px] border border-emerald-200 dark:border-emerald-500/30 font-medium"
                                        >
                                            <CheckCircle2 class="mr-1 h-3 w-3" /> Akses Aktif (Reviewer)
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-[11px] border border-amber-200 dark:border-amber-500/30 font-medium"
                                        >
                                            <Clock class="mr-1 h-3 w-3" /> Menunggu Konfirmasi
                                        </Badge>
                                    </div>
                                    <span class="text-[11px] text-slate-400 dark:text-neutral-400">{{ project.invited_at }}</span>
                                </div>

                                <!-- Body -->
                                <div class="p-5">
                                    <h3 class="font-bold text-base line-clamp-1 text-slate-900 dark:text-neutral-100" :title="project.title">
                                        {{ project.title }}
                                    </h3>
                                    <p class="mt-1 text-xs text-slate-400 dark:text-neutral-400 line-clamp-2 min-h-[32px]">
                                        {{ project.description || 'Proyek visualisasi arsitektur 3D dari klien.' }}
                                    </p>

                                    <!-- Architect information -->
                                    <div class="mt-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-3 text-xs space-y-1">
                                        <div class="text-slate-400 dark:text-neutral-400">Arsitek Pengundang:</div>
                                        <div class="font-semibold text-slate-900 dark:text-neutral-100">
                                            {{ project.architect_name }}
                                        </div>
                                        <div class="text-slate-400 dark:text-neutral-400 text-[11px]">
                                            {{ project.architect_email }}
                                        </div>
                                    </div>

                                    <!-- Revision status -->
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="text-slate-400 dark:text-neutral-400">Revisi Terpakai:</span>
                                        <span class="font-semibold text-slate-900 dark:text-neutral-100">
                                            {{ project.current_revision_count }} / {{ project.max_revisions_allowed }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="border-t border-slate-100 dark:border-white/10 bg-slate-50 dark:bg-white/[0.02] p-4">
                                <div v-if="project.invitation_status === 'pending'">
                                    <Button
                                        @click="acceptInvitation(project)"
                                        class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-[0_0_20px_rgba(16,185,129,0.3)] transition-all duration-300"
                                        size="sm"
                                    >
                                        <CheckCircle2 class="mr-1.5 h-3.5 w-3.5" /> Terima Undangan & Buka
                                    </Button>
                                </div>
                                <div v-else>
                                    <Link :href="`/projects/${project.id}/viewer`">
                                        <Button class="w-full text-xs font-semibold bg-blue-500 hover:bg-blue-600 text-white shadow-[0_0_20px_rgba(59,130,246,0.3)] transition-all duration-300" size="sm">
                                            <Box class="mr-1.5 h-3.5 w-3.5" /> Buka & Beri Feedback
                                        </Button>
                                    </Link>
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
                            <div class="relative">
                                <div class="flex gap-2">
                                    <Input
                                        id="client_email"
                                        type="email"
                                        v-model="inviteForm.email"
                                        placeholder="klien@gmail.com"
                                        required
                                        autocomplete="off"
                                        class="text-sm"
                                        @input="onEmailInput"
                                        @blur="closeSuggestions"
                                        @keydown.escape="showSuggestions = false"
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="inviteForm.processing || !inviteForm.email"
                                    >
                                        <Send class="h-3.5 w-3.5 mr-1" /> Undang
                                    </Button>
                                </div>

                                <!-- Suggestion dropdown -->
                                <div
                                    v-if="showSuggestions"
                                    class="absolute left-0 right-10 top-full mt-1 z-50 rounded-xl border border-border bg-popover shadow-xl overflow-hidden"
                                >
                                    <!-- Loading -->
                                    <div v-if="suggestLoading" class="flex items-center gap-2 px-3 py-2.5 text-xs text-muted-foreground">
                                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="10"/></svg>
                                        Mencari...
                                    </div>

                                    <!-- No results -->
                                    <div
                                        v-else-if="suggestEmpty"
                                        class="flex flex-col items-center gap-1 px-3 py-3 text-center"
                                    >
                                        <span class="text-xs font-medium text-muted-foreground">Pengguna tidak ditemukan</span>
                                        <span class="text-[11px] text-muted-foreground/70">Email ini belum terdaftar — undangan tetap bisa dikirim</span>
                                    </div>

                                    <!-- Results -->
                                    <button
                                        v-else
                                        v-for="user in suggestions"
                                        :key="user.id"
                                        type="button"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-sm hover:bg-accent transition-colors"
                                        @mousedown.prevent="selectSuggestion(user)"
                                    >
                                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">
                                            {{ user.initial }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-medium text-foreground">{{ user.name }}</div>
                                            <div class="truncate text-[11px] text-muted-foreground">{{ user.email }}</div>
                                        </div>
                                    </button>
                                </div>
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
                                            class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] py-0 transition-all duration-500"
                                        >
                                            ✓ Accepted
                                        </Badge>
                                        <Badge
                                            v-else
                                            class="bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] py-0 animate-pulse"
                                        >
                                            ⏳ Pending
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
                </template><!-- end v-else real content -->
            </main>
        </div>
    </div>

    <!-- Global confirm dialog + toast (satu instance, di-share seluruh halaman) -->
    <ConfirmDialog />
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
