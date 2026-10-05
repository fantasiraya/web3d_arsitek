<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle, Box, ExternalLink, Filter,
    FolderOpen, Search, Users,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineOptions({ layout: AdminLayout });

interface ProjectItem {
    id: string;
    title: string;
    slug: string;
    file_size_bytes: number;
    owner_max_file_size_mb: number;
    current_revision_count: number;
    max_revisions_allowed: number;
    versions_count: number;
    comments_count: number;
    clients_count: number;
    owner: {
        id: string;
        name: string;
        email: string;
        subscription_status: string;
    };
    created_at: string;
    updated_at: string;
}

interface UserOption { id: string; name: string; email: string; }

const props = defineProps<{
    projects: {
        data: ProjectItem[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filters: {
        search: string;
        plan: string;
        user_id: string;
        sort: string;
    };
    users: UserOption[];
}>();

// ─── Filter state ─────────────────────────────────────────
const searchInput = ref(props.filters.search);
const planFilter  = ref(props.filters.plan);
const sortFilter  = ref(props.filters.sort);

function applyFilter() {
    router.get('/admin/projects', {
        search:  searchInput.value,
        plan:    planFilter.value,
        sort:    sortFilter.value,
    }, { preserveState: true });
}

// ─── Pagination ───────────────────────────────────────────
function goToPage(page: number) {
    router.get('/admin/projects', {
        search: props.filters.search,
        plan:   props.filters.plan,
        sort:   props.filters.sort,
        page,
    }, { preserveState: true, preserveScroll: true });
}

const pageRange = computed(() => {
    const total   = props.projects.last_page;
    const current = props.projects.current_page;
    const delta   = 2;
    const pages: (number | '...')[] = [];
    const start = Math.max(2, current - delta);
    const end   = Math.min(total - 1, current + delta);
    pages.push(1);
    if (start > 2) pages.push('...');
    for (let i = start; i <= end; i++) pages.push(i);
    if (end < total - 1) pages.push('...');
    if (total > 1) pages.push(total);
    return pages;
});

// ─── Helpers ─────────────────────────────────────────────
function formatBytes(bytes: number): string {
    if (!bytes) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function planColor(status: string) {
    if (status === 'pro')        return 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/25';
    if (status === 'enterprise') return 'bg-purple-500/15 text-purple-700 dark:text-purple-300 border-purple-500/25';
    return 'bg-muted text-muted-foreground border-border';
}
</script>

<template>
    <Head title="Admin — Projects" />

    <div class="space-y-6">

        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Projects</h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Total {{ projects.total }} proyek terdaftar
            </p>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchInput"
                    placeholder="Cari judul, slug, atau nama arsitek..."
                    class="pl-9"
                    @keydown.enter="applyFilter"
                />
            </div>
            <select
                v-model="planFilter"
                class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                @change="applyFilter"
            >
                <option value="">Semua Plan</option>
                <option value="free">Free</option>
                <option value="pro">Pro</option>
                <option value="enterprise">Enterprise</option>
            </select>
            <select
                v-model="sortFilter"
                class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                @change="applyFilter"
            >
                <option value="latest">Terbaru</option>
                <option value="oldest">Terlama</option>
            </select>
            <Button variant="outline" size="sm" class="gap-1.5" @click="applyFilter">
                <Filter class="h-3.5 w-3.5" /> Filter
            </Button>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">

            <!-- Empty state -->
            <div v-if="projects.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <FolderOpen class="h-10 w-10 text-muted-foreground/50 mb-3" />
                <p class="font-medium text-foreground">Tidak ada proyek ditemukan</p>
                <p class="text-sm text-muted-foreground mt-1">Coba ubah filter pencarian.</p>
            </div>

            <table v-else class="w-full text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left">Proyek</th>
                        <th class="px-4 py-3 text-left">Arsitek</th>
                        <th class="px-4 py-3 text-left hidden sm:table-cell">Ukuran / Limit Per Proyek</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Revisi</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Klien</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Dibuat</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="p in projects.data"
                        :key="p.id"
                        class="hover:bg-muted/20 transition-colors"
                    >
                        <!-- Proyek -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <Box class="h-4 w-4 shrink-0 text-indigo-400" />
                                <div>
                                    <div class="font-medium text-foreground line-clamp-1">{{ p.title }}</div>
                                    <div class="text-[11px] text-muted-foreground font-mono">{{ p.slug }}</div>
                                </div>
                            </div>
                        </td>

                        <!-- Arsitek -->
                        <td class="px-4 py-3">
                            <div class="font-medium text-foreground text-xs">{{ p.owner.name }}</div>
                            <div class="text-[11px] text-muted-foreground">{{ p.owner.email }}</div>
                            <span
                                class="inline-flex items-center rounded-full border px-1.5 py-0.5 text-[10px] font-semibold mt-0.5"
                                :class="planColor(p.owner.subscription_status)"
                            >
                                {{ p.owner.subscription_status }}
                            </span>
                        </td>

                        <!-- Ukuran -->
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="text-xs font-mono text-foreground">{{ formatBytes(p.file_size_bytes) }}</span>
                            <span class="text-xs text-muted-foreground"> / {{ p.owner_max_file_size_mb }} MB</span>
                        </td>

                        <!-- Revisi -->
                        <td class="px-4 py-3 hidden md:table-cell">
                            <div class="flex items-center gap-1.5 text-xs">
                                <span
                                    class="font-semibold"
                                    :class="p.current_revision_count >= p.max_revisions_allowed ? 'text-rose-500' : 'text-foreground'"
                                >
                                    {{ p.current_revision_count }}
                                </span>
                                <span class="text-muted-foreground">/ {{ p.max_revisions_allowed }}</span>
                                <AlertTriangle
                                    v-if="p.current_revision_count >= p.max_revisions_allowed"
                                    class="h-3 w-3 text-rose-500"
                                />
                            </div>
                            <div class="text-[10px] text-muted-foreground mt-0.5">{{ p.versions_count }} versi · {{ p.comments_count }} komentar</div>
                        </td>

                        <!-- Klien -->
                        <td class="px-4 py-3 hidden md:table-cell">
                            <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                <Users class="h-3.5 w-3.5" />
                                {{ p.clients_count }} klien
                            </div>
                        </td>

                        <!-- Dibuat -->
                        <td class="px-4 py-3 hidden lg:table-cell text-xs text-muted-foreground">
                            {{ p.created_at }}
                        </td>

                        <!-- Aksi -->
                        <td class="px-4 py-3 text-right">
                            <a
                                :href="`/projects/${p.id}/viewer`"
                                target="_blank"
                                class="inline-flex items-center gap-1 rounded-lg border border-border bg-muted/30 px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted transition-colors"
                            >
                                <ExternalLink class="h-3.5 w-3.5" /> Buka
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-border px-4 py-3">
                <p class="text-xs text-muted-foreground order-2 sm:order-1">
                    Menampilkan <span class="font-semibold text-foreground">{{ projects.data.length }}</span>
                    dari <span class="font-semibold text-foreground">{{ projects.total }}</span> proyek
                    <template v-if="projects.last_page > 1">
                        · Hal. <span class="font-semibold text-foreground">{{ projects.current_page }}</span>/{{ projects.last_page }}
                    </template>
                </p>
                <div v-if="projects.last_page > 1" class="flex items-center gap-1 order-1 sm:order-2">
                    <button
                        :disabled="projects.current_page === 1"
                        @click="goToPage(projects.current_page - 1)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-sm text-muted-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                    >‹</button>
                    <template v-for="p in pageRange" :key="String(p)">
                        <span v-if="p === '...'" class="inline-flex h-8 w-8 items-center justify-center text-xs text-muted-foreground select-none">…</span>
                        <button
                            v-else
                            @click="goToPage(p as number)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-medium transition-colors"
                            :class="p === projects.current_page
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'"
                        >{{ p }}</button>
                    </template>
                    <button
                        :disabled="projects.current_page === projects.last_page"
                        @click="goToPage(projects.current_page + 1)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-sm text-muted-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                    >›</button>
                </div>
            </div>
        </div>
    </div>
</template>
