<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
    Menu,
    SlidersHorizontal,
    ChevronRight,
    ChevronLeft,
    Download,
    Copy,
    ExternalLink,
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
    client_name?: string;
    client_address?: string;
    user?: ProjectUser;
}


interface ProjectCapture {
    id: string;
    code?: string;
    project_id: string;
    drawing_sheet_id?: string;
    room_element_id?: string;
    title?: string;
    room_name: string;
    captured_hour: string;
    file_path: string;
    depth_map_path?: string;
    key_plan_image_path?: string;
    ai_rendered_path?: string;
    url: string;
    depth_map_url?: string;
    key_plan_image_url?: string;
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
    image_path?: string;
    image_url?: string;
    elements?: DrawingElement[];
    captures?: ProjectCapture[];
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

interface PlanSheetSummary {
    id: string;
    title: string;
    scale?: string;
    unit?: string;
    type: string;
    image_path?: string;
    image_url?: string;
}

const props = defineProps<{
    project: ProjectData;
    planSheet?: DrawingSheet | null;
    planSheets?: PlanSheetSummary[];
    captures: ProjectCapture[];
    rabDocuments: RabDocument[];
    isOwner: boolean;
    selectedSheetId?: string | null;
    architect: {
        name: string;
        company_name: string;
        company_type: string;
        phone: string;
        city_name: string;
        avatar?: string;
    };
}>();

const currentSelectedSheetId = ref(props.planSheet?.id || (props.planSheets?.[0]?.id ?? ''));

function handlePlanSheetChange(e: Event) {
    const target = e.target as HTMLSelectElement;
    if (!target) return;
    const newId = target.value;
    currentSelectedSheetId.value = newId;
    router.visit(`/projects/${props.project.id}/booklet?sheet_id=${newId}`, {
        preserveState: false,
    });
}

// Pengaturan Tampilan Lembar Kerja
const paperSize = ref<'A3' | 'A4'>('A3');
const preferAiRender = ref(true);
const includeRabAppendix = ref(false);

// State Title Block (Kop Gambar) Dinamis
const clientName = ref(props.project.client_name || 'Mr. Jerry');
const clientAddress = ref(props.project.client_address || props.project.description || 'JL. Sentot Prawirodirdjo, Kaliwates, Jember');
const documentDate = ref('September 15, 2026');
const drawnBy = ref(props.architect.name || 'FADIL');
const sheetNote = ref('');
const isEditingKop = ref(false);
const isSavingKop = ref(false);
const isMobileMenuOpen = ref(false);

async function saveKopInfo() {
    isSavingKop.value = true;
    try {
        await fetch(`/projects/${props.project.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                client_name: clientName.value,
                client_address: clientAddress.value,
            }),
        });
    } catch (err) {
        console.error('Failed to save kop info:', err);
    } finally {
        isSavingKop.value = false;
        isEditingKop.value = false;
    }
}

// State Upload AI Render
const isUploadingAi = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const activeUploadCaptureId = ref<string | null>(null);

// State Generate RAB
const isGeneratingRab = ref(false);
const rabMessage = ref('');

// Pilihan Gambar Cover Booklet (Dapat dipilih arsitek atau auto)
const selectedCoverCaptureId = ref<string>('auto');

// Cek apakah lembar kerja aktif memiliki capture 3D yang ditautkan
const hasSheetCaptures = computed(() => {
    if (!props.planSheet?.id) return false;
    const fromProps = props.captures.some(c => c.drawing_sheet_id === props.planSheet?.id);
    const fromSheet = Boolean(props.planSheet.captures && props.planSheet.captures.length > 0);
    return fromProps || fromSheet;
});

// Mode filter captures: jika dibuka spesifik dari sheet dan ada captures tertaut, default 'sheet'
const filterMode = ref<'sheet' | 'all'>(
    (props.selectedSheetId || props.planSheet?.id) && hasSheetCaptures.value ? 'sheet' : 'all'
);

// Captures aktif yang digunakan di halaman eksterior & interior
const activeCaptures = computed(() => {
    if (filterMode.value === 'sheet' && props.planSheet?.id) {
        const linked = props.captures.filter(c => c.drawing_sheet_id === props.planSheet?.id);
        if (linked.length > 0) return linked;
        if (props.planSheet.captures && props.planSheet.captures.length > 0) {
            return props.planSheet.captures;
        }
    }
    return props.captures;
});

// Filter & susun halaman lembar kerja
const exteriorCaptures = computed(() => {
    return activeCaptures.value.filter(c => {
        const code = (c.code || '').toUpperCase();
        if (code.startsWith('EXT')) return true;
        const name = (c.room_name || '').toUpperCase();
        return name.includes('TAMPAK') || name.includes('DEPAN') || name.includes('FASAD') || name.includes('EKSTERIOR');
    });
});

const interiorCaptures = computed(() => {
    return activeCaptures.value.filter(c => {
        const code = (c.code || '').toUpperCase();
        if (code.startsWith('EXT')) return false;
        const name = (c.room_name || '').toUpperCase();
        return !name.includes('TAMPAK') && !name.includes('DEPAN') && !name.includes('FASAD') && !name.includes('EKSTERIOR');
    });
});

// Capture yang dipilih untuk Cover
const selectedCoverCapture = computed(() => {
    if (selectedCoverCaptureId.value !== 'auto') {
        const found = props.captures.find(c => c.id === selectedCoverCaptureId.value);
        if (found) return found;
    }
    // Default otomatis: prioritaskan eksterior aktif, lalu capture aktif pertama
    if (exteriorCaptures.value.length > 0) {
        return exteriorCaptures.value[0];
    }
    if (activeCaptures.value.length > 0) {
        return activeCaptures.value[0];
    }
    return null;
});

// URL Gambar Cover aktif: TIDAK ADA GAMBAR DEFAULT PALSU!
const coverImage = computed(() => {
    if (selectedCoverCapture.value) {
        return (preferAiRender.value && selectedCoverCapture.value.ai_rendered_url)
            ? selectedCoverCapture.value.ai_rendered_url
            : selectedCoverCapture.value.url;
    }
    return null; // Return null jika belum ada gambar cover
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
    // Beri sedikit jeda microtask agar image render di canvas/DOM selesai ter-paint
    requestAnimationFrame(() => {
        setTimeout(() => {
            window.print();
        }, 120);
    });
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

// ── State & Handler Update Gambar 3D Snapshot Utama ──
const isUploadingCapture = ref<string | null>(null);
const activeUpdateCaptureId = ref<string | null>(null);
const captureFileInputRef = ref<HTMLInputElement | null>(null);

function triggerUpdateCapture(captureId: string) {
    activeUpdateCaptureId.value = captureId;
    captureFileInputRef.value?.click();
}

async function handleCaptureFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0 || !activeUpdateCaptureId.value) return;

    const file = input.files[0];
    const captureId = activeUpdateCaptureId.value;
    isUploadingCapture.value = captureId;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(`/projects/${props.project.id}/captures/${captureId}/image`, {
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
            alert(data.message || 'Gagal memperbarui gambar.');
        }
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat memperbarui gambar.');
    } finally {
        isUploadingCapture.value = null;
        activeUpdateCaptureId.value = null;
        if (input) input.value = '';
    }
}

// ── State & Handler Gambar 2D Denah Master (Halaman 2) ──
const isUploadingSheet2d = ref(false);
const sheetFileInputRef = ref<HTMLInputElement | null>(null);
const showVectorCanvasHalaman2 = ref(false);

function triggerUploadSheet2d() {
    if (!props.planSheet?.id) {
        alert('Tidak ada lembar kerja denah yang dipilih.');
        return;
    }
    sheetFileInputRef.value?.click();
}

async function handleSheetFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0 || !props.planSheet?.id) return;

    const file = input.files[0];
    isUploadingSheet2d.value = true;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(`/drawings/${props.planSheet.id}/image`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: formData,
        });

        const data = await res.json();
        if (res.ok && props.planSheet) {
            props.planSheet.image_path = data.sheet.image_path;
            props.planSheet.image_url = data.sheet.image_url;
            showVectorCanvasHalaman2.value = false;
        } else {
            alert(data.message || 'Gagal mengunggah gambar denah 2D.');
        }
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat mengunggah gambar denah 2D.');
    } finally {
        isUploadingSheet2d.value = false;
        if (input) input.value = '';
    }
}

async function removeSheet2dImage() {
    if (!props.planSheet?.id) return;
    if (!confirm('Hapus gambar denah 2D dan kembali ke kanvas vektor otomatis?')) return;

    try {
        const res = await fetch(`/drawings/${props.planSheet.id}/image`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });

        const data = await res.json();
        if (res.ok && props.planSheet) {
            props.planSheet.image_path = null;
            props.planSheet.image_url = null;
            showVectorCanvasHalaman2.value = true;
        }
    } catch (err) {
        console.error(err);
    }
}

// ── State & Handler Gambar 2D Key Plan per Ruangan (Per Lembar) ──
const isUploadingRoom2d = ref<string | null>(null);
const activeRoom2dCaptureId = ref<string | null>(null);
const room2dFileInputRef = ref<HTMLInputElement | null>(null);

function triggerUploadRoom2d(captureId: string) {
    activeRoom2dCaptureId.value = captureId;
    room2dFileInputRef.value?.click();
}

async function handleRoom2dFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0 || !activeRoom2dCaptureId.value) return;

    const file = input.files[0];
    const captureId = activeRoom2dCaptureId.value;
    isUploadingRoom2d.value = captureId;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await fetch(`/projects/${props.project.id}/captures/${captureId}/key-plan-image`, {
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
            alert(data.message || 'Gagal mengunggah denah 2D ruangan.');
        }
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat mengunggah denah 2D ruangan.');
    } finally {
        isUploadingRoom2d.value = null;
        activeRoom2dCaptureId.value = null;
        if (input) input.value = '';
    }
}

async function removeRoom2dImage(captureId: string) {
    if (!confirm('Hapus gambar denah 2D khusus ruangan ini dan kembali ke denah otomatis?')) return;

    try {
        const res = await fetch(`/projects/${props.project.id}/captures/${captureId}/key-plan-image`, {
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

// State & Handler Generate AI Render via Gemini Imagen
const isAiModalOpen = ref(false);
const activeGenerateCapture = ref<ProjectCapture | null>(null);
const aiPromptText = ref('');
const selectedMood = ref('natural bright daylight');
const selectedAspectRatio = ref<'16:9' | '4:3' | '1:1'>('16:9');
const isGeneratingAiRender = ref<string | null>(null);
const aiGenerationError = ref<string | null>(null);
const depthMapPreviewUrl = ref<string | null>(null);
const isGeneratingDepthPreview = ref(false);

const moodPresets = [
    { id: 'natural bright daylight', label: '☀️ Siang Alami', desc: 'Cahaya matahari siang cerah, bayangan lembut dan natural' },
    { id: 'golden hour sunset', label: '🌅 Golden Hour', desc: 'Cahaya keemasan sore hari dengan pantulan hangat dramatis' },
    { id: 'evening ambient lighting', label: '🌙 Malam Hangat', desc: 'Pencahayaan lampu arsitektural interior yang elegan dan intim' },
    { id: 'minimalist cloudy diffuse', label: '☁️ Mendung Teduh', desc: 'Pencahayaan difus merata dengan nuansa arsitektural modern' },
];

/**
 * Buat Blob Depth Map hitam-putih skala perspektif untuk ControlNet langsung dari Canvas browser
 */
function createDepthMapBlob(imgUrl: string): Promise<Blob | null> {
    return new Promise((resolve) => {
        const processImage = (img: HTMLImageElement) => {
            try {
                const width = img.naturalWidth || img.width || 1280;
                const height = img.naturalHeight || img.height || 720;
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(null);
                    return;
                }

                ctx.drawImage(img, 0, 0, width, height);
                const imgData = ctx.getImageData(0, 0, width, height);
                const data = imgData.data;

                for (let y = 0; y < height; y++) {
                    const yNorm = y / height;
                    const perspectiveFactor = 0.65 + (yNorm * 0.35);

                    for (let x = 0; x < width; x++) {
                        const idx = (y * width + x) * 4;
                        const r = data[idx];
                        const g = data[idx + 1];
                        const b = data[idx + 2];

                        const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                        const depth = Math.min(255, Math.max(0, Math.round(lum * perspectiveFactor)));

                        data[idx] = depth;
                        data[idx + 1] = depth;
                        data[idx + 2] = depth;
                    }
                }

                ctx.putImageData(imgData, 0, 0);
                canvas.toBlob((blob) => {
                    resolve(blob);
                }, 'image/png');
            } catch (e) {
                console.error('[DepthMap] Canvas error:', e);
                resolve(null);
            }
        };

        // Ambil blob melalui same-origin fetch terlebih dahulu agar tidak terkendala CORS / tainted canvas
        fetch(imgUrl)
            .then(res => res.blob())
            .then(blob => {
                const blobUrl = URL.createObjectURL(blob);
                const img = new Image();
                img.onload = () => {
                    processImage(img);
                    URL.revokeObjectURL(blobUrl);
                };
                img.onerror = () => {
                    URL.revokeObjectURL(blobUrl);
                    resolve(null);
                };
                img.src = blobUrl;
            })
            .catch(() => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => processImage(img);
                img.onerror = () => resolve(null);
                img.src = imgUrl;
            });
    });
}

async function loadDepthMapPreview(imgUrl: string) {
    isGeneratingDepthPreview.value = true;
    depthMapPreviewUrl.value = null;
    try {
        const blob = await createDepthMapBlob(imgUrl);
        if (blob) {
            depthMapPreviewUrl.value = URL.createObjectURL(blob);
        }
    } catch (err) {
        console.error('[DepthMap] Preview error:', err);
    } finally {
        isGeneratingDepthPreview.value = false;
    }
}

function openAiGenerateModal(capture: ProjectCapture) {
    activeGenerateCapture.value = capture;
    aiGenerationError.value = null;
    selectedMood.value = 'natural bright daylight';
    selectedAspectRatio.value = '16:9';

    if (capture.prompt && capture.prompt.trim().length > 0) {
        aiPromptText.value = capture.prompt;
    } else {
        const roomName = capture.room_name || 'Ruangan Arsitektur';
        aiPromptText.value = `Masterpiece architectural photograph of ${roomName}, modern high-end architectural design, realistic physical materials, clean geometry, architectural digest style, natural daylight, photorealistic 8k.`;
    }

    isAiModalOpen.value = true;
    loadDepthMapPreview(capture.url);
}

function applyMoodPreset(presetId: string) {
    selectedMood.value = presetId;
    // Tambahkan atau update mood di prompt text
    aiPromptText.value = aiPromptText.value.replace(/, (natural bright daylight|golden hour sunset|evening ambient lighting|minimalist cloudy diffuse)/g, '');
    aiPromptText.value += `, ${presetId}`;
}

const isPromptCopied = ref(false);

function copyAiPrompt() {
    if (!aiPromptText.value) return;
    navigator.clipboard.writeText(aiPromptText.value);
    isPromptCopied.value = true;
    setTimeout(() => {
        isPromptCopied.value = false;
    }, 2000);
}

function downloadReferenceImage() {
    if (!activeGenerateCapture.value) return;
    const a = document.createElement('a');
    a.href = activeGenerateCapture.value.url;
    a.download = `3d_ref_${activeGenerateCapture.value.room_name || 'capture'}.jpg`;
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

function downloadDepthMapFromModal() {
    if (!depthMapPreviewUrl.value || !activeGenerateCapture.value) return;
    const a = document.createElement('a');
    a.href = depthMapPreviewUrl.value;
    const roomSlug = (activeGenerateCapture.value.room_name || 'capture').replace(/\s+/g, '-').toLowerCase();
    a.download = `controlnet-depth-${roomSlug}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

async function handleExecuteAiGenerate() {
    if (!activeGenerateCapture.value) return;

    const capture = activeGenerateCapture.value;
    const captureId = capture.id;
    isGeneratingAiRender.value = captureId;
    aiGenerationError.value = null;

    try {
        const formData = new FormData();
        formData.append('prompt', aiPromptText.value);
        formData.append('mood', selectedMood.value);
        formData.append('aspect_ratio', selectedAspectRatio.value);

        // 1. Ambil file gambar 3D (RGB) aktif dan kirimkan sebagai file referensi nyata
        try {
            const imgRes = await fetch(capture.url);
            if (imgRes.ok) {
                const blob = await imgRes.blob();
                formData.append('image', blob, `ref_${capture.room_name || '3d'}.jpg`);
            }
        } catch (fetchErr) {
            console.warn('Gagal fetch blob gambar, backend akan membaca file_path dari storage:', fetchErr);
        }

        // 2. Buat dan kirim file Depth Map (ControlNet) agar geometri 100% presisi
        try {
            const depthBlob = await createDepthMapBlob(capture.url);
            if (depthBlob) {
                formData.append('depth_map', depthBlob, `depth_${capture.room_name || '3d'}.png`);
            }
        } catch (depthErr) {
            console.warn('Gagal buat depth map blob, backend akan meng-generate otomatis:', depthErr);
        }

        const res = await fetch(`/projects/${props.project.id}/captures/${captureId}/generate-ai`, {
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
            // Otomatis aktifkan view preferensi AI agar gambar langsung berubah
            preferAiRender.value = true;
            isAiModalOpen.value = false;
        } else {
            aiGenerationError.value = data.message || 'Gagal membuat render AI.';
        }
    } catch (err: any) {
        console.error(err);
        aiGenerationError.value = err.message || 'Terjadi kesalahan saat memproses permintaan render AI.';
    } finally {
        isGeneratingAiRender.value = null;
    }
}

// State dan Handler Batch Render Seluruh Section / Ruangan
const isBatchRendering = ref(false);
const batchProgress = ref({ current: 0, total: 0, currentName: '' });
const abortBatchFlag = ref(false);

async function handleBatchGenerateAi() {
    if (isBatchRendering.value) return;

    const targets = activeCaptures.value.length > 0 ? activeCaptures.value : props.captures;
    if (targets.length === 0) {
        alert('Tidak ada snapshot ruangan untuk dirender.');
        return;
    }

    const unrenderedCount = targets.filter((c: any) => !c.ai_rendered_url).length;
    const confirmText = unrenderedCount > 0
        ? `Mulai render visual AI untuk seluruh ${targets.length} lembar ruangan (${unrenderedCount} belum dirender)?\n\nGambar referensi 3D & Depth Map akan disertakan otomatis.`
        : `Render ulang visual AI untuk semua ${targets.length} lembar ruangan?\n\nGambar referensi 3D & Depth Map akan disertakan otomatis.`;

    if (!confirm(confirmText)) return;

    isBatchRendering.value = true;
    abortBatchFlag.value = false;
    preferAiRender.value = true;

    let successCount = 0;
    let failCount = 0;

    for (let i = 0; i < targets.length; i++) {
        if (abortBatchFlag.value) {
            break;
        }

        const capture = targets[i];
        batchProgress.value = {
            current: i + 1,
            total: targets.length,
            currentName: capture.room_name || `Ruangan ${i + 1}`,
        };
        isGeneratingAiRender.value = capture.id;

        try {
            const formData = new FormData();
            formData.append('mood', 'natural bright daylight');
            formData.append('aspect_ratio', '16:9');
            if (capture.prompt) {
                formData.append('prompt', capture.prompt);
            }

            try {
                const imgRes = await fetch(capture.url);
                if (imgRes.ok) {
                    const blob = await imgRes.blob();
                    formData.append('image', blob, `ref_${capture.room_name || '3d'}.jpg`);
                }
            } catch (fetchErr) {
                console.warn('Gagal fetch blob gambar:', fetchErr);
            }

            try {
                const depthBlob = await createDepthMapBlob(capture.url);
                if (depthBlob) {
                    formData.append('depth_map', depthBlob, `depth_${capture.room_name || '3d'}.png`);
                }
            } catch (depthErr) {
                console.warn('Gagal generate depth map untuk batch:', depthErr);
            }

            const res = await fetch(`/projects/${props.project.id}/captures/${capture.id}/generate-ai`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: formData,
            });

            const data = await res.json();
            if (res.ok && data.data) {
                const idx = props.captures.findIndex(c => c.id === capture.id);
                if (idx !== -1) {
                    props.captures[idx] = data.data;
                }
                successCount++;
            } else {
                failCount++;
            }
        } catch (err) {
            console.error(`Batch render gagal untuk ${capture.room_name}:`, err);
            failCount++;
        }
    }

    isGeneratingAiRender.value = null;
    isBatchRendering.value = false;

    if (abortBatchFlag.value) {
        alert(`Proses render dihentikan. (${successCount} ruangan berhasil dirender)`);
    } else if (successCount > 0) {
        alert(`Selesai! Berhasil merender ${successCount} lembar ruangan secara otomatis.`);
    } else if (failCount > 0) {
        alert('Terjadi kendala saat merender beberapa lembar ruangan.');
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

// State dan Handler Scroll Horizontal Satu Baris Toolbar pada layar menengah (md - xl)
const toolsScrollRef = ref<HTMLDivElement | null>(null);
const canScrollRight = ref(false);
const canScrollLeft = ref(false);

function updateToolsScrollIndicators() {
    if (!toolsScrollRef.value) return;
    const { scrollLeft, scrollWidth, clientWidth } = toolsScrollRef.value;
    canScrollLeft.value = scrollLeft > 8;
    canScrollRight.value = scrollLeft + clientWidth < scrollWidth - 8;
}

function scrollTools(direction: 'left' | 'right') {
    if (!toolsScrollRef.value) return;
    const offset = direction === 'right' ? 240 : -240;
    toolsScrollRef.value.scrollBy({ left: offset, behavior: 'smooth' });
}

onMounted(() => {
    // Beri sedikit delay untuk kalkulasi layout awal
    setTimeout(() => {
        updateToolsScrollIndicators();
    }, 100);
    window.addEventListener('resize', updateToolsScrollIndicators);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateToolsScrollIndicators);
});
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

    <!-- Hidden input file untuk Update Gambar 3D Snapshot -->
    <input
        ref="captureFileInputRef"
        type="file"
        accept="image/png,image/jpeg,image/webp"
        class="hidden"
        @change="handleCaptureFileSelected"
    />

    <!-- Hidden input file untuk Gambar 2D Denah Master (Halaman 2) -->
    <input
        ref="sheetFileInputRef"
        type="file"
        accept="image/png,image/jpeg,image/webp"
        class="hidden"
        @change="handleSheetFileSelected"
    />

    <!-- Hidden input file untuk Denah 2D Ruangan (Per Lembar) -->
    <input
        ref="room2dFileInputRef"
        type="file"
        accept="image/png,image/jpeg,image/webp"
        class="hidden"
        @change="handleRoom2dFileSelected"
    />

    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans select-none print:bg-white print:text-black">
        
        <!-- TOP TOOLBAR CONTROLS (Hidden saat print) -->
        <header class="no-print sticky top-0 z-40 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-3 sm:px-4 py-2.5 sm:py-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <Link
                    :href="`/projects/${project.id}/viewer`"
                    class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1.5 text-xs font-medium shrink-0"
                    title="Kembali ke 3D Viewer"
                >
                    <ArrowLeft class="w-4 h-4" />
                    <span class="hidden sm:inline">Kembali ke 3D</span>
                </Link>
                <div class="h-5 w-px bg-slate-700 hidden sm:block"></div>
                <div class="min-w-0">
                    <h1 class="text-xs sm:text-sm font-bold text-white flex items-center gap-1.5 sm:gap-2 truncate">
                        <Layers class="w-4 h-4 text-orange-500 shrink-0" />
                        <span class="truncate">Architectural Working Booklet</span>
                    </h1>
                    <p class="text-[10px] sm:text-[11px] text-slate-400 truncate hidden md:block">
                        {{ project.title }} • {{ architect.company_name }}
                    </p>
                </div>
            </div>

            <!-- Action Controls (1 Baris Scrollable pada layar md ke atas dengan indikator petunjuk kanan) -->
            <div class="hidden md:flex flex-1 min-w-0 relative items-center justify-end pl-2">
                <div class="relative w-full flex items-center justify-end overflow-hidden">
                    <!-- Scrollable Track: 1 Baris Penuh (no-wrap) -->
                    <div
                        ref="toolsScrollRef"
                        @scroll="updateToolsScrollIndicators"
                        class="flex items-center gap-2.5 overflow-x-auto scrollbar-none scroll-smooth py-1 px-1 flex-nowrap"
                    >
                        <!-- Pilih Gambar Cover -->
                        <div class="flex items-center gap-1.5 bg-slate-800 border border-slate-700 rounded-lg px-2 py-1 text-xs shrink-0">
                            <span class="text-slate-400 font-medium shrink-0">Cover:</span>
                            <select
                                v-model="selectedCoverCaptureId"
                                class="bg-transparent text-white text-xs font-semibold focus:outline-none cursor-pointer max-w-[140px] truncate"
                                title="Pilih gambar yang dijadikan cover booklet"
                            >
                                <option value="auto" class="bg-neutral-900 text-white">Otomatis (Fasad / Eksterior)</option>
                                <option
                                    v-for="cap in captures"
                                    :key="cap.id"
                                    :value="cap.id"
                                    class="bg-neutral-900 text-white"
                                >
                                    {{ cap.code ? `[${cap.code}] ` : '' }}{{ cap.room_name }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter Visual Ruangan: Khusus Lembar Ini vs Semua Proyek -->
                        <div v-if="hasSheetCaptures && captures.length > 0" class="flex items-center bg-slate-800 border border-slate-700 rounded-lg p-0.5 text-xs shrink-0">
                            <button
                                @click="filterMode = 'sheet'"
                                class="px-2.5 py-1 rounded transition"
                                :class="filterMode === 'sheet' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                title="Tampilkan hanya visual 3D yang ditautkan ke lembar kerja ini"
                            >
                                Khusus Lembar Ini
                            </button>
                            <button
                                @click="filterMode = 'all'"
                                class="px-2.5 py-1 rounded transition"
                                :class="filterMode === 'all' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                title="Tampilkan semua visual 3D dari proyek ini"
                            >
                                Semua Proyek ({{ captures.length }})
                            </button>
                        </div>

                        <!-- Selector Denah Acuan jika ada lebih dari 1 denah -->
                        <div v-if="planSheets && planSheets.length > 1" class="flex items-center gap-1.5 px-2 py-1 bg-slate-800/80 border border-slate-700 rounded-lg text-xs shrink-0">
                            <span class="text-slate-400 font-medium shrink-0">Lembar:</span>
                            <select
                                :value="currentSelectedSheetId"
                                @change="handlePlanSheetChange"
                                class="bg-transparent text-amber-300 text-xs font-semibold focus:outline-none cursor-pointer max-w-[150px] truncate"
                                title="Pilih lembar kerja yang ditampilkan"
                            >
                                <option
                                    v-for="s in planSheets"
                                    :key="s.id"
                                    :value="s.id"
                                    class="bg-neutral-900 text-white"
                                >
                                    {{ s.title }} ({{ s.scale || '1:100' }})
                                </option>
                            </select>
                        </div>

                        <!-- Edit Kop Gambar Dialog Trigger -->
                        <button
                            @click="isEditingKop = true"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-medium transition flex items-center gap-1.5 shrink-0"
                            title="Ubah info Kop Gambar (Client, Tanggal, Drafter)"
                        >
                            <FileText class="w-3.5 h-3.5 text-orange-400" />
                            <span>Kop Info</span>
                        </button>

                        <!-- Toggle AI Render vs 3D Asli -->
                        <button
                            @click="preferAiRender = !preferAiRender"
                            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition flex items-center gap-1.5 shrink-0"
                            :class="preferAiRender
                                ? 'bg-orange-500/15 border-orange-500/40 text-orange-400'
                                : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-slate-200'"
                            title="Gunakan gambar render AI eksternal jika tersedia"
                        >
                            <Sparkles class="w-3.5 h-3.5" :class="preferAiRender ? 'text-orange-400 animate-spin-slow' : ''" />
                            <span>{{ preferAiRender ? 'Prioritas: Render AI' : 'Prioritas: 3D Asli' }}</span>
                        </button>

                        <!-- Tombol 1-Klik Render AI Semua Section -->
                        <button
                            v-if="isOwner && captures.length > 0"
                            @click="handleBatchGenerateAi"
                            :disabled="isBatchRendering || Boolean(isGeneratingAiRender)"
                            class="px-3.5 py-1.5 rounded-lg border text-xs font-bold transition flex items-center gap-1.5 shrink-0"
                            :class="isBatchRendering
                                ? 'bg-purple-600 text-white border-purple-500 animate-pulse'
                                : 'bg-gradient-to-r from-purple-900/60 to-indigo-900/60 border-purple-700/60 text-purple-200 hover:text-white hover:border-purple-500 hover:from-purple-800/80 hover:to-indigo-800/80'"
                            title="Render visual AI untuk seluruh lembar ruangan secara otomatis"
                        >
                            <Sparkles class="w-3.5 h-3.5 text-purple-300" :class="isBatchRendering ? 'animate-spin' : ''" />
                            <span>{{ isBatchRendering ? `Merender (${batchProgress.current}/${batchProgress.total})...` : '✨ Render AI Semua Lembar' }}</span>
                        </button>

                        <!-- Ukuran Kertas Selector -->
                        <div class="flex items-center bg-slate-800 border border-slate-700 rounded-lg p-0.5 text-xs font-mono shrink-0">
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
                            class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-sm transition flex items-center gap-1.5 disabled:opacity-50 shrink-0"
                        >
                            <Calculator class="w-3.5 h-3.5" />
                            <span>{{ isGeneratingRab ? 'Menghitung...' : '🧮 Generate Draf RAB' }}</span>
                        </button>

                        <!-- Tombol 1-Klik Export / Print PDF -->
                        <button
                            @click="handlePrintPdf"
                            class="px-4 py-1.5 rounded-lg bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-bold shadow-md shadow-orange-950/40 transition flex items-center gap-2 shrink-0"
                        >
                            <Printer class="w-4 h-4" />
                            <span>Cetak / Download PDF</span>
                        </button>
                    </div>

                    <!-- Indikator Geser Kiri (jika konten di-scroll ke kanan) -->
                    <div
                        v-if="canScrollLeft"
                        class="absolute left-0 top-0 bottom-0 flex items-center pl-1 z-10 pointer-events-none"
                    >
                        <div class="absolute inset-0 bg-gradient-to-r from-slate-900 via-slate-900/90 to-transparent w-14 pointer-events-none"></div>
                        <button
                            @click="scrollTools('left')"
                            class="relative pointer-events-auto p-1.5 rounded-full bg-slate-800/95 hover:bg-slate-700 text-slate-200 border border-slate-700 shadow-md transition shrink-0"
                            title="Geser ke kiri"
                        >
                            <ChevronLeft class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Indikator Geser Kanan (Visual Cue: Ada tools lain di kanan) -->
                    <div
                        v-if="canScrollRight"
                        class="absolute right-0 top-0 bottom-0 flex items-center pr-1 z-10 pointer-events-none"
                    >
                        <!-- Gradient Fade -->
                        <div class="absolute inset-0 bg-gradient-to-l from-slate-900 via-slate-900/95 to-transparent w-28 pointer-events-none"></div>

                        <!-- Tombol Petunjuk "Tools Lain ❯" -->
                        <button
                            @click="scrollTools('right')"
                            class="relative pointer-events-auto flex items-center gap-1 px-2.5 py-1 rounded-full bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-[11px] font-bold shadow-lg shadow-orange-950/60 border border-orange-400/40 transition shrink-0 animate-pulse"
                            title="Ada tools lain di sebelah kanan, klik atau geser untuk melihat"
                        >
                            <span>Tools Lain</span>
                            <ChevronRight class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Only Controls (< md) -->
            <div class="flex md:hidden items-center gap-2 shrink-0">
                <!-- Quick Print Button di mobile -->
                <button
                    @click="handlePrintPdf"
                    class="p-2 sm:px-3 sm:py-1.5 rounded-lg bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-bold shadow-md shadow-orange-950/40 transition flex items-center gap-1.5"
                    title="Cetak / Download PDF"
                >
                    <Printer class="w-4 h-4" />
                    <span class="hidden sm:inline">Cetak PDF</span>
                </button>

                <!-- Toggle Hamburger / Pengaturan Menu -->
                <button
                    @click="isMobileMenuOpen = true"
                    class="p-2 sm:px-2.5 sm:py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 transition flex items-center gap-1.5 text-xs font-medium"
                    aria-label="Buka Menu Booklet"
                >
                    <SlidersHorizontal class="w-4 h-4 text-orange-400" />
                    <span class="hidden sm:inline text-xs font-medium">Menu</span>
                </button>
            </div>
        </header>

        <!-- MOBILE SLIDE-OVER DRAWER MENU (Khusus layar < md, hidden saat print) -->
        <div
            v-if="isMobileMenuOpen"
            class="no-print fixed inset-0 z-50 md:hidden flex justify-end"
            role="dialog"
            aria-modal="true"
        >
            <!-- Backdrop Overlay -->
            <div
                class="fixed inset-0 bg-black/60 backdrop-blur-sm transition-opacity"
                @click="isMobileMenuOpen = false"
            ></div>

            <!-- Drawer Container -->
            <div class="relative w-full max-w-sm bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col h-full z-10">
                <!-- Drawer Header -->
                <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/95 sticky top-0">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg bg-orange-500/10 text-orange-400 border border-orange-500/20">
                            <SlidersHorizontal class="w-4 h-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white">Menu & Pengaturan</h2>
                            <p class="text-[11px] text-slate-400 truncate max-w-[190px]">{{ project.title }}</p>
                        </div>
                    </div>
                    <button
                        @click="isMobileMenuOpen = false"
                        class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                        title="Tutup Menu"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Drawer Body (Scrollable) -->
                <div class="flex-1 overflow-y-auto p-4 space-y-5 text-xs">
                    <!-- Aksi Dokumen Utama -->
                    <div class="space-y-2.5">
                        <span class="text-[10px] font-bold tracking-wider text-slate-400 uppercase block">Aksi Dokumen</span>
                        
                        <button
                            @click="handlePrintPdf(); isMobileMenuOpen = false"
                            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-bold shadow-lg shadow-orange-950/40 transition flex items-center justify-center gap-2"
                        >
                            <Printer class="w-4 h-4" />
                            <span>Cetak / Download PDF</span>
                        </button>

                        <button
                            @click="handleGenerateRabFromWorksheet(); isMobileMenuOpen = false"
                            :disabled="isGeneratingRab"
                            class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold shadow-sm transition flex items-center justify-center gap-2 disabled:opacity-50"
                        >
                            <Calculator class="w-4 h-4" />
                            <span>{{ isGeneratingRab ? 'Menghitung...' : '🧮 Generate Draf RAB' }}</span>
                        </button>

                        <button
                            v-if="isOwner && captures.length > 0"
                            @click="handleBatchGenerateAi(); isMobileMenuOpen = false"
                            :disabled="isBatchRendering || Boolean(isGeneratingAiRender)"
                            class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold shadow-lg shadow-purple-950/40 transition flex items-center justify-center gap-2 text-xs disabled:opacity-50"
                        >
                            <Sparkles class="w-4 h-4" :class="isBatchRendering ? 'animate-spin' : ''" />
                            <span>{{ isBatchRendering ? `Sedang Render (${batchProgress.current}/${batchProgress.total})...` : '✨ Render AI Semua Lembar' }}</span>
                        </button>
                    </div>

                    <!-- Format Lembar & Kertas -->
                    <div class="border-t border-slate-800 pt-4 space-y-3.5">
                        <span class="text-[10px] font-bold tracking-wider text-slate-400 uppercase block">Format Lembar & Tampilan</span>

                        <!-- Selector Denah Acuan jika > 1 -->
                        <div v-if="planSheets && planSheets.length > 1" class="space-y-1.5">
                            <label class="text-slate-300 font-medium block">Pilih Lembar Acuan:</label>
                            <select
                                :value="currentSelectedSheetId"
                                @change="handlePlanSheetChange"
                                class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-amber-300 font-semibold focus:outline-none"
                            >
                                <option
                                    v-for="s in planSheets"
                                    :key="s.id"
                                    :value="s.id"
                                    class="bg-neutral-900 text-white"
                                >
                                    {{ s.title }} ({{ s.scale || '1:100' }})
                                </option>
                            </select>
                        </div>

                        <!-- Ukuran Kertas -->
                        <div class="space-y-1.5">
                            <label class="text-slate-300 font-medium block">Ukuran Kertas:</label>
                            <div class="grid grid-cols-2 gap-1.5 bg-slate-800 p-1 border border-slate-700 rounded-lg">
                                <button
                                    @click="paperSize = 'A3'"
                                    class="py-1.5 text-center rounded transition font-mono"
                                    :class="paperSize === 'A3' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                >
                                    A3 Landscape
                                </button>
                                <button
                                    @click="paperSize = 'A4'"
                                    class="py-1.5 text-center rounded transition font-mono"
                                    :class="paperSize === 'A4' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                >
                                    A4 Landscape
                                </button>
                            </div>
                        </div>

                        <!-- Prioritas AI vs 3D -->
                        <div class="space-y-1.5">
                            <label class="text-slate-300 font-medium block">Prioritas Visual Render:</label>
                            <button
                                @click="preferAiRender = !preferAiRender"
                                class="w-full py-2 px-3 rounded-lg border font-medium transition flex items-center justify-between"
                                :class="preferAiRender
                                    ? 'bg-orange-500/15 border-orange-500/40 text-orange-400'
                                    : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-slate-200'"
                            >
                                <span class="flex items-center gap-2">
                                    <Sparkles class="w-4 h-4" />
                                    <span>{{ preferAiRender ? 'Prioritas: Render AI' : 'Prioritas: 3D Asli' }}</span>
                                </span>
                                <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700">Ganti</span>
                            </button>
                        </div>
                    </div>

                    <!-- Gambar Cover & Filter Visual -->
                    <div class="border-t border-slate-800 pt-4 space-y-3.5">
                        <span class="text-[10px] font-bold tracking-wider text-slate-400 uppercase block">Konten Visual & Cover</span>

                        <!-- Pilih Gambar Cover -->
                        <div class="space-y-1.5">
                            <label class="text-slate-300 font-medium block">Gambar Cover Depan:</label>
                            <select
                                v-model="selectedCoverCaptureId"
                                class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white font-semibold focus:outline-none"
                            >
                                <option value="auto" class="bg-neutral-900 text-white">Otomatis (Fasad / Eksterior)</option>
                                <option
                                    v-for="cap in captures"
                                    :key="cap.id"
                                    :value="cap.id"
                                    class="bg-neutral-900 text-white"
                                >
                                    {{ cap.code ? `[${cap.code}] ` : '' }}{{ cap.room_name }}
                                </option>
                            </select>
                        </div>

                        <!-- Filter Ruangan: Khusus Lembar Ini vs Semua Proyek -->
                        <div v-if="hasSheetCaptures && captures.length > 0" class="space-y-1.5">
                            <label class="text-slate-300 font-medium block">Visual Ruangan Tampil:</label>
                            <div class="grid grid-cols-2 gap-1.5 bg-slate-800 p-1 border border-slate-700 rounded-lg">
                                <button
                                    @click="filterMode = 'sheet'"
                                    class="py-1.5 text-center rounded transition"
                                    :class="filterMode === 'sheet' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                >
                                    Khusus Lembar Ini
                                </button>
                                <button
                                    @click="filterMode = 'all'"
                                    class="py-1.5 text-center rounded transition"
                                    :class="filterMode === 'all' ? 'bg-orange-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                >
                                    Semua ({{ captures.length }})
                                </button>
                            </div>
                        </div>

                        <!-- Edit Kop Gambar Trigger -->
                        <div class="pt-1">
                            <button
                                @click="isEditingKop = true; isMobileMenuOpen = false"
                                class="w-full py-2.5 px-3 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 transition flex items-center justify-between"
                            >
                                <span class="flex items-center gap-2">
                                    <FileText class="w-4 h-4 text-orange-400" />
                                    <span>Kop Info (Client & Proyek)</span>
                                </span>
                                <span class="text-slate-400 text-xs font-mono">Ubah →</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer -->
                <div class="p-3 border-t border-slate-800 bg-slate-900/95 text-center">
                    <button
                        @click="isMobileMenuOpen = false"
                        class="w-full py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium transition"
                    >
                        Tutup Menu
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Edit Kop Gambar -->
        <div v-if="isEditingKop" class="no-print fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md shadow-2xl flex flex-col max-h-[92vh] sm:max-h-[90vh] overflow-hidden my-auto animate-in fade-in zoom-in-95 duration-200">
                <!-- Header Pinned -->
                <div class="shrink-0 flex items-center justify-between p-4 sm:p-5 border-b border-slate-800 bg-slate-900">
                    <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                        <FileText class="w-4 h-4 text-orange-500" />
                        Kustomisasi Kop Gambar (Title Block)
                    </h3>
                    <button @click="isEditingKop = false" class="text-slate-400 hover:text-white p-1 rounded-lg transition">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <!-- Body Scrollable -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3 text-xs overscroll-contain">
                    <div>
                        <label class="block text-slate-400 font-mono mb-1">CLIENT NAME</label>
                        <input v-model="clientName" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500" />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-mono mb-1">CLIENT ADDRESS / LOCATION</label>
                        <input v-model="clientAddress" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500" />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-mono mb-1">DATE</label>
                        <input v-model="documentDate" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500" />
                    </div>
                    <div>
                        <label class="block text-slate-400 font-mono mb-1">DRAWN BY</label>
                        <input v-model="drawnBy" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500" />
                    </div>
                </div>
                <!-- Footer Pinned -->
                <div class="shrink-0 p-3 sm:p-4 flex items-center justify-between border-t border-slate-800 bg-slate-900/95 text-xs">
                    <span class="text-[11px] text-slate-400 hidden sm:inline">Data akan tersimpan permanen pada proyek</span>
                    <div class="flex items-center gap-2 ml-auto">
                        <button @click="isEditingKop = false" class="px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white text-xs">
                            Batal
                        </button>
                        <button
                            @click="saveKopInfo"
                            :disabled="isSavingKop"
                            class="px-4 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-lg font-bold text-xs flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <span>{{ isSavingKop ? 'Menyimpan...' : 'Simpan ke Proyek' }}</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Pop-up Generate AI Visual Ruangan via Gemini Imagen -->
        <div v-if="isAiModalOpen && activeGenerateCapture" class="no-print fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[92vh] sm:max-h-[88vh] overflow-hidden my-auto animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header (Sticky / Pinned Top) -->
                <div class="shrink-0 flex items-center justify-between p-4 sm:p-5 border-b border-slate-800 bg-slate-900">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-orange-500/15 text-orange-400 border border-orange-500/30">
                            <Sparkles class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-white flex items-center gap-2">
                                Generate Render AI
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                {{ activeGenerateCapture.room_name || 'Ruangan' }} • Snapshot 3D ke Render Fotorealistik
                            </p>
                        </div>
                    </div>
                    <button
                        @click="isAiModalOpen = false"
                        :disabled="Boolean(isGeneratingAiRender)"
                        class="text-slate-400 hover:text-white p-1 rounded-lg transition disabled:opacity-30"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body (Scrollable Content) -->
                <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4 text-xs overscroll-contain">
                    <!-- Dual Preview: Gambar Referensi 3D & Depth Map (ControlNet) -->
                    <div class="space-y-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <!-- 1. Acuan Geometri Snapshot 3D (RGB) -->
                            <div class="relative rounded-xl overflow-hidden bg-slate-950 border border-slate-800 h-28 sm:h-32 flex items-center justify-center">
                                <img
                                    :src="activeGenerateCapture.url"
                                    :alt="activeGenerateCapture.room_name"
                                    class="w-full h-full object-cover"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-transparent"></div>
                                <div class="absolute bottom-1.5 left-2 right-2 flex items-center justify-between text-[10px]">
                                    <span class="px-2 py-0.5 rounded bg-black/80 text-white font-mono backdrop-blur border border-white/10">
                                        🎨 1. Gambar Warna (RGB)
                                    </span>
                                </div>
                            </div>

                            <!-- 2. Peta Kedalaman (Depth Map ControlNet) -->
                            <div class="relative rounded-xl overflow-hidden bg-slate-950 border border-slate-800 h-28 sm:h-32 flex items-center justify-center">
                                <img
                                    v-if="depthMapPreviewUrl"
                                    :src="depthMapPreviewUrl"
                                    alt="Depth Map ControlNet"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="flex flex-col items-center justify-center text-slate-500 gap-1 text-[10px]">
                                    <Layers class="w-5 h-5 text-amber-400" :class="isGeneratingDepthPreview ? 'animate-pulse' : ''" />
                                    <span>{{ isGeneratingDepthPreview ? 'Membuat Depth Map...' : 'Depth Map Otomatis' }}</span>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-transparent to-transparent pointer-events-none"></div>
                                <div class="absolute bottom-1.5 left-2 right-2 flex items-center justify-between text-[10px] pointer-events-none">
                                    <span class="px-2 py-0.5 rounded bg-black/80 text-amber-300 font-mono backdrop-blur border border-amber-500/30">
                                        🗺️ 2. Depth Map (ControlNet)
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Shortcut Aksi Gemini / Download Referensi & Depth Map -->
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2 bg-slate-950/60 border border-slate-800 rounded-xl text-[11px]">
                            <span class="text-slate-400 text-[10px]">
                                💡 RGB + Depth Map otomatis dikirim ke AI agar geometri 100% presisi
                            </span>
                            <div class="flex items-center gap-1.5 ml-auto">
                                <button
                                    type="button"
                                    @click="downloadReferenceImage"
                                    class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1 font-medium text-[11px]"
                                    title="Download gambar 3D (RGB) ini ke laptop"
                                >
                                    <Download class="w-3 h-3 text-rose-400" />
                                    <span>Unduh RGB</span>
                                </button>
                                <button
                                    type="button"
                                    :disabled="!depthMapPreviewUrl"
                                    @click="downloadDepthMapFromModal"
                                    class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 hover:text-white transition flex items-center gap-1 font-medium text-[11px] disabled:opacity-40"
                                    title="Download file Depth Map hitam-putih untuk ControlNet"
                                >
                                    <Layers class="w-3 h-3 text-amber-400" />
                                    <span>Unduh Depth</span>
                                </button>
                                <button
                                    type="button"
                                    @click="copyAiPrompt"
                                    class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition flex items-center gap-1 font-medium text-[11px]"
                                    title="Salin teks prompt arsitektural"
                                >
                                    <Copy class="w-3 h-3 text-orange-400" />
                                    <span>{{ isPromptCopied ? 'Tersalin!' : 'Salin Prompt' }}</span>
                                </button>
                                <a
                                    href="https://gemini.google.com/app"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="px-2 py-1 rounded-lg bg-blue-600/20 hover:bg-blue-600/30 text-blue-300 border border-blue-500/30 transition flex items-center gap-1 font-medium text-[11px]"
                                    title="Buka web Google Gemini"
                                >
                                    <span>Buka Gemini</span>
                                    <ExternalLink class="w-3 h-3" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Pilihan Mood Pencahayaan Cepat -->
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider">
                            1. Pilih Suasana / Pencahayaan (Mood):
                        </label>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <button
                                v-for="preset in moodPresets"
                                :key="preset.id"
                                type="button"
                                @click="applyMoodPreset(preset.id)"
                                class="p-2.5 rounded-xl border text-left transition flex flex-col justify-between"
                                :class="selectedMood === preset.id
                                    ? 'bg-orange-500/15 border-orange-500 text-white shadow-sm'
                                    : 'bg-slate-800/80 border-slate-700/80 text-slate-300 hover:border-slate-600 hover:text-white'"
                            >
                                <span class="font-bold text-xs">{{ preset.label }}</span>
                                <span class="text-[10px] text-slate-400 mt-0.5 line-clamp-1">{{ preset.desc }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Input / Edit Prompt AI -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="block text-[11px] font-bold text-slate-300 uppercase tracking-wider">
                                2. Prompt Arsitektural:
                            </label>
                            <span class="text-[10px] text-slate-400 font-mono">Bisa Diedit</span>
                        </div>
                        <textarea
                            v-model="aiPromptText"
                            rows="3"
                            class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-orange-500 transition resize-none leading-relaxed"
                            placeholder="Deskripsi prompt visual arsitektur..."
                        ></textarea>
                    </div>

                    <!-- Error Alert jika ada -->
                    <div v-if="aiGenerationError" class="p-3 rounded-xl bg-red-950/70 border border-red-800 text-red-200 text-xs flex items-start gap-2">
                        <span class="shrink-0 font-bold">⚠️</span>
                        <div class="flex-1 text-[11px]">{{ aiGenerationError }}</div>
                    </div>
                </div>

                <!-- Modal Footer (Sticky / Pinned Bottom) -->
                <div class="shrink-0 p-3 sm:p-4 flex items-center justify-between border-t border-slate-800 bg-slate-900/95 text-xs">
                    <span class="text-[11px] text-slate-400 flex items-center gap-1 font-mono">
                        <span class="hidden sm:inline">AI Render Engine (Gemini / Flux)</span>
                        <span class="sm:hidden">AI Render</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="isAiModalOpen = false"
                            :disabled="Boolean(isGeneratingAiRender)"
                            class="px-3 py-2 rounded-xl border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800 transition disabled:opacity-50"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="handleExecuteAiGenerate"
                            :disabled="Boolean(isGeneratingAiRender)"
                            class="px-4 py-2 bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white rounded-xl font-bold transition flex items-center gap-2 shadow-lg shadow-orange-950/50 disabled:opacity-50"
                        >
                            <Sparkles class="w-4 h-4" :class="isGeneratingAiRender ? 'animate-spin' : ''" />
                            <span>{{ isGeneratingAiRender ? 'Sedang Me-render...' : '🚀 Mulai Render AI' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Floating Batch Progress Indicator (Hidden saat print) -->
        <div
            v-if="isBatchRendering"
            class="no-print fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900/95 border border-purple-500/50 shadow-2xl rounded-2xl px-5 py-3 flex items-center gap-4 text-xs backdrop-blur-md animate-in slide-in-from-bottom-5"
        >
            <Sparkles class="w-5 h-5 text-purple-400 animate-spin shrink-0" />
            <div>
                <p class="font-bold text-white flex items-center gap-2">
                    <span>Sedang Me-render AI Ruangan ({{ batchProgress.current }} / {{ batchProgress.total }})</span>
                    <span class="text-purple-300 font-normal">({{ Math.round((batchProgress.current / batchProgress.total) * 100) }}%)</span>
                </p>
                <p class="text-[11px] text-slate-400 mt-0.5 truncate max-w-[200px] sm:max-w-xs">{{ batchProgress.currentName }}</p>
            </div>
            <button
                @click="abortBatchFlag = true"
                class="ml-2 px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-red-900/40 border border-slate-700 hover:border-red-600 text-slate-300 hover:text-red-200 transition text-[11px] font-medium"
            >
                Hentikan
            </button>
        </div>

        <!-- BOOKLET PAGES CONTAINER (Printable Area) -->
        <main class="flex-1 w-full flex flex-col items-center py-6 px-4 sm:px-6 space-y-10 print:p-0 print:m-0 print:space-y-0">
            
            <!-- ======================================================== -->
            <!-- HALAMAN 1: COVER PAGE                                     -->
            <!-- ======================================================== -->
            <section
                class="booklet-sheet cover-sheet relative bg-slate-950 shadow-2xl overflow-hidden flex flex-col justify-between print:bg-slate-950"
                :class="paperSize === 'A3' ? 'sheet-a3' : 'sheet-a4'"
            >
                <!-- Full Cover Background Visual (Jika Ada) -->
                <template v-if="coverImage">
                    <img
                        :src="coverImage"
                        alt="Cover Visual"
                        class="cover-bg-image absolute inset-0 w-full h-full object-cover opacity-90 print:opacity-100"
                    />
                    <div class="cover-gradient absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-black/40"></div>
                </template>
                <!-- Cover Minimalis Arsitektural Mewah (Jika Belum Ada Visual Cover) -->
                <template v-else>
                    <div class="cover-minimalist absolute inset-0 bg-gradient-to-br from-slate-950 via-[#0b101d] to-slate-900 flex flex-col justify-between">
                        <!-- Blueprint grid pattern background -->
                        <div class="absolute inset-0 opacity-15 pointer-events-none" style="background-image: linear-gradient(to right, #475569 1px, transparent 1px), linear-gradient(to bottom, #475569 1px, transparent 1px); background-size: 32px 32px;"></div>
                        <div class="absolute inset-0 bg-radial-at-c from-transparent via-black/40 to-black/80 pointer-events-none"></div>
                    </div>
                </template>

                <!-- Floating Switch Cover di Halaman 1 Preview (Hidden saat print) -->
                <div v-if="captures.length > 0" class="no-print absolute top-6 right-6 z-20 flex items-center gap-2 bg-black/75 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/20 text-xs shadow-lg">
                    <ImageIcon class="w-4 h-4 text-orange-400" />
                    <span class="text-neutral-300 font-medium">Gambar Cover:</span>
                    <select
                        v-model="selectedCoverCaptureId"
                        class="bg-transparent text-white font-semibold focus:outline-none cursor-pointer max-w-[200px] truncate"
                    >
                        <option value="auto" class="bg-neutral-900 text-white">Otomatis (Fasad / Eksterior)</option>
                        <option
                            v-for="cap in captures"
                            :key="cap.id"
                            :value="cap.id"
                            class="bg-neutral-900 text-white"
                        >
                            {{ cap.code ? `[${cap.code}] ` : '' }}{{ cap.room_name }} ({{ cap.captured_hour }})
                        </option>
                    </select>
                </div>

                <!-- Header / Title Atas -->
                <div class="cover-content relative z-10 p-8 sm:p-12 flex justify-between items-start">
                    <div>
                        <span class="inline-block px-3 py-1 rounded bg-orange-600 text-white font-mono text-[11px] font-bold tracking-widest uppercase mb-3 shadow-md">
                            ARCHITECTURAL PRESENTATION • {{ planSheet?.title ? 'WORKING SHEET' : 'FULL PROJECT' }}
                        </span>
                        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight uppercase drop-shadow-md">
                            {{ project.title }}
                        </h1>
                        <p v-if="planSheet?.title" class="text-lg sm:text-2xl font-bold text-amber-400 mt-2 tracking-wide drop-shadow flex items-center gap-2">
                            <span>📄</span>
                            <span>{{ planSheet.title }}</span>
                        </p>
                        <p class="text-sm sm:text-base text-slate-200 mt-1 drop-shadow font-light">
                            {{ clientAddress }}
                        </p>
                    </div>
                </div>

                <!-- Footer Watermark Credit: DESIGN BY [company_name] -->
                <div class="cover-content relative z-10 p-8 sm:p-12 flex flex-col items-center justify-center text-center">
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
                    <!-- Kanvas Denah 2D Mini/Utuh atau Gambar 2D Hasil Upload -->
                    <div class="flex-1 min-h-0 w-full relative flex items-center justify-center rounded overflow-hidden">
                        <!-- Mode Gambar 2D jika diunggah -->
                        <div
                            v-if="planSheet?.image_url && !showVectorCanvasHalaman2"
                            class="w-full h-full relative flex items-center justify-center p-2 bg-slate-50 border border-slate-200 rounded overflow-hidden"
                        >
                            <img
                                :src="planSheet.image_url"
                                :alt="planSheet?.title || 'Denah 2D'"
                                class="max-w-full max-h-full object-contain"
                            />
                        </div>

                        <!-- Mode Kanvas SVG Vektor (Default) -->
                        <KeyPlanCanvas
                            v-else
                            :elements="planSheet?.elements || []"
                            width="100%"
                            height="100%"
                            className="w-full h-full border-none shadow-none bg-transparent"
                        />

                        <!-- Floating Controls untuk Denah 2D (Hidden saat print) -->
                        <div v-if="isOwner" class="no-print absolute top-3 right-3 flex items-center gap-1.5 z-20">
                            <!-- Toggle Gambar vs Vektor jika ada gambar -->
                            <button
                                v-if="planSheet?.image_url"
                                @click="showVectorCanvasHalaman2 = !showVectorCanvasHalaman2"
                                class="px-2.5 py-1 rounded text-[10px] font-bold bg-slate-800 text-slate-200 hover:bg-slate-700 transition flex items-center gap-1 shadow backdrop-blur"
                                title="Beralih antara gambar denah 2D dan kanvas vektor"
                            >
                                <Layers class="w-3 h-3 text-amber-400" />
                                <span>{{ showVectorCanvasHalaman2 ? 'Lihat Gambar 2D' : 'Lihat Vektor' }}</span>
                            </button>

                            <!-- Tombol Update / Unggah Gambar 2D -->
                            <button
                                @click="triggerUploadSheet2d"
                                :disabled="isUploadingSheet2d"
                                class="px-2.5 py-1 rounded text-[10px] font-bold bg-amber-600 hover:bg-amber-500 text-white transition flex items-center gap-1 shadow backdrop-blur disabled:opacity-50"
                                :title="planSheet?.image_url ? 'Ganti gambar denah 2D ini' : 'Unggah file gambar denah 2D (PNG/JPG)'"
                            >
                                <Upload class="w-3 h-3" :class="isUploadingSheet2d ? 'animate-spin' : ''" />
                                <span>{{ planSheet?.image_url ? 'Ganti Gambar 2D' : 'Unggah Gambar 2D' }}</span>
                            </button>

                            <!-- Tombol Hapus Gambar 2D -->
                            <button
                                v-if="planSheet?.image_url"
                                @click="removeSheet2dImage"
                                class="p-1 rounded bg-red-600/80 text-white hover:bg-red-500 transition shadow"
                                title="Hapus gambar denah 2D dan gunakan kanvas vektor"
                            >
                                <X class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Label Nama Halaman Kiri Bawah -->
                    <div class="pt-4 shrink-0">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                            {{ planSheet?.title || 'DENAH' }}
                        </h2>
                        <p class="text-xs text-slate-500 font-mono">
                            {{ (planSheet?.type === 'plan' ? 'MASTER FLOOR PLAN' : (planSheet?.type || 'LEMBAR KERJA')).toUpperCase() }} • SKALA {{ planSheet?.scale || '1:100' }}
                        </p>
                    </div>
                </div>

                <!-- Area Kanan: 20% Kop Gambar (Title Block) Standar Arsitektur -->
                <div class="w-[20%] h-full bg-white flex flex-col justify-between p-4 sm:p-5 border-l border-slate-300 text-[11px] leading-tight">
                    <!-- Logo / Brand Studio Atas -->
                    <div class="border-b border-slate-800 pb-4 text-center">
                        <h3 class="text-xl sm:text-2xl font-black tracking-wider text-slate-900 uppercase font-serif">
                            {{ architect.company_name || 'STUDIO ARSITEK' }}
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
                            <p class="text-[10px] text-slate-800 font-bold uppercase truncate">{{ planSheet?.title || 'Master Layout Plan' }}</p>
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
                        <div class="flex-1 min-h-0 w-full relative rounded overflow-hidden bg-black flex items-center justify-center">
                            <img
                                :src="getCaptureDisplayUrl(ext)"
                                :alt="ext.room_name"
                                class="w-full h-full object-cover"
                            />

                            <!-- Loading Overlay saat sedang di-generate -->
                            <div
                                v-if="isGeneratingAiRender === ext.id"
                                class="no-print absolute inset-0 bg-slate-950/85 backdrop-blur-sm flex flex-col items-center justify-center gap-2.5 text-white p-4 text-center z-20"
                            >
                                <Sparkles class="w-8 h-8 text-orange-400 animate-spin" />
                                <div>
                                    <p class="text-xs font-bold text-orange-300">Sedang Me-render via Gemini AI...</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Memproses pencahayaan dan material arsitektur fotorealistik</p>
                                </div>
                            </div>

                            <!-- Badge Sumber Gambar & Controls (Hidden saat print) -->
                            <div class="no-print absolute top-3 right-3 flex items-center gap-1.5 z-10">
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

                                <!-- Tombol Ganti / Update Gambar 3D Snapshot -->
                                <button
                                    v-if="isOwner"
                                    @click="triggerUpdateCapture(ext.id)"
                                    :disabled="isUploadingCapture === ext.id"
                                    class="px-2.5 py-1 rounded text-[10px] font-bold bg-slate-900/90 text-white hover:bg-slate-800 transition flex items-center gap-1 shadow backdrop-blur border border-white/20 disabled:opacity-50"
                                    title="Ganti gambar foto/snapshot 3D ini dengan file baru"
                                >
                                    <RefreshCw class="w-3 h-3 text-amber-400" :class="isUploadingCapture === ext.id ? 'animate-spin' : ''" />
                                    <span>Update Gambar</span>
                                </button>

                                <!-- Tombol 1-Click Generate AI (Gemini) -->
                                <button
                                    v-if="isOwner"
                                    @click="openAiGenerateModal(ext)"
                                    :disabled="isGeneratingAiRender === ext.id"
                                    class="px-2.5 py-1 rounded text-[10px] font-bold bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white transition flex items-center gap-1 shadow backdrop-blur disabled:opacity-50"
                                    title="Generate Render AI fotorealistik menggunakan Gemini"
                                >
                                    <Sparkles class="w-3 h-3" :class="isGeneratingAiRender === ext.id ? 'animate-spin' : ''" />
                                    <span>{{ ext.ai_rendered_url ? 'Regenerate AI' : '✨ Generate AI' }}</span>
                                </button>

                                <button
                                    v-if="isOwner"
                                    @click="triggerUploadAi(ext.id)"
                                    class="px-2 py-1 rounded text-[10px] font-bold bg-slate-800 text-white hover:bg-slate-700 transition flex items-center gap-1 shadow"
                                    title="Unggah file gambar render AI manual"
                                >
                                    <Upload class="w-3 h-3" />
                                    <span>Upload AI</span>
                                </button>

                                <button
                                    v-if="isOwner && ext.ai_rendered_url"
                                    @click="removeAiRender(ext.id)"
                                    class="p-1 rounded bg-red-600/80 text-white hover:bg-red-500 transition shadow"
                                    title="Hapus render AI dan gunakan 3D asli"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Judul Bawah -->
                        <div class="pt-4 shrink-0">
                            <div class="flex items-center gap-2">
                                <span v-if="ext.code" class="px-2 py-0.5 rounded bg-orange-600 text-white font-mono text-xs font-bold shadow-sm">
                                    {{ ext.code }}
                                </span>
                                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                                    {{ ext.room_name || 'TAMPAK DEPAN' }}
                                </h2>
                            </div>
                            <p class="text-xs text-slate-500 font-mono mt-0.5">
                                PERSPEKTIF EKSTERIOR • JAM {{ ext.captured_hour }}
                            </p>
                        </div>
                    </div>

                    <!-- Area Kanan: 20% Kop Gambar -->
                    <div class="w-[20%] h-full bg-white flex flex-col justify-between p-4 sm:p-5 border-l border-slate-300 text-[11px] leading-tight">
                        <div class="border-b border-slate-800 pb-4 text-center">
                            <h3 class="text-xl sm:text-2xl font-black tracking-wider text-slate-900 uppercase font-serif">
                                {{ architect.company_name || 'STUDIO ARSITEK' }}
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

                            <!-- Loading Overlay saat sedang di-generate -->
                            <div
                                v-if="isGeneratingAiRender === room.id"
                                class="no-print absolute inset-0 bg-slate-950/85 backdrop-blur-sm flex flex-col items-center justify-center gap-2.5 text-white p-4 text-center z-20"
                            >
                                <Sparkles class="w-8 h-8 text-orange-400 animate-spin" />
                                <div>
                                    <p class="text-xs font-bold text-orange-300">Sedang Me-render via Gemini AI...</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Memproses pencahayaan dan material arsitektur fotorealistik</p>
                                </div>
                            </div>

                            <!-- Controls AI & Upload (Hidden saat print) -->
                            <div class="no-print absolute top-3 right-3 flex items-center gap-1.5 z-10">
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

                                <!-- Tombol Ganti / Update Gambar 3D Snapshot -->
                                <button
                                    v-if="isOwner"
                                    @click="triggerUpdateCapture(room.id)"
                                    :disabled="isUploadingCapture === room.id"
                                    class="px-2.5 py-1 rounded text-[10px] font-bold bg-slate-900/90 text-white hover:bg-slate-800 transition flex items-center gap-1 shadow backdrop-blur border border-white/20 disabled:opacity-50"
                                    title="Ganti gambar foto/snapshot 3D ruangan ini dengan file baru"
                                >
                                    <RefreshCw class="w-3 h-3 text-amber-400" :class="isUploadingCapture === room.id ? 'animate-spin' : ''" />
                                    <span>Update Gambar</span>
                                </button>

                                <!-- Tombol 1-Click Generate AI (Gemini) -->
                                <button
                                    v-if="isOwner"
                                    @click="openAiGenerateModal(room)"
                                    :disabled="isGeneratingAiRender === room.id"
                                    class="px-2.5 py-1 rounded text-[10px] font-bold bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white transition flex items-center gap-1 shadow backdrop-blur disabled:opacity-50"
                                    title="Generate Render AI fotorealistik menggunakan Gemini"
                                >
                                    <Sparkles class="w-3 h-3" :class="isGeneratingAiRender === room.id ? 'animate-spin' : ''" />
                                    <span>{{ room.ai_rendered_url ? 'Regenerate AI' : '✨ Generate AI' }}</span>
                                </button>

                                <button
                                    v-if="isOwner"
                                    @click="triggerUploadAi(room.id)"
                                    class="px-2 py-1 rounded text-[10px] font-bold bg-slate-800/90 text-white hover:bg-slate-700 transition flex items-center gap-1 shadow backdrop-blur"
                                    title="Upload gambar render AI manual untuk ruangan ini"
                                >
                                    <Upload class="w-3 h-3" />
                                    <span>Upload AI</span>
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
                                <div class="flex items-center gap-2">
                                    <span v-if="room.code" class="px-2 py-0.5 rounded bg-orange-600 text-white font-mono text-xs font-bold shadow-sm">
                                        {{ room.code }}
                                    </span>
                                    <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight uppercase">
                                        {{ room.room_name }}
                                    </h2>
                                </div>
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

                            <!-- Tengah/Kanan Bawah: Key Plan (Denah Mini Locator Otomatis atau Gambar 2D Ruangan) -->
                            <div class="w-[280px] sm:w-[320px] h-full flex-shrink-0 relative group">
                                <!-- Mode Gambar 2D Custom Ruangan -->
                                <div
                                    v-if="room.key_plan_image_url"
                                    class="key-plan-container relative bg-slate-50 border border-slate-300 rounded shadow-sm overflow-hidden flex flex-col justify-between w-full h-full"
                                >
                                    <!-- Header Key Plan Badge -->
                                    <div class="px-2 py-1 bg-slate-800 text-white flex items-center justify-between text-[10px] font-mono tracking-wider font-semibold uppercase shrink-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                            KEY PLAN (2D RUANGAN)
                                        </span>
                                        <span class="text-orange-400 truncate max-w-[110px]">{{ room.room_name }}</span>
                                    </div>

                                    <!-- Gambar 2D Ruangan -->
                                    <div class="flex-1 min-h-0 w-full relative flex items-center justify-center p-1.5 bg-white">
                                        <img
                                            :src="room.key_plan_image_url"
                                            :alt="`Denah 2D ${room.room_name}`"
                                            class="max-w-full max-h-full object-contain"
                                        />
                                    </div>

                                    <!-- Footer Key Plan -->
                                    <div class="px-2 py-0.5 bg-slate-100 border-t border-slate-200 flex items-center justify-between text-[9px] text-slate-500 font-mono">
                                        <span>N ↑ UTARA</span>
                                        <span>DENAH KHUSUS RUANGAN</span>
                                    </div>
                                </div>

                                <!-- Mode Default: Kanvas Key Plan Vektor Otomatis -->
                                <KeyPlanCanvas
                                    v-else
                                    :elements="planSheet?.elements || []"
                                    :activeRoomName="room.room_name"
                                    :activeElementId="room.room_element_id"
                                    :activeCode="room.code"
                                    width="100%"
                                    height="100%"
                                />

                                <!-- Floating Action Buttons untuk Denah 2D Ruangan (Hidden saat print) -->
                                <div v-if="isOwner" class="no-print absolute top-1.5 right-1.5 flex items-center gap-1 z-20">
                                    <button
                                        @click="triggerUploadRoom2d(room.id)"
                                        :disabled="isUploadingRoom2d === room.id"
                                        class="px-2 py-0.5 rounded text-[9.5px] font-bold bg-slate-900/90 hover:bg-slate-800 text-amber-300 border border-amber-500/40 transition flex items-center gap-1 shadow backdrop-blur disabled:opacity-50"
                                        :title="room.key_plan_image_url ? 'Ganti gambar denah 2D khusus ruangan ini' : 'Unggah gambar denah 2D khusus untuk lembar ruangan ini'"
                                    >
                                        <Upload class="w-3 h-3" :class="isUploadingRoom2d === room.id ? 'animate-spin' : ''" />
                                        <span>{{ room.key_plan_image_url ? 'Ganti 2D' : '+ Denah 2D' }}</span>
                                    </button>

                                    <button
                                        v-if="room.key_plan_image_url"
                                        @click="removeRoom2dImage(room.id)"
                                        class="p-0.5 rounded bg-red-600/80 text-white hover:bg-red-500 transition shadow"
                                        title="Hapus gambar denah 2D dan kembali ke denah otomatis"
                                    >
                                        <X class="w-3 h-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Area Kanan: 20% Kop Gambar (Title Block) Standar Arsitektur -->
                    <div class="w-[20%] h-full bg-white flex flex-col justify-between p-4 sm:p-5 border-l border-slate-300 text-[11px] leading-tight">
                        <div class="border-b border-slate-800 pb-4 text-center">
                            <h3 class="text-xl sm:text-2xl font-black tracking-wider text-slate-900 uppercase font-serif">
                                {{ architect.company_name || 'STUDIO ARSITEK' }}
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

    html, body {
        width: 100% !important;
        min-height: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .no-print {
        display: none !important;
    }

    .booklet-sheet {
        position: relative !important;
        width: 100% !important;
        height: 100vh !important;
        min-height: 100vh !important;
        max-width: none !important;
        page-break-after: always !important;
        page-break-inside: avoid !important;
        break-after: page !important;
        break-inside: avoid !important;
        border: none !important;
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        border-radius: 0 !important;
        overflow: hidden !important;
        box-sizing: border-box !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Khusus Halaman 1 (Cover Sheet) saat Cetak / PDF */
    .cover-sheet {
        position: relative !important;
        background-color: #020617 !important;
        color: white !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cover-sheet .cover-bg-image {
        display: block !important;
        visibility: visible !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
        opacity: 1 !important;
        z-index: 1 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cover-sheet .cover-gradient {
        display: block !important;
        visibility: visible !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        z-index: 2 !important;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.25) 50%, rgba(0, 0, 0, 0.45) 100%) !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cover-sheet .cover-minimalist {
        background: #0b101d !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cover-sheet .cover-content {
        position: relative !important;
        z-index: 10 !important;
    }

    .cover-sheet,
    .cover-sheet * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cover-sheet h1,
    .cover-sheet h2,
    .cover-sheet p,
    .cover-sheet span {
        color: white !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

