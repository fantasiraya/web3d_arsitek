<script setup lang="ts">
/**
 * Rab/PriceItemComponents.vue
 * Analisa Harga Satuan Pekerjaan (AHSP) — kelola komponen tenaga/bahan/peralatan
 * per satu harga satuan.
 *
 * Layout mengikuti standar tabel AHSP Indonesia:
 *   Header: Nomor analisa, nama pekerjaan, satuan
 *   Kelompok: Tenaga / Bahan / Peralatan
 *   Per baris: No | Item | Kode | Sat | Koefisien | Harga Satuan | Jumlah Harga
 *   Footer: Jumlah per kelompok → Total → Overhead → Harga Satuan Pekerjaan
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, Plus, Pencil, Trash2, X, Save, ChevronRight,
    AlertCircle, CheckCircle2, RefreshCw,
} from '@lucide/vue';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { useConfirm } from '@/composables/useConfirm';

defineOptions({ layout: UnifiedLayout });

// ── Types ─────────────────────────────────────────────────────────────────────
interface Component {
    id: string;
    component_type: 'tenaga' | 'bahan' | 'peralatan';
    name: string;
    code: string | null;
    unit: string;
    coefficient: number;
    unit_price: number;
    amount: number;
    sort_order: number;
}

interface PriceItem {
    id: string;
    code: string | null;
    name: string;
    unit: string;
    unit_price: number;
    category: string | null;
    overhead_percent: number;
    has_components: boolean;
}

const props = defineProps<{
    price_item: PriceItem;
    components: Component[];
}>();

const { confirm } = useConfirm();

// ── Grouped ───────────────────────────────────────────────────────────────────
const TYPES = ['tenaga', 'bahan', 'peralatan'] as const;
const typeLabels: Record<string, string> = {
    tenaga:    'Tenaga',
    bahan:     'Bahan',
    peralatan: 'Peralatan',
};

const grouped = computed(() => {
    const g: Record<string, Component[]> = { tenaga: [], bahan: [], peralatan: [] };
    for (const c of props.components) {
        if (g[c.component_type]) g[c.component_type].push(c);
    }
    return g;
});

const subtotals = computed(() => {
    const s: Record<string, number> = { tenaga: 0, bahan: 0, peralatan: 0 };
    for (const c of props.components) s[c.component_type] = (s[c.component_type] || 0) + c.amount;
    return s;
});

const baseTotal     = computed(() => Object.values(subtotals.value).reduce((a, b) => a + b, 0));
const overheadAmt   = computed(() => Math.round(baseTotal.value * (props.price_item.overhead_percent / 100) * 100) / 100);
const unitPriceFinal = computed(() => Math.round((baseTotal.value + overheadAmt.value) * 100) / 100);

// ── Add/Edit form ─────────────────────────────────────────────────────────────
const isOpen     = ref(false);
const editingId  = ref<string | null>(null);
const defaultType = ref<'tenaga' | 'bahan' | 'peralatan'>('tenaga');

const form = useForm({
    component_type: 'tenaga' as 'tenaga' | 'bahan' | 'peralatan',
    name:           '',
    code:           '',
    unit:           'OH',
    coefficient:    0 as number,
    unit_price:     0 as number,
});

const previewAmount = computed(() =>
    Math.round((Number(form.coefficient) || 0) * (Number(form.unit_price) || 0) * 100) / 100,
);

function openAdd(type: 'tenaga' | 'bahan' | 'peralatan') {
    editingId.value = null;
    form.reset();
    form.component_type = type;
    form.unit = type === 'tenaga' ? 'OH' : type === 'peralatan' ? 'jam' : 'unit';
    isOpen.value = true;
}

function openEdit(c: Component) {
    editingId.value     = c.id;
    form.component_type = c.component_type;
    form.name           = c.name;
    form.code           = c.code ?? '';
    form.unit           = c.unit;
    form.coefficient    = c.coefficient;
    form.unit_price     = c.unit_price;
    isOpen.value        = true;
}

function closeModal() { isOpen.value = false; editingId.value = null; form.reset(); }

function submit() {
    const base = `/rab/price-items/${props.price_item.id}/components`;
    if (editingId.value) {
        form.put(`${base}/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Komponen diperbarui.'); closeModal(); },
        });
    } else {
        form.post(base, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Komponen ditambahkan.'); closeModal(); },
        });
    }
}

async function del(c: Component) {
    const ok = await confirm({
        title:        `Hapus ${c.name}?`,
        message:      'Komponen akan dihapus dan harga satuan akan dihitung ulang.',
        confirmLabel: 'Hapus',
        variant:      'destructive',
    });
    if (!ok) return;
    router.delete(`/rab/price-items/${props.price_item.id}/components/${c.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Komponen dihapus.'),
    });
}

// ── Overhead ──────────────────────────────────────────────────────────────────
const overheadForm = useForm({ overhead_percent: props.price_item.overhead_percent });

function saveOverhead() {
    overheadForm.patch(`/rab/price-items/${props.price_item.id}/overhead`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Overhead diperbarui.'),
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function fmt(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(val);
}

function fmtNum(val: number, dec = 4) {
    return val.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: dec });
}

// Saran satuan per tipe
const unitSuggestions: Record<string, string[]> = {
    tenaga:    ['OH', 'hari', 'jam', 'OB'],
    bahan:     ['m³', 'm²', 'm\'', 'kg', 'unit', 'btg', 'lbr', 'zak', 'liter', 'ls'],
    peralatan: ['jam', 'hari', 'unit', 'ls'],
};
</script>

<template>
    <Head :title="`AHSP — ${price_item.name}`" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6 max-w-5xl mx-auto">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400 flex-wrap">
            <Link href="/rab/price-items" class="hover:text-slate-600 dark:hover:text-slate-300">
                Harga Satuan
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-900 dark:text-white font-medium truncate">AHSP: {{ price_item.name }}</span>
        </nav>

        <!-- Header -->
        <div class="p-5 rounded-2xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span v-if="price_item.code" class="text-xs font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 px-2 py-0.5 rounded">
                            {{ price_item.code }}
                        </span>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            {{ price_item.name }}
                        </h1>
                        <Badge variant="outline">1 {{ price_item.unit }}</Badge>
                        <Badge v-if="price_item.has_components"
                               class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                            Dari komponen AHSP
                        </Badge>
                    </div>
                    <p v-if="price_item.category" class="text-sm text-slate-400 mt-0.5">{{ price_item.category }}</p>
                </div>

                <!-- Harga Satuan hasil kalkulasi -->
                <div class="text-right">
                    <p class="text-xs text-slate-400 uppercase tracking-wide">Harga Satuan Pekerjaan</p>
                    <p class="text-2xl font-bold text-sky-600 dark:text-sky-400">{{ fmt(price_item.unit_price) }}</p>
                    <p class="text-xs text-slate-400">per {{ price_item.unit }}</p>
                </div>
            </div>
        </div>

        <!-- Tabel AHSP utama -->
        <div class="rounded-2xl border border-slate-200 dark:border-white/10 overflow-hidden">
            <!-- Table header -->
            <div class="grid grid-cols-[auto_1fr_auto_auto_auto_auto_auto] gap-0 bg-slate-800 text-white text-xs font-semibold uppercase tracking-wide">
                <div class="px-3 py-2.5 w-10 text-center border-r border-white/10">No</div>
                <div class="px-3 py-2.5 border-r border-white/10">Item</div>
                <div class="px-3 py-2.5 w-20 text-center border-r border-white/10">Kode</div>
                <div class="px-3 py-2.5 w-16 text-center border-r border-white/10">Sat.</div>
                <div class="px-3 py-2.5 w-24 text-right border-r border-white/10">Koefisien</div>
                <div class="px-3 py-2.5 w-32 text-right border-r border-white/10">Harga Satuan</div>
                <div class="px-3 py-2.5 w-36 text-right">Jumlah Harga</div>
            </div>

            <!-- Kelompok: Tenaga / Bahan / Peralatan -->
            <template v-for="type in TYPES" :key="type">
                <!-- Group header -->
                <div class="flex items-center justify-between px-4 py-2 bg-slate-100 dark:bg-white/[0.04] border-b border-slate-200 dark:border-white/10">
                    <span class="font-bold text-sm text-slate-700 dark:text-slate-200">{{ typeLabels[type] }}</span>
                    <Button size="sm" variant="ghost" class="h-7 gap-1 text-xs text-sky-600 dark:text-sky-400" @click="openAdd(type)">
                        <Plus class="h-3.5 w-3.5" /> Tambah
                    </Button>
                </div>

                <!-- Items -->
                <div v-if="grouped[type].length === 0"
                     class="px-4 py-3 text-xs text-slate-400 italic border-b border-slate-100 dark:border-white/5">
                    — Belum ada komponen {{ typeLabels[type].toLowerCase() }} —
                </div>

                <div v-for="(comp, idx) in grouped[type]" :key="comp.id"
                     class="grid grid-cols-[auto_1fr_auto_auto_auto_auto_auto] gap-0 border-b border-slate-100 dark:border-white/5 hover:bg-slate-50 dark:hover:bg-white/[0.02] items-center">
                    <div class="px-3 py-2.5 w-10 text-center text-sm text-slate-500">{{ idx + 1 }}</div>
                    <div class="px-3 py-2.5 text-sm text-slate-800 dark:text-slate-100">{{ comp.name }}</div>
                    <div class="px-3 py-2.5 w-20 text-center text-xs font-mono text-slate-400">{{ comp.code ?? '—' }}</div>
                    <div class="px-3 py-2.5 w-16 text-center text-xs text-slate-500">{{ comp.unit }}</div>
                    <div class="px-3 py-2.5 w-24 text-right text-sm font-mono text-slate-700 dark:text-slate-200">{{ fmtNum(comp.coefficient) }}</div>
                    <div class="px-3 py-2.5 w-32 text-right text-sm text-slate-700 dark:text-slate-200">{{ fmt(comp.unit_price) }}</div>
                    <div class="px-3 py-2.5 w-36 text-right text-sm font-semibold text-slate-800 dark:text-slate-100 flex items-center justify-end gap-1">
                        <span>{{ fmt(comp.amount) }}</span>
                        <div class="flex gap-0.5 ml-1">
                            <button @click="openEdit(comp)" class="p-1 rounded text-slate-400 hover:text-sky-500 transition-colors">
                                <Pencil class="h-3 w-3" />
                            </button>
                            <button @click="del(comp)" class="p-1 rounded text-slate-400 hover:text-rose-500 transition-colors">
                                <Trash2 class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Subtotal per kelompok -->
                <div class="grid grid-cols-[auto_1fr_auto_auto_auto_auto_auto] gap-0 bg-slate-50 dark:bg-white/[0.02] border-b border-slate-200 dark:border-white/10">
                    <div class="px-3 py-2 w-10"></div>
                    <div class="px-3 py-2 col-span-5 text-right text-xs font-medium text-slate-500 uppercase tracking-wide pr-2">
                        Jumlah {{ typeLabels[type] }}
                    </div>
                    <div class="px-3 py-2 w-36 text-right text-sm font-bold text-slate-700 dark:text-slate-200">
                        {{ fmt(subtotals[type]) }}
                    </div>
                </div>
            </template>

            <!-- Total base -->
            <div class="grid grid-cols-[auto_1fr_auto] gap-0 bg-slate-100 dark:bg-white/[0.04]">
                <div class="px-3 py-2.5 w-10"></div>
                <div class="px-3 py-2.5 text-right text-sm font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wide">
                    Jumlah Total
                </div>
                <div class="px-3 py-2.5 w-36 text-right text-sm font-bold text-slate-800 dark:text-white">
                    {{ fmt(baseTotal) }}
                </div>
            </div>

            <!-- Overhead row -->
            <div class="grid grid-cols-[auto_1fr_auto] gap-0 bg-white dark:bg-transparent">
                <div class="px-3 py-2.5 w-10"></div>
                <div class="px-3 py-2.5 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-medium">Overhead &amp; Profit</span>
                    <div class="flex items-center gap-1">
                        <input
                            v-model="overheadForm.overhead_percent"
                            type="number" min="0" max="100" step="0.5"
                            class="w-16 h-7 text-sm text-right rounded border border-slate-200 dark:border-white/15 bg-transparent px-2 focus:outline-none focus:ring-1 focus:ring-sky-500"
                            @blur="saveOverhead"
                            @keydown.enter.prevent="saveOverhead"
                        />
                        <span class="text-slate-400 text-xs">%</span>
                    </div>
                </div>
                <div class="px-3 py-2.5 w-36 text-right text-sm text-slate-700 dark:text-slate-200">
                    {{ fmt(overheadAmt) }}
                </div>
            </div>

            <!-- Harga Satuan Pekerjaan FINAL -->
            <div class="grid grid-cols-[auto_1fr_auto] gap-0 bg-yellow-50 dark:bg-yellow-500/10 border-t-2 border-yellow-400 dark:border-yellow-500/40">
                <div class="px-3 py-3 w-10"></div>
                <div class="px-3 py-3 text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">
                    Harga Satuan Pekerjaan
                </div>
                <div class="px-3 py-3 w-36 text-right text-base font-bold text-sky-700 dark:text-sky-300">
                    {{ fmt(unitPriceFinal) }}
                </div>
            </div>
        </div>

        <!-- Info jika harga beda dari kalkulasi -->
        <div v-if="price_item.has_components && Math.abs(price_item.unit_price - unitPriceFinal) > 0.1"
             class="flex items-center gap-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-700 dark:text-amber-400">
            <AlertCircle class="h-4 w-4 shrink-0" />
            Harga tersimpan ({{ fmt(price_item.unit_price) }}) berbeda dari kalkulasi. Simpan overhead untuk sinkronkan.
        </div>

        <!-- Back -->
        <Link href="/rab/price-items" class="flex items-center gap-2 text-sm text-slate-400 hover:text-slate-700 dark:hover:text-white">
            <ArrowLeft class="h-4 w-4" /> Kembali ke Harga Satuan
        </Link>
    </div>

    <!-- Add/Edit Component Modal -->
    <Dialog :open="isOpen" @update:open="v => !v && closeModal()">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editingId ? 'Edit Komponen' : `Tambah Komponen ${typeLabels[form.component_type]}` }}
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 pt-1">
                <!-- Tipe -->
                <div class="space-y-1.5">
                    <Label>Tipe Komponen</Label>
                    <div class="flex gap-2">
                        <button v-for="t in TYPES" :key="t" type="button"
                                @click="form.component_type = t"
                                :class="[
                                    'flex-1 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                                    form.component_type === t
                                        ? 'bg-slate-800 dark:bg-white text-white dark:text-slate-900 border-slate-800'
                                        : 'bg-white dark:bg-white/5 text-slate-500 border-slate-200 dark:border-white/10 hover:border-slate-300',
                                ]">
                            {{ typeLabels[t] }}
                        </button>
                    </div>
                </div>

                <!-- Nama + Kode -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2 space-y-1.5">
                        <Label>Nama <span class="text-rose-500">*</span></Label>
                        <Input v-model="form.name" placeholder="Pekerja, Batu Belah…" required />
                        <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Kode <span class="text-slate-400 text-xs">(opsional)</span></Label>
                        <Input v-model="form.code" placeholder="A.1" class="font-mono" />
                    </div>
                </div>

                <!-- Satuan -->
                <div class="space-y-1.5">
                    <Label>Satuan <span class="text-rose-500">*</span></Label>
                    <div class="flex gap-2 flex-wrap">
                        <button v-for="u in unitSuggestions[form.component_type]" :key="u"
                                type="button"
                                @click="form.unit = u"
                                :class="[
                                    'px-2.5 py-1 rounded text-xs border transition-colors',
                                    form.unit === u
                                        ? 'bg-sky-500 text-white border-sky-500'
                                        : 'bg-white dark:bg-white/5 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-white/10 hover:border-slate-300',
                                ]">
                            {{ u }}
                        </button>
                    </div>
                    <Input v-model="form.unit" placeholder="OH, m³, kg…" required />
                </div>

                <!-- Koefisien + Harga -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label>Koefisien <span class="text-rose-500">*</span></Label>
                        <Input v-model="form.coefficient" type="number" min="0" step="0.0001" required />
                        <p class="text-xs text-slate-400">Contoh: 1.5 OH per m³</p>
                        <p v-if="form.errors.coefficient" class="text-xs text-rose-500">{{ form.errors.coefficient }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Harga Satuan (Rp) <span class="text-rose-500">*</span></Label>
                        <Input v-model="form.unit_price" type="number" min="0" step="1000" required />
                        <p v-if="form.errors.unit_price" class="text-xs text-rose-500">{{ form.errors.unit_price }}</p>
                    </div>
                </div>

                <!-- Preview jumlah -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/10">
                    <span class="text-sm text-slate-500">Jumlah Harga (preview)</span>
                    <span class="text-base font-bold text-slate-800 dark:text-white">{{ fmt(previewAmount) }}</span>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeModal">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit" :disabled="!form.name || !form.unit || form.processing" class="gap-2">
                        <Save class="h-4 w-4" /> {{ editingId ? 'Simpan' : 'Tambah' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
