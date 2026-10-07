<script setup lang="ts">
/**
 * Rab/Import.vue — Wizard 3-step Import Quantity Take-off
 *
 * Step 1: Upload file + deteksi header
 * Step 2: Pilih kolom + dry-run mapping preview
 * Step 3: Review hasil (mapped / unmapped) + isi harga manual + konfirmasi import
 */
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Upload, ChevronRight, ChevronLeft, Check, X, AlertTriangle,
    FileSpreadsheet, ArrowRight, CheckCircle2, HelpCircle, Loader2,
} from '@lucide/vue';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';

defineOptions({ layout: UnifiedLayout });

interface Project   { id: string; title: string; slug: string; }
interface RabDoc    { id: string; title: string; status: 'draft' | 'final'; }
interface MappedRow {
    row_index: number;
    description: string;
    quantity: number;
    unit: string;
    section: string;
    source_ref: string;
    is_mapped: boolean;
    unit_price: number;
    price_item: { id: string; name: string; unit: string; unit_price: number } | null;
}

const props = defineProps<{ project: Project; document: RabDoc }>();

// ── Step state ────────────────────────────────────────────────────────────────
type Step = 1 | 2 | 3;
const currentStep = ref<Step>(1);
const stepLabels  = ['Upload File', 'Pilih Kolom', 'Review & Konfirmasi'];

// ── CSRF helper ───────────────────────────────────────────────────────────────
function csrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

// ── Step 1: Upload ────────────────────────────────────────────────────────────
const fileInput    = ref<HTMLInputElement | null>(null);
const uploadedFile = ref<File | null>(null);
const headerRow    = ref(1);
const isDragging   = ref(false);
const isUploading  = ref(false);

// Results from /preview
const headers             = ref<string[]>([]);
const previewRows         = ref<Record<string, string>[]>([]);
const totalRows           = ref(0);
const suggestedNameCol    = ref('');
const suggestedQtyCol     = ref('');
const suggestedUnitCol    = ref('');
const suggestedSectionCol = ref('');

function onFileDrop(e: DragEvent) {
    isDragging.value = false;
    const file = e.dataTransfer?.files[0];
    if (file) setFile(file);
}

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) setFile(file);
}

function setFile(file: File) {
    const ext = file.name.split('.').pop()?.toLowerCase();
    if (!['csv', 'xlsx', 'xls'].includes(ext ?? '')) {
        toast.error('Format tidak didukung. Gunakan CSV, XLSX, atau XLS.');
        return;
    }
    uploadedFile.value = file;
}

async function runPreview() {
    if (!uploadedFile.value) { toast.error('Pilih file terlebih dahulu.'); return; }

    isUploading.value = true;
    const fd = new FormData();
    fd.append('file', uploadedFile.value);
    fd.append('header_row', String(headerRow.value));

    try {
        const res = await fetch(
            `/projects/${props.project.id}/rab/${props.document.id}/import/preview`,
            { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() }, body: fd },
        );
        if (!res.ok) {
            const b = await res.json().catch(() => ({}));
            throw new Error(b.message ?? `HTTP ${res.status}`);
        }
        const data = await res.json();

        headers.value             = data.headers ?? [];
        previewRows.value         = data.preview_rows ?? [];
        totalRows.value           = data.total_rows ?? 0;
        suggestedNameCol.value    = data.suggested_name_col ?? '';
        suggestedQtyCol.value     = data.suggested_qty_col ?? '';
        suggestedUnitCol.value    = data.suggested_unit_col ?? '';
        suggestedSectionCol.value = data.suggested_section_col ?? '';

        // Pre-fill kolom dengan saran otomatis
        nameCol.value    = suggestedNameCol.value;
        qtyCol.value     = suggestedQtyCol.value;
        unitCol.value    = suggestedUnitCol.value;
        sectionCol.value = suggestedSectionCol.value;

        currentStep.value = 2;
        toast.success(`File dibaca: ${totalRows.value} baris ditemukan.`);
    } catch (err: any) {
        toast.error(err.message ?? 'Gagal membaca file.');
    } finally {
        isUploading.value = false;
    }
}

// ── Step 2: Pilih kolom + dry-run ─────────────────────────────────────────────
const nameCol      = ref('');
const qtyCol       = ref('');
const unitCol      = ref('');
const materialCol  = ref('');
const sectionCol   = ref('');
const isDryRunning = ref(false);

const mappedRows    = ref<MappedRow[]>([]);
const unmappedRows  = ref<MappedRow[]>([]);
const mappedCount   = ref(0);
const unmappedCount = ref(0);

async function runDryRun() {
    if (!nameCol.value || !qtyCol.value) { toast.error('Kolom nama dan kuantitas wajib dipilih.'); return; }
    if (!uploadedFile.value) return;

    isDryRunning.value = true;
    const fd = new FormData();
    fd.append('file', uploadedFile.value);
    fd.append('name_col', nameCol.value);
    fd.append('qty_col', qtyCol.value);
    if (unitCol.value)     fd.append('unit_col', unitCol.value);
    if (materialCol.value) fd.append('material_col', materialCol.value);
    if (sectionCol.value)  fd.append('section_col', sectionCol.value);
    fd.append('header_row', String(headerRow.value));

    try {
        const res = await fetch(
            `/projects/${props.project.id}/rab/${props.document.id}/import/dry-run`,
            { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() }, body: fd },
        );
        if (!res.ok) {
            const b = await res.json().catch(() => ({}));
            throw new Error(b.message ?? `HTTP ${res.status}`);
        }
        const data = await res.json();

        mappedRows.value   = data.mapped ?? [];
        unmappedRows.value = data.unmapped ?? [];
        mappedCount.value  = data.mapped_count ?? 0;
        unmappedCount.value = data.unmapped_count ?? 0;

        // Inisialisasi manual price placeholder untuk unmapped
        for (const row of unmappedRows.value) {
            if (!manualPrices.value[row.row_index]) {
                manualPrices.value[row.row_index] = { unit_price: '0', price_item_id: '' };
            }
        }

        currentStep.value = 3;
        toast.success(`Mapping selesai: ${mappedCount.value} terpetakan, ${unmappedCount.value} belum.`);
    } catch (err: any) {
        toast.error(err.message ?? 'Gagal menjalankan mapping.');
    } finally {
        isDryRunning.value = false;
    }
}

// ── Step 3: Review + submit ───────────────────────────────────────────────────
const manualPrices = ref<Record<number, { unit_price: string; price_item_id: string }>>({});
const isSubmitting = ref(false);

async function submitImport() {
    if (!uploadedFile.value) return;
    isSubmitting.value = true;

    const fd = new FormData();
    fd.append('file', uploadedFile.value);
    fd.append('name_col', nameCol.value);
    fd.append('qty_col', qtyCol.value);
    if (unitCol.value)     fd.append('unit_col', unitCol.value);
    if (materialCol.value) fd.append('material_col', materialCol.value);
    if (sectionCol.value)  fd.append('section_col', sectionCol.value);
    fd.append('header_row', String(headerRow.value));

    let idx = 0;
    for (const [rowIndex, prices] of Object.entries(manualPrices.value)) {
        if (parseFloat(prices.unit_price) > 0 || prices.price_item_id) {
            fd.append(`manual_prices[${idx}][row_index]`,  rowIndex);
            fd.append(`manual_prices[${idx}][unit_price]`, prices.unit_price);
            if (prices.price_item_id) fd.append(`manual_prices[${idx}][price_item_id]`, prices.price_item_id);
            idx++;
        }
    }

    try {
        const inertiaVersion = (document.querySelector('meta[name="inertia-version"]') as HTMLMetaElement)?.content ?? '';
        const res = await fetch(
            `/projects/${props.project.id}/rab/${props.document.id}/import`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':    csrf(),
                    'X-Inertia':       '1',
                    'X-Inertia-Version': inertiaVersion,
                    'Accept':          'application/json',
                },
                body: fd,
            },
        );

        if (res.ok || res.redirected || res.status === 302) {
            toast.success('Import berhasil!');
            setTimeout(() => router.visit(`/projects/${props.project.id}/rab/${props.document.id}`), 500);
        } else {
            const body = await res.json().catch(() => ({}));
            toast.error(body.message ?? 'Gagal melakukan import.');
        }
    } catch {
        toast.error('Terjadi kesalahan jaringan.');
    } finally {
        isSubmitting.value = false;
    }
}

function fmt(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}
</script>

<template>
    <Head :title="`Import Take-off — ${document.title}`" />
    <Toaster />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400 flex-wrap">
            <Link href="/dashboard" class="hover:text-slate-600 dark:hover:text-slate-300">Dashboard</Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <Link :href="`/projects/${project.id}/rab`" class="hover:text-slate-600 dark:hover:text-slate-300">
                RAB — {{ project.title }}
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <Link :href="`/projects/${project.id}/rab/${document.id}`"
                  class="hover:text-slate-600 dark:hover:text-slate-300 truncate max-w-[160px]">
                {{ document.title }}
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-900 dark:text-white font-medium">Import Take-off</span>
        </nav>

        <!-- Title -->
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Import Quantity Take-off</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Upload file CSV atau Excel dari Revit / SketchUp, pilih kolom, dan petakan ke harga satuan.
            </p>
        </div>

        <!-- Step indicator -->
        <div class="flex items-center">
            <template v-for="(label, i) in stepLabels" :key="i">
                <div class="flex items-center gap-2">
                    <div :class="[
                        'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors',
                        currentStep === i + 1
                            ? 'bg-sky-600 text-white'
                            : currentStep > i + 1
                                ? 'bg-emerald-500 text-white'
                                : 'bg-slate-100 dark:bg-white/10 text-slate-400',
                    ]">
                        <Check v-if="currentStep > i + 1" class="h-3.5 w-3.5" />
                        <span v-else>{{ i + 1 }}</span>
                    </div>
                    <span :class="[
                        'text-sm hidden sm:inline',
                        currentStep === i + 1 ? 'font-semibold text-slate-800 dark:text-white' : 'text-slate-400',
                    ]">{{ label }}</span>
                </div>
                <div v-if="i < stepLabels.length - 1"
                     :class="['flex-1 h-0.5 mx-3', currentStep > i + 1 ? 'bg-emerald-400' : 'bg-slate-200 dark:bg-white/10']" />
            </template>
        </div>

        <!-- ═══ STEP 1: Upload ═══════════════════════════════════════════════ -->
        <div v-if="currentStep === 1" class="space-y-5">
            <div
                :class="[
                    'relative border-2 border-dashed rounded-2xl p-10 flex flex-col items-center justify-center gap-4 text-center transition-colors cursor-pointer',
                    isDragging
                        ? 'border-sky-500 bg-sky-50 dark:bg-sky-500/10'
                        : 'border-slate-300 dark:border-white/15 hover:border-sky-400 dark:hover:border-sky-500/50 hover:bg-slate-50 dark:hover:bg-white/[0.02]',
                ]"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="onFileDrop"
                @click="fileInput?.click()"
            >
                <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="onFileChange" />

                <div class="w-14 h-14 rounded-xl bg-sky-50 dark:bg-sky-500/10 flex items-center justify-center">
                    <FileSpreadsheet class="h-7 w-7 text-sky-600 dark:text-sky-400" />
                </div>

                <div>
                    <p class="font-semibold text-slate-700 dark:text-slate-200">
                        {{ uploadedFile ? uploadedFile.name : 'Drag & drop atau klik untuk pilih file' }}
                    </p>
                    <p class="text-sm text-slate-400 mt-1">
                        {{ uploadedFile
                            ? `${(uploadedFile.size / 1024).toFixed(0)} KB • Siap diproses`
                            : 'Mendukung CSV, XLSX, XLS — maks 10 MB' }}
                    </p>
                </div>

                <Button v-if="!uploadedFile" size="sm" variant="outline" @click.stop="fileInput?.click()">
                    Pilih File
                </Button>
                <Button v-else size="sm" variant="outline" class="text-slate-500"
                        @click.stop="uploadedFile = null; if(fileInput) fileInput.value = ''">
                    <X class="h-3.5 w-3.5 mr-1.5" /> Ganti File
                </Button>
            </div>

            <!-- Header row -->
            <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/10">
                <HelpCircle class="h-4 w-4 text-slate-400 shrink-0" />
                <div class="flex-1 text-sm text-slate-500 dark:text-slate-400">Baris mana yang berisi nama kolom (header)?</div>
                <div class="flex items-center gap-2">
                    <Label class="text-sm shrink-0">Baris header:</Label>
                    <Input v-model="headerRow" type="number" min="1" max="10" class="w-16 h-8 text-center" />
                </div>
            </div>

            <!-- Format hint -->
            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-sm text-amber-700 dark:text-amber-400 space-y-1">
                <p class="font-semibold">Format file yang diharapkan:</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    <li>Kolom <strong>Nama/Uraian</strong> — deskripsi pekerjaan (misal: "Pasang bata merah")</li>
                    <li>Kolom <strong>Volume/Qty</strong> — kuantitas numerik</li>
                    <li>Kolom <strong>Satuan</strong> — opsional (m², m³, unit)</li>
                    <li>Kolom <strong>Bagian</strong> — opsional (Struktur, Finishing, dll.)</li>
                </ul>
            </div>

            <div class="flex justify-end">
                <Button @click="runPreview" :disabled="!uploadedFile || isUploading" class="gap-2 min-w-36">
                    <Loader2 v-if="isUploading" class="h-4 w-4 animate-spin" />
                    <Upload v-else class="h-4 w-4" />
                    {{ isUploading ? 'Membaca file…' : 'Baca File' }}
                </Button>
            </div>
        </div>

        <!-- ═══ STEP 2: Pilih Kolom ══════════════════════════════════════════ -->
        <div v-if="currentStep === 2" class="space-y-5">
            <div class="flex items-center gap-3 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-sm">
                <CheckCircle2 class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span class="text-emerald-700 dark:text-emerald-300">
                    File berhasil dibaca — <strong>{{ totalRows }} baris</strong>, <strong>{{ headers.length }} kolom</strong>.
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <Label>Kolom Nama/Uraian Pekerjaan <span class="text-rose-500">*</span></Label>
                    <select v-model="nameCol" class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">— Pilih kolom —</option>
                        <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
                    </select>
                    <p v-if="suggestedNameCol" class="text-xs text-sky-600 dark:text-sky-400">Saran: "{{ suggestedNameCol }}"</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Kolom Volume/Kuantitas <span class="text-rose-500">*</span></Label>
                    <select v-model="qtyCol" class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">— Pilih kolom —</option>
                        <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
                    </select>
                    <p v-if="suggestedQtyCol" class="text-xs text-sky-600 dark:text-sky-400">Saran: "{{ suggestedQtyCol }}"</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Kolom Satuan <span class="text-slate-400 text-xs">(opsional)</span></Label>
                    <select v-model="unitCol" class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">— Tidak ada —</option>
                        <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <Label>Kolom Bagian/Section <span class="text-slate-400 text-xs">(opsional)</span></Label>
                    <select v-model="sectionCol" class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">— Tidak ada —</option>
                        <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
                    </select>
                </div>
                <div class="space-y-1.5 sm:col-span-2">
                    <Label>Kolom Material <span class="text-slate-400 text-xs">(opsional — untuk pencocokan material mapping)</span></Label>
                    <select v-model="materialCol" class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                        <option value="">— Tidak ada —</option>
                        <option v-for="h in headers" :key="h" :value="h">{{ h }}</option>
                    </select>
                </div>
            </div>

            <!-- Preview table -->
            <div v-if="previewRows.length > 0">
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300 mb-2">Preview 10 baris pertama:</p>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/10">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-white/[0.03] border-b border-slate-200 dark:border-white/10">
                                <th v-for="h in headers" :key="h"
                                    :class="['px-3 py-2 text-left font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap',
                                             (h === nameCol || h === qtyCol) ? 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300' : '']">
                                    {{ h }}
                                    <span v-if="h === nameCol" class="ml-1 text-[10px] text-sky-500">▲ Nama</span>
                                    <span v-if="h === qtyCol"  class="ml-1 text-[10px] text-sky-500">▲ Qty</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in previewRows" :key="i"
                                class="border-b border-slate-100 dark:border-white/5 last:border-0 hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                <td v-for="h in headers" :key="h"
                                    class="px-3 py-2 text-slate-700 dark:text-slate-300 max-w-[200px] truncate">
                                    {{ row[h] ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <Button variant="outline" @click="currentStep = 1" class="gap-2">
                    <ChevronLeft class="h-4 w-4" /> Kembali
                </Button>
                <Button @click="runDryRun" :disabled="!nameCol || !qtyCol || isDryRunning" class="gap-2 min-w-40">
                    <Loader2 v-if="isDryRunning" class="h-4 w-4 animate-spin" />
                    <ArrowRight v-else class="h-4 w-4" />
                    {{ isDryRunning ? 'Memproses…' : 'Proses Mapping' }}
                </Button>
            </div>
        </div>

        <!-- ═══ STEP 3: Review + Konfirmasi ══════════════════════════════════ -->
        <div v-if="currentStep === 3" class="space-y-6">
            <!-- Summary -->
            <div class="grid grid-cols-3 gap-3">
                <div class="p-3 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 text-center">
                    <p class="text-2xl font-bold text-slate-800 dark:text-white">{{ mappedCount + unmappedCount }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Total Item</p>
                </div>
                <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-center">
                    <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ mappedCount }}</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">Terpetakan</p>
                </div>
                <div :class="['p-3 rounded-xl border text-center',
                              unmappedCount > 0
                                  ? 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20'
                                  : 'bg-slate-50 dark:bg-white/[0.02] border-slate-200 dark:border-white/10']">
                    <p :class="['text-2xl font-bold', unmappedCount > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-slate-400']">
                        {{ unmappedCount }}
                    </p>
                    <p :class="['text-xs mt-0.5', unmappedCount > 0 ? 'text-amber-600 dark:text-amber-500' : 'text-slate-400']">
                        Belum Dipetakan
                    </p>
                </div>
            </div>

            <!-- Mapped items list -->
            <div v-if="mappedRows.length > 0">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 mb-2 flex items-center gap-2">
                    <CheckCircle2 class="h-4 w-4 text-emerald-500" /> Item Terpetakan ({{ mappedCount }})
                </p>
                <div class="border border-slate-200 dark:border-white/10 rounded-xl overflow-hidden">
                    <div class="hidden sm:grid grid-cols-[2fr_1fr_1fr_1fr] gap-2 px-4 py-2 text-xs font-medium text-slate-400 uppercase tracking-wide bg-slate-50 dark:bg-white/[0.03]">
                        <span>Uraian</span><span>Satuan</span><span class="text-right">Volume</span><span class="text-right">Harga Satuan</span>
                    </div>
                    <div v-for="row in mappedRows" :key="row.row_index"
                         class="grid grid-cols-1 sm:grid-cols-[2fr_1fr_1fr_1fr] gap-2 items-center px-4 py-2.5 border-b border-slate-100 dark:border-white/5 last:border-0 hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                        <div>
                            <p class="text-sm text-slate-800 dark:text-slate-100">{{ row.description }}</p>
                            <p v-if="row.price_item" class="text-xs text-emerald-600 dark:text-emerald-400">→ {{ row.price_item.name }}</p>
                        </div>
                        <span class="text-xs font-mono text-slate-500">{{ row.unit }}</span>
                        <span class="text-sm text-right text-slate-700 dark:text-slate-300">{{ row.quantity }}</span>
                        <span class="text-sm text-right font-semibold text-slate-700 dark:text-slate-300">{{ fmt(row.unit_price) }}</span>
                    </div>
                </div>
            </div>

            <!-- Unmapped items + manual price -->
            <div v-if="unmappedRows.length > 0">
                <p class="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-2 flex items-center gap-2">
                    <AlertTriangle class="h-4 w-4" /> Item Belum Dipetakan ({{ unmappedCount }}) — Isi harga manual atau biarkan kosong
                </p>
                <div class="border border-amber-200 dark:border-amber-500/20 rounded-xl overflow-hidden divide-y divide-amber-100 dark:divide-amber-500/10">
                    <div v-for="row in unmappedRows" :key="row.row_index"
                         class="flex items-center gap-3 px-4 py-3 bg-amber-50/50 dark:bg-amber-500/5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">{{ row.description }}</p>
                            <p class="text-xs text-slate-400">{{ row.source_ref }} • {{ row.unit }} × {{ row.quantity }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <Label class="text-xs text-slate-500 shrink-0">Harga/satuan:</Label>
                            <Input
                                v-model="manualPrices[row.row_index].unit_price"
                                type="number" min="0" step="1000" placeholder="0"
                                class="w-32 h-8 text-right text-sm"
                            />
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    Item dengan harga 0 tetap diimport. Petakan ke
                    <Link href="/rab/price-items" class="underline">Harga Satuan</Link> lalu import ulang.
                </p>
            </div>

            <div class="flex items-center justify-between pt-2">
                <Button variant="outline" @click="currentStep = 2" class="gap-2">
                    <ChevronLeft class="h-4 w-4" /> Kembali
                </Button>
                <Button @click="submitImport" :disabled="isSubmitting" class="gap-2 min-w-44">
                    <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                    <Check v-else class="h-4 w-4" />
                    {{ isSubmitting ? 'Mengimport…' : `Import ${mappedCount + unmappedCount} Item` }}
                </Button>
            </div>
        </div>
    </div>
</template>
