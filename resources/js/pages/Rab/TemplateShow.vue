<script setup lang="ts">
/**
 * Rab/TemplateShow.vue — Halaman detail & kelola item template RAB
 *
 * Fungsi utama:
 *  - Lihat semua item yang ada di template ini (dikelompokkan per section)
 *  - Tambah item dari daftar harga satuan yang sudah ada
 *  - Hapus item dari template
 *  - Template ini bisa dipilih saat buat dokumen RAB baru di project manapun
 */
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ChevronRight, Plus, Trash2, X, Save, Tag, DollarSign,
    LayoutList, ArrowLeft, Search, GripVertical,
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
interface PriceItem {
    id: string;
    code: string | null;
    name: string;
    unit: string;
    unit_price: number;
    category: string | null;
}

interface TemplateItem {
    id: string;
    rab_template_id: string;
    rab_price_item_id: string;
    section: string;
    sort_order: number;
    price_item: PriceItem;
}

interface RabTemplate {
    id: string;
    name: string;
    description: string | null;
    items: TemplateItem[];
}

const props = defineProps<{
    template:   RabTemplate;
    priceItems: PriceItem[];
}>();

const { confirm } = useConfirm();

// ── Grouped items per section ─────────────────────────────────────────────────
const itemsBySection = computed(() => {
    const groups: Record<string, TemplateItem[]> = {};
    for (const item of props.template.items) {
        const sec = item.section || 'Umum';
        if (!groups[sec]) groups[sec] = [];
        groups[sec].push(item);
    }
    return groups;
});

const totalItems = computed(() => props.template.items.length);

// ── Add item modal ────────────────────────────────────────────────────────────
const isAddOpen  = ref(false);
const searchItem = ref('');

const filteredPriceItems = computed(() => {
    const q = searchItem.value.toLowerCase();
    if (!q) return props.priceItems;
    return props.priceItems.filter(
        p => p.name.toLowerCase().includes(q) ||
             (p.code ?? '').toLowerCase().includes(q) ||
             (p.category ?? '').toLowerCase().includes(q),
    );
});

// Items yang sudah ada di template (untuk highlight / disable duplikat)
const existingPriceItemIds = computed(() =>
    new Set(props.template.items.map(i => i.rab_price_item_id)),
);

const addForm = useForm({
    rab_price_item_id: '',
    section:           'Umum',
});

function openAdd() {
    searchItem.value = '';
    addForm.reset();
    addForm.section = 'Umum';
    isAddOpen.value = true;
}

function selectPriceItem(item: PriceItem) {
    addForm.rab_price_item_id = item.id;
}

function submitAdd() {
    addForm.post(`/rab/templates/${props.template.id}/items`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Item ditambahkan ke template.');
            isAddOpen.value = false;
            addForm.reset();
        },
    });
}

// ── Remove item ───────────────────────────────────────────────────────────────
async function removeItem(item: TemplateItem) {
    const ok = await confirm({
        title:        'Hapus item dari template?',
        message:      `"${item.price_item.name}" akan dihapus dari template ini. Dokumen RAB yang sudah menggunakan template ini tidak berubah.`,
        confirmLabel: 'Hapus',
        variant:      'destructive',
    });
    if (!ok) return;

    router.delete(`/rab/templates/${props.template.id}/items/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Item dihapus.'),
    });
}

// ── Format helpers ────────────────────────────────────────────────────────────
function fmt(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

// Section options — ambil dari items yang sudah ada + default
const commonSections = ['Pekerjaan Persiapan', 'Pekerjaan Struktur', 'Pekerjaan Arsitektur', 'Pekerjaan MEP', 'Pekerjaan Finishing', 'Lain-lain'];
</script>

<template>
    <Head :title="`Template: ${template.name}`" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400 flex-wrap">
            <Link href="/rab/templates" class="hover:text-slate-600 dark:hover:text-slate-300 flex items-center gap-1">
                <LayoutList class="h-3.5 w-3.5" /> Template RAB
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-900 dark:text-white font-medium truncate">{{ template.name }}</span>
        </nav>

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ template.name }}</h1>
                <p v-if="template.description" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ template.description }}
                </p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">
                    {{ totalItems }} item pekerjaan
                </p>
            </div>
            <Button @click="openAdd" class="gap-2 shrink-0">
                <Plus class="h-4 w-4" /> Tambah Item
            </Button>
        </div>

        <!-- Info box -->
        <div class="p-4 rounded-xl bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 text-sm text-sky-700 dark:text-sky-400">
            <p class="font-semibold mb-1">Cara menggunakan template ini:</p>
            <ol class="list-decimal list-inside space-y-0.5 text-xs text-sky-600 dark:text-sky-500">
                <li>Isi template dengan item-item pekerjaan standar yang sering Anda gunakan.</li>
                <li>Saat membuat dokumen RAB baru di project, pilih template ini dari dropdown.</li>
                <li>Semua item akan ter-copy otomatis ke RAB baru dengan snapshot harga saat itu.</li>
                <li>Anda tinggal isi volume/kuantitas masing-masing item.</li>
            </ol>
        </div>

        <!-- Empty state -->
        <div v-if="totalItems === 0"
             class="flex flex-col items-center py-16 text-center border border-dashed border-slate-200 dark:border-white/10 rounded-2xl">
            <LayoutList class="h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="font-medium text-slate-600 dark:text-slate-300">Template masih kosong</p>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 mb-4">
                Tambahkan item pekerjaan dari daftar harga satuan Anda.
                <br>Belum punya harga satuan?
                <Link href="/rab/price-items" class="underline text-sky-500">Buat di sini</Link>.
            </p>
            <Button @click="openAdd" variant="outline" class="gap-2">
                <Plus class="h-4 w-4" /> Tambah Item Pertama
            </Button>
        </div>

        <!-- Items grouped by section -->
        <div v-else class="space-y-4">
            <div v-for="(items, section) in itemsBySection" :key="String(section)"
                 class="border border-slate-200 dark:border-white/10 rounded-xl overflow-hidden">
                <!-- Section header -->
                <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 dark:bg-white/[0.03]">
                    <Tag class="h-3.5 w-3.5 text-sky-500 shrink-0" />
                    <span class="font-semibold text-sm text-slate-700 dark:text-slate-200">{{ section }}</span>
                    <Badge variant="secondary" class="text-xs ml-1">{{ items.length }}</Badge>
                </div>

                <!-- Table header -->
                <div class="hidden sm:grid grid-cols-[2fr_1fr_1fr_auto] gap-2 px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-400 bg-white dark:bg-transparent border-b border-slate-100 dark:border-white/5">
                    <span>Nama Pekerjaan</span>
                    <span>Satuan</span>
                    <span class="text-right">Harga Satuan</span>
                    <span></span>
                </div>

                <!-- Items -->
                <div v-for="item in items" :key="item.id"
                     class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 dark:border-white/5 last:border-0 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">
                            {{ item.price_item.name }}
                        </p>
                        <p v-if="item.price_item.code" class="text-xs text-slate-400 font-mono">
                            {{ item.price_item.code }}
                        </p>
                    </div>
                    <span class="hidden sm:block w-20 text-xs font-mono text-slate-500 text-center">
                        {{ item.price_item.unit }}
                    </span>
                    <span class="hidden sm:block w-36 text-sm font-semibold text-slate-700 dark:text-slate-200 text-right">
                        {{ fmt(item.price_item.unit_price) }}
                    </span>
                    <Button
                        size="icon" variant="ghost"
                        class="h-7 w-7 shrink-0 text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                        @click="removeItem(item)"
                        title="Hapus dari template"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Summary -->
        <div v-if="totalItems > 0" class="p-4 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 flex items-center justify-between">
            <span class="text-sm text-slate-500">Total item di template ini</span>
            <span class="text-lg font-bold text-slate-800 dark:text-white">{{ totalItems }} item</span>
        </div>

        <!-- Back link -->
        <div class="flex justify-start pt-2">
            <Link href="/rab/templates" class="flex items-center gap-2 text-sm text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors">
                <ArrowLeft class="h-4 w-4" /> Kembali ke daftar template
            </Link>
        </div>
    </div>

    <!-- Add Item Modal -->
    <Dialog :open="isAddOpen" @update:open="v => !v && (isAddOpen = false)">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Tambah Item ke Template</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 pt-1">
                <!-- Section -->
                <div class="space-y-1.5">
                    <Label>Bagian Pekerjaan <span class="text-rose-500">*</span></Label>
                    <div class="flex gap-2">
                        <Input v-model="addForm.section" placeholder="Struktur, Finishing…" class="flex-1" />
                    </div>
                    <!-- Quick section buttons -->
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        <button
                            v-for="sec in commonSections" :key="sec"
                            type="button"
                            @click="addForm.section = sec"
                            :class="[
                                'px-2 py-0.5 rounded-full text-xs border transition-colors',
                                addForm.section === sec
                                    ? 'bg-sky-500 text-white border-sky-500'
                                    : 'bg-slate-50 dark:bg-white/5 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-white/10 hover:border-sky-300',
                            ]"
                        >
                            {{ sec }}
                        </button>
                    </div>
                </div>

                <!-- Search price item -->
                <div class="space-y-1.5">
                    <Label>Pilih Harga Satuan <span class="text-rose-500">*</span></Label>
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <Input v-model="searchItem" placeholder="Cari nama, kode…" class="pl-9" />
                    </div>
                </div>

                <!-- Price item list -->
                <div class="border border-slate-200 dark:border-white/10 rounded-xl overflow-hidden max-h-64 overflow-y-auto">
                    <div v-if="filteredPriceItems.length === 0"
                         class="px-4 py-6 text-center text-sm text-slate-400">
                        Tidak ada harga satuan yang cocok.
                        <Link href="/rab/price-items" class="underline text-sky-500">Tambah harga satuan</Link>.
                    </div>
                    <button
                        v-for="item in filteredPriceItems"
                        :key="item.id"
                        type="button"
                        :disabled="existingPriceItemIds.has(item.id)"
                        @click="selectPriceItem(item)"
                        :class="[
                            'w-full flex items-center gap-3 px-4 py-3 text-left border-b border-slate-100 dark:border-white/5 last:border-0 transition-colors',
                            addForm.rab_price_item_id === item.id
                                ? 'bg-sky-50 dark:bg-sky-500/10'
                                : existingPriceItemIds.has(item.id)
                                    ? 'opacity-40 cursor-not-allowed bg-slate-50 dark:bg-white/[0.02]'
                                    : 'hover:bg-slate-50 dark:hover:bg-white/[0.03]',
                        ]"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ item.name }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span v-if="item.code" class="text-xs font-mono text-slate-400">{{ item.code }}</span>
                                <span v-if="item.category" class="text-xs text-slate-400">{{ item.category }}</span>
                                <span v-if="existingPriceItemIds.has(item.id)" class="text-xs text-amber-500">Sudah ada</span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs font-mono text-slate-500">{{ item.unit }}</span>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ fmt(item.unit_price) }}</p>
                        </div>
                        <div :class="[
                            'h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0',
                            addForm.rab_price_item_id === item.id
                                ? 'border-sky-500 bg-sky-500'
                                : 'border-slate-300 dark:border-white/20',
                        ]">
                            <div v-if="addForm.rab_price_item_id === item.id" class="h-2 w-2 rounded-full bg-white" />
                        </div>
                    </button>
                </div>

                <p v-if="addForm.errors.rab_price_item_id" class="text-xs text-rose-500">
                    {{ addForm.errors.rab_price_item_id }}
                </p>
            </div>

            <DialogFooter class="pt-2">
                <Button type="button" variant="outline" @click="isAddOpen = false">
                    <X class="h-4 w-4 mr-1.5" /> Batal
                </Button>
                <Button
                    @click="submitAdd"
                    :disabled="!addForm.rab_price_item_id || !addForm.section || addForm.processing"
                    class="gap-2"
                >
                    <Save class="h-4 w-4" /> Tambah ke Template
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
