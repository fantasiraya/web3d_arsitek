<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import KeyPlanCanvas from '@/components/drawing/KeyPlanCanvas.vue';
import {
    ArrowLeft,
    Printer,
    Sparkles,
    Calculator,
    Layers,
    Image as ImageIcon,
    FileText,
    CheckCircle,
    Building2,
    Calendar,
    User,
    Eye,
    Upload,
    RefreshCw,
    X,
} from '@lucide/vue';

interface ProjectUser {
    id: string;
    name: string;
    email: string;
    company_name?: string;
    company_type?: string;
    phone?: string;
    city_name?: string;
    avatar?: string;
}

interface ProjectData {
    id: string;
    title: string;
    slug?: string;
    description?: string;
    user?: ProjectUser;
}

interface ProjectCapture {
    id: string;
    project_id: string;
    drawing_sheet_id?: string;
    room_element_id?: string;
    title?: string;
    room_name: string;
    captured_hour: string;
    file_path: string;
    ai_rendered_path?: string;
    url: string;
    ai_rendered_url?: string;
    is_visible_to_clients: boolean;
    prompt?: string;
    description?: string;
    sort_order: number;
}

interface DrawingElement {
    id: string;
    drawing_sheet_id: string;
    type: string;
    data: any;
    sort_order?: number;
}

interface DrawingSheet {
    id: string;
    title: string;
    type: string;
    scale?: string;
    width: number;
    height: number;
    elements?: DrawingElement[];
}

interface RabItem {
    id: string;
    section: string;
    description: string;
    unit: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
}

interface RabDocument {
    id: string;
    title: string;
    total: number;
    subtotal: number;
    overhead_amount: number;
    ppn_amount: number;
    items?: RabItem[];
}

const props = defineProps<{
    project: ProjectData;
    planSheet?: DrawingSheet | null;
    captures: ProjectCapture[];
    rabDocuments: RabDocument[];
    isOwner: boolean;
    architect: {
        name: string;
        company_name: string;
        company_type: string;
        phone: string;
        city_name: string;
        avatar?: string;
    };
}>();

// Pengaturan Tampilan Lembar Kerja
const paperSize = ref<'A3' | 'A4'>('A3');
const preferAiRender = ref(true);
const includeRabAppendix = ref(false);

// State Title Block (Kop Gambar) Dinamis
const clientName = ref('Mr. Client');
const clientAddress = ref(props.project.description || 'Lokasi Proyek');
const documentDate = ref(new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }));
const drawnBy = ref(props.architect.name || 'FADIL');
const sheetNote = ref('');

// State Upload AI Render
const isUploadingAi = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const activeUploadCaptureId = ref<string | null>(null);

// State Generate RAB
const isGeneratingRab = ref(false);
const rabMessage = ref('');

// Filter & susun halaman lembar kerja
const exteriorCaptures = computed(() => {
    return props.captures.filter(c => {
        const name = (c.room_name || '').toUpperCase();
        return name.includes('TAMPAK') || name.includes('DEPAN') || name.includes('FASAD') || name.includes('EKSTERIOR');
    });
});

const interiorCaptures = computed(() => {
    return props.captures.filter(c => {
        const name = (c.room_name || '').toUpperCase();
        return !name.includes('TAMPAK') && !name.includes('DEPAN') && !name.includes('FASAD') && !name.includes('EKSTERIOR');
    });
});

// Gambar yang dipilih untuk Cover (prioritaskan eksterior, atau capture pertama)
const coverImage = computed(() => {
    if (exteriorCaptures.value.length > 0) {
        const c = exteriorCaptures.value[0];
        return (preferAiRender.value && c.ai_rendered_url) ? c.ai_rendered_url : c.url;
    }
    if (props.captures.length > 0) {
        const c = props.captures[0];
        return (preferAiRender.value && c.ai_rendered_url) ? c.ai_rendered_url : c.url;
    }
    return '/images/villa_orbit_360.jpg';
});

// Dapatkan URL gambar aktif (AI atau Asli)
function getCaptureDisplayUrl(capture: ProjectCapture): string {
    if (preferAiRender.value && capture.ai_rendered_url) {
        return capture.ai_rendered_url;
    }
    return capture.url;
}

// 1-Click Print / Download PDF
function handlePrintPdf() {
    window.print();
}

// Trigger upload file AI Render untuk capture tertentu
function triggerUploadAi(captureId: string) {
    activeUploadCaptureId.value = captureId;
    fileInputRef.value?.click();
}

async function handleFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0 || !activeUploadCaptureId.value) return;

    const file = input.files[0];
    const captureId = activeUploadCaptureId.value;
    isUploadingAi.value = captureId;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(`/projects/${props.project.id}/captures/${captureId}/ai-render`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: formData,
        });

        const data = await res.json();
        if (res.ok) {
            const idx = props.captures.findIndex(c => c.id === captureId);
            if (idx !== -1) {
                props.captures[idx] = data.data;
            }
        } else {
            alert(data.message || 'Gagal mengunggah render AI');
        }
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat mengunggah gambar');
    } finally {
        isUploadingAi.value = null;
        activeUploadCaptureId.value = null;
        if (input) input.value = '';
    }
}

// Hapus render AI dari capture
async function removeAiRender(captureId: string) {
    if (!confirm('Hapus gambar render AI ini dan kembali ke snapshot 3D asli?')) return;
    try {
        const res = await fetch(`/projects/${props.project.id}/captures/${captureId}/ai-render`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        const data = await res.json();
        if (res.ok) {
            const idx = props.captures.findIndex(c => c.id === captureId);
            if (idx !== -1) {
                props.captures[idx] = data.data;
            }
        }
    } catch (err) {
        console.error(err);
    }
}

// Eksekusi 1-Klik Pembuatan Draf RAB Per Ruangan
async function handleGenerateRabFromWorksheet() {
    isGeneratingRab.value = true;
    rabMessage.value = 'Mengkalkulasi geometri denah & membuat draf RAB...';

    // Kumpulkan daftar seluruh ruangan aktif dari capture interior
    const roomsPayload = interiorCaptures.value.map(c => {
        return {
            name: c.room_name || 'Ruangan',
            area_sqm: 16.0, // Default estimasi ruangan standar jika belum di-bind luas 2D
            wall_perimeter_m: 16.0,
        };
    });

    if (roomsPayload.length === 0) {
        // Fallback jika belum ada capture
        roomsPayload.push(
            { name: 'TERAS', area_sqm: 12.0, wall_perimeter_m: 14.0 },
            { name: 'KASIR', area_sqm: 15.0, wall_perimeter_m: 16.0 },
            { name: 'AREA VIP', area_sqm: 24.0, wall_perimeter_m: 20.0 },
            { name: 'KAMAR MANDI', area_sqm: 6.0, wall_perimeter_m: 10.0 }
        );
    }

    try {
        const res = await fetch(`/projects/${props.project.id}/rab/from-worksheet`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                title: `RAB Lembar Kerja - ${props.project.title}`,
                drawing_sheet_id: props.planSheet?.id ?? null,
                rooms: roomsPayload,
            }),
        });

        const data = await res.json();
        if (res.ok && data.redirect) {
            router.visit(data.redirect);
        } else {
            alert(data.message || 'Gagal membuat draf RAB');
            isGeneratingRab.value = false;
        }
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat memproses RAB.');
        isGeneratingRab.value = false;
    }
}
</script>

<template>
    <Head :title="`Booklet Lembar Kerja — ${project.title}`" />

    <!-- Hidden input file untuk upload AI Render -->
    <input
        ref="fileInputRef"
        type="file"
        accept="image/png,image/jpeg,image/webp"
        class="hidden"
        @change="handleFileSelected"
    />

    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans select-none print:bg-white print:text-black">
        
        <!-- TOP TOOLBAR CONTROLS (Hidden saat print) -->
        <header class="no-print sticky top-0 z-50 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-4 py-3 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link
                    :href="`/projects/${project.id}/viewer`"
                    class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1.5 text-xs font-medium"
                >
                    <ArrowLeft class="w-4 h-4" />
                    <span>Kembali ke 3D</span>
                </Link>
                <div class="h-5 w-px bg-slate-700"></div>
                <div>
                    <h1 class="text-sm font-bold text-white flex items-center gap-2">
                        <Layers class="w-4 h-4 text-orange-500" />
                        Architectural Working Booklet
                    </h1>
                    <p class="text-[11px] text-slate-400">
                        {{ project.title }} • {{ architect.company_name }}
                    </p>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="flex items-center flex-wrap gap-2.5">
                <!-- Toggle AI Render vs 3D Asli -->
                <button
                    @click="preferAiRender = !preferAiRender"
                    class="px-3 py-1.5 rounded-lg border text-xs font-medium transition flex items-center gap-1.5"
                    :class="preferAiRender
                        ? 'bg-orange-500/15 border-orange-500/40 text-orange-400'
                        : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-slate-200'"
                    title="Gunakan gambar render AI eksternal jika tersedia"
                >
                    <Sparkles class="w-3.5 h-3.5" :class="preferAiRender ? 'text-orange-400 animate-spin-slow' : ''" />
                    <span>{{ preferAiRender ? 'Prioritas: Render AI' : 'Prioritas: 3D Asli' }}</span>
                </button>

                <!-- Ukuran Kertas Selector -->
                <div class="flex items-center bg-slate-800 border border-slate-700 rounded-lg p-0.5 text-xs font-mono">
                    <button
                        @click="paperSize = 'A3'"
                        class="px-2.5 py-1 rounded transition"
                        :class="paperSize === 'A3' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        A3 Landscape
                    </button>
                    <button
                        @click="paperSize = 'A4'"
                        class="px-2.5 py-1 rounded transition"
                        :class="paperSize === 'A4' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                    >
                        A4 Landscape
                    </button>
                </div>

                <!-- Tombol 1-Klik Generate RAB -->
                <button
                    @click="handleGenerateRabFromWorksheet"
                    :disabled="isGeneratingRab"
                    class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5 disabled:opacity-50"
                >
                    <Calculator class="w-3.5 h-3.5" />
                    <span>{{ isGeneratingRab ? 'Menghitung...' : '🧮 Generate Draf RAB' }}</span>
                </button>

                <!-- Tombol 1-Klik Export / Print PDF -->
                <button
                    @click="handlePrintPdf"
                    class="px-4 py-1.5 rounded-lg bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-bold shadow-md shadow-orange-950/40 transition flex items-center gap-2"
                >
                    <Printer class="w-4 h-4" />
                    <span>Cetak / Download PDF</span>
                </button>
            </div>
        </header>

        <!-- BOOKLET PAGES CONTAINER (Printable Area) -->
        <main class="flex-1 w-full flex flex-col items-center py-6 px-4 sm:px-6 space-y-10 print:p-0 print:m-0 print:space-y-0">
            
            <!-- ======================================================== -->
            <!-- HALAMAN 1: COVER PAGE                                     -->
            <!-- ======================================================== -->
            <section
                class="booklet-sheet relative bg-black shadow-2xl overflow-hidden flex flex-col justify-between"
                :class="paperSize === 'A3' ? 'sheet-a3' : 'sheet-a4'"
            >
                <!-- Full Cover Background Visual -->
                <img
                    :src="coverImage"
                    alt="Cover Visual"
                    class="absolute inset-0 w-full h-full object-cover opacity-90"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-black/40"></div>

                <!-- Header / Title Atas -->
                <div class="relative z-10 p-8 sm:p-12 flex justify-between items-start">
                    <div>
                        <span class="inline-block px-3 py-1 rounded bg-orange-600 text-white font-mono text-[11px] font-bold tracking-widest uppercase mb-3">
                            ARCHITECTURAL PRESENTATION
                        </span>
                        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight uppercase drop-shadow-md">
                            {{ project.title }}
                        </h1>
                        <p class="text-sm sm:text-base text-slate-200 mt-1 drop-shadow font-light">
                            {{ clientAddress }}
                        </p>
                    </div>
                </div>

                <!-- Footer Watermark Credit: DESIGN BY [company_name] -->
                <div class="relative z-10 p-8 sm:p-12 flex flex-col items-center justify-center text-center">
                    <p class="text-xs sm:text-sm uppercase tracking-[0.3em] text-slate-300 font-semibold mb-1">
                        DESIGN BY
                    </p>
                    <h2 class="text-3xl sm:text-6xl font-black tracking-widest text-white uppercase drop-shadow-lg font-serif">
                        {{ architect.company_name || 'STUDIO ARSITEK' }}
                    </h2>
                    <p class="text-[10px] sm:text-xs text-slate-400 mt-2 font-mono">
                        {{ architect.city_name ? `${architect.city_name} • ` : '' }}{{ documentDate }}
                    </p>
                </div>
            </section>

            <!-- ======================================================== -->
            <!-- HALAMAN 2: MASTER FLOOR PLAN (DENAH 2D TERUKUR)           -->
            <!-- ======================================================== -->
            <section
                class="booklet-sheet bg-white text-slate-900 shadow-2xl overflow-hidden flex border border-slate-300 print:border-none"
                :class="paperSize === 'A3' ? 'sheet-a3' : 'sheet-a4'"
            >
                <!-- Area Kiri: 80% Denah 2D Utuh + Garis Dimensi -->
                <div class="w-[80%] h-full p-6 sm:p-8 flex flex-col justify-between relative bg-white border-r border-slate-800">
                    <!-- Kanvas Denah 2D Mini/Utuh -->
                    <div class="flex-1 w-full h-full relative flex items-center justify-center">
                        <KeyPlanCanvas
                            :elements="planSheet?.elements || []"
                            width="100%"
                            height="100%"
                            className="w-full h-full border-none shadow-none bg-transparent"
                        />
                    </div>

                    <!-- Label Nama Halaman Kiri Bawah -->
                    <div class="pt-4">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                            DENAH
                        </h2>
                        <p class="text-xs text-slate-500 font-mono">
                            MASTER FLOOR PLAN • SKALA {{ planSheet?.scale || '1:100' }}
                        </p>
                    </div>
                </div>

                <!-- Area Kanan: 20% Kop Gambar (Title Block) Standar Arsitektur -->
                <div class="w-[20%] h-full bg-white flex flex-col justify-between p-4 sm:p-5 border-l border-slate-300 text-[11px] leading-tight">
                    <!-- Logo / Brand Studio Atas -->
                    <div class="border-b border-slate-800 pb-4 text-center">
                        <h3 class="text-xl sm:text-2xl font-black tracking-wider text-slate-900 uppercase font-serif">
                            {{ architect.company_name || 'TRIVES' }}
                        </h3>
                        <p class="text-[9px] text-slate-500 uppercase tracking-widest mt-0.5">
                            ARCHITECTURAL STUDIO
                        </p>
                    </div>

                    <!-- Isian Spesifikasi Kop Gambar -->
                    <div class="space-y-3.5 my-auto py-4">
                        <div class="border-b border-slate-300 pb-2">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">PROJECT</span>
                            <span class="font-bold text-slate-800 text-xs line-clamp-2">{{ project.title }}</span>
                        </div>
                        <div class="border-b border-slate-300 pb-2">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">CLIENT</span>
                            <span class="font-bold text-slate-800">{{ clientName }}</span>
                            <p class="text-[10px] text-slate-500 truncate">{{ clientAddress }}</p>
                        </div>
                        <div class="border-b border-slate-300 pb-2">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DATE</span>
                            <span class="font-medium text-slate-700 font-mono">{{ documentDate }}</span>
                        </div>
                        <div class="border-b border-slate-300 pb-2">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DRAWN BY</span>
                            <span class="font-bold text-slate-800 uppercase">{{ drawnBy }}</span>
                        </div>
                        <div class="border-b border-slate-300 pb-2">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DESCRIPTION</span>
                            <p class="text-[10px] text-slate-600 italic">Master Layout Plan</p>
                        </div>
                    </div>

                    <!-- Note Bawah -->
                    <div class="pt-2 border-t border-slate-800">
                        <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">NOTE</span>
                        <p class="text-[9px] text-slate-500 mt-0.5">Seluruh dimensi terukur dalam satuan meter (m).</p>
                    </div>
                </div>
            </section>

            <!-- ======================================================== -->
            <!-- HALAMAN 3+: TAMPAK DEPAN / EKSTERIOR (Jika Ada)           -->
            <!-- ======================================================== -->
            <template v-for="(ext, idx) in exteriorCaptures" :key="'ext-' + ext.id">
                <section
                    class="booklet-sheet bg-white text-slate-900 shadow-2xl overflow-hidden flex border border-slate-300 print:border-none"
                    :class="paperSize === 'A3' ? 'sheet-a3' : 'sheet-a4'"
                >
                    <!-- Area Kiri: 80% Visual Eksterior 3D / AI -->
                    <div class="w-[80%] h-full flex flex-col justify-between p-6 sm:p-8 relative bg-slate-100 border-r border-slate-800">
                        <!-- Gambar 3D / AI -->
                        <div class="flex-1 w-full relative rounded overflow-hidden bg-black flex items-center justify-center">
                            <img
                                :src="getCaptureDisplayUrl(ext)"
                                :alt="ext.room_name"
                                class="w-full h-full object-cover"
                            />

                            <!-- Badge Sumber Gambar (AI / 3D) -->
                            <div class="no-print absolute top-3 right-3 flex items-center gap-1.5">
                                <span
                                    v-if="ext.ai_rendered_url"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-600 text-white shadow"
                                >
                                    RENDER AI
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900/80 text-slate-300 backdrop-blur"
                                >
                                    SNAPSHOT 3D
                                </span>
                                <button
                                    v-if="isOwner"
                                    @click="triggerUploadAi(ext.id)"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-white hover:bg-slate-700 transition flex items-center gap-1 shadow"
                                >
                                    <Upload class="w-3 h-3" />
                                    <span>Ganti AI</span>
                                </button>
                            </div>
                        </div>

                        <!-- Judul Bawah -->
                        <div class="pt-4">
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                                {{ ext.room_name || 'TAMPAK DEPAN' }}
                            </h2>
                            <p class="text-xs text-slate-500 font-mono">
                                PERSPEKTIF EKSTERIOR • JAM {{ ext.captured_hour }}
                            </p>
                        </div>
                    </div>

                    <!-- Area Kanan: 20% Kop Gambar -->
                    <div class="w-[20%] h-full bg-white flex flex-col justify-between p-4 sm:p-5 border-l border-slate-300 text-[11px] leading-tight">
                        <div class="border-b border-slate-800 pb-4 text-center">
                            <h3 class="text-xl sm:text-2xl font-black tracking-wider text-slate-900 uppercase font-serif">
                                {{ architect.company_name || 'TRIVES' }}
                            </h3>
                            <p class="text-[9px] text-slate-500 uppercase tracking-widest mt-0.5">ARCHITECTURAL STUDIO</p>
                        </div>
                        <div class="space-y-3.5 my-auto py-4">
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">PROJECT</span>
                                <span class="font-bold text-slate-800 text-xs">{{ project.title }}</span>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">CLIENT</span>
                                <span class="font-bold text-slate-800">{{ clientName }}</span>
                                <p class="text-[10px] text-slate-500 truncate">{{ clientAddress }}</p>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DATE</span>
                                <span class="font-medium text-slate-700 font-mono">{{ documentDate }}</span>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DRAWN BY</span>
                                <span class="font-bold text-slate-800 uppercase">{{ drawnBy }}</span>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DESCRIPTION</span>
                                <p class="text-[10px] text-slate-600 italic">Visual Eksterior Bangunan</p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-800">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">NOTE</span>
                            <p class="text-[9px] text-slate-500 mt-0.5">Hasil simulasi pencahayaan matahari aktual.</p>
                        </div>
                    </div>
                </section>
            </template>

            <!-- ======================================================== -->
            <!-- HALAMAN 4 dst.: INTERIOR PER RUANGAN + KEY PLAN LOCATOR   -->
            <!-- ======================================================== -->
            <template v-for="(room, rIdx) in interiorCaptures" :key="'room-' + room.id">
                <section
                    class="booklet-sheet bg-white text-slate-900 shadow-2xl overflow-hidden flex border border-slate-300 print:border-none"
                    :class="paperSize === 'A3' ? 'sheet-a3' : 'sheet-a4'"
                >
                    <!-- Area Kiri: 80% Lebar Lembar Kerja -->
                    <div class="w-[80%] h-full flex flex-col justify-between p-6 sm:p-8 relative bg-white border-r border-slate-800">
                        
                        <!-- Kiri Atas (~68% Tinggi): Visual 3D / Render AI Ruangan -->
                        <div class="h-[68%] w-full relative rounded overflow-hidden bg-black flex items-center justify-center">
                            <img
                                :src="getCaptureDisplayUrl(room)"
                                :alt="room.room_name"
                                class="w-full h-full object-cover"
                            />

                            <!-- Controls AI & Upload (Hidden saat print) -->
                            <div class="no-print absolute top-3 right-3 flex items-center gap-1.5">
                                <span
                                    v-if="room.ai_rendered_url"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-orange-600 text-white shadow"
                                >
                                    RENDER AI
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-900/80 text-slate-300 backdrop-blur"
                                >
                                    SNAPSHOT 3D
                                </span>

                                <button
                                    v-if="isOwner"
                                    @click="triggerUploadAi(room.id)"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800/90 text-white hover:bg-slate-700 transition flex items-center gap-1 shadow backdrop-blur"
                                    title="Upload gambar render AI untuk ruangan ini"
                                >
                                    <Upload class="w-3 h-3" />
                                    <span>{{ room.ai_rendered_url ? 'Ganti AI' : '+ AI Render' }}</span>
                                </button>
                                
                                <button
                                    v-if="isOwner && room.ai_rendered_url"
                                    @click="removeAiRender(room.id)"
                                    class="p-1 rounded bg-red-600/80 text-white hover:bg-red-500 transition shadow"
                                    title="Hapus render AI dan gunakan 3D asli"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Baris Bawah: [Judul Ruangan Kiri] + [Key Plan Locator Kanan] -->
                        <div class="h-[30%] w-full pt-4 flex items-end justify-between gap-4">
                            <!-- Sisi Kiri Bawah: Nama Ruangan Kapital Tebal & Catatan -->
                            <div class="flex-1 pr-4">
                                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                                    {{ room.room_name }}
                                </h2>
                                <p class="text-xs text-slate-500 font-mono mt-0.5">
                                    INTERIOR PERSPECTIVE • JAM {{ room.captured_hour }}
                                </p>
                                <p v-if="room.description" class="text-xs text-slate-600 mt-2 line-clamp-3">
                                    {{ room.description }}
                                </p>
                                <p v-else class="text-[11px] text-slate-400 mt-2 italic">
                                    Material finishing dinding, penutup lantai, serta armature penerangan arsitektural.
                                </p>
                            </div>

                            <!-- Tengah/Kanan Bawah: Key Plan (Denah Mini Locator Otomatis) -->
                            <div class="w-[280px] sm:w-[320px] h-full flex-shrink-0">
                                <KeyPlanCanvas
                                    :elements="planSheet?.elements || []"
                                    :activeRoomName="room.room_name"
                                    width="100%"
                                    height="100%"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Area Kanan: 20% Kop Gambar (Title Block) Standar Arsitektur -->
                    <div class="w-[20%] h-full bg-white flex flex-col justify-between p-4 sm:p-5 border-l border-slate-300 text-[11px] leading-tight">
                        <div class="border-b border-slate-800 pb-4 text-center">
                            <h3 class="text-xl sm:text-2xl font-black tracking-wider text-slate-900 uppercase font-serif">
                                {{ architect.company_name || 'TRIVES' }}
                            </h3>
                            <p class="text-[9px] text-slate-500 uppercase tracking-widest mt-0.5">ARCHITECTURAL STUDIO</p>
                        </div>
                        <div class="space-y-3.5 my-auto py-4">
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">PROJECT</span>
                                <span class="font-bold text-slate-800 text-xs">{{ project.title }}</span>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">CLIENT</span>
                                <span class="font-bold text-slate-800">{{ clientName }}</span>
                                <p class="text-[10px] text-slate-500 truncate">{{ clientAddress }}</p>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DATE</span>
                                <span class="font-medium text-slate-700 font-mono">{{ documentDate }}</span>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DRAWN BY</span>
                                <span class="font-bold text-slate-800 uppercase">{{ drawnBy }}</span>
                            </div>
                            <div class="border-b border-slate-300 pb-2">
                                <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">DESCRIPTION</span>
                                <p class="text-[10px] text-slate-700 font-semibold uppercase">{{ room.room_name }}</p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-800">
                            <span class="block text-[9px] font-mono uppercase tracking-wider text-slate-400">NOTE</span>
                            <p class="text-[9px] text-slate-500 mt-0.5">Lihat denah mini locator untuk lokasi ruangan terkait.</p>
                        </div>
                    </div>
                </section>
            </template>
        </main>
    </div>
</template>

<style scoped>
/* Rasio Lembar Kerja ISO Landscape */
.sheet-a3 {
    width: 100%;
    max-width: 1190px;
    height: 700px;
    aspect-ratio: 1.414;
}

.sheet-a4 {
    width: 100%;
    max-width: 960px;
    height: 565px;
    aspect-ratio: 1.414;
}

@media screen and (max-width: 1024px) {
    .sheet-a3, .sheet-a4 {
        height: auto;
        min-height: 540px;
    }
}

/* CSS Cetak / Print Engine Tanpa Bleed */
@media print {
    @page {
        size: A3 landscape;
        margin: 0;
    }

    body {
        background: white !important;
        color: black !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .no-print {
        display: none !important;
    }

    .booklet-sheet {
        width: 100vw !important;
        height: 100vh !important;
        max-width: none !important;
        page-break-after: always !important;
        break-after: page !important;
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        border-radius: 0 !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

