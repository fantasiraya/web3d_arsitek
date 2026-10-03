<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle, Badge as BadgeIcon, Check, CheckCircle2,
    CreditCard, Filter, RefreshCw, Search,
    Sparkles, UserCheck, X, Zap,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog, DialogClose, DialogContent, DialogDescription,
    DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useConfirm } from '@/composables/useConfirm';

defineOptions({ layout: AdminLayout });

// ─── Types ───────────────────────────────────────────────
interface SubItem {
    id: string;
    user: { id: string; name: string; email: string; subscription_status: string };
    plan: string;
    plan_name: string;
    status: string;
    billing_type: string;
    started_at: string;
    expires_at: string;
    project_count: number;
    effective_limit: number | null;
    is_over_limit: boolean;
}

interface PendingTx {
    id: string;
    order_id: string;
    amount: string;
    user_name: string;
    user_email: string;
    user_id: string;
    created_at: string;
    plan_slug: string | null;
    billing_type: string;
    payment_type: string;
}

interface PendingTxPage {
    data: PendingTx[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
}

interface PlanOption { id: string; name: string; slug: string; }

// ─── Props ───────────────────────────────────────────────
const props = defineProps<{
    subscriptions: { data: SubItem[]; total: number; current_page: number; last_page: number };
    plans: PlanOption[];
    filters: { search: string; plan: string; status: string; tx_search: string };
    pendingTransactions: PendingTxPage;
}>();

const { confirm } = useConfirm();

// ─── Pending TX filter + paginate (server-side) ───────────
const txSearch = ref(props.filters.tx_search ?? '');
let txSearchTimer: ReturnType<typeof setTimeout> | null = null;

function applyTxSearch() {
    router.get('/admin/subscriptions', {
        search:    props.filters.search,
        plan:      props.filters.plan,
        tx_search: txSearch.value,
        tx_page:   1,
    }, { preserveState: true, preserveScroll: true });
}

// Debounce search 400ms
watch(txSearch, () => {
    if (txSearchTimer) clearTimeout(txSearchTimer);
    txSearchTimer = setTimeout(applyTxSearch, 400);
});

function goTxPage(page: number) {
    router.get('/admin/subscriptions', {
        search:    props.filters.search,
        plan:      props.filters.plan,
        tx_search: props.filters.tx_search ?? '',
        tx_page:   page,
    }, { preserveState: true, preserveScroll: true });
}

const txPageRange = computed(() => {
    const total   = props.pendingTransactions.last_page;
    const current = props.pendingTransactions.current_page;
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

// ─── Search / filter ─────────────────────────────────────
const searchInput = ref(props.filters.search);
const planFilter  = ref(props.filters.plan);

function applyFilter() {
    router.get('/admin/subscriptions', {
        search: searchInput.value,
        plan:   planFilter.value,
    }, { preserveState: true });
}

// ─── Manual Activate Modal ────────────────────────────────
const isManualOpen  = ref(false);

const manualForm = useForm({
    user_id:      '',
    plan_slug:    'pro',
    billing_type: 'monthly',
    notes:        '',
});

function submitManual() {
    manualForm.post('/admin/subscriptions/manual-activate', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Akun berhasil diaktifkan.');
            isManualOpen.value = false;
            manualForm.reset();
        },
    });
}

// ─── Confirm Transfer ────────────────────────────────────
const confirmingTx  = ref<PendingTx | null>(null);
const isConfirmOpen = ref(false);

const confirmForm = useForm({
    plan_slug:    'pro',
    billing_type: 'monthly',
    notes:        '',
});

function openConfirmTransfer(tx: PendingTx) {
    confirmingTx.value = tx;
    confirmForm.plan_slug    = tx.plan_slug ?? 'pro';
    confirmForm.billing_type = tx.billing_type ?? 'monthly';
    confirmForm.notes        = '';
    isConfirmOpen.value = true;
}

function submitConfirmTransfer() {
    if (!confirmingTx.value) return;
    confirmForm.patch(`/admin/subscriptions/transactions/${confirmingTx.value.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Transfer ${confirmingTx.value!.order_id} dikonfirmasi.`);
            isConfirmOpen.value = false;
            confirmingTx.value  = null;
        },
    });
}

// ─── Helpers ─────────────────────────────────────────────
function statusColor(status: string) {
    const map: Record<string, string> = {
        active:    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/25',
        expired:   'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/25',
        cancelled: 'bg-neutral-500/15 text-neutral-500 border-neutral-500/25',
        trialing:  'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/25',
    };
    return map[status] ?? 'bg-muted text-muted-foreground border-border';
}

function statusLabel(status: string) {
    const map: Record<string, string> = {
        active:    'Aktif',
        expired:   'Kadaluarsa',
        cancelled: 'Dibatalkan',
        trialing:  'Trial',
    };
    return map[status] ?? status;
}

function planColor(slug: string) {
    if (slug === 'pro') return 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300';
    if (slug === 'enterprise') return 'bg-purple-500/15 text-purple-700 dark:text-purple-300';
    return 'bg-muted text-muted-foreground';
}

// ─── Pagination ───────────────────────────────────────────
function goToPage(page: number) {
    router.get('/admin/subscriptions', {
        search: props.filters.search,
        plan:   props.filters.plan,
        page,
    }, { preserveState: true, preserveScroll: true });
}

const pageRange = computed(() => {
    const total   = props.subscriptions.last_page;
    const current = props.subscriptions.current_page;
    const delta   = 2; // halaman di kiri-kanan current
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
</script>

<template>
    <Head title="Subscriptions" />

    <div class="space-y-6">

        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Subscriptions</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Total {{ subscriptions.total }} subscription · Kelola aktivasi manual & konfirmasi transfer
                </p>
            </div>
            <Button @click="isManualOpen = true" class="gap-2 shrink-0">
                <UserCheck class="h-4 w-4" /> Aktivasi Manual
            </Button>
        </div>

        <!-- ── PENDING TRANSFER SECTION ─────────────────── -->
        <div v-if="pendingTransactions.total > 0" class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-amber-500 shrink-0" />
                    <div>
                        <h2 class="font-semibold text-foreground">Transaksi Menunggu Konfirmasi</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ pendingTransactions.total }} transaksi belum dikonfirmasi admin.
                        </p>
                    </div>
                </div>
                <!-- Search filter -->
                <div class="relative w-full sm:w-64">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                    <input
                        v-model="txSearch"
                        type="text"
                        placeholder="Cari nama, email, order ID..."
                        class="w-full h-8 pl-8 pr-3 rounded-lg border border-amber-500/30 bg-white/50 dark:bg-black/20 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-amber-500/50"
                    />
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-amber-500/20">
                <table class="w-full text-sm">
                    <thead class="bg-amber-500/10 text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 text-left">Order ID</th>
                            <th class="px-4 py-2.5 text-left">User</th>
                            <th class="px-4 py-2.5 text-left hidden sm:table-cell">Metode</th>
                            <th class="px-4 py-2.5 text-left">Jumlah</th>
                            <th class="px-4 py-2.5 text-left hidden md:table-cell">Dikirim</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card">
                        <tr v-if="pendingTransactions.data.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                Tidak ada transaksi yang cocok dengan pencarian.
                            </td>
                        </tr>
                        <tr v-for="tx in pendingTransactions.data" :key="tx.id" class="hover:bg-muted/30 transition-colors">
                            <td class="px-4 py-3 font-mono text-xs">{{ tx.order_id }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-foreground">{{ tx.user_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ tx.user_email }}</div>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <span class="text-xs px-2 py-0.5 rounded-full border"
                                    :class="tx.payment_type === 'Midtrans'
                                        ? 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20'
                                        : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'">
                                    {{ tx.payment_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-foreground">{{ tx.amount }}</td>
                            <td class="px-4 py-3 text-xs text-muted-foreground hidden md:table-cell">{{ tx.created_at }}</td>
                            <td class="px-4 py-3 text-right">
                                <Button size="sm" class="h-7 text-xs gap-1 bg-emerald-600 hover:bg-emerald-700 text-white" @click="openConfirmTransfer(tx)">
                                    <CheckCircle2 class="h-3.5 w-3.5" /> Konfirmasi
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination pending (server-side) -->
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between pt-1">
                <p class="text-xs text-muted-foreground">
                    Menampilkan <span class="font-semibold text-foreground">{{ pendingTransactions.data.length }}</span>
                    dari <span class="font-semibold text-foreground">{{ pendingTransactions.total }}</span> transaksi
                    · Hal. <span class="font-semibold text-foreground">{{ pendingTransactions.current_page }}</span>/{{ pendingTransactions.last_page }}
                </p>
                <div v-if="pendingTransactions.last_page > 1" class="flex items-center gap-1">
                    <button
                        :disabled="pendingTransactions.current_page === 1"
                        @click="goTxPage(pendingTransactions.current_page - 1)"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-amber-500/30 text-sm text-muted-foreground hover:bg-amber-500/10 disabled:opacity-40 disabled:cursor-not-allowed"
                    >‹</button>
                    <template v-for="p in txPageRange" :key="String(p)">
                        <span v-if="p === '...'" class="inline-flex h-7 w-7 items-center justify-center text-xs text-muted-foreground">…</span>
                        <button
                            v-else
                            @click="goTxPage(p as number)"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg border text-xs font-medium transition-colors"
                            :class="p === pendingTransactions.current_page
                                ? 'border-amber-500 bg-amber-500 text-white'
                                : 'border-amber-500/30 text-muted-foreground hover:bg-amber-500/10'"
                        >{{ p }}</button>
                    </template>
                    <button
                        :disabled="pendingTransactions.current_page === pendingTransactions.last_page"
                        @click="goTxPage(pendingTransactions.current_page + 1)"
                        class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-amber-500/30 text-sm text-muted-foreground hover:bg-amber-500/10 disabled:opacity-40 disabled:cursor-not-allowed"
                    >›</button>
                </div>
            </div>
        </div>

        <!-- ── FILTER BAR ──────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    v-model="searchInput"
                    placeholder="Cari nama / email..."
                    class="pl-9"
                    @keydown.enter="applyFilter"
                />
            </div>
            <select
                v-model="planFilter"
                class="h-9 rounded-lg border border-input bg-background px-3 text-sm"
                @change="applyFilter"
            >
                <option value="">Semua Paket</option>
                <option v-for="p in plans" :key="p.slug" :value="p.slug">{{ p.name }}</option>
            </select>
            <Button variant="outline" size="sm" class="gap-1.5" @click="applyFilter">
                <Filter class="h-3.5 w-3.5" /> Filter
            </Button>
        </div>

        <!-- ── SUBSCRIPTIONS TABLE ──────────────────────── -->
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div v-if="subscriptions.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <CreditCard class="h-10 w-10 text-muted-foreground/50 mb-3" />
                <p class="font-medium text-foreground">Belum ada subscription</p>
                <p class="text-sm text-muted-foreground mt-1">Gunakan tombol "Aktivasi Manual" untuk mengaktifkan akun.</p>
            </div>
            <table v-else class="w-full text-sm">
                <thead class="border-b border-border bg-muted/40 text-xs uppercase tracking-wider text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Paket</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Mulai</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Berakhir</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Proyek</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="sub in subscriptions.data"
                        :key="sub.id"
                        class="hover:bg-muted/20 transition-colors"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-foreground">{{ sub.user.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ sub.user.email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="planColor(sub.plan)">
                                <Sparkles v-if="sub.plan === 'pro' || sub.plan === 'enterprise'" class="h-2.5 w-2.5" />
                                {{ sub.plan_name }}
                            </span>
                            <div class="text-[10px] text-muted-foreground mt-0.5 font-mono">{{ sub.billing_type }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="statusColor(sub.status)">
                                {{ statusLabel(sub.status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground hidden md:table-cell">{{ sub.started_at }}</td>
                        <td class="px-4 py-3 text-xs text-muted-foreground hidden md:table-cell">{{ sub.expires_at }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <span
                                class="text-xs font-medium"
                                :class="sub.is_over_limit ? 'text-rose-500' : 'text-foreground'"
                            >
                                {{ sub.project_count }} /
                                {{ sub.effective_limit === null ? '∞' : sub.effective_limit }}
                            </span>
                            <AlertTriangle v-if="sub.is_over_limit" class="inline h-3 w-3 ml-1 text-rose-500" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination subscription — selalu tampil -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-border px-4 py-3">
                <p class="text-xs text-muted-foreground order-2 sm:order-1">
                    Menampilkan <span class="font-semibold text-foreground">{{ subscriptions.data.length }}</span>
                    dari <span class="font-semibold text-foreground">{{ subscriptions.total }}</span> subscription
                    <template v-if="subscriptions.last_page > 1">
                        · Halaman <span class="font-semibold text-foreground">{{ subscriptions.current_page }}</span>/{{ subscriptions.last_page }}
                    </template>
                </p>
                <div v-if="subscriptions.last_page > 1" class="flex items-center gap-1 order-1 sm:order-2">
                    <button
                        :disabled="subscriptions.current_page === 1"
                        @click="goToPage(subscriptions.current_page - 1)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-sm text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                    >‹</button>
                    <template v-for="p in pageRange" :key="String(p)">
                        <span v-if="p === '...'" class="inline-flex h-8 w-8 items-center justify-center text-xs text-muted-foreground select-none">…</span>
                        <button
                            v-else
                            @click="goToPage(p as number)"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-medium transition-colors"
                            :class="p === subscriptions.current_page
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border text-muted-foreground hover:bg-muted'"
                        >{{ p }}</button>
                    </template>
                    <button
                        :disabled="subscriptions.current_page === subscriptions.last_page"
                        @click="goToPage(subscriptions.current_page + 1)"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-border text-sm text-muted-foreground transition-colors hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed"
                    >›</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── MODAL: Aktivasi Manual ─────────────────────── -->
    <Dialog :open="isManualOpen" @update:open="(v) => { if (!v) { isManualOpen = false; manualForm.reset(); } }">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserCheck class="h-5 w-5 text-primary" /> Aktivasi Manual
                </DialogTitle>
                <DialogDescription>
                    Aktifkan paket untuk user tertentu tanpa payment gateway. Catat sebagai manual oleh admin.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitManual" class="space-y-4 py-2">
                <div class="space-y-1.5">
                    <Label>User ID / Email <span class="text-rose-500">*</span></Label>
                    <Input
                        v-model="manualForm.user_id"
                        placeholder="UUID user"
                        required
                    />
                    <p class="text-[11px] text-muted-foreground">Masukkan UUID user dari halaman Users.</p>
                    <p v-if="manualForm.errors.user_id" class="text-xs text-rose-500">{{ manualForm.errors.user_id }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>Paket <span class="text-rose-500">*</span></Label>
                        <select v-model="manualForm.plan_slug" class="w-full h-9 rounded-lg border border-input bg-background px-3 text-sm">
                            <option v-for="p in plans" :key="p.slug" :value="p.slug">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Billing Type</Label>
                        <select v-model="manualForm.billing_type" class="w-full h-9 rounded-lg border border-input bg-background px-3 text-sm">
                            <option value="monthly">Bulanan</option>
                            <option value="annual">Tahunan</option>
                            <option value="lifetime">Selamanya</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label>Catatan (Opsional)</Label>
                    <Input v-model="manualForm.notes" placeholder="Misal: Pembayaran via transfer BCA tanggal 1 Okt" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Batal</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="manualForm.processing" class="gap-2">
                        <UserCheck class="h-4 w-4" />
                        {{ manualForm.processing ? 'Mengaktifkan...' : 'Aktifkan' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- ── MODAL: Konfirmasi Transfer ────────────────── -->
    <Dialog :open="isConfirmOpen" @update:open="(v) => { if (!v) { isConfirmOpen = false; confirmingTx = null; } }">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <CheckCircle2 class="h-5 w-5 text-emerald-500" /> Konfirmasi Transfer
                </DialogTitle>
                <DialogDescription>
                    Konfirmasi bahwa transfer dari <strong>{{ confirmingTx?.user_name }}</strong>
                    ({{ confirmingTx?.order_id }}) sebesar <strong>{{ confirmingTx?.amount }}</strong> sudah diterima.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitConfirmTransfer" class="space-y-4 py-2">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>Aktifkan Paket <span class="text-rose-500">*</span></Label>
                        <select v-model="confirmForm.plan_slug" class="w-full h-9 rounded-lg border border-input bg-background px-3 text-sm">
                            <option v-for="p in plans" :key="p.slug" :value="p.slug">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Billing Type</Label>
                        <select v-model="confirmForm.billing_type" class="w-full h-9 rounded-lg border border-input bg-background px-3 text-sm">
                            <option value="monthly">Bulanan</option>
                            <option value="annual">Tahunan</option>
                            <option value="lifetime">Selamanya</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label>Catatan (Opsional)</Label>
                    <Input v-model="confirmForm.notes" placeholder="Konfirmasi via mutasi rekening BCA" />
                </div>

                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 text-xs text-emerald-700 dark:text-emerald-300 space-y-1">
                    <p class="font-semibold">Tindakan ini akan:</p>
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li>Menandai transaksi {{ confirmingTx?.order_id }} sebagai <strong>settlement</strong></li>
                        <li>Mengaktifkan paket yang dipilih untuk akun user</li>
                    </ul>
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Batal</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="confirmForm.processing" class="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white">
                        <CheckCircle2 class="h-4 w-4" />
                        {{ confirmForm.processing ? 'Mengkonfirmasi...' : 'Konfirmasi & Aktifkan' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog />
    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>
