<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronDown, ChevronRight, Filter,
    Search, ShieldCheck, User,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineOptions({ layout: AdminLayout });

interface AdminInfo  { id: string; name: string; email: string; }
interface TargetUser { id: string; name: string; email: string; }

interface AuditLogItem {
    id: string;
    action: string;
    admin: AdminInfo;
    target_user: TargetUser | null;
    summary: string;
    reason: string | null;
    old_value: Record<string, any> | null;
    new_value: Record<string, any> | null;
    metadata: Record<string, any> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    created_at_human: string;
}

const props = defineProps<{
    audit_logs: {
        data: AuditLogItem[];
        total: number;
        current_page: number;
        last_page: number;
    };
    actions: string[];
    filters: { search: string; action: string };
}>();

// ─── Filter ──────────────────────────────────────────────
const searchInput  = ref(props.filters.search);
const actionFilter = ref(props.filters.action);

function applyFilter() {
    router.get('/admin/audit-logs', {
        search: searchInput.value,
        action: actionFilter.value,
    }, { preserveState: true });
}

// ─── Expand detail ───────────────────────────────────────
const expandedId = ref<string | null>(null);
function toggleExpand(id: string) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ─── Pagination ───────────────────────────────────────────
function goToPage(page: number) {
    router.get('/admin/audit-logs', {
        search: props.filters.search,
        action: props.filters.action,
        page,
    }, { preserveState: true, preserveScroll: true });
}

const pageRange = computed(() => {
    const total   = props.audit_logs.last_page;
    const current = props.audit_logs.current_page;
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
function actionColor(action: string) {
    if (action.includes('deleted') || action.includes('revoked') || action.includes('banned'))
        return 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/25';
    if (action.includes('created') || action.includes('activated') || action.includes('settlement'))
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/25';
    if (action.includes('updated') || action.includes('changed') || action.includes('confirmed'))
        return 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/25';
    return 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/25';
}

function prettyJson(val: Record<string, any> | null): string {
    if (!val) return '—';
    return JSON.stringify(val, null, 2);
}
</script>

<template>
    <Head title="Admin — Audit Logs" />

    <div class="space-y-6">

        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2.5">
                <ShieldCheck class="h-6 w-6 text-indigo-500" />
                Audit Logs
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">
                Total {{ audit_logs.total }} aktivitas tercatat
            </p>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchInput"
                    placeholder="Cari action, IP, nama admin..."
                    class="pl-9"
                    @keydown.enter="applyFilter"
                />
            </div>
            <select
                v-model="actionFilter"
                class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                @change="applyFilter"
            >
                <option value="">Semua Action</option>
                <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
            </select>
            <Button variant="outline" size="sm" class="gap-1.5" @click="applyFilter">
                <Filter class="h-3.5 w-3.5" /> Filter
            </Button>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">

            <div v-if="audit_logs.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <ShieldCheck class="h-10 w-10 text-muted-foreground/50 mb-3" />
                <p class="font-medium text-foreground">Belum ada log</p>
                <p class="text-sm text-muted-foreground mt-1">Aktivitas admin akan tercatat di sini.</p>
            </div>

            <table v-else class="w-full text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left w-8"></th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Admin</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Target</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">IP</th>
                        <th class="px-4 py-3 text-left">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <template v-for="log in audit_logs.data" :key="log.id">
                        <!-- Main row -->
                        <tr
                            class="hover:bg-muted/20 transition-colors cursor-pointer"
                            @click="toggleExpand(log.id)"
                        >
                            <!-- Expand toggle -->
                            <td class="px-4 py-3">
                                <component
                                    :is="expandedId === log.id ? ChevronDown : ChevronRight"
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                            </td>

                            <!-- Action -->
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="actionColor(log.action)"
                                >
                                    {{ log.action }}
                                </span>
                                <div class="text-[11px] text-muted-foreground mt-0.5 line-clamp-1 max-w-[220px]">
                                    {{ log.summary }}
                                </div>
                            </td>

                            <!-- Admin -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <User class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    <div>
                                        <div class="text-xs font-medium text-foreground">{{ log.admin.name }}</div>
                                        <div class="text-[11px] text-muted-foreground">{{ log.admin.email }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Target -->
                            <td class="px-4 py-3 hidden md:table-cell">
                                <template v-if="log.target_user">
                                    <div class="text-xs font-medium text-foreground">{{ log.target_user.name }}</div>
                                    <div class="text-[11px] text-muted-foreground">{{ log.target_user.email }}</div>
                                </template>
                                <span v-else class="text-[11px] text-muted-foreground">—</span>
                            </td>

                            <!-- IP -->
                            <td class="px-4 py-3 hidden lg:table-cell">
                                <span class="font-mono text-xs text-muted-foreground">{{ log.ip_address ?? '—' }}</span>
                            </td>

                            <!-- Waktu -->
                            <td class="px-4 py-3">
                                <div class="text-xs text-foreground">{{ log.created_at_human }}</div>
                                <div class="text-[10px] text-muted-foreground">{{ log.created_at }}</div>
                            </td>
                        </tr>

                        <!-- Expanded detail row -->
                        <tr v-if="expandedId === log.id" class="bg-muted/10">
                            <td colspan="6" class="px-6 py-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                    <!-- Reason -->
                                    <div v-if="log.reason" class="space-y-1">
                                        <p class="font-semibold text-foreground uppercase tracking-wider text-[10px]">Alasan</p>
                                        <p class="text-muted-foreground">{{ log.reason }}</p>
                                    </div>

                                    <!-- Old value -->
                                    <div v-if="log.old_value" class="space-y-1">
                                        <p class="font-semibold text-rose-500 uppercase tracking-wider text-[10px]">Nilai Lama</p>
                                        <pre class="text-[11px] text-muted-foreground bg-muted rounded-lg p-3 overflow-x-auto max-h-48">{{ prettyJson(log.old_value) }}</pre>
                                    </div>

                                    <!-- New value -->
                                    <div v-if="log.new_value" class="space-y-1">
                                        <p class="font-semibold text-emerald-500 uppercase tracking-wider text-[10px]">Nilai Baru</p>
                                        <pre class="text-[11px] text-muted-foreground bg-muted rounded-lg p-3 overflow-x-auto max-h-48">{{ prettyJson(log.new_value) }}</pre>
                                    </div>

                                    <!-- User agent -->
                                    <div v-if="log.user_agent" class="space-y-1 md:col-span-2">
                                        <p class="font-semibold text-foreground uppercase tracking-wider text-[10px]">User Agent</p>
                                        <p class="text-[11px] text-muted-foreground truncate">{{ log.user_agent }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-border px-4 py-3">
                <p class="text-xs text-muted-foreground order-2 sm:order-1">
                    Menampilkan <span class="font-semibold text-foreground">{{ audit_logs.data.length }}</span>
                    dari <span class="font-semibold text-foreground">{{ audit_logs.total }}</span> log
                    <template v-if="audit_logs.last_page > 1">
                        · Hal. <span class="font-semibold text-foreground">{{ audit_logs.current_page }}</span>/{{ audit_logs.last_page }}
                    </template>
                </p>
                <div v-if="audit_logs.last_page > 1" class="flex items-center gap-1 order-1 sm:order-2">
                    <button
                        :disabled="audit_logs.current_page === 1"
                        @click="goToPage(audit_logs.current_page - 1)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-sm text-muted-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                    >‹</button>
                    <template v-for="p in pageRange" :key="String(p)">
                        <span v-if="p === '...'" class="inline-flex h-8 w-8 items-center justify-center text-xs text-muted-foreground select-none">…</span>
                        <button
                            v-else
                            @click="goToPage(p as number)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-medium transition-colors"
                            :class="p === audit_logs.current_page
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'"
                        >{{ p }}</button>
                    </template>
                    <button
                        :disabled="audit_logs.current_page === audit_logs.last_page"
                        @click="goToPage(audit_logs.current_page + 1)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-sm text-muted-foreground hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                    >›</button>
                </div>
            </div>
        </div>
    </div>
</template>
