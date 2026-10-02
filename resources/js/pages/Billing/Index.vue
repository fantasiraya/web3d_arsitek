<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    CreditCard,
    Receipt,
    ShoppingBag,
    XCircle,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppHeader from '@/components/app/AppHeader.vue';
import { useSidebar } from '@/composables/useSidebar';

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
    transactions: TransactionPage;
}>();

const { isSidebarOpen, isMobile } = useSidebar();

// ── Status helpers ────────────────────────────────────────
function statusColor(status: string) {
    const map: Record<string, string> = {
        pending:    'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 border-amber-200 dark:border-amber-500/30',
        settlement: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
        cancel:     'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 border-rose-200 dark:border-rose-500/30',
        expire:     'bg-neutral-100 text-neutral-500 dark:bg-neutral-500/15 dark:text-neutral-400 border-neutral-200 dark:border-neutral-500/30',
    };
    return map[status] ?? 'bg-muted text-muted-foreground border-border';
}

function statusLabel(status: string) {
    const map: Record<string, string> = {
        pending:    'Menunggu',
        settlement: 'Lunas',
        cancel:     'Dibatalkan',
        expire:     'Kadaluarsa',
    };
    return map[status] ?? status;
}

function statusIcon(status: string) {
    const map: Record<string, any> = {
        pending:    Clock,
        settlement: CheckCircle2,
        cancel:     XCircle,
        expire:     XCircle,
    };
    return map[status] ?? Clock;
}

// ── Pagination ────────────────────────────────────────────
function goToPage(page: number) {
    router.get('/billing', { page }, { preserveState: true, preserveScroll: true });
}

const pageRange = computed(() => {
    const total   = props.transactions.last_page;
    const current = props.transactions.current_page;
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
</script>

<template>
    <Head title="Riwayat Pembelian — AETHER 3D" />

    <div class="min-h-screen bg-[#F8F9FA] dark:bg-[#0b0c10] text-slate-900 dark:text-[#f3f4f6] font-['Geist',sans-serif] antialiased flex relative overflow-x-hidden transition-colors duration-300">

        <!-- Ambient glow (dark only) -->
        <div class="hidden dark:block fixed top-0 left-64 w-[650px] h-[400px] bg-[#38bdf8]/10 blur-[150px] pointer-events-none rounded-full"></div>

        <AppSidebar />

        <div :class="['flex-1 flex flex-col min-w-0 transition-all duration-300', isSidebarOpen && !isMobile ? 'ml-64' : 'ml-0 lg:ml-16']">
            <AppHeader />

            <main class="flex-1 p-6 lg:p-8 space-y-6 relative z-10">

                <!-- Page header -->
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-neutral-100 flex items-center gap-2.5">
                            <Receipt class="h-6 w-6 text-indigo-500" />
                            Riwayat Pembelian
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-neutral-400">
                            Semua transaksi pembelian paket Anda — total {{ transactions.total }} transaksi.
                        </p>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="transactions.data.length === 0"
                    class="flex min-h-[360px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 dark:border-white/15 bg-white dark:bg-white/[0.02] p-8 text-center"
                >
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-500/20 text-indigo-500 border border-indigo-100 dark:border-indigo-500/30 mb-4">
                        <ShoppingBag class="h-8 w-8" />
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-neutral-100">Belum Ada Transaksi</h3>
                    <p class="mt-2 max-w-sm text-sm text-slate-500 dark:text-neutral-400">
                        Transaksi pembelian paket Pro atau Enterprise akan tampil di sini setelah Anda melakukan checkout.
                    </p>
                </div>

                <!-- Table -->
                <div v-else class="overflow-hidden rounded-2xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.03] shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/[0.03] text-[11px] uppercase tracking-wider text-slate-400 dark:text-neutral-500">
                                <tr>
                                    <th class="px-5 py-3 text-left">Order ID</th>
                                    <th class="px-5 py-3 text-left">Paket</th>
                                    <th class="px-5 py-3 text-left hidden sm:table-cell">Periode</th>
                                    <th class="px-5 py-3 text-left hidden md:table-cell">Metode</th>
                                    <th class="px-5 py-3 text-right">Jumlah</th>
                                    <th class="px-5 py-3 text-center">Status</th>
                                    <th class="px-5 py-3 text-left hidden lg:table-cell">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <tr
                                    v-for="tx in transactions.data"
                                    :key="tx.id"
                                    class="hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors"
                                >
                                    <!-- Order ID -->
                                    <td class="px-5 py-4">
                                        <span class="font-mono text-xs text-slate-600 dark:text-neutral-300">
                                            {{ tx.order_id }}
                                        </span>
                                    </td>

                                    <!-- Paket -->
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-800 dark:text-neutral-100">
                                            <CreditCard class="h-3.5 w-3.5 text-indigo-400 shrink-0" />
                                            {{ tx.plan_name }}
                                        </span>
                                    </td>

                                    <!-- Periode -->
                                    <td class="px-5 py-4 hidden sm:table-cell">
                                        <span class="text-xs text-slate-500 dark:text-neutral-400">{{ tx.billing_type }}</span>
                                    </td>

                                    <!-- Metode -->
                                    <td class="px-5 py-4 hidden md:table-cell">
                                        <span class="text-xs text-slate-500 dark:text-neutral-400">{{ tx.payment_type }}</span>
                                    </td>

                                    <!-- Jumlah -->
                                    <td class="px-5 py-4 text-right">
                                        <span class="font-mono font-bold text-slate-900 dark:text-neutral-100">{{ tx.amount }}</span>
                                    </td>

                                    <!-- Status badge -->
                                    <td class="px-5 py-4 text-center">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                            :class="statusColor(tx.status)"
                                        >
                                            <component :is="statusIcon(tx.status)" class="h-3 w-3 shrink-0" />
                                            {{ statusLabel(tx.status) }}
                                        </span>
                                    </td>

                                    <!-- Tanggal -->
                                    <td class="px-5 py-4 hidden lg:table-cell">
                                        <div class="text-xs text-slate-500 dark:text-neutral-400">{{ tx.created_at }}</div>
                                        <div v-if="tx.paid_at" class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">
                                            Dibayar: {{ tx.paid_at }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="transactions.last_page > 1"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-slate-100 dark:border-white/5 px-5 py-3"
                    >
                        <!-- Info -->
                        <p class="text-xs text-slate-400 dark:text-neutral-500 order-2 sm:order-1">
                            Halaman <span class="font-semibold text-slate-700 dark:text-neutral-200">{{ transactions.current_page }}</span>
                            dari <span class="font-semibold text-slate-700 dark:text-neutral-200">{{ transactions.last_page }}</span>
                            · Total <span class="font-semibold text-slate-700 dark:text-neutral-200">{{ transactions.total }}</span> transaksi
                        </p>

                        <!-- Navigasi -->
                        <div class="flex items-center gap-1 order-1 sm:order-2">
                            <!-- Prev -->
                            <button
                                :disabled="transactions.current_page === 1"
                                @click="goToPage(transactions.current_page - 1)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 dark:border-white/10 text-sm text-slate-400 dark:text-neutral-500 transition-colors hover:bg-slate-50 dark:hover:bg-white/5 disabled:opacity-40 disabled:cursor-not-allowed"
                            >‹</button>

                            <template v-for="p in pageRange" :key="String(p)">
                                <span
                                    v-if="p === '...'"
                                    class="inline-flex h-8 w-8 items-center justify-center text-xs text-slate-400 dark:text-neutral-500 select-none"
                                >…</span>
                                <button
                                    v-else
                                    @click="goToPage(p as number)"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-xs font-medium transition-colors"
                                    :class="p === transactions.current_page
                                        ? 'border-indigo-500 bg-indigo-500 text-white dark:border-indigo-400 dark:bg-indigo-500 dark:text-white'
                                        : 'border-slate-200 dark:border-white/10 text-slate-500 dark:text-neutral-400 hover:bg-slate-50 dark:hover:bg-white/5'"
                                >{{ p }}</button>
                            </template>

                            <!-- Next -->
                            <button
                                :disabled="transactions.current_page === transactions.last_page"
                                @click="goToPage(transactions.current_page + 1)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 dark:border-white/10 text-sm text-slate-400 dark:text-neutral-500 transition-colors hover:bg-slate-50 dark:hover:bg-white/5 disabled:opacity-40 disabled:cursor-not-allowed"
                            >›</button>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>
</template>
