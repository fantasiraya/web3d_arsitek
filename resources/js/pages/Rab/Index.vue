<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Plus, FileText, Trash2, X, Save, Eye, Lock, Unlock,
    ArrowRight, AlertCircle, ChevronRight, Cpu,
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

interface Project {
    id: string;
    title: string;
    slug: string;
}

interface RabTemplate {
    id: string;
    name: string;
}

interface RabDocument {
    id: string;
    title: string;
    status: 'draft' | 'final';
    source: 'manual' | 'csv' | 'glb';
    is_visible_to_clients: boolean;
    total: number;
    items_count: number;
    created_at: string;
}

const props = defineProps<{
    project:       Project;
    documents:     RabDocument[];
    can_edit:      boolean;
    is_owner:      boolean;
    has_rab_plan:  boolean;
    // templates untuk dropdown saat buat dokumen baru (hanya untuk owner)
    templates?:    RabTemplate[];
}>();

const { confirm } = useConfirm();

// ── buat dokumen baru ────────────────────────
const isCreateOpen = ref(false);
const createForm = useForm({
    title:            '',
    source:           'manual' as 'manual',
    rab_template_id:  '',
    overhead_percent: 0,
    ppn_percent:      11,
});

function openCreate() { isCreateOpen.value = true; }
function closeCreate() { isCreateOpen.value = false; createForm.reset(); }

function submitCreate() {
    createForm.post(`/projects/${props.project.id}/rab`, {
        onSuccess: () => { toast.success('Dokumen RAB dibuat.'); closeCreate(); },
        onError:   () => toast.error('Gagal membuat dokumen RAB.'),
    });
}

// ── hapus ────────────────────────────────────
async function deleteDoc(doc: RabDocument) {
    const ok = await confirm({
        title:        'Hapus Dokumen RAB?',
        message:      `"${doc.title}" dan seluruh itemnya akan dihapus permanen.`,
        confirmLabel: 'Hapus',
        variant:      'destructive',
    });
    if (!ok) return;

    router.delete(`/projects/${props.project.id}/rab/${doc.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Dokumen RAB dihapus.'),
        onError:   () => toast.error('Gagal menghapus.'),
    });
}

// ── helpers ──────────────────────────────────
function formatCurrency(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

function formatDate(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

const statusColors = {
    draft: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
    final: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
};

const sourceLabels: Record<string, string> = {
    manual: 'Manual',
    csv:    'Import CSV',
    glb:    'Estimasi 3D',
};
</script>

<template>
    <Head :title="`RAB — ${project.title}`" />
    <Toaster />
    <ConfirmDialog />

    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400">
            <Link href="/dashboard" class="hover:text-slate-600 dark:hover:text-slate-300 transition-colors">Dashboard</Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <Link href="/projects" class="hover:text-slate-600 dark:hover:text-slate-300 transition-colors">Proyek</Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-600 dark:text-slate-300 truncate max-w-[180px]">{{ project.title }}</span>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-900 dark:text-white font-medium">RAB</span>
        </nav>

        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Rencana Anggaran Biaya</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ project.title }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Button v-if="can_edit" @click="openCreate" class="gap-2">
                    <Plus class="h-4 w-4" /> Buat RAB Baru
                </Button>
            </div>
        </div>

        <!-- Upgrade notice jika plan tidak support RAB -->
        <div v-if="is_owner && !has_rab_plan"
             class="flex items-start gap-3 p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
            <AlertCircle class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
            <div class="flex-1">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Fitur RAB memerlukan paket Pro atau Enterprise</p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                    Upgrade paket Anda untuk dapat membuat dan mengelola Rencana Anggaran Biaya.
                </p>
            </div>
            <Link href="/plans">
                <Button size="sm" variant="outline" class="border-amber-300 text-amber-700 hover:bg-amber-100 dark:border-amber-500/40 dark:text-amber-400 shrink-0">
                    Upgrade
                </Button>
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="documents.length === 0"
             class="flex flex-col items-center justify-center py-20 text-center border border-dashed border-slate-200 dark:border-white/10 rounded-2xl">
            <FileText class="h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" />
            <p class="font-medium text-slate-600 dark:text-slate-300">Belum ada dokumen RAB</p>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-1 mb-4">
                {{ can_edit ? 'Mulai buat Rencana Anggaran Biaya untuk project ini.' : 'Belum ada RAB yang dibagikan ke Anda.' }}
            </p>
            <Button v-if="can_edit" @click="openCreate" variant="outline" class="gap-2">
                <Plus class="h-4 w-4" /> Buat RAB Pertama
            </Button>
        </div>

        <!-- Document list -->
        <div v-else class="space-y-3">
            <div v-for="doc in documents" :key="doc.id"
                 class="group flex items-center gap-4 p-4 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] hover:border-sky-300 dark:hover:border-sky-500/40 hover:shadow-sm transition-all">

                <div class="shrink-0">
                    <div class="w-10 h-10 rounded-lg bg-sky-50 dark:bg-sky-500/10 flex items-center justify-center">
                        <FileText class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ doc.title }}</p>
                        <span :class="['px-2 py-0.5 rounded-full text-xs font-medium border', statusColors[doc.status]]">
                            {{ doc.status === 'draft' ? 'Draft' : 'Final' }}
                        </span>
                        <span v-if="doc.source !== 'manual'"
                              class="px-2 py-0.5 rounded-full text-xs border bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-500/10 dark:text-violet-400 dark:border-violet-500/20">
                            {{ sourceLabels[doc.source] }}
                        </span>
                        <span v-if="doc.is_visible_to_clients"
                              class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                            <Eye class="h-3 w-3" /> Terlihat Klien
                        </span>
                    </div>
                    <div class="flex items-center gap-3 mt-1 text-xs text-slate-400">
                        <span>{{ doc.items_count }} item</span>
                        <span>•</span>
                        <span>{{ formatDate(doc.created_at) }}</span>
                        <span>•</span>
                        <span class="font-semibold text-slate-600 dark:text-slate-300">{{ formatCurrency(doc.total) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Link :href="`/projects/${project.id}/rab/${doc.id}`">
                        <Button size="sm" variant="outline" class="gap-1.5 h-8">
                            <span class="hidden sm:inline">Buka</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Button>
                    </Link>
                    <!-- Tombol Estimasi 3D — hanya untuk draft + owner -->
                    <Link v-if="can_edit && doc.status === 'draft'"
                          :href="`/projects/${project.id}/rab/${doc.id}/glb-estimator`"
                          title="Estimasi kuantitas dari model 3D">
                        <Button size="icon" variant="ghost"
                                class="h-8 w-8 text-violet-500 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-violet-500/10">
                            <Cpu class="h-3.5 w-3.5" />
                        </Button>
                    </Link>
                    <Button v-if="can_edit" size="icon" variant="ghost"
                            class="h-8 w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10"
                            @click="deleteDoc(doc)" title="Hapus">
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create RAB Modal -->
    <Dialog :open="isCreateOpen" @update:open="v => !v && closeCreate()">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Buat Dokumen RAB Baru</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitCreate" class="space-y-4 pt-1">
                <div class="space-y-1.5">
                    <Label for="rab-title">Judul RAB <span class="text-rose-500">*</span></Label>
                    <Input id="rab-title" v-model="createForm.title" placeholder="RAB Rumah Tinggal 2 Lantai" required />
                    <p v-if="createForm.errors.title" class="text-xs text-rose-500">{{ createForm.errors.title }}</p>
                </div>

                <div v-if="templates && templates.length > 0" class="space-y-1.5">
                    <Label for="rab-template">Dari Template <span class="text-slate-400 text-xs">(opsional)</span></Label>
                    <select id="rab-template" v-model="createForm.rab_template_id"
                            class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">— Tanpa template —</option>
                        <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label for="rab-overhead">Overhead (%)</Label>
                        <Input id="rab-overhead" v-model="createForm.overhead_percent" type="number" min="0" max="100" step="0.5" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="rab-ppn">PPN (%)</Label>
                        <Input id="rab-ppn" v-model="createForm.ppn_percent" type="number" min="0" max="100" step="0.5" />
                    </div>
                </div>

                <DialogFooter class="pt-2">
                    <Button type="button" variant="outline" @click="closeCreate">
                        <X class="h-4 w-4 mr-1.5" /> Batal
                    </Button>
                    <Button type="submit" :disabled="createForm.processing" class="gap-2">
                        <Save class="h-4 w-4" /> Buat RAB
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
