<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Plus, Pencil, Trash2, X, Save, Lock, Unlock, Eye, EyeOff,
    ChevronRight, ChevronDown, AlertTriangle, CheckCircle2,
    DollarSign, Percent, FileText, Cpu, Download,
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

interface RabPriceItemRef {
    id: string;
    name: string;
    unit: string;
    unit_price: number;
}

interface RabItem {
    id: string;
    section: string;
    description: string;
    unit: string;
    quantity: number;
    waste_percent: number;
    unit_price: number;
    subtotal: number;
    is_estimate: boolean;
    is_mapped: boolean;
    sort_order: number;
    rab_price_item_id: string | null;
}

interface RabDocument {
    id: string;
    title: string;
    status: 'draft' | 'final';
    source: string;
    is_visible_to_clients: boolean;
    overhead_percent: number;
    ppn_percent: number;
    subtotal: number;
    overhead_amount: number;
    ppn_amount: number;
    total: number;
    finalized_at: string | null;
    created_at: string;
}

interface Project {
    id: string;
    title: string;
    slug: string;
}

const props = defineProps<{
    project:          Project;
    document:         RabDocument;
    items_by_section: Record<string, RabItem[]>;
    can_edit:         boolean;
    is_owner:         boolean;
}>();

const { confirm } = useConfirm();

// ── collapsible sections ─────────────────────
const collapsed = ref<Set<string>>(new Set());
function toggleSection(s: string) {
    collapsed.value.has(s) ? collapsed.value.delete(s) : collapsed.value.add(s);
}

// ── computed totals ──────────────────────────
const totalItems = computed(() =>
    Object.values(props.items_by_section).reduce((s, arr) => s + arr.length, 0),
);

// ── add/edit item modal ──────────────────────
const isItemOpen   = ref(false);
const editingItemId = ref<string | null>(null);

const itemForm = useForm({
    section:           'Umum',
    description:       '',
    unit:              '',
    quantity:          0 as number,
    waste_percent:     0 as number,
    unit_price:        0 as number,
    rab_price_item_id: '' as string,
    sort_order:        0 as number,
});

function openAddItem(section = 'Umum') {
    editingItemId.value     = null;
    itemForm.reset();
    itemForm.section        = section;
    isItemOpen.value        = true;
}

function openEditItem(item: RabItem) {
    editingItemId.value         = item.id;
    itemForm.section            = item.section;
    itemForm.description        = item.description;
    itemForm.unit               = item.unit;
    itemForm.quantity           = item.quantity;
    itemForm.waste_percent      = item.waste_percent;
    itemForm.unit_price         = item.unit_price;
    itemForm.rab_price_item_id  = item.rab_price_item_id ?? '';
    itemForm.sort_order         = item.sort_order;
    isItemOpen.value            = true;
}

function closeItem() {
    isItemOpen.value    = false;
    editingItemId.value = null;
    itemForm.reset();
}

function submitItem() {
    const base = `/projects/${props.project.id}/rab/${props.document.id}/items`;
    if (editingItemId.value) {
        itemForm.put(`${base}/${editingItemId.value}`, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Item diperbarui.'); closeItem(); },
        });
    } else {
        itemForm.post(base, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Item ditambahkan.'); closeItem(); },
        });
    }
}

// ── delete item ──────────────────────────────
async function deleteItem(item: RabItem) {
    const ok = await confirm({
        title: 'Hapus item?',
        message: `"${item.description}" akan dihapus dari RAB.`,
        confirmLabel: 'Hapus',
        variant: 'destructive',
    });
    if (!ok) return;

    router.delete(`/projects/${props.project.id}/rab/${props.document.id}/items/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Item dihapus.'),
    });
}

// ── edit doc metadata ────────────────────────
const isMetaOpen = ref(false);
const metaForm = useForm({
    title:            props.document.title,
    overhead_percent: props.document.overhead_percent,
    ppn_percent:      props.document.ppn_percent,
});

function openMeta() { isMetaOpen.value = true; }
function closeMeta() { isMetaOpen.value = false; }
function submitMeta() {
    metaForm.put(`/projects/${props.project.id}/rab/${props.document.id}`, {
        preserveScroll: true,
        onSuccess: () => { toast.success('RAB diperbarui.'); closeMeta(); },
    });
}

// ── finalize / reopen ────────────────────────
async function finalizeDoc() {
    const ok = await confirm({
        title:   'Finalisasi RAB?',
        message: 'Dokumen akan dikunci dan tidak dapat diedit lagi. Anda bisa membuka kembali (reopen) jika diperlukan.',
        confirmLabel: 'Finalisasi',
    });
    if (!ok) return;
    router.post(`/projects/${props.project.id}/rab/${props.document.id}/finalize`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('RAB telah difinalisasi.'),
    });
}

async function reopenDoc() {
    const ok = await confirm({
        title: 'Buka Kembali RAB?',
        message: 'Dokumen akan kembali ke status draft dan dapat diedit.',
        confirmLabel: 'Reopen',
    });
    if (!ok) return;
    router.post(`/projects/${props.project.id}/rab/${props.document.id}/reopen`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('RAB dibuka kembali.'),
    });
}

// ── visibility toggle ────────────────────────
function toggleVisibility() {
    router.patch(`/projects/${props.project.id}/rab/${props.document.id}/visibility`, {
        is_visible_to_clients: !props.document.is_visible_to_clients,
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success(
            props.document.is_visible_to_clients ? 'RAB disembunyikan dari klien.' : 'RAB sekarang terlihat oleh klien.',
        ),
    });
}

// ── helpers ──────────────────────────────────
function fmt(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}
</script>

<template>
    <Head :title="`${document.title} — RAB`" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400 flex-wrap">
            <Link href="/dashboard" class="hover:text-slate-600 dark:hover:text-slate-300">Dashboard</Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <Link :href="`/projects/${project.id}/rab`" class="hover:text-slate-600 dark:hover:text-slate-300">
                RAB — {{ project.title }}
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-900 dark:text-white font-medium truncate max-w-[200px]">{{ document.title }}</span>
        </nav>

        <!-- Header row -->
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ document.title }}</h1>
                    <span :class="[
                        'px-2.5 py-1 rounded-full text-xs font-semibold border',
                        document.status === 'draft'
                            ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20'
                            : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20'
                    ]">
                        <Lock v-if="document.status === 'final'" class="h-3 w-3 inline mr-0.5" />
                        {{ document.status === 'draft' ? 'Draft' : 'Final' }}
                    </span>
                    <span v-if="document.is_visible_to_clients"
                          class="flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold border bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/20">
                        <Eye class="h-3 w-3" /> Terlihat Klien
                    </span>
                </div>
                <p class="text-sm text-slate-400 mt-1">{{ totalItems }} item pekerjaan</p>
            </div>

            <!-- Action buttons -->
            <div v-if="is_owner" class="flex items-center gap-2 flex-wrap">
                <!-- Visibility toggle — boleh kapan saja -->
                <Button size="sm" variant="outline" class="gap-1.5" @click="toggleVisibility">
                    <component :is="document.is_visible_to_clients ? EyeOff : Eye" class="h-4 w-4" />
                    {{ document.is_visible_to_clients ? 'Sembunyikan dari Klien' : 'Tampilkan ke Klien' }}
                </Button>

                <template v-if="can_edit">
                    <Button size="sm" variant="outline" class="gap-1.5" @click="openMeta">
                        <Pencil class="h-4 w-4" /> Edit
                    </Button>
                    <Link :href="`/projects/${project.id}/rab/${document.id}/import`">
                        <Button size="sm" variant="outline" class="gap-1.5">
                            <FileText class="h-4 w-4" /> Import CSV/Excel
                        </Button>
                    </Link>
                    <Link :href="`/projects/${project.id}/rab/${document.id}/glb-estimator`">
                        <Button size="sm" variant="outline"
                                class="gap-1.5 border-violet-300 dark:border-violet-500/40 text-violet-700 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-500/10">
                            <Cpu class="h-4 w-4" /> Estimasi 3D
                        </Button>
                    </Link>
                    <Button v-if="document.status === 'draft'" size="sm" class="gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white" @click="finalizeDoc">
                        <CheckCircle2 class="h-4 w-4" /> Finalisasi
                    </Button>
                </template>
                <Button v-if="document.status === 'final' && is_owner" size="sm" variant="outline" class="gap-1.5" @click="reopenDoc">
                    <Unlock class="h-4 w-4" /> Reopen
                </Button>

                <!-- Export Excel — selalu muncul untuk owner -->
                <a :href="`/projects/${project.id}/rab/${document.id}/export`" target="_blank">
                    <Button size="sm" variant="outline"
                            class="gap-1.5 border-sky-300 dark:border-sky-500/40 text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-500/10">
                        <Download class="h-4 w-4" /> Export Excel
                    </Button>
                </a>
            </div>
        </div>

        <!-- Final lock notice -->
        <div v-if="document.status === 'final'"
             class="flex items-center gap-2 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/10 text-sm text-slate-500">
            <Lock class="h-4 w-4 shrink-0" />
            Dokumen ini telah difinalisasi pada {{ document.finalized_at ? new Date(document.finalized_at).toLocaleDateString('id-ID') : '—' }}. Hanya pemilik yang dapat membuka kembali.
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-4 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10">
                <p class="text-xs text-slate-400 uppercase tracking-wide">Subtotal</p>
                <p class="text-lg font-bold text-slate-800 dark:text-white mt-0.5">{{ fmt(document.subtotal) }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10">
                <p class="text-xs text-slate-400 uppercase tracking-wide">Overhead ({{ document.overhead_percent }}%)</p>
                <p class="text-lg font-bold text-slate-800 dark:text-white mt-0.5">{{ fmt(document.overhead_amount) }}</p>
            </div>
            <div class="p-4 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10">
                <p class="text-xs text-slate-400 uppercase tracking-wide">PPN ({{ document.ppn_percent }}%)</p>
                <p class="text-lg font-bold text-slate-800 dark:text-white mt-0.5">{{ fmt(document.ppn_amount) }}</p>
            </div>
            <div class="p-4 rounded-xl bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20">
                <p class="text-xs text-sky-600 dark:text-sky-400 uppercase tracking-wide font-semibold">Total</p>
                <p class="text-lg font-bold text-sky-700 dark:text-sky-300 mt-0.5">{{ fmt(document.total) }}</p>
            </div>
        </div>

        <!-- Item sections -->
        <div class="space-y-4">
            <!-- Add item button (draft only) -->
            <div v-if="can_edit" class="flex justify-end">
                <Button size="sm" variant="outline" class="gap-1.5" @click="openAddItem()">
                    <Plus class="h-4 w-4" /> Tambah Item
                </Button>
            </div>

            <!-- Empty items state -->
            <div v-if="totalItems === 0"
                 class="flex flex-col items-center py-16 text-center border border-dashed border-slate-200 dark:border-white/10 rounded-xl">
                <FileText class="h-8 w-8 text-slate-300 dark:text-slate-600 mb-2" />
                <p class="text-sm text-slate-500">Belum ada item pekerjaan.</p>
                <Button v-if="can_edit" size="sm" variant="ghost" class="mt-3 gap-1.5" @click="openAddItem()">
                    <Plus class="h-4 w-4" /> Tambah Item Pertama
                </Button>
            </div>

            <!-- Section blocks -->
            <div v-for="(items, section) in items_by_section" :key="String(section)"
                 class="border border-slate-200 dark:border-white/10 rounded-xl overflow-hidden">
                <!-- Section header -->
                <div class="flex items-center justify-between px-4 py-2.5 bg-slate-50 dark:bg-white/[0.03]">
                    <button class="flex items-center gap-2 text-left" @click="toggleSection(String(section))">
                        <component :is="collapsed.has(String(section)) ? ChevronRight : ChevronDown"
                                   class="h-4 w-4 text-slate-400 shrink-0" />
                        <span class="font-semibold text-sm text-slate-700 dark:text-slate-200">{{ section }}</span>
                        <Badge variant="secondary" class="text-xs">{{ items.length }}</Badge>
                    </button>
                    <Button v-if="can_edit" size="icon" variant="ghost" class="h-7 w-7"
                            @click="openAddItem(String(section))" title="Tambah item di seksi ini">
                        <Plus class="h-3.5 w-3.5" />
                    </Button>
                </div>

                <!-- Items table -->
                <div v-if="!collapsed.has(String(section))">
                    <!-- Table header -->
                    <div class="hidden sm:grid grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] gap-2 px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-400 bg-white dark:bg-transparent border-b border-slate-100 dark:border-white/5">
                        <span>Uraian Pekerjaan</span>
                        <span>Satuan</span>
                        <span class="text-right">Volume</span>
                        <span class="text-right">Harga Satuan</span>
                        <span class="text-right">Subtotal</span>
                        <span></span>
                    </div>

                    <div v-for="item in items" :key="item.id"
                         class="grid grid-cols-1 sm:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] gap-2 items-center px-4 py-3 border-b border-slate-100 dark:border-white/5 last:border-0 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-800 dark:text-slate-100 truncate">
                                {{ item.description }}
                                <span v-if="item.is_estimate"
                                      class="ml-1 px-1.5 py-0.5 rounded text-[10px] bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400 border border-violet-200 dark:border-violet-500/20">
                                    Estimasi
                                </span>
                            </p>
                            <p v-if="item.waste_percent > 0" class="text-xs text-slate-400 mt-0.5">
                                Sisa: {{ item.waste_percent }}%
                            </p>
                        </div>
                        <span class="text-xs text-slate-500 font-mono">{{ item.unit }}</span>
                        <span class="text-sm text-right text-slate-700 dark:text-slate-300">{{ item.quantity.toLocaleString('id-ID') }}</span>
                        <span class="text-sm text-right text-slate-700 dark:text-slate-300">{{ fmt(item.unit_price) }}</span>
                        <span class="text-sm text-right font-semibold text-slate-800 dark:text-slate-100">{{ fmt(item.subtotal) }}</span>
                        <div v-if="can_edit" class="flex items-center gap-0.5 justify-end">
                            <Button size="icon" variant="ghost" class="h-7 w-7" @click="openEditItem(item)">
                                <Pencil class="h-3 w-3" />
                            </Button>
                            <Button size="icon" variant="ghost"
                                    class="h-7 w-7 text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                    @click="deleteItem(item)">
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Item Modal -->
    <Dialog :open="isItemOpen" @update:open="v => !v && closeItem()">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ editingItemId ? 'Edit Item' : 'Tambah Item' }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitItem" class="space-y-4 pt-1">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label>Bagian Pekerjaan <span class="text-rose-500">*</span></Label>
                        <Input v-model="itemForm.section" placeholder="Struktur, Finishing…" required />
                        <p v-if="itemForm.errors.section" class="text-xs text-rose-500">{{ itemForm.errors.section }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Satuan <span class="text-rose-500">*</span></Label>
                        <Input v-model="itemForm.unit" placeholder="m², m³, unit…" required />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label>Uraian Pekerjaan <span class="text-rose-500">*</span></Label>
                    <Input v-model="itemForm.description" placeholder="Pasang bata merah campuran 1:4" required />
                    <p v-if="itemForm.errors.description" class="text-xs text-rose-500">{{ itemForm.errors.description }}</p>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <Label>Volume <span class="text-rose-500">*</span></Label>
                        <Input v-model="itemForm.quantity" type="number" min="0" step="0.001" required />
                        <p v-if="itemForm.errors.quantity" class="text-xs text-rose-500">{{ itemForm.errors.quantity }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Sisa / Waste (%)</Label>
                        <Input v-model="itemForm.waste_percent" type="number" min="0" max="100" step="0.5" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Harga Satuan (Rp) <span class="text-rose-500">*</span></Label>
                        <Input v-model="itemForm.unit_price" type="number" min="0" step="1000" required />
                        <p v-if="itemForm.errors.unit_price" class="text-xs text-rose-500">{{ itemForm.errors.unit_price }}</p>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeItem">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit" :disabled="itemForm.processing" class="gap-2">
                        <Save class="h-4 w-4" />
                        {{ editingItemId ? 'Simpan' : 'Tambah' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Edit Doc Metadata Modal -->
    <Dialog :open="isMetaOpen" @update:open="v => !v && closeMeta()">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Edit Dokumen RAB</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitMeta" class="space-y-4 pt-1">
                <div class="space-y-1.5">
                    <Label>Judul RAB <span class="text-rose-500">*</span></Label>
                    <Input v-model="metaForm.title" required />
                    <p v-if="metaForm.errors.title" class="text-xs text-rose-500">{{ metaForm.errors.title }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label>Overhead (%)</Label>
                        <Input v-model="metaForm.overhead_percent" type="number" min="0" max="100" step="0.5" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>PPN (%)</Label>
                        <Input v-model="metaForm.ppn_percent" type="number" min="0" max="100" step="0.5" />
                    </div>
                </div>
                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeMeta">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit" :disabled="metaForm.processing" class="gap-2">
                        <Save class="h-4 w-4" /> Simpan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
