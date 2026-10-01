<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2, Clock, FolderOpen, Search, Send,
    ShieldOff, Users, X, UserCheck, UserX,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppHeader from '@/components/app/AppHeader.vue';
import { useSidebar } from '@/composables/useSidebar';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import { useConfirm } from '@/composables/useConfirm';
import { usePageLoading } from '@/composables/usePageLoading';
import SkeletonStatCards from '@/components/skeletons/SkeletonStatCards.vue';
import SkeletonTable from '@/components/skeletons/SkeletonTable.vue';
import { Skeleton } from '@/components/ui/skeleton';

// ─── Types ───────────────────────────────────────────────
interface Client {
    id: string;
    email: string;
    status: 'pending' | 'accepted' | 'revoked';
    invited_at: string;
    accepted_at: string | null;
    user_name: string | null;
    user_id: string | null;
    project_id: string;
    project_title: string;
}

interface ProjectSummary {
    id: string;
    title: string;
    total_clients: number;
    accepted: number;
    pending: number;
}

// ─── Props ───────────────────────────────────────────────
const props = defineProps<{
    clients: Client[];
    projectSummaries: ProjectSummary[];
    search: string;
    stats: {
        total_clients: number;
        accepted: number;
        pending: number;
        total_projects: number;
    };
}>();

// ─── Layout ──────────────────────────────────────────────
const { isSidebarOpen, isMobile } = useSidebar();
const { confirm } = useConfirm();
const { isLoading } = usePageLoading(80);

// ─── Search ──────────────────────────────────────────────
const searchInput = ref(props.search);

function doSearch() {
    router.get('/teams', { search: searchInput.value }, { preserveState: true, preserveScroll: true });
}

function clearSearch() {
    searchInput.value = '';
    router.get('/teams', {}, { preserveState: true });
}

// ─── Revoke client ───────────────────────────────────────
async function revokeClient(client: Client) {
    const ok = await confirm({
        title: 'Cabut Akses Klien?',
        description: `Akses ${client.email} ke proyek "${client.project_title}" akan dicabut permanen.`,
        confirmText: 'Ya, Cabut Akses',
        cancelText: 'Batal',
        variant: 'destructive',
        icon: 'trash',
    });
    if (!ok) return;

    router.delete(`/projects/${client.project_id}/clients/${client.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Akses ${client.email} berhasil dicabut.`);
            router.reload({ only: ['clients', 'stats', 'projectSummaries'] });
        },
        onError: () => toast.error('Gagal mencabut akses klien.'),
    });
}
</script>

<template>
    <Head title="Tim & Klien" />

    <div class="min-h-screen bg-[#F8F9FA] dark:bg-[#0b0c10] text-slate-900 dark:text-[#f3f4f6] font-['Geist',sans-serif] antialiased relative flex">
        <AppSidebar />

        <div :class="['flex-1 flex flex-col min-w-0 transition-all duration-300', isSidebarOpen && !isMobile ? 'ml-64' : 'ml-0 lg:ml-16']">
            <AppHeader />

            <main class="flex-1 p-6 lg:p-8 space-y-6">

                <!-- ── SKELETON ── -->
                <template v-if="isLoading">
                    <div class="space-y-2">
                        <Skeleton class="h-8 w-36" />
                        <Skeleton class="h-4 w-64" />
                    </div>
                    <SkeletonStatCards :count="4" />
                    <Skeleton class="h-10 w-80 max-w-full rounded-xl" />
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2">
                            <SkeletonTable :rows="6" :cols="4" />
                        </div>
                        <div class="space-y-3">
                            <Skeleton class="h-4 w-40" />
                            <Skeleton v-for="i in 4" :key="i" class="h-24 w-full rounded-xl" />
                        </div>
                    </div>
                </template>

                <!-- ── REAL CONTENT ── -->
                <template v-else>

                <!-- Page header -->
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">Tim & Klien</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                        Semua klien yang diundang ke proyek Anda
                    </p>
                </div>

                <!-- Stats row -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <Users class="h-4 w-4 text-indigo-500" />
                            <span class="text-xs font-medium text-slate-500 dark:text-neutral-400 uppercase tracking-wider">Total Klien</span>
                        </div>
                        <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ stats.total_clients }}</span>
                    </div>
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <UserCheck class="h-4 w-4 text-emerald-500" />
                            <span class="text-xs font-medium text-slate-500 dark:text-neutral-400 uppercase tracking-wider">Accepted</span>
                        </div>
                        <span class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">{{ stats.accepted }}</span>
                    </div>
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <Clock class="h-4 w-4 text-amber-500" />
                            <span class="text-xs font-medium text-slate-500 dark:text-neutral-400 uppercase tracking-wider">Pending</span>
                        </div>
                        <span class="text-2xl font-extrabold text-amber-600 dark:text-amber-400">{{ stats.pending }}</span>
                    </div>
                    <div class="rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] p-4 shadow-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <FolderOpen class="h-4 w-4 text-purple-500" />
                            <span class="text-xs font-medium text-slate-500 dark:text-neutral-400 uppercase tracking-wider">Proyek</span>
                        </div>
                        <span class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ stats.total_projects }}</span>
                    </div>
                </div>

                <!-- Search bar -->
                <div class="flex items-center gap-2 max-w-lg">
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <Input
                            v-model="searchInput"
                            placeholder="Cari email, nama, atau judul proyek..."
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

                <!-- Two-column layout: table + project sidebar -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Client table (2/3) -->
                    <div class="lg:col-span-2 space-y-3">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-neutral-300 uppercase tracking-wider">
                            Daftar Klien {{ search ? `— hasil untuk "${search}"` : '' }}
                        </h2>

                        <!-- Empty -->
                        <div v-if="clients.length === 0" class="flex min-h-[200px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 dark:border-white/20 bg-white dark:bg-white/[0.02] p-8 text-center">
                            <UserX class="h-10 w-10 text-slate-300 dark:text-neutral-600 mb-3" />
                            <p class="text-sm font-semibold text-slate-700 dark:text-neutral-300">
                                {{ search ? 'Tidak ada klien yang cocok' : 'Belum ada klien diundang' }}
                            </p>
                            <p class="text-xs text-slate-400 dark:text-neutral-500 mt-1">
                                {{ search ? `Tidak ada hasil untuk "${search}".` : 'Undang klien dari halaman Proyek 3D.' }}
                            </p>
                            <Link v-if="!search" href="/projects" class="mt-4">
                                <Button variant="outline" size="sm">Ke Halaman Proyek</Button>
                            </Link>
                        </div>

                        <!-- Client rows -->
                        <div v-else class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.02] shadow-sm">
                            <div
                                v-for="(client, idx) in clients"
                                :key="client.id"
                                :class="[
                                    'flex items-center justify-between px-4 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-white/5',
                                    idx !== 0 && 'border-t border-slate-100 dark:border-white/8',
                                ]"
                            >
                                <!-- Avatar + info -->
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-sm font-bold text-indigo-700 dark:text-indigo-400">
                                        {{ (client.user_name ?? client.email).charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                                {{ client.user_name ?? client.email }}
                                            </span>
                                            <Badge
                                                v-if="client.status === 'accepted'"
                                                class="bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-[10px] py-0 shrink-0"
                                            >✓ Accepted</Badge>
                                            <Badge
                                                v-else
                                                class="bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] py-0 shrink-0 animate-pulse"
                                            >⏳ Pending</Badge>
                                        </div>
                                        <div class="text-xs text-slate-400 dark:text-neutral-500 flex items-center gap-2 flex-wrap mt-0.5">
                                            <span v-if="client.user_name" class="truncate">{{ client.email }}</span>
                                            <span class="text-slate-300 dark:text-white/20 hidden sm:inline">·</span>
                                            <Link :href="`/projects/${client.project_id}/viewer`" class="text-indigo-500 dark:text-indigo-400 hover:underline truncate max-w-[160px]">
                                                {{ client.project_title }}
                                            </Link>
                                            <span class="text-slate-300 dark:text-white/20 hidden sm:inline">·</span>
                                            <span>{{ client.invited_at }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2 shrink-0 ml-3">
                                    <Link :href="`/projects/${client.project_id}/viewer`" target="_blank">
                                        <Button variant="ghost" size="sm" class="h-8 px-2 text-xs text-slate-500 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white" title="Buka viewer proyek">
                                            <FolderOpen class="h-3.5 w-3.5" />
                                        </Button>
                                    </Link>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 px-2 text-xs text-slate-400 dark:text-neutral-500 hover:text-rose-600 dark:hover:text-rose-400"
                                        title="Cabut akses"
                                        @click="revokeClient(client)"
                                    >
                                        <ShieldOff class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Project summary sidebar (1/3) -->
                    <div class="space-y-3">
                        <h2 class="text-sm font-semibold text-slate-700 dark:text-neutral-300 uppercase tracking-wider">
                            Ringkasan per Proyek
                        </h2>
                        <div v-if="projectSummaries.length === 0" class="rounded-2xl border border-dashed border-slate-200 dark:border-white/10 p-6 text-center text-xs text-slate-400">
                            Belum ada proyek.
                        </div>
                        <div v-else class="space-y-2">
                            <div
                                v-for="proj in projectSummaries"
                                :key="proj.id"
                                class="rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.03] p-4 hover:border-indigo-300 dark:hover:border-indigo-500/40 transition-colors"
                            >
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    <Link :href="`/projects/${proj.id}/viewer`" class="text-sm font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 line-clamp-1 transition-colors">
                                        {{ proj.title }}
                                    </Link>
                                    <span class="text-[11px] font-mono text-slate-400 dark:text-neutral-500 shrink-0">
                                        {{ proj.total_clients }} klien
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-[11px]">
                                    <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 class="h-3 w-3" /> {{ proj.accepted }} diterima
                                    </span>
                                    <span class="flex items-center gap-1 text-amber-500 dark:text-amber-400">
                                        <Clock class="h-3 w-3" /> {{ proj.pending }} pending
                                    </span>
                                </div>
                                <!-- Mini progress -->
                                <div v-if="proj.total_clients > 0" class="mt-2 h-1 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                                    <div
                                        class="h-full bg-emerald-500 rounded-full transition-all"
                                        :style="{ width: `${(proj.accepted / proj.total_clients) * 100}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                </template><!-- end v-else -->
            </main>
        </div>
    </div>

    <ConfirmDialog />
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
