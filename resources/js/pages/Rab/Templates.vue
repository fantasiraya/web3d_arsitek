<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Plus, Pencil, Trash2, X, Save, LayoutList, ChevronRight } from '@lucide/vue';
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

interface RabTemplate {
    id: string;
    name: string;
    description: string | null;
    items_count: number;
    created_at: string;
}

const props = defineProps<{
    templates: RabTemplate[];
}>();

const { confirm } = useConfirm();

// ── modal form ───────────────────────────────
const isOpen    = ref(false);
const editingId = ref<string | null>(null);

const form = useForm({
    name:        '',
    description: '',
});

function openCreate() {
    editingId.value = null;
    form.reset();
    isOpen.value    = true;
}

function openEdit(t: RabTemplate) {
    editingId.value    = t.id;
    form.name          = t.name;
    form.description   = t.description ?? '';
    isOpen.value       = true;
}

function closeModal() {
    isOpen.value    = false;
    editingId.value = null;
    form.reset();
}

function submit() {
    if (editingId.value) {
        form.put(`/rab/templates/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Template berhasil diperbarui.'); closeModal(); },
        });
    } else {
        form.post('/rab/templates', {
            preserveScroll: true,
            onSuccess: () => { toast.success('Template berhasil dibuat.'); closeModal(); },
        });
    }
}

async function deleteTemplate(t: RabTemplate) {
    const ok = await confirm({
        title:        'Hapus Template?',
        message:      `Template "${t.name}" akan dihapus permanen. Dokumen RAB yang dibuat dari template ini tidak akan terpengaruh.`,
        confirmLabel: 'Hapus',
        variant:      'destructive',
    });
    if (!ok) return;

    router.delete(`/rab/templates/${t.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Template dihapus.'),
        onError:   () => toast.error('Gagal menghapus template.'),
    });
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Template RAB" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Template RAB</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                    Buat template susunan pekerjaan untuk mempercepat pembuatan RAB baru.
                </p>
            </div>
            <Button @click="openCreate" class="gap-2">
                <Plus class="h-4 w-4" /> Buat Template
            </Button>
        </div>

        <!-- Empty state -->
        <div v-if="templates.length === 0"
             class="flex flex-col items-center justify-center py-20 text-center border border-dashed border-slate-200 dark:border-white/10 rounded-2xl">
            <LayoutList class="h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada template</p>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 mb-4">
                Template memudahkan Anda membuat RAB dengan susunan pekerjaan yang sudah ditentukan.
            </p>
            <Button @click="openCreate" variant="outline" class="gap-2">
                <Plus class="h-4 w-4" /> Buat Template Pertama
            </Button>
        </div>

        <!-- Template grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="t in templates" :key="t.id"
                 class="group relative flex flex-col gap-3 p-4 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] hover:border-sky-300 dark:hover:border-sky-500/40 hover:shadow-sm transition-all">

                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ t.name }}</p>
                        <p v-if="t.description" class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 line-clamp-2">
                            {{ t.description }}
                        </p>
                    </div>
                    <Badge variant="secondary" class="shrink-0 text-xs">{{ t.items_count }} item</Badge>
                </div>

                <div class="flex items-center justify-between mt-auto pt-2 border-t border-slate-100 dark:border-white/5">
                    <span class="text-xs text-slate-400">{{ formatDate(t.created_at) }}</span>
                    <div class="flex items-center gap-1">
                        <Button size="icon" variant="ghost" class="h-7 w-7" @click="openEdit(t)" title="Edit">
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                        <Button size="icon" variant="ghost"
                                class="h-7 w-7 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                                @click="deleteTemplate(t)" title="Hapus">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <Dialog :open="isOpen" @update:open="v => !v && closeModal()">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>{{ editingId ? 'Edit Template' : 'Buat Template RAB' }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 pt-1">
                <div class="space-y-1.5">
                    <Label for="tpl-name">Nama Template <span class="text-rose-500">*</span></Label>
                    <Input id="tpl-name" v-model="form.name" placeholder="Rumah Tinggal 2 Lantai" required />
                    <p v-if="form.errors.name" class="text-xs text-rose-500">{{ form.errors.name }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label for="tpl-desc">Deskripsi <span class="text-slate-400 text-xs">(opsional)</span></Label>
                    <Input id="tpl-desc" v-model="form.description" placeholder="Digunakan untuk proyek perumahan standar…" />
                </div>

                <p class="text-xs text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-white/[0.03] rounded-lg p-3">
                    Setelah template dibuat, tambahkan item pekerjaan dari halaman detail template.
                    Template akan muncul sebagai pilihan saat membuat dokumen RAB baru.
                </p>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeModal">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                        <Save class="h-4 w-4" />
                        {{ editingId ? 'Simpan' : 'Buat Template' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
