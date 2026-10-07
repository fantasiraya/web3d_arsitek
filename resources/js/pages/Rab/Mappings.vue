<script setup lang="ts">
/**
 * Rab/Mappings.vue — Aturan Pencocokan Nama Objek → Harga Satuan
 *
 * Mapping dipakai otomatis oleh:
 *  - Fase B (Import CSV/Excel): cocokkan nama kolom → harga satuan
 *  - Fase C (Estimasi .glb): cocokkan nama mesh → harga satuan
 *
 * Tipe match:
 *  - exact       : cocok persis (case-insensitive)
 *  - name_pattern: wildcard * (contoh: DINDING_BATA_* cocok semua DINDING_BATA_...)
 *  - material    : cocokkan via kolom material di CSV
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Plus, Pencil, Trash2, X, Save, Search, GitMerge, Info,
    ChevronRight, ArrowRight,
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

interface PriceItem {
    id: string;
    code: string | null;
    name: string;
    unit: string;
    unit_price: number;
    category: string | null;
}

interface Mapping {
    id: string;
    match_type: 'exact' | 'name_pattern' | 'material';
    pattern: string;
    quantity_basis: 'area' | 'volume' | 'count' | 'length';
    rab_price_item_id: string;
    price_item: PriceItem;
}

const props = defineProps<{
    mappings:   Mapping[];
    priceItems: PriceItem[];
}>();

const { confirm } = useConfirm();

// ── search + filter ───────────────────────────────────────────────────────────
const search    = ref('');
const filterType = ref<'all' | 'exact' | 'name_pattern' | 'material'>('all');

const filtered = computed(() => {
    let list = props.mappings;
    if (filterType.value !== 'all') list = list.filter(m => m.match_type === filterType.value);
    const q = search.value.toLowerCase();
    if (q) list = list.filter(m =>
        m.pattern.toLowerCase().includes(q) ||
        m.price_item.name.toLowerCase().includes(q),
    );
    return list;
});

// ── modal form ────────────────────────────────────────────────────────────────
const isOpen     = ref(false);
const editingId  = ref<string | null>(null);
const piSearch   = ref('');

const form = useForm({
    match_type:        'exact' as 'exact' | 'name_pattern' | 'material',
    pattern:           '',
    rab_price_item_id: '',
    quantity_basis:    'area' as 'area' | 'volume' | 'count' | 'length',
});

const filteredPi = computed(() => {
    const q = piSearch.value.toLowerCase();
    if (!q) return props.priceItems;
    return props.priceItems.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.code ?? '').toLowerCase().includes(q) ||
        (p.category ?? '').toLowerCase().includes(q),
    );
});

function openCreate() {
    editingId.value = null;
    form.reset();
    piSearch.value  = '';
    isOpen.value    = true;
}

function openEdit(m: Mapping) {
    editingId.value        = m.id;
    form.match_type        = m.match_type;
    form.pattern           = m.pattern;
    form.rab_price_item_id = m.rab_price_item_id;
    form.quantity_basis    = m.quantity_basis;
    piSearch.value         = '';
    isOpen.value           = true;
}

function closeModal() { isOpen.value = false; editingId.value = null; form.reset(); }

function submit() {
    if (editingId.value) {
        form.put(`/rab/mappings/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Mapping diperbarui.'); closeModal(); },
        });
    } else {
        form.post('/rab/mappings', {
            preserveScroll: true,
            onSuccess: () => { toast.success('Mapping disimpan.'); closeModal(); },
        });
    }
}

async function del(m: Mapping) {
    const ok = await confirm({
        title:        'Hapus Mapping?',
        message:      `Aturan "${m.pattern}" akan dihapus.`,
        confirmLabel: 'Hapus',
        variant:      'destructive',
    });
    if (!ok) return;
    router.delete(`/rab/mappings/${m.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Mapping dihapus.'),
    });
}

// ── helpers ───────────────────────────────────────────────────────────────────
function fmt(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

const matchLabels: Record<string, string> = {
    exact:        'Tepat',
    name_pattern: 'Wildcard (*)',
    material:     'Material',
};

const matchColors: Record<string, string> = {
    exact:        'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/20',
    name_pattern: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-400 dark:border-violet-500/20',
    material:     'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
};

const basisLabels: Record<string, string> = {
    area: 'Luas (m²)', volume: 'Volume (m³)', count: 'Jumlah (unit)', length: 'Panjang (m\')',
};
</script>

<template>
    <Head title="Mapping RAB" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <GitMerge class="h-6 w-6 text-violet-500" /> Aturan Mapping RAB
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Cocokkan nama objek .glb atau kolom CSV secara otomatis ke harga satuan.
                </p>
            </div>
            <Button @click="openCreate" class="gap-2 shrink-0">
                <Plus class="h-4 w-4" /> Tambah Mapping
            </Button>
        </div>

        <!-- Info box -->
        <div class="flex items-start gap-3 p-4 rounded-xl bg-violet-50 dark:bg-violet-500/10 border border-violet-200 dark:border-violet-500/20">
            <Info class="h-5 w-5 text-violet-600 dark:text-violet-400 shrink-0 mt-0.5" />
            <div class="text-sm text-violet-700 dark:text-violet-400 space-y-1">
                <p class="font-semibold">Cara kerja mapping:</p>
                <ul class="list-disc list-inside text-xs space-y-0.5 text-violet-600 dark:text-violet-500">
                    <li><strong>Tepat (exact)</strong>: nama harus cocok persis. Contoh: <code>DINDING_BATA_15</code></li>
                    <li><strong>Wildcard (*)</strong>: gunakan * untuk cocokkan banyak nama. Contoh: <code>DINDING_*</code> cocok semua nama yang diawali DINDING_</li>
                    <li><strong>Material</strong>: cocokkan via kolom material di file CSV</li>
                    <li>Prioritas: Tepat → Wildcard → Material</li>
                </ul>
            </div>
        </div>

        <!-- Filter + search -->
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative flex-1 min-w-48 max-w-xs">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                <Input v-model="search" placeholder="Cari pattern atau harga satuan…" class="pl-9" />
            </div>
            <div class="flex items-center gap-1">
                <button v-for="t in ['all', 'exact', 'name_pattern', 'material']" :key="t"
                        @click="filterType = t as any"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                            filterType === t
                                ? 'bg-slate-800 dark:bg-white text-white dark:text-slate-900 border-slate-800 dark:border-white'
                                : 'bg-white dark:bg-white/5 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-white/10 hover:border-slate-300',
                        ]">
                    {{ t === 'all' ? 'Semua' : matchLabels[t] }}
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="mappings.length === 0"
             class="flex flex-col items-center py-16 text-center border border-dashed border-slate-200 dark:border-white/10 rounded-2xl">
            <GitMerge class="h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada aturan mapping</p>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 mb-4 max-w-sm">
                Buat aturan untuk mencocokkan nama objek model 3D atau kolom CSV ke harga satuan secara otomatis.
            </p>
            <Button @click="openCreate" variant="outline" class="gap-2">
                <Plus class="h-4 w-4" /> Buat Mapping Pertama
            </Button>
        </div>

        <!-- Mapping list -->
        <div v-else class="space-y-2">
            <p v-if="filtered.length === 0" class="text-sm text-center text-slate-400 py-6">
                Tidak ada hasil yang cocok.
            </p>

            <!-- Table header -->
            <div class="hidden sm:grid grid-cols-[auto_1fr_1fr_1fr_auto] gap-3 px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-400 bg-slate-50 dark:bg-white/[0.03] rounded-xl border border-slate-200 dark:border-white/10">
                <span class="w-24">Tipe</span>
                <span>Pattern</span>
                <span>Harga Satuan</span>
                <span>Basis</span>
                <span class="w-16"></span>
            </div>

            <div v-for="m in filtered" :key="m.id"
                 class="flex flex-col sm:grid sm:grid-cols-[auto_1fr_1fr_1fr_auto] gap-3 items-start sm:items-center px-4 py-3 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] hover:border-slate-300 dark:hover:border-white/20 transition-colors">
                <!-- Type badge -->
                <div class="w-24 shrink-0">
                    <span :class="['px-2 py-0.5 rounded-full text-xs font-medium border', matchColors[m.match_type]]">
                        {{ matchLabels[m.match_type] }}
                    </span>
                </div>

                <!-- Pattern -->
                <div class="min-w-0">
                    <p class="text-sm font-mono font-medium text-slate-800 dark:text-slate-100 truncate">{{ m.pattern }}</p>
                </div>

                <!-- Price item -->
                <div class="min-w-0">
                    <p class="text-sm text-slate-700 dark:text-slate-200 truncate">{{ m.price_item.name }}</p>
                    <p class="text-xs text-slate-400">{{ fmt(m.price_item.unit_price) }} / {{ m.price_item.unit }}</p>
                </div>

                <!-- Basis -->
                <div>
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ basisLabels[m.quantity_basis] }}</span>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 shrink-0">
                    <Button size="icon" variant="ghost" class="h-8 w-8" @click="openEdit(m)" title="Edit">
                        <Pencil class="h-3.5 w-3.5" />
                    </Button>
                    <Button size="icon" variant="ghost"
                            class="h-8 w-8 text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                            @click="del(m)" title="Hapus">
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog :open="isOpen" @update:open="v => !v && closeModal()">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit Mapping' : 'Tambah Mapping Baru' }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 pt-1">
                <!-- Match type -->
                <div class="space-y-1.5">
                    <Label>Tipe Pencocokan <span class="text-rose-500">*</span></Label>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="t in ['exact', 'name_pattern', 'material']" :key="t"
                                type="button"
                                @click="form.match_type = t as any"
                                :class="[
                                    'px-3 py-2 rounded-lg text-xs font-medium border transition-colors text-center',
                                    form.match_type === t
                                        ? 'bg-slate-800 dark:bg-white text-white dark:text-slate-900 border-slate-800 dark:border-white'
                                        : 'bg-white dark:bg-white/5 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-white/10 hover:border-slate-300',
                                ]">
                            {{ matchLabels[t] }}
                        </button>
                    </div>
                    <p class="text-xs text-slate-400">
                        <span v-if="form.match_type === 'exact'">Cocok persis dengan nama (case-insensitive)</span>
                        <span v-if="form.match_type === 'name_pattern'">Gunakan * sebagai wildcard. Contoh: <code class="font-mono">DINDING_*</code></span>
                        <span v-if="form.match_type === 'material'">Cocokkan via kolom material di file CSV</span>
                    </p>
                </div>

                <!-- Pattern -->
                <div class="space-y-1.5">
                    <Label>Pattern <span class="text-rose-500">*</span></Label>
                    <Input v-model="form.pattern"
                           :placeholder="form.match_type === 'name_pattern' ? 'DINDING_BATA_*' : 'DINDING_BATA_15'"
                           class="font-mono" />
                    <p v-if="form.errors.pattern" class="text-xs text-rose-500">{{ form.errors.pattern }}</p>
                </div>

                <!-- Quantity basis -->
                <div class="space-y-1.5">
                    <Label>Basis Kuantitas <span class="text-rose-500">*</span></Label>
                    <div class="grid grid-cols-2 gap-2">
                        <button v-for="b in ['area', 'volume', 'count', 'length']" :key="b"
                                type="button"
                                @click="form.quantity_basis = b as any"
                                :class="[
                                    'px-3 py-2 rounded-lg text-xs font-medium border transition-colors text-left',
                                    form.quantity_basis === b
                                        ? 'bg-slate-800 dark:bg-white text-white dark:text-slate-900 border-slate-800 dark:border-white'
                                        : 'bg-white dark:bg-white/5 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-white/10 hover:border-slate-300',
                                ]">
                            {{ basisLabels[b] }}
                        </button>
                    </div>
                </div>

                <!-- Price item -->
                <div class="space-y-1.5">
                    <Label>Harga Satuan Tujuan <span class="text-rose-500">*</span></Label>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <Input v-model="piSearch" placeholder="Cari harga satuan…" class="pl-9" />
                    </div>
                    <div class="border border-slate-200 dark:border-white/10 rounded-xl max-h-48 overflow-y-auto">
                        <p v-if="filteredPi.length === 0" class="px-4 py-3 text-xs text-slate-400 text-center">
                            Tidak ada hasil.
                            <Link href="/rab/price-items" class="underline text-sky-500">Tambah harga satuan</Link>.
                        </p>
                        <button v-for="p in filteredPi" :key="p.id"
                                type="button"
                                @click="form.rab_price_item_id = p.id"
                                :class="[
                                    'w-full flex items-center gap-3 px-4 py-2.5 text-left border-b border-slate-100 dark:border-white/5 last:border-0 transition-colors',
                                    form.rab_price_item_id === p.id
                                        ? 'bg-sky-50 dark:bg-sky-500/10'
                                        : 'hover:bg-slate-50 dark:hover:bg-white/[0.03]',
                                ]">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ p.name }}</p>
                                <p class="text-xs text-slate-400">{{ p.category ?? '' }} {{ p.code ? `· ${p.code}` : '' }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-mono text-slate-500">{{ p.unit }}</p>
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ fmt(p.unit_price) }}</p>
                            </div>
                            <div :class="[
                                'h-4 w-4 rounded-full border-2 shrink-0',
                                form.rab_price_item_id === p.id ? 'border-sky-500 bg-sky-500' : 'border-slate-300 dark:border-white/20',
                            ]" />
                        </button>
                    </div>
                    <p v-if="form.errors.rab_price_item_id" class="text-xs text-rose-500">{{ form.errors.rab_price_item_id }}</p>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeModal">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit"
                            :disabled="!form.pattern || !form.rab_price_item_id || form.processing"
                            class="gap-2">
                        <Save class="h-4 w-4" /> {{ editingId ? 'Simpan' : 'Tambah Mapping' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
