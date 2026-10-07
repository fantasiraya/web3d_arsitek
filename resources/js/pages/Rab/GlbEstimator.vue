<script setup lang="ts">
/**
 * Rab/GlbEstimator.vue — Estimasi Kuantitas RAB dari Model 3D (.glb)
 *
 * Alur:
 *  1. Auto-load file .glb dari project version terbaru
 *  2. Kalkulasi luas/volume/count per mesh via useGlbQuantities (Three.js)
 *  3. User pilih: basis kuantitas + mapping ke harga satuan
 *  4. Submit ke POST /projects/{id}/rab/{rab}/from-glb
 *
 * Semua item yang dihasilkan otomatis is_estimate = true (bukan RAB final kontrak).
 */
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle, Check, ChevronRight, Loader2, Cpu,
    TriangleAlert, Info, CheckCircle2, X, Search, ArrowRight,
} from '@lucide/vue';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import { useGlbQuantities, type MeshQuantity } from '@/composables/useGlbQuantities';

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

interface MappingRule {
    id: string;
    match_type: 'exact' | 'name_pattern' | 'material';
    pattern: string;
    rab_price_item_id: string;
    quantity_basis: 'area' | 'volume' | 'count' | 'length';
}

interface RowItem {
    name: string;
    area: number;
    volume: number;
    count: number;
    is_open_mesh: boolean;
    warnings: string[];
    /** Basis yang dipilih user */
    quantity_basis: 'area' | 'volume' | 'count' | 'length';
    /** Nilai kuantitas sesuai basis yang dipilih */
    quantity: number;
    unit: string;
    section: string;
    /** ID harga satuan yang dipilih (null = belum dipetakan) */
    rab_price_item_id: string | null;
    unit_price: number;
    /** Apakah row ini disertakan dalam submit */
    included: boolean;
    /** True jika mesh punya custom properties dari Blender */
    has_extras: boolean;
}

const props = defineProps<{
    project:       { id: string; title: string; slug: string };
    document:      { id: string; title: string; status: string; source: string };
    latestVersion: { id: string; file_path: string; version_number: number } | null;
    glbUrl:        string | null;
    priceItems:    PriceItem[];
    mappings:      MappingRule[];
}>();

// ── Composable ────────────────────────────────────────────────────────────────
const { isLoading, progress, error, result, calculate } = useGlbQuantities();

// ── Row state — diinisialisasi setelah kalkulasi selesai ──────────────────────
const rows = ref<RowItem[]>([]);

function initRows(meshes: MeshQuantity[]) {
    rows.value = meshes.map(m => {
        // ── 1. Prioritaskan custom properties Blender (extras) ────────────
        let suggestedBasis: 'area' | 'volume' | 'count' | 'length' = 'area';
        let suggestedUnit  = 'm²';
        let unitPrice      = 0;
        let category       = '';
        let section        = 'Estimasi 3D';

        if (m.has_extras) {
            // Basis dari custom property
            const bp = m.extras.quantity_basis;
            if (bp === 'volume')  { suggestedBasis = 'volume'; suggestedUnit = 'm³'; }
            else if (bp === 'count')  { suggestedBasis = 'count';  suggestedUnit = 'unit'; }
            else if (bp === 'length') { suggestedBasis = 'length'; suggestedUnit = 'm\''; }
            else                      { suggestedBasis = 'area';   suggestedUnit = 'm²'; }

            // Override dengan unit dari Blender jika ada
            if (m.extras.unit) {
                suggestedUnit = m.extras.unit.replace('m2', 'm²').replace('m3', 'm³');
            }

            unitPrice = m.extras.unit_price ?? 0;
            category  = m.extras.category ?? '';
            section   = m.extras.section  ?? 'Estimasi 3D';
        } else {
            // ── 2. Fallback: heuristic dari nama mesh ─────────────────────
            const nameLower = m.name.toLowerCase();
            if (nameLower.includes('kolom') || nameLower.includes('balok') || nameLower.includes('pondasi') || nameLower.includes('cor')) {
                suggestedBasis = 'volume'; suggestedUnit = 'm³';
            } else if (nameLower.includes('pintu') || nameLower.includes('jendela') || nameLower.includes('kusen') || nameLower.includes('tangga')) {
                suggestedBasis = 'count'; suggestedUnit = 'unit';
            } else if (nameLower.includes('pipa') || nameLower.includes('railing') || nameLower.includes('lisplang')) {
                suggestedBasis = 'length'; suggestedUnit = 'm\'';
            }
        }

        // ── 3. Mapping rule dari database ─────────────────────────────────
        const mapped     = findMapping(m.name);
        const priceItem  = mapped ? props.priceItems.find(p => p.id === mapped.rab_price_item_id) : null;

        if (mapped) {
            suggestedBasis = mapped.quantity_basis as any;
            if (priceItem) { suggestedUnit = priceItem.unit; }
        }

        // Harga: extras > mapping > 0
        const finalUnitPrice = unitPrice > 0 ? unitPrice : (priceItem?.unit_price ?? 0);

        const quantity = getQuantity(m, suggestedBasis);

        return {
            name:              m.name,
            area:              m.area,
            volume:            m.volume,
            count:             m.count,
            is_open_mesh:      m.is_open_mesh,
            warnings:          m.warnings,
            quantity_basis:    suggestedBasis,
            quantity,
            unit:              suggestedUnit,
            section,
            rab_price_item_id: priceItem?.id ?? null,
            unit_price:        finalUnitPrice,
            included:          quantity > 0 && m.name !== 'Objek_Tanpa_Nama',
            has_extras:        m.has_extras,
        };
    });
}

function getQuantity(m: MeshQuantity, basis: 'area' | 'volume' | 'count' | 'length'): number {
    switch (basis) {
        case 'area':   return m.area;
        case 'volume': return m.volume;
        case 'count':  return m.count;
        case 'length': return m.area; // tidak ada data panjang langsung; fallback ke area
        default:       return m.area;
    }
}

function onBasisChange(row: RowItem) {
    const mesh = result.value?.meshes.find(m => m.name === row.name);
    if (!mesh) return;
    row.quantity = getQuantity(mesh, row.quantity_basis);

    const unitMap: Record<string, string> = {
        area: 'm²', volume: 'm³', count: 'unit', length: 'm\'',
    };
    row.unit = unitMap[row.quantity_basis] ?? 'm²';

    // Update harga satuan jika ada mapping yang pakai basis ini
    const mapped = findMapping(row.name);
    if (mapped && mapped.quantity_basis === row.quantity_basis) {
        const p = props.priceItems.find(p => p.id === mapped.rab_price_item_id);
        if (p) { row.rab_price_item_id = p.id; row.unit_price = p.unit_price; row.unit = p.unit; }
    }
}

// ── Mapping helpers ───────────────────────────────────────────────────────────
function findMapping(name: string): MappingRule | null {
    for (const m of props.mappings) {
        if (m.match_type === 'exact' && m.pattern.toLowerCase() === name.toLowerCase()) return m;
        if (m.match_type === 'name_pattern' && matchWildcard(m.pattern, name)) return m;
    }
    return null;
}

function matchWildcard(pattern: string, subject: string): boolean {
    const regex = new RegExp('^' + pattern.replace(/[.+^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*').replace(/\?/g, '.') + '$', 'i');
    return regex.test(subject);
}

function suggestSection(name: string): string {
    const n = name.toUpperCase();
    if (n.startsWith('STRUKTUR') || n.startsWith('PONDASI') || n.startsWith('KOLOM') || n.startsWith('BALOK') || n.startsWith('PLAT')) return 'Pekerjaan Struktur';
    if (n.startsWith('DINDING') || n.startsWith('LANTAI') || n.startsWith('PLAFON') || n.startsWith('ATAP')) return 'Pekerjaan Arsitektur';
    if (n.startsWith('PINTU') || n.startsWith('JENDELA') || n.startsWith('KUSEN')) return 'Pekerjaan Kusen & Pintu';
    if (n.startsWith('FINISHING') || n.startsWith('CAT') || n.startsWith('KERAMIK')) return 'Pekerjaan Finishing';
    return 'Estimasi 3D';
}

// ── Price item selection ──────────────────────────────────────────────────────
const editingRowIndex    = ref<number | null>(null);
const priceItemSearch    = ref('');
const showPriceItemPicker = ref(false);

const filteredPriceItems = computed(() => {
    const q = priceItemSearch.value.toLowerCase();
    if (!q) return props.priceItems;
    return props.priceItems.filter(
        p => p.name.toLowerCase().includes(q) ||
             (p.code ?? '').toLowerCase().includes(q) ||
             (p.category ?? '').toLowerCase().includes(q),
    );
});

function openPriceItemPicker(idx: number) {
    editingRowIndex.value     = idx;
    priceItemSearch.value     = '';
    showPriceItemPicker.value = true;
}

function selectPriceItem(item: PriceItem) {
    if (editingRowIndex.value === null) return;
    const row            = rows.value[editingRowIndex.value];
    row.rab_price_item_id = item.id;
    row.unit_price        = item.unit_price;
    row.unit              = item.unit;
    showPriceItemPicker.value = false;
    editingRowIndex.value     = null;
}

function clearPriceItem(idx: number) {
    rows.value[idx].rab_price_item_id = null;
    rows.value[idx].unit_price        = 0;
}

// ── Computed stats ────────────────────────────────────────────────────────────
const includedRows    = computed(() => rows.value.filter(r => r.included));
const mappedCount     = computed(() => includedRows.value.filter(r => r.rab_price_item_id).length);
const unmappedCount   = computed(() => includedRows.value.filter(r => !r.rab_price_item_id).length);
const hasNoGlb        = computed(() => !props.glbUrl);
const globalWarnings  = computed(() => result.value?.warnings ?? []);

// ── Submit ────────────────────────────────────────────────────────────────────
const isSubmitting = ref(false);

async function submitEstimation() {
    if (includedRows.value.length === 0) {
        toast.error('Pilih minimal satu item untuk disertakan.');
        return;
    }

    isSubmitting.value = true;

    const items = includedRows.value.map(r => ({
        name:              r.name,
        quantity:          r.quantity,
        quantity_basis:    r.quantity_basis,
        unit:              r.unit,
        section:           r.section,
        rab_price_item_id: r.rab_price_item_id ?? null,
        unit_price:        r.unit_price,
    }));

    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    const body = JSON.stringify({
        items,
        project_version_id: props.latestVersion?.id ?? null,
    });

    try {
        const res = await fetch(`/projects/${props.project.id}/rab/${props.document.id}/from-glb`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept':       'application/json',
            },
            body,
        });

        if (res.ok || res.redirected) {
            toast.success('Estimasi berhasil diterapkan ke RAB!');
            setTimeout(() => router.visit(`/projects/${props.project.id}/rab/${props.document.id}`), 600);
        } else {
            const data = await res.json().catch(() => ({}));
            const msg  = data.message ?? (data.errors ? Object.values(data.errors).flat().join(' ') : `HTTP ${res.status}`);
            toast.error(msg);
        }
    } catch {
        toast.error('Terjadi kesalahan jaringan.');
    } finally {
        isSubmitting.value = false;
    }
}

// ── Auto-load on mount ────────────────────────────────────────────────────────
onMounted(async () => {
    if (!props.glbUrl) return;
    try {
        const res = await calculate(props.glbUrl);
        initRows(res.meshes);
    } catch (err: any) {
        toast.error(err.message ?? 'Gagal memuat model.');
    }
});

// ── Format helpers ────────────────────────────────────────────────────────────
function fmt(val: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(val);
}

function fmtNum(val: number, decimals = 2) {
    return val.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

const basisOptions = [
    { value: 'area',   label: 'Luas (m²)',    icon: '⬛' },
    { value: 'volume', label: 'Volume (m³)',   icon: '⬜' },
    { value: 'count',  label: 'Jumlah (unit)', icon: '🔢' },
    { value: 'length', label: 'Panjang (m\')', icon: '📏' },
];

// ── Panduan format penamaan ───────────────────────────────────────────────────
const namingExamples = [
    { name: 'DINDING_BATA_15',   basis: 'Luas (area)',    unit: 'm²',  note: 'Dinding bata tebal 15cm' },
    { name: 'LANTAI_KERAMIK_60', basis: 'Luas (area)',    unit: 'm²',  note: 'Lantai keramik 60×60' },
    { name: 'ATAP_GENTENG',      basis: 'Luas (area)',    unit: 'm²',  note: 'Penutup atap' },
    { name: 'KOLOM_BETON_30X30', basis: 'Volume',         unit: 'm³',  note: 'Kolom beton bertulang' },
    { name: 'PONDASI_BATU_KALI', basis: 'Volume',         unit: 'm³',  note: 'Pondasi batu kali' },
    { name: 'PINTU_P1',          basis: 'Jumlah (count)', unit: 'unit', note: 'Pintu tipe P1' },
    { name: 'JENDELA_J2',        basis: 'Jumlah (count)', unit: 'unit', note: 'Jendela tipe J2' },
    { name: 'RAILING_TANGGA',    basis: 'Panjang',        unit: 'm\'', note: 'Railing besi tangga' },
];

const blenderProps = [
    { key: 'unit_price',     label: 'Harga Satuan',    desc: 'Angka tanpa titik/koma ribuan',  example: '85000' },
    { key: 'unit',           label: 'Satuan',           desc: 'Gunakan: m2, m3, unit, m, jam', example: '"m2"' },
    { key: 'category',       label: 'Kategori',         desc: 'Nama kategori pekerjaan',        example: '"Pek. Arsitektur"' },
    { key: 'section',        label: 'Bagian',           desc: 'Bagian dalam RAB',               example: '"Struktur"' },
    { key: 'quantity_basis', label: 'Basis Kuantitas',  desc: 'area | volume | count | length', example: '"area"' },
];

const supportedUnits = ['m2', 'm3', 'm', 'unit', 'OH', 'jam', 'kg', 'zak', 'btg', 'lbr', 'ls'];
</script>

<template>
    <Head :title="`Estimasi 3D — ${document.title}`" />
    <Toaster />

    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-sm text-slate-400 flex-wrap">
            <Link href="/dashboard" class="hover:text-slate-600 dark:hover:text-slate-300">Dashboard</Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <Link :href="`/projects/${project.id}/rab`" class="hover:text-slate-600 dark:hover:text-slate-300">
                RAB — {{ project.title }}
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <Link :href="`/projects/${project.id}/rab/${document.id}`"
                  class="hover:text-slate-600 dark:hover:text-slate-300 truncate max-w-[140px]">
                {{ document.title }}
            </Link>
            <ChevronRight class="h-3.5 w-3.5 shrink-0" />
            <span class="text-slate-900 dark:text-white font-medium">Estimasi 3D</span>
        </nav>

        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <Cpu class="h-6 w-6 text-violet-500" /> Estimasi Kuantitas dari Model 3D
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Luas, volume, dan jumlah objek dihitung otomatis dari geometri .glb.
                Semua item akan ditandai <strong class="text-violet-600 dark:text-violet-400">"Estimasi"</strong> — bukan RAB final kontrak.
            </p>
        </div>

        <!-- No GLB warning -->
        <div v-if="hasNoGlb"
             class="p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 flex items-start gap-3">
            <AlertTriangle class="h-5 w-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold text-rose-700 dark:text-rose-300">Tidak ada file 3D</p>
                <p class="text-sm text-rose-600 dark:text-rose-400">
                    Project ini belum memiliki file .glb. Upload model 3D terlebih dahulu dari Dashboard.
                </p>
            </div>
        </div>

        <!-- ═══ Panduan Format Penamaan ══════════════════════════════════════ -->
        <details class="group rounded-2xl border border-violet-200 dark:border-violet-500/20 overflow-hidden">
            <summary class="flex items-center justify-between px-5 py-3.5 bg-violet-50 dark:bg-violet-500/10 cursor-pointer list-none">
                <div class="flex items-center gap-2">
                    <Cpu class="h-4 w-4 text-violet-600 dark:text-violet-400 shrink-0" />
                    <span class="font-semibold text-sm text-violet-700 dark:text-violet-300">
                        Panduan Format Penamaan Objek di Blender / SketchUp
                    </span>
                </div>
                <ChevronRight class="h-4 w-4 text-violet-400 transition-transform group-open:rotate-90" />
            </summary>

            <div class="px-5 py-4 space-y-5 bg-white dark:bg-white/[0.02]">
                <!-- Cara 1: Nama objek -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-sky-100 dark:bg-sky-500/20 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-500/30">Cara 1</span>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Nama Object — Konvensi Standar</p>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Beri nama object di Blender/SketchUp menggunakan format:
                        <code class="font-mono bg-slate-100 dark:bg-white/10 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-200">KATEGORI_JENIS_SPEK</code>
                    </p>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-white/10">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-white/[0.03] border-b border-slate-200 dark:border-white/10">
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Nama Object</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Basis Otomatis</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Satuan</th>
                                    <th class="px-3 py-2 text-left font-semibold text-slate-500 uppercase tracking-wide">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                <tr v-for="ex in namingExamples" :key="ex.name" class="hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-3 py-2 font-mono font-medium text-violet-700 dark:text-violet-300">{{ ex.name }}</td>
                                    <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ ex.basis }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-500">{{ ex.unit }}</td>
                                    <td class="px-3 py-2 text-slate-400">{{ ex.note }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cara 2: Custom Properties Blender -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">Cara 2</span>
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Custom Properties Blender — Harga Otomatis ✨</p>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Di Blender, tambahkan custom properties pada object (Properties → Object Properties → Custom Properties):
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="prop in blenderProps" :key="prop.key"
                             class="flex items-start gap-3 p-3 rounded-xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/10">
                            <div class="shrink-0">
                                <code class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded">{{ prop.key }}</code>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ prop.label }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ prop.desc }}</p>
                                <p class="text-xs font-mono text-slate-500 mt-0.5">Contoh: <span class="text-slate-700 dark:text-slate-200">{{ prop.example }}</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Contoh lengkap -->
                    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20">
                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-2">📐 Contoh Object Lengkap di Blender:</p>
                        <div class="font-mono text-xs text-slate-700 dark:text-slate-200 space-y-0.5">
                            <p><span class="text-violet-600 dark:text-violet-400">Object Name:</span> DINDING_BATA_15</p>
                            <p class="pl-4"><span class="text-emerald-600 dark:text-emerald-400">unit_price</span> = <span class="text-amber-600 dark:text-amber-400">85000</span></p>
                            <p class="pl-4"><span class="text-emerald-600 dark:text-emerald-400">unit</span> = <span class="text-amber-600 dark:text-amber-400">"m2"</span></p>
                            <p class="pl-4"><span class="text-emerald-600 dark:text-emerald-400">category</span> = <span class="text-amber-600 dark:text-amber-400">"Pekerjaan Arsitektur"</span></p>
                            <p class="pl-4"><span class="text-emerald-600 dark:text-emerald-400">section</span> = <span class="text-amber-600 dark:text-amber-400">"Struktur"</span></p>
                            <p class="pl-4"><span class="text-emerald-600 dark:text-emerald-400">quantity_basis</span> = <span class="text-amber-600 dark:text-amber-400">"area"</span></p>
                        </div>
                        <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-2">
                            ✅ Harga satuan akan terisi otomatis — tidak perlu mapping manual!
                        </p>
                    </div>
                </div>

                <!-- Nilai satuan yang didukung -->
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">Nilai <code class="font-mono">unit</code> yang didukung:</p>
                    <div class="flex flex-wrap gap-1.5">
                        <code v-for="u in supportedUnits" :key="u"
                              class="text-[11px] font-mono px-2 py-0.5 rounded bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-white/10">
                            {{ u }}
                        </code>
                    </div>
                </div>
            </div>
        </details>

        <!-- Loading state -->
        <div v-if="isLoading"
             class="flex flex-col items-center justify-center py-16 gap-4">
            <div class="relative w-20 h-20">
                <div class="absolute inset-0 rounded-full border-4 border-violet-200 dark:border-violet-500/20"></div>
                <div class="absolute inset-0 rounded-full border-4 border-t-violet-500 dark:border-t-violet-400 animate-spin"></div>
                <Cpu class="absolute inset-0 m-auto h-8 w-8 text-violet-500" />
            </div>
            <div class="text-center">
                <p class="font-semibold text-slate-700 dark:text-slate-200">Memproses model 3D…</p>
                <p class="text-sm text-slate-400 mt-0.5">{{ progress }}% — Menghitung geometri mesh</p>
            </div>
            <div class="w-64 h-2 rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                <div class="h-full bg-violet-500 rounded-full transition-all duration-300"
                     :style="{ width: `${progress}%` }" />
            </div>
        </div>

        <!-- Error state -->
        <div v-if="error && !isLoading"
             class="p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20">
            <p class="font-semibold text-rose-700 dark:text-rose-300">Gagal memproses model</p>
            <p class="text-sm text-rose-600 dark:text-rose-400 mt-1">{{ error }}</p>
        </div>

        <!-- Result UI -->
        <template v-if="result && !isLoading">
            <!-- Global warnings -->
            <div v-if="globalWarnings.length > 0" class="space-y-2">
                <div v-for="(w, i) in globalWarnings" :key="i"
                     class="flex items-start gap-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                    <TriangleAlert class="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <p class="text-sm text-amber-700 dark:text-amber-400">{{ w }}</p>
                </div>
            </div>

            <!-- Stats bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="p-3 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 text-center">
                    <p class="text-xl font-bold text-slate-800 dark:text-white">{{ result.total_count }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Total Objek</p>
                </div>
                <div class="p-3 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 text-center">
                    <p class="text-xl font-bold text-slate-800 dark:text-white">{{ fmtNum(result.total_area) }} m²</p>
                    <p class="text-xs text-slate-400 mt-0.5">Total Luas</p>
                </div>
                <div class="p-3 rounded-xl bg-white dark:bg-white/[0.02] border border-slate-200 dark:border-white/10 text-center">
                    <p class="text-xl font-bold text-slate-800 dark:text-white">{{ fmtNum(result.total_volume, 3) }} m³</p>
                    <p class="text-xs text-slate-400 mt-0.5">Total Volume*</p>
                </div>
                <div :class="[
                    'p-3 rounded-xl border text-center',
                    includedRows.length > 0
                        ? 'bg-violet-50 dark:bg-violet-500/10 border-violet-200 dark:border-violet-500/20'
                        : 'bg-white dark:bg-white/[0.02] border-slate-200 dark:border-white/10',
                ]">
                    <p :class="['text-xl font-bold', includedRows.length > 0 ? 'text-violet-700 dark:text-violet-300' : 'text-slate-800 dark:text-white']">
                        {{ includedRows.length }}
                    </p>
                    <p :class="['text-xs mt-0.5', includedRows.length > 0 ? 'text-violet-500' : 'text-slate-400']">Item Dipilih</p>
                </div>
            </div>
            <p class="text-xs text-slate-400">* Volume hanya akurat untuk mesh tertutup (watertight solid)</p>

            <!-- Disclaimer estimasi -->
            <div class="flex items-start gap-3 p-4 rounded-xl bg-violet-50 dark:bg-violet-500/10 border border-violet-200 dark:border-violet-500/20">
                <Info class="h-5 w-5 text-violet-600 dark:text-violet-400 shrink-0 mt-0.5" />
                <div class="text-sm text-violet-700 dark:text-violet-400 space-y-1">
                    <p class="font-semibold">Tentang estimasi .glb</p>
                    <ul class="list-disc list-inside text-xs space-y-0.5">
                        <li>Kalkulasi berdasarkan geometri mesh, bukan data BIM.</li>
                        <li>Bagian yang tidak dimodelkan tidak ikut terhitung.</li>
                        <li>Semua item akan berlabel <strong>Estimasi</strong> — bukan RAB final kontrak.</li>
                        <li>Beri nama objek dengan konvensi: <code class="font-mono">KATEGORI_JENIS_SPEK</code> (contoh: DINDING_BATA_15).</li>
                    </ul>
                </div>
            </div>

            <!-- Mesh table -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ rows.length }} objek terdeteksi
                    </p>
                    <div class="flex items-center gap-2">
                        <Button size="sm" variant="outline" class="text-xs h-7"
                                @click="rows.forEach(r => r.included = r.quantity > 0)">
                            Pilih Semua
                        </Button>
                        <Button size="sm" variant="outline" class="text-xs h-7"
                                @click="rows.forEach(r => r.included = false)">
                            Batalkan Semua
                        </Button>
                    </div>
                </div>

                <!-- Table header -->
                <div class="hidden lg:grid grid-cols-[auto_1fr_auto_1fr_auto_auto] gap-2 px-4 py-2 text-xs font-medium uppercase tracking-wide text-slate-400 bg-slate-50 dark:bg-white/[0.03] rounded-xl border border-slate-200 dark:border-white/10">
                    <span class="w-5"></span>
                    <span>Nama Objek</span>
                    <span class="w-48 text-center">Basis Kuantitas</span>
                    <span>Harga Satuan</span>
                    <span class="w-32 text-right">Subtotal Est.</span>
                    <span class="w-6"></span>
                </div>

                <div v-for="(row, idx) in rows" :key="row.name"
                     :class="[
                         'rounded-xl border transition-all',
                         row.included
                             ? 'border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02]'
                             : 'border-slate-100 dark:border-white/5 bg-slate-50/50 dark:bg-transparent opacity-60',
                     ]">
                    <!-- Row main -->
                    <div class="flex flex-col lg:grid lg:grid-cols-[auto_1fr_auto_1fr_auto_auto] gap-3 items-start lg:items-center px-4 py-3">
                        <!-- Checkbox -->
                        <div class="w-5 shrink-0">
                            <input type="checkbox" v-model="row.included"
                                   class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500 cursor-pointer" />
                        </div>

                        <!-- Name + warnings -->
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ row.name }}</p>
                                <Badge v-if="row.is_open_mesh" variant="outline"
                                       class="text-[10px] border-amber-300 text-amber-600 dark:text-amber-400">
                                    open mesh
                                </Badge>
                                <Badge v-if="row.rab_price_item_id" variant="outline"
                                       class="text-[10px] border-emerald-300 text-emerald-600 dark:text-emerald-400">
                                    terpetakan
                                </Badge>
                                <Badge v-if="row.has_extras" variant="outline"
                                       class="text-[10px] border-violet-300 text-violet-600 dark:text-violet-400 flex items-center gap-0.5">
                                    ✨ Blender
                                </Badge>
                            </div>
                            <div class="flex items-center gap-3 mt-0.5 text-xs text-slate-400">
                                <span>Luas: {{ fmtNum(row.area) }} m²</span>
                                <span v-if="row.volume > 0">Vol: {{ fmtNum(row.volume, 3) }} m³</span>
                                <span>Jml: {{ row.count }}</span>
                            </div>
                            <p v-for="(w, wi) in row.warnings" :key="wi"
                               class="text-xs text-amber-600 dark:text-amber-400 mt-0.5 flex items-start gap-1">
                                <TriangleAlert class="h-3 w-3 shrink-0 mt-0.5" /> {{ w }}
                            </p>
                        </div>

                        <!-- Basis selector -->
                        <div class="w-48 shrink-0">
                            <div class="space-y-1">
                                <select
                                    v-model="row.quantity_basis"
                                    @change="onBasisChange(row)"
                                    class="w-full h-8 rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                                >
                                    <option v-for="opt in basisOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <div class="flex items-center gap-1">
                                    <Input
                                        v-model="row.quantity"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="h-7 text-xs flex-1"
                                    />
                                    <span class="text-xs text-slate-400 shrink-0 w-8">{{ row.unit }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Price item picker -->
                        <div class="flex-1 min-w-0">
                            <div v-if="row.rab_price_item_id" class="flex items-center gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-slate-700 dark:text-slate-200 truncate">
                                        {{ props.priceItems.find(p => p.id === row.rab_price_item_id)?.name ?? '—' }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <Input
                                            v-model="row.unit_price"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            class="h-7 text-xs w-32"
                                            placeholder="Harga satuan"
                                        />
                                        <span class="text-[10px] text-slate-400">Rp/{{ row.unit }}</span>
                                    </div>
                                </div>
                                <Button size="icon" variant="ghost" class="h-7 w-7 shrink-0 text-slate-400 hover:text-rose-500"
                                        @click="clearPriceItem(idx)" title="Hapus mapping">
                                    <X class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                            <div v-else>
                                <Button size="sm" variant="outline"
                                        class="h-7 text-xs gap-1 border-dashed border-slate-300 dark:border-white/15 text-slate-500"
                                        @click="openPriceItemPicker(idx)">
                                    <Search class="h-3 w-3" /> Pilih Harga Satuan
                                </Button>
                                <p class="text-[10px] text-slate-400 mt-0.5">Item akan tetap diimport dengan harga 0</p>
                            </div>
                        </div>

                        <!-- Subtotal estimasi -->
                        <div class="w-32 text-right shrink-0">
                            <p v-if="row.unit_price > 0 && row.quantity > 0"
                               class="text-sm font-semibold text-violet-600 dark:text-violet-400">
                                {{ fmt(row.quantity * row.unit_price) }}
                            </p>
                            <p v-else class="text-xs text-slate-400">—</p>
                        </div>

                        <!-- Bagian / Section -->
                        <div class="w-6 shrink-0">
                            <!-- spacer for large screen grid alignment -->
                        </div>
                    </div>

                    <!-- Section editor (inline) -->
                    <div class="px-4 pb-2 flex items-center gap-2">
                        <Label class="text-xs text-slate-400 shrink-0">Bagian:</Label>
                        <Input v-model="row.section" placeholder="Pekerjaan Struktur…" class="h-6 text-xs max-w-48" />
                    </div>
                </div>
            </div>

            <!-- Summary + submit -->
            <div class="sticky bottom-0 bg-white dark:bg-[#0b0c10] border-t border-slate-200 dark:border-white/10 py-4 flex items-center justify-between gap-4 flex-wrap">
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-semibold text-slate-800 dark:text-white">{{ includedRows.length }}</span> item dipilih —
                    <span class="text-emerald-600 dark:text-emerald-400">{{ mappedCount }} terpetakan</span>
                    <span v-if="unmappedCount > 0">, <span class="text-amber-600 dark:text-amber-400">{{ unmappedCount }} tanpa harga</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="`/projects/${project.id}/rab/${document.id}`">
                        <Button variant="outline" size="sm">Batal</Button>
                    </Link>
                    <Button
                        @click="submitEstimation"
                        :disabled="includedRows.length === 0 || isSubmitting"
                        class="gap-2 bg-violet-600 hover:bg-violet-700 text-white min-w-44"
                    >
                        <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                        <Check v-else class="h-4 w-4" />
                        {{ isSubmitting ? 'Menyimpan…' : `Terapkan ${includedRows.length} Item Estimasi` }}
                    </Button>
                </div>
            </div>
        </template>
    </div>

    <!-- Price item picker modal -->
    <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="showPriceItemPicker"
             class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
             @click.self="showPriceItemPicker = false">
            <div class="bg-white dark:bg-[#13141a] rounded-2xl border border-slate-200 dark:border-white/10 shadow-2xl w-full max-w-md">
                <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-white/10">
                    <p class="font-semibold text-slate-800 dark:text-white">Pilih Harga Satuan</p>
                    <button @click="showPriceItemPicker = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-white">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <div class="px-4 py-3">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
                        <Input v-model="priceItemSearch" placeholder="Cari nama, kode…" class="pl-9" autofocus />
                    </div>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-white/5 max-h-72 overflow-y-auto">
                    <p v-if="filteredPriceItems.length === 0" class="px-4 py-6 text-sm text-center text-slate-400">
                        Tidak ada hasil. <Link href="/rab/price-items" class="underline text-sky-500">Tambah harga satuan</Link>.
                    </p>
                    <button
                        v-for="item in filteredPriceItems"
                        :key="item.id"
                        type="button"
                        @click="selectPriceItem(item)"
                        class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-white/[0.04] transition-colors"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100 truncate">{{ item.name }}</p>
                            <p class="text-xs text-slate-400">{{ item.category ?? '' }} {{ item.code ? `· ${item.code}` : '' }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs text-slate-500 font-mono">{{ item.unit }}</p>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ fmt(item.unit_price) }}</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
