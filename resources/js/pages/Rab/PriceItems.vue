<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Plus, Pencil, Trash2, X, Save, Search, Tag, DollarSign, ChevronDown, ChevronRight,
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

const props = defineProps<{
    price_items: PriceItem[];
    grouped: Record<string, PriceItem[]>;
}>();

const { confirm } = useConfirm();

// ── search ──────────────────────────────────
const search = ref('');
const filteredItems = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.price_items;
    return props.price_items.filter(
        i => i.name.toLowerCase().includes(q) ||
             (i.code ?? '').toLowerCase().includes(q) ||
             (i.category ?? '').toLowerCase().includes(q),
    );
});

// grouped yang sudah difilter
const filteredGrouped = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.grouped;
    const result: Record<string, PriceItem[]> = {};
    for (const [cat, items] of Object.entries(props.grouped)) {
        const filtered = items.filter(
            i => i.name.toLowerCase().includes(q) ||
                 (i.code ?? '').toLowerCase().includes(q),
        );
        if (filtered.length) result[cat] = filtered;
    }
    return result;
});

const collapsedCategories = ref<Set<string>>(new Set());
function toggleCategory(cat: string) {
    if (collapsedCategories.value.has(cat)) {
        collapsedCategories.value.delete(cat);
    } else {
        collapsedCategories.value.add(cat);
    }
}

// ── form modal ───────────────────────────────
const isOpen     = ref(false);
const editingId  = ref<string | null>(null);

const form = useForm({
    code:       '',
    name:       '',
    unit:       '',
    unit_price: '' as string | number,
    category:   '',
});

function openCreate() {
    editingId.value = null;
    form.reset();
    isOpen.value    = true;
}

function openEdit(item: PriceItem) {
    editingId.value    = item.id;
    form.code          = item.code ?? '';
    form.name          = item.name;
    form.unit          = item.unit;
    form.unit_price    = item.unit_price;
    form.category      = item.category ?? '';
    isOpen.value       = true;
}

function closeModal() {
    isOpen.value    = false;
    editingId.value = null;
    form.reset();
}

function submit() {
    if (editingId.value) {
        form.put(`/rab/price-items/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Harga satuan berhasil diperbarui.'); closeModal(); },
        });
    } else {
        form.post('/rab/price-items', {
            preserveScroll: true,
            onSuccess: () => { toast.success('Harga satuan berhasil ditambahkan.'); closeModal(); },
        });
    }
}

async function deleteItem(item: PriceItem) {
    const ok = await confirm({
        title:   'Hapus Harga Satuan?',
        message: `"${item.name}" akan dihapus. Item RAB yang sudah menggunakan harga ini tidak akan berubah (snapshot).`,
        confirmLabel: 'Hapus',
        variant:      'destructive',
    });
    if (!ok) return;

    router.delete(`/rab/price-items/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Harga satuan dihapus.'),
        onError:   () => toast.error('Gagal menghapus harga satuan.'),
    });
}

function formatCurrency(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}
</script>

<template>
    <Head title="Harga Satuan RAB" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Harga Satuan</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Master daftar harga satuan pekerjaan. Dipakai sebagai referensi saat membuat RAB.
                </p>
            </div>
            <Button @click="openCreate" class="gap-2">
                <Plus class="h-4 w-4" /> Tambah Harga Satuan
            </Button>
        </div>

        <!-- Search -->
        <div class="relative max-w-sm">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input v-model="search" placeholder="Cari nama, kode, kategori…" class="pl-9" />
        </div>

        <!-- Empty state -->
        <div v-if="price_items.length === 0"
             class="flex flex-col items-center justify-center py-20 text-center border border-dashed border-slate-200 dark:border-white/10 rounded-2xl">
            <DollarSign class="h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada harga satuan</p>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 mb-4">Tambahkan harga satuan pekerjaan untuk mulai membuat RAB.</p>
            <Button @click="openCreate" variant="outline" class="gap-2">
                <Plus class="h-4 w-4" /> Tambah Pertama
            </Button>
        </div>

        <!-- Grouped list -->
        <div v-else class="space-y-3">
            <div v-for="(items, category) in filteredGrouped" :key="category"
                 class="border border-slate-200 dark:border-white/10 rounded-xl overflow-hidden">
                <!-- Category header -->
                <button
                    class="w-full flex items-center gap-2 px-4 py-3 bg-slate-50 dark:bg-white/[0.03] hover:bg-slate-100 dark:hover:bg-white/[0.05] transition-colors text-left"
                    @click="toggleCategory(String(category))"
                >
                    <component :is="collapsedCategories.has(String(category)) ? ChevronRight : ChevronDown"
                               class="h-4 w-4 text-slate-400 shrink-0" />
                    <Tag class="h-4 w-4 text-sky-500 shrink-0" />
                    <span class="font-medium text-sm text-slate-700 dark:text-slate-200">{{ category }}</span>
                    <Badge variant="secondary" class="ml-auto text-xs">{{ items.length }}</Badge>
                </button>

                <!-- Items table -->
                <div v-if="!collapsedCategories.has(String(category))" class="divide-y divide-slate-100 dark:divide-white/5">
                    <div v-for="item in items" :key="item.id"
                         class="flex items-center gap-4 px-4 py-3 hover:bg-slate-50 dark:hover:bg-white/[0.02] transition-colors">
                        <div class="w-20 shrink-0">
                            <span class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ item.code ?? '—' }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ item.name }}</p>
                        </div>
                        <div class="w-16 text-center">
                            <Badge variant="outline" class="text-xs">{{ item.unit }}</Badge>
                        </div>
                        <div class="w-36 text-right">
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                {{ formatCurrency(item.unit_price) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <Button size="icon" variant="ghost" class="h-8 w-8" @click="openEdit(item)" title="Edit">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button size="icon" variant="ghost" class="h-8 w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                    @click="deleteItem(item)" title="Hapus">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No match search -->
            <p v-if="Object.keys(filteredGrouped).length === 0" class="text-sm text-center text-slate-400 py-8">
                Tidak ada harga satuan yang cocok dengan "{{ search }}".
            </p>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog :open="isOpen" @update:open="v => !v && closeModal()">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit Harga Satuan' : 'Tambah Harga Satuan' }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 pt-1">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="pi-code">Kode <span class="text-slate-400 text-xs">(opsional)</span></Label>
                        <Input id="pi-code" v-model="form.code" placeholder="DINDING_BATA_15" />
                        <p v-if="form.errors.code" class="text-xs text-rose-500">{{ form.errors.code }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="pi-category">Kategori</Label>
                        <Input id="pi-category" v-model="form.category" placeholder="Struktur, Finishing…" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="pi-name">Nama Pekerjaan <span class="text-rose-500">*</span></Label>
                    <Input id="pi-name" v-model="form.name" placeholder="Pasang bata merah 1:4" required />
                    <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="pi-unit">Satuan <span class="text-rose-500">*</span></Label>
                        <Input id="pi-unit" v-model="form.unit" placeholder="m², m³, unit…" required />
                        <p v-if="form.errors.unit" class="text-xs text-rose-500">{{ form.errors.unit }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="pi-price">Harga Satuan (Rp) <span class="text-rose-500">*</span></Label>
                        <Input id="pi-price" v-model="form.unit_price" type="number" min="0" step="1000"
                               placeholder="150000" required />
                        <p v-if="form.errors.unit_price" class="text-xs text-rose-500">{{ form.errors.unit_price }}</p>
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeModal">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                        <Save class="h-4 w-4" />
                        {{ editingId ? 'Simpan Perubahan' : 'Tambah' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
