<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import {
    Sparkles,
    Camera,
    Film,
    Download,
    Trash2,
    Copy,
    Check,
    RefreshCw,
    Info,
    Sliders,
    X,
    Upload,
    Image as ImageIcon,
    Clapperboard,
    Loader2,
    CheckCircle2,
    AlertCircle,
    Maximize2,
    Bookmark,
    ArrowUpRight,
    Layers,
    Pencil,
} from '@lucide/vue';

defineOptions({ layout: UnifiedLayout });

interface VisionCreationItem {
    id: string;
    title: string | null;
    room_name: string;
    prompt_type: 'image' | 'video';
    file_path: string;
    end_file_path: string | null;
    file_size_bytes: number;
    width: number;
    height: number;
    prompt: string | null;
    negative_prompt: string | null;
    metadata: Record<string, any> | null;
    url: string;
    end_url: string | null;
    created_at: string;
}

const props = defineProps<{
    initialCreations: VisionCreationItem[];
}>();

// ── Tab Navigation (Studio vs Galeri) with LocalStorage Persistence ───────────
const STORAGE_TAB_KEY = 'pitcharch_vision_studio_active_tab';

function getInitialTab(): 'studio' | 'gallery' {
    if (typeof window !== 'undefined' && window.localStorage) {
        try {
            const saved = localStorage.getItem(STORAGE_TAB_KEY);
            if (saved === 'studio' || saved === 'gallery') {
                return saved;
            }
        } catch {
            // ignore
        }
    }
    return 'studio';
}

const activeViewTab = ref<'studio' | 'gallery'>(getInitialTab());
const savedCreations = ref<VisionCreationItem[]>([...props.initialCreations]);

onMounted(() => {
    if (typeof window !== 'undefined' && window.localStorage) {
        try {
            const saved = localStorage.getItem(STORAGE_TAB_KEY);
            if (saved === 'studio' || saved === 'gallery') {
                activeViewTab.value = saved;
            }
        } catch {
            // ignore
        }
    }
});

watch(activeViewTab, (newTab) => {
    if (typeof window !== 'undefined' && window.localStorage) {
        try {
            localStorage.setItem(STORAGE_TAB_KEY, newTab);
        } catch {
            // ignore
        }
    }
});

// ── Image Upload State ────────────────────────────────────────────────────────
const imageFileA = ref<File | null>(null);
const imageADataUrl = ref<string>('');
const imageAName = ref<string>('');
const imageASize = ref<string>('');
const imageADimensions = ref<{ width: number; height: number } | null>(null);

const imageFileB = ref<File | null>(null);
const imageBDataUrl = ref<string>('');
const imageBName = ref<string>('');
const imageBSize = ref<string>('');

const activeFrameTab = ref<'frame_a' | 'frame_b'>('frame_a');
const isDraggingA = ref(false);
const isDraggingB = ref(false);

const fileInputRefA = ref<HTMLInputElement | null>(null);
const fileInputRefB = ref<HTMLInputElement | null>(null);

// ── Prompt & AI Generation State ──────────────────────────────────────────────
const capturePromptMode = ref<'image' | 'video'>('image');
const captureSelectedSyntax = ref<'universal' | 'midjourney' | 'sdxl' | 'flux'>('universal');

// Video AI parameters
const captureVideoMotions = ref<string[]>(['push_in']);
const captureVideoDuration = ref<'5s' | '10s'>('5s');
const captureVideoPace = ref<'slow' | 'normal' | 'dynamic'>('slow');
const captureVideoMood = ref<'scene' | 'golden_hour' | 'overcast' | 'rainy' | 'night_luxury' | 'misty_morning'>('scene');
const captureVideoLens = ref<'anamorphic_35mm' | 'wide_16mm' | 'macro_50mm'>('anamorphic_35mm');
const captureVideoTarget = ref<'runway' | 'kling' | 'luma' | 'universal'>('runway');
const captureVideoStrictGeometry = ref(true);

// Scene descriptor inputs (customizable)
const roomName = ref<string>('');
const selectedStyle = ref<string>('Modern Minimalist');
const selectedLighting = ref<string>('Daylight');

// Prompts
const capturePrompt = ref<string>('');
const promptSource = ref<'local-draft' | 'vision-ai'>('local-draft');
const hasVisionAiGenerated = ref(false);
const isGeneratingVisionPrompt = ref(false);

// Negative Prompts
const defaultNegativePrompt = ref<string>(
    'blurry, deformed, distorted, low quality, warped lines, tilted horizon, extra columns, watermark, signature, oversaturated, amateur photography'
);
const defaultVideoNegativePrompt = ref<string>(
    'deformed architecture, morphing walls, bending columns, warped windows, flickering geometry, sudden camera cuts, jerky movement, erratic zoom, floating artifacts, distorted proportions, low framerate, jittery shadows'
);

// Copy & Feedback states
const copiedPrompt = ref(false);
const copiedNegative = ref(false);
const copiedVideoNegative = ref(false);

// Save to Studio Gallery state
const isSavingToGallery = ref(false);
const saveSuccessMessage = ref('');
const saveErrorMessage = ref('');

// Fullscreen preview modal
const isLightboxOpen = ref(false);
const lightboxImageUrl = ref<string>('');

// ── Motions Dictionary ────────────────────────────────────────────────────────
const motionOptions = [
    { id: 'two_point', label: '🎯 2 Titik', title: 'Transisi kamera dari Frame 1 (Awal) ke Frame 2 (Akhir)' },
    { id: 'push_in', label: '🚶 Push-In', title: 'Kamera bergerak maju masuk ke ruangan' },
    { id: 'pull_out', label: '🔙 Pull-Out', title: 'Kamera mundur mengungkap fasad bangunan' },
    { id: 'orbit_left', label: '🛸 Orbit Kiri', title: 'Kamera mengitari bangunan searah jarum jam' },
    { id: 'orbit_right', label: '🔄 Orbit Kanan', title: 'Kamera mengitari bangunan berlawanan arah jarum jam' },
    { id: 'pan_left', label: '⬅️ Pan Kiri', title: 'Geser kamera horizontal ke kiri' },
    { id: 'pan_right', label: '➡️ Pan Kanan', title: 'Geser kamera horizontal ke kanan' },
    { id: 'crane_up', label: '🏗️ Crane Naik', title: 'Kamera naik vertikal ke atas mengungkap atap' },
    { id: 'crane_down', label: '⬇️ Crane Turun', title: 'Kamera turun vertikal mendekati lantai' },
    { id: 'fpv_drone', label: '🛩️ FPV Drone', title: 'Penerbangan drone FPV mulus melintasi bukaan ruangan' },
    { id: 'spiral_ascend', label: '🌀 Spiral Naik', title: 'Kamera naik melingkar spiral mengitari bangunan' },
    { id: 'tilt_up', label: '📐 Tilt Up', title: 'Sudut kamera menengadah ke atas dari lantai' },
    { id: 'tilt_down', label: '📐 Tilt Down', title: 'Sudut kamera menunduk ke bawah dari plafon' },
    { id: 'timelapse', label: '🌅 Timelapse', title: 'Kamera statis dengan bayangan matahari bergerak cepat' },
    { id: 'static_subtle', label: '📹 Tripod Parallax', title: 'Tripod statis dengan micro-parallax alami' },
];

const isTwoPointActive = computed(() => {
    return capturePromptMode.value === 'video' && captureVideoMotions.value.includes('two_point');
});

// ── File Handlers ─────────────────────────────────────────────────────────────
function handleFileSelectA(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        processUploadedFileA(input.files[0]);
    }
}

function handleFileSelectB(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        processUploadedFileB(input.files[0]);
    }
}

function handleDropA(e: DragEvent) {
    isDraggingA.value = false;
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        processUploadedFileA(e.dataTransfer.files[0]);
    }
}

function handleDropB(e: DragEvent) {
    isDraggingB.value = false;
    if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
        processUploadedFileB(e.dataTransfer.files[0]);
    }
}

function processUploadedFileA(file: File) {
    if (!file.type.startsWith('image/')) {
        alert('Silakan pilih file gambar yang valid (JPG, PNG, WEBP).');
        return;
    }
    imageFileA.value = file;
    imageAName.value = file.name;
    imageASize.value = formatBytes(file.size);

    const reader = new FileReader();
    reader.onload = (event) => {
        const dataUrl = event.target?.result as string;
        imageADataUrl.value = dataUrl;

        const img = new Image();
        img.onload = () => {
            imageADimensions.value = { width: img.naturalWidth, height: img.naturalHeight };
        };
        img.src = dataUrl;

        if (!roomName.value) {
            const cleanName = file.name.replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
            roomName.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
        }

        hasVisionAiGenerated.value = false;
        generateDraftPrompt();
    };
    reader.readAsDataURL(file);
}

function processUploadedFileB(file: File) {
    if (!file.type.startsWith('image/')) {
        alert('Silakan pilih file gambar yang valid (JPG, PNG, WEBP).');
        return;
    }
    imageFileB.value = file;
    imageBName.value = file.name;
    imageBSize.value = formatBytes(file.size);

    const reader = new FileReader();
    reader.onload = (event) => {
        imageBDataUrl.value = event.target?.result as string;
        hasVisionAiGenerated.value = false;
        generateDraftPrompt();
    };
    reader.readAsDataURL(file);
}

function clearImageA() {
    imageFileA.value = null;
    imageADataUrl.value = '';
    imageAName.value = '';
    imageASize.value = '';
    imageADimensions.value = null;
    hasVisionAiGenerated.value = false;
    if (fileInputRefA.value) fileInputRefA.value.value = '';
    generateDraftPrompt();
}

function clearImageB() {
    imageFileB.value = null;
    imageBDataUrl.value = '';
    imageBName.value = '';
    imageBSize.value = '';
    hasVisionAiGenerated.value = false;
    if (fileInputRefB.value) fileInputRefB.value.value = '';
    generateDraftPrompt();
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// ── Toggle Motion ─────────────────────────────────────────────────────────────
function toggleVideoMotion(id: string) {
    if (captureVideoMotions.value.includes(id)) {
        if (captureVideoMotions.value.length > 1) {
            captureVideoMotions.value = captureVideoMotions.value.filter(m => m !== id);
        }
    } else {
        captureVideoMotions.value.push(id);
    }
    generateDraftPrompt();
}

function getSelectedMotionsLabel(): string {
    return captureVideoMotions.value
        .map(id => motionOptions.find(m => m.id === id)?.label ?? id)
        .join(', ');
}

// ── Local Draft Prompt Generator (0 Token) ────────────────────────────────────
function generateDraftPrompt() {
    if (hasVisionAiGenerated.value) return;

    const space = roomName.value.trim() || 'Modern Architectural Space';
    const style = selectedStyle.value;
    const lighting = selectedLighting.value;

    if (capturePromptMode.value === 'video') {
        const motionList = captureVideoMotions.value
            .map(m => motionOptions.find(opt => opt.id === m)?.title || m)
            .join(' followed by ');

        const paceText = captureVideoPace.value === 'slow'
            ? 'slow and majestic camera velocity (0.5x speed) preserving razor-sharp architectural geometry'
            : captureVideoPace.value === 'dynamic'
            ? 'dynamic fluid cinematic velocity'
            : 'steady natural walking velocity';

        const moodText = matchMood(captureVideoMood.value);
        const lensText = matchLens(captureVideoLens.value);
        const geoText = captureVideoStrictGeometry.value
            ? '100% rigid architectural geometry, straight vertical perspective lines, zero warping or morphing.'
            : 'Fluid cinematic motion without morphing.';

        const twoPointText = isTwoPointActive.value
            ? 'Seamless camera transition traveling steadily from starting camera viewpoint in Frame 1 to destination viewpoint in Frame 2.'
            : '';

        capturePrompt.value = `Cinematic 4K architectural video of ${space}. ${twoPointText} Continuous shot: ${motionList}. Pacing: ${paceText}. Style: ${style}. Lighting: ${lighting}, ${moodText}. Lens: ${lensText}. ${geoText}`;
        promptSource.value = 'local-draft';
    } else {
        const prefix = 'Strict image-to-image architectural photorealistic rendering of the attached reference image.';
        capturePrompt.value = `${prefix} Realistic architectural visualization of ${space}, ${style} aesthetic, ${lighting} natural illumination with clean geometric shadows, high-end interior and exterior materials, true color fidelity. Shot on Hasselblad H6D-100c, 35mm lens, f/8, ambient occlusion crevices, raytraced global illumination, 8k resolution, award-winning architectural showcase.`;
        promptSource.value = 'local-draft';
    }
}

function matchMood(mood: string): string {
    switch (mood) {
        case 'golden_hour': return 'warm golden hour sunset with elongated geometric architectural shadows';
        case 'overcast': return 'soft overcast diffuse daylight with minimalist even illumination';
        case 'rainy': return 'cinematic dusk rain with soft droplets, reflective wet surfaces and glass reflections';
        case 'night_luxury': return 'dramatic evening twilight with warm interior spotlights and linear LED accents';
        case 'misty_morning': return 'atmospheric dawn mist with volumetric sunbeams filtering through openings';
        default: return 'crisp natural daylight with directional architectural shadows';
    }
}

function matchLens(lens: string): string {
    switch (lens) {
        case 'wide_16mm': return 'shot on 16mm ultra-wide rectilinear architectural lens, zero optical distortion, straight vertical lines';
        case 'macro_50mm': return 'shot on 50mm f/2.0 portrait lens, gentle background bokeh emphasizing tactile textures';
        default: return 'shot on Arri Alexa Mini, 35mm anamorphic lens, 24fps film reel';
    }
}

// ── Vision AI Prompt Generation ───────────────────────────────────────────────
async function requestVisionAiPrompt() {
    if (!imageADataUrl.value) {
        alert('Silakan upload gambar referensi terlebih dahulu.');
        return;
    }

    isGeneratingVisionPrompt.value = true;
    saveErrorMessage.value = '';

    const payload: Record<string, any> = {
        image: imageADataUrl.value,
        prompt_type: capturePromptMode.value,
        camera_motions: captureVideoMotions.value,
        duration: captureVideoDuration.value,
        camera_pace: captureVideoPace.value,
        ambient_mood: captureVideoMood.value,
        lens_style: captureVideoLens.value,
        strict_geometry: captureVideoStrictGeometry.value,
        video_target: captureVideoTarget.value,
        scene_metadata: {
            room_name: roomName.value || 'Architectural Space',
            lighting_desc: selectedLighting.value,
            style: selectedStyle.value,
        },
    };

    if (capturePromptMode.value === 'video' && imageBDataUrl.value) {
        payload.end_image = imageBDataUrl.value;
    }

    try {
        const res = await fetch('/vision-studio/generate-prompt', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.prompt) {
                capturePrompt.value = data.prompt;
                promptSource.value = data.source ? 'vision-ai' : 'local-draft';
                hasVisionAiGenerated.value = Boolean(data.source);
            }
        } else {
            const err = await res.json();
            alert(err.message || 'Gagal menghubungi Vision AI. Menggunakan prompt draft lokal.');
        }
    } catch (e) {
        console.error('Vision AI generate error:', e);
        alert('Terjadi kesalahan koneksi saat memproses Vision AI.');
    } finally {
        isGeneratingVisionPrompt.value = false;
    }
}

// ── Copy Prompt Functions ─────────────────────────────────────────────────────
function copyFormattedPrompt() {
    let textToCopy = capturePrompt.value;

    if (capturePromptMode.value === 'image' && captureSelectedSyntax.value === 'midjourney') {
        textToCopy = `${textToCopy} --iw 2.0 --ar 16:9 --style raw --v 6.1`;
    }

    navigator.clipboard.writeText(textToCopy);
    copiedPrompt.value = true;
    setTimeout(() => {
        copiedPrompt.value = false;
    }, 2000);
}

function copyCreationPrompt(prompt?: string | null) {
    if (!prompt) return;
    navigator.clipboard.writeText(prompt);
    alert('Prompt disalin ke clipboard!');
}

function copyNegativePrompt() {
    navigator.clipboard.writeText(defaultNegativePrompt.value);
    copiedNegative.value = true;
    setTimeout(() => {
        copiedNegative.value = false;
    }, 2000);
}

function copyVideoNegativePrompt() {
    navigator.clipboard.writeText(defaultVideoNegativePrompt.value);
    copiedVideoNegative.value = true;
    setTimeout(() => {
        copiedVideoNegative.value = false;
    }, 2000);
}

// ── Download Image ────────────────────────────────────────────────────────────
function downloadImage(dataUrl: string, suffix: string = 'image') {
    if (!dataUrl) return;
    const a = document.createElement('a');
    a.href = dataUrl;
    const slug = (roomName.value || 'vision-studio').toLowerCase().replace(/\s+/g, '-');
    a.download = `${slug}-${suffix}-${Date.now()}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// ── Save to Private Studio Gallery ────────────────────────────────────────────
async function saveToStudioGallery() {
    if (!imageADataUrl.value) {
        saveErrorMessage.value = 'Silakan upload gambar terlebih dahulu.';
        return;
    }

    isSavingToGallery.value = true;
    saveSuccessMessage.value = '';
    saveErrorMessage.value = '';

    const payload = {
        image: imageADataUrl.value,
        end_image: capturePromptMode.value === 'video' && imageBDataUrl.value ? imageBDataUrl.value : null,
        room_name: roomName.value || 'Arsitektur',
        title: `Vision: ${roomName.value || 'Karya Arsitektur'}`,
        prompt_type: capturePromptMode.value,
        prompt: capturePrompt.value || null,
        negative_prompt: capturePromptMode.value === 'video' ? defaultVideoNegativePrompt.value : (captureSelectedSyntax.value === 'sdxl' ? defaultNegativePrompt.value : null),
        width: imageADimensions.value?.width ?? 1920,
        height: imageADimensions.value?.height ?? 1080,
        metadata: {
            style: selectedStyle.value,
            lighting: selectedLighting.value,
            syntax: captureSelectedSyntax.value,
            motions: captureVideoMotions.value,
            duration: captureVideoDuration.value,
            pace: captureVideoPace.value,
            mood: captureVideoMood.value,
            lens: captureVideoLens.value,
            target: captureVideoTarget.value,
        },
    };

    try {
        const res = await fetch('/vision-studio/creations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (res.ok && data.creation) {
            savedCreations.value.unshift(data.creation);
            saveSuccessMessage.value = 'Berhasil disimpan ke Galeri Studio Anda!';
            setTimeout(() => {
                saveSuccessMessage.value = '';
            }, 3500);
        } else {
            saveErrorMessage.value = data.message || 'Gagal menyimpan ke galeri studio.';
        }
    } catch {
        saveErrorMessage.value = 'Terjadi kesalahan koneksi saat menyimpan gambar.';
    } finally {
        isSavingToGallery.value = false;
    }
}

// ── Edit Creation Modal State ────────────────────────────────────────────────
const editingCreation = ref<VisionCreationItem | null>(null);
const editForm = ref({
    room_name: '',
    prompt: '',
    negative_prompt: '',
});
const isUpdatingCreation = ref(false);
const updateErrorMessage = ref('');

function openEditModal(creation: VisionCreationItem) {
    editingCreation.value = creation;
    editForm.value = {
        room_name: creation.room_name || '',
        prompt: creation.prompt || '',
        negative_prompt: creation.negative_prompt || '',
    };
    updateErrorMessage.value = '';
}

async function saveEditedCreation() {
    if (!editingCreation.value) return;

    isUpdatingCreation.value = true;
    updateErrorMessage.value = '';

    try {
        const res = await fetch(`/vision-studio/creations/${editingCreation.value.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(editForm.value),
        });

        const data = await res.json();
        if (res.ok && data.creation) {
            const idx = savedCreations.value.findIndex(c => c.id === data.creation.id);
            if (idx !== -1) {
                savedCreations.value[idx] = data.creation;
            }
            editingCreation.value = null;
        } else {
            updateErrorMessage.value = data.message || 'Gagal memperbarui karya.';
        }
    } catch {
        updateErrorMessage.value = 'Terjadi kesalahan koneksi saat memperbarui karya.';
    } finally {
        isUpdatingCreation.value = false;
    }
}

// ── Delete from Studio Gallery ────────────────────────────────────────────────
async function deleteCreation(creation: VisionCreationItem) {
    if (!confirm(`Hapus "${creation.room_name || 'Karya ini'}" dari Galeri Studio Anda?`)) return;

    try {
        const res = await fetch(`/vision-studio/creations/${creation.id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (res.ok) {
            savedCreations.value = savedCreations.value.filter(c => c.id !== creation.id);
        } else {
            alert('Gagal menghapus karya dari galeri.');
        }
    } catch {
        alert('Terjadi kesalahan koneksi saat menghapus karya.');
    }
}

// ── Load Creation back to Studio ──────────────────────────────────────────────
async function loadCreationToStudio(creation: VisionCreationItem) {
    // 1. Set image and metadata immediately so it shows right away
    imageADataUrl.value = creation.url;
    imageAName.value = `${creation.room_name}.png`;
    roomName.value = creation.room_name;
    capturePromptMode.value = creation.prompt_type;
    capturePrompt.value = creation.prompt || '';
    promptSource.value = 'vision-ai';
    hasVisionAiGenerated.value = true;
    imageADimensions.value = { width: creation.width || 1920, height: creation.height || 1080 };
    imageASize.value = formatBytes(creation.file_size_bytes || 0);

    if (creation.end_url) {
        imageBDataUrl.value = creation.end_url;
        imageBName.value = 'Frame-2-Akhir.png';
    } else {
        clearImageB();
    }

    if (creation.metadata) {
        if (creation.metadata.style) selectedStyle.value = creation.metadata.style;
        if (creation.metadata.lighting) selectedLighting.value = creation.metadata.lighting;
        if (creation.metadata.motions) captureVideoMotions.value = creation.metadata.motions;
        if (creation.metadata.syntax) captureSelectedSyntax.value = creation.metadata.syntax;
        if (creation.metadata.duration) captureVideoDuration.value = creation.metadata.duration;
        if (creation.metadata.pace) captureVideoPace.value = creation.metadata.pace;
        if (creation.metadata.mood) captureVideoMood.value = creation.metadata.mood;
        if (creation.metadata.lens) captureVideoLens.value = creation.metadata.lens;
        if (creation.metadata.target) captureVideoTarget.value = creation.metadata.target;
    }

    activeViewTab.value = 'studio';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // 2. Convert to Base64 Data URL in background for smooth re-generation
    try {
        const res = await fetch(creation.url);
        const blob = await res.blob();
        const reader = new FileReader();
        reader.onload = (e) => {
            if (e.target?.result) {
                imageADataUrl.value = e.target.result as string;
            }
        };
        reader.readAsDataURL(blob);
    } catch (e) {
        // Fallback: direct URL remains in place
    }
}

function openLightbox(url: string) {
    lightboxImageUrl.value = url;
    isLightboxOpen.value = true;
}

// Watchers to update draft prompt when user changes inputs
watch(
    [capturePromptMode, captureVideoDuration, captureVideoPace, captureVideoMood, captureVideoLens, captureVideoStrictGeometry, selectedStyle, selectedLighting],
    () => {
        generateDraftPrompt();
    }
);

// Initialize draft prompt
generateDraftPrompt();
</script>

<template>
    <Head title="AI Vision Studio - Pitcharch" />

    <div class="space-y-6 max-w-7xl mx-auto pb-12">
        <!-- ── HEADER BANNER ── -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-purple-950 p-6 sm:p-8 text-white border border-white/10 shadow-2xl">
            <div class="absolute -right-16 -top-16 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>
            <div class="absolute right-32 -bottom-20 h-56 w-56 rounded-full bg-purple-500/15 blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1.5">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-xs font-medium">
                        <Sparkles class="h-3.5 w-3.5 text-amber-300" />
                        <span>AI Vision Studio &bull; Image-to-Prompt &amp; Video Sinematik</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white flex items-center gap-2.5">
                        <span>Studio Prompt Gambar Arsitektur</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-neutral-300 max-w-2xl leading-relaxed">
                        Unggah gambar arsitektur (render eksternal, foto proyek, atau sketsa konsep), lalu formulasikan prompt visual presisi untuk Midjourney, SDXL, Flux, Runway Gen-3, dan Kling AI.
                    </p>
                </div>

                <!-- Navigation Tabs: Studio vs Galeri Studio -->
                <div class="flex items-center p-1 rounded-2xl bg-black/40 border border-white/15 backdrop-blur-md self-start md:self-center">
                    <button
                        type="button"
                        @click="activeViewTab = 'studio'"
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2"
                        :class="activeViewTab === 'studio' ? 'bg-indigo-600 text-white shadow' : 'text-neutral-400 hover:text-white'"
                    >
                        <Sparkles class="h-3.5 w-3.5" />
                        <span>Generator Studio</span>
                    </button>
                    <button
                        type="button"
                        @click="activeViewTab = 'gallery'"
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2"
                        :class="activeViewTab === 'gallery' ? 'bg-purple-600 text-white shadow' : 'text-neutral-400 hover:text-white'"
                    >
                        <Bookmark class="h-3.5 w-3.5" />
                        <span>Galeri Studio Saya</span>
                        <span v-if="savedCreations.length > 0" class="px-1.5 py-0.2 rounded-full bg-white/20 text-[10px] font-mono">
                            {{ savedCreations.length }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════
             TAB 1: GENERATOR STUDIO (2-COLUMN)
        ═══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeViewTab === 'studio'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- ── KOLOM KIRI: AREA UPLOAD & PREVIEW GAMBAR (LG: 5 COLS) ── -->
            <div class="lg:col-span-5 space-y-4">
                <!-- Frame Tab Switcher jika Video 2-Titik aktif -->
                <div v-if="isTwoPointActive" class="flex items-center p-1 rounded-2xl bg-purple-950/40 border border-purple-500/30 text-xs">
                    <button
                        type="button"
                        @click="activeFrameTab = 'frame_a'"
                        class="flex-1 py-2 px-3 rounded-xl font-medium transition-all flex items-center justify-center gap-1.5"
                        :class="activeFrameTab === 'frame_a' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                    >
                        <span>1️⃣ Frame 1 (Awal)</span>
                        <span v-if="imageADataUrl" class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    </button>
                    <button
                        type="button"
                        @click="activeFrameTab = 'frame_b'"
                        class="flex-1 py-2 px-3 rounded-xl font-medium transition-all flex items-center justify-center gap-1.5"
                        :class="activeFrameTab === 'frame_b' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-neutral-400 hover:text-white'"
                    >
                        <span>2️⃣ Frame 2 (Akhir)</span>
                        <span v-if="imageBDataUrl" class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    </button>
                </div>

                <!-- ── UPLOAD / PREVIEW SLOT 1 (FRAME A) ── -->
                <div v-show="activeFrameTab === 'frame_a' || !isTwoPointActive" class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-slate-700 dark:text-neutral-200 flex items-center gap-1.5">
                            <ImageIcon class="h-4 w-4 text-indigo-500" />
                            <span>{{ isTwoPointActive ? 'Gambar Frame 1 (Posisi Awal)' : 'Gambar Referensi Arsitektur' }}</span>
                        </span>
                        <span v-if="imageADataUrl" class="text-[11px] font-mono text-neutral-400">
                            {{ imageAName }} ({{ imageASize }})
                        </span>
                    </div>

                    <!-- DROPZONE / PREVIEW CONTAINER -->
                    <div
                        v-if="!imageADataUrl"
                        @dragover.prevent="isDraggingA = true"
                        @dragleave.prevent="isDraggingA = false"
                        @drop.prevent="handleDropA"
                        @click="fileInputRefA?.click()"
                        class="relative aspect-[16/10] sm:aspect-video rounded-3xl border-2 border-dashed transition-all cursor-pointer flex flex-col items-center justify-center p-6 text-center group bg-white dark:bg-neutral-900/60 shadow-inner"
                        :class="isDraggingA
                            ? 'border-indigo-500 bg-indigo-500/10 scale-[0.99]'
                            : 'border-slate-300 dark:border-white/15 hover:border-indigo-400 dark:hover:border-indigo-500/50 hover:bg-slate-50 dark:hover:bg-neutral-900/90'"
                    >
                        <input
                            ref="fileInputRefA"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleFileSelectA"
                        />
                        <div class="h-14 w-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <Upload class="h-7 w-7" />
                        </div>
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-white">
                            Pilih atau Tarik Gambar ke Sini
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-neutral-400 max-w-xs mt-1">
                            Mendukung file JPG, PNG, WEBP hingga resolusi 4K.
                        </p>
                        <span class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-slate-100 dark:bg-white/10 text-[11px] font-medium text-slate-600 dark:text-neutral-300">
                            Browse File Komputer
                        </span>
                    </div>

                    <!-- PREVIEW IMAGE IF LOADED -->
                    <div v-else class="relative aspect-video rounded-3xl overflow-hidden border border-slate-200 dark:border-white/15 bg-black shadow-2xl flex items-center justify-center group">
                        <img
                            :src="imageADataUrl"
                            alt="Reference Preview"
                            class="h-full w-full object-contain"
                        />

                        <!-- Overlay Badge -->
                        <div class="absolute bottom-3 left-3 flex items-center gap-1.5 pointer-events-none">
                            <span class="px-2.5 py-0.5 rounded-full bg-black/80 border border-white/15 text-[10px] font-medium text-white backdrop-blur">
                                {{ roomName || 'Gambar Utama' }}
                            </span>
                            <span v-if="imageADimensions" class="px-2 py-0.5 rounded-full bg-black/80 border border-white/15 text-[10px] font-mono text-amber-300 backdrop-blur">
                                {{ imageADimensions.width }}x{{ imageADimensions.height }}
                            </span>
                        </div>

                        <!-- Hover Action Bar -->
                        <div class="absolute top-3 right-3 flex items-center gap-1.5 opacity-90 sm:opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                type="button"
                                @click="openLightbox(imageADataUrl)"
                                class="p-2 rounded-xl bg-black/70 hover:bg-black text-white border border-white/20 backdrop-blur transition shadow"
                                title="Lihat Penuh"
                            >
                                <Maximize2 class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="downloadImage(imageADataUrl, 'start-frame')"
                                class="p-2 rounded-xl bg-black/70 hover:bg-black text-white border border-white/20 backdrop-blur transition shadow"
                                title="Download Gambar"
                            >
                                <Download class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="clearImageA"
                                class="p-2 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white border border-rose-400/30 backdrop-blur transition shadow"
                                title="Hapus Gambar"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── UPLOAD / PREVIEW SLOT 2 (FRAME B - END FRAME) ── -->
                <div v-show="activeFrameTab === 'frame_b' && isTwoPointActive" class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-purple-700 dark:text-purple-300 flex items-center gap-1.5">
                            <Clapperboard class="h-4 w-4 text-purple-500" />
                            <span>Gambar Frame 2 (Posisi Akhir Kamera)</span>
                        </span>
                        <span v-if="imageBDataUrl" class="text-[11px] font-mono text-neutral-400">
                            {{ imageBName }} ({{ imageBSize }})
                        </span>
                    </div>

                    <div
                        v-if="!imageBDataUrl"
                        @dragover.prevent="isDraggingB = true"
                        @dragleave.prevent="isDraggingB = false"
                        @drop.prevent="handleDropB"
                        @click="fileInputRefB?.click()"
                        class="relative aspect-[16/10] sm:aspect-video rounded-3xl border-2 border-dashed transition-all cursor-pointer flex flex-col items-center justify-center p-6 text-center group bg-white dark:bg-neutral-900/60 shadow-inner"
                        :class="isDraggingB
                            ? 'border-purple-500 bg-purple-500/10 scale-[0.99]'
                            : 'border-slate-300 dark:border-white/15 hover:border-purple-400 dark:hover:border-purple-500/50 hover:bg-slate-50 dark:hover:bg-neutral-900/90'"
                    >
                        <input
                            ref="fileInputRefB"
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="handleFileSelectB"
                        />
                        <div class="h-14 w-14 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-500 dark:text-purple-400 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                            <Upload class="h-7 w-7" />
                        </div>
                        <h4 class="text-sm font-semibold text-slate-800 dark:text-white">
                            Upload Frame 2 (Posisi Akhir)
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-neutral-400 max-w-xs mt-1">
                            Diperlukan untuk transisi kamera 2-Titik di Runway Gen-3 atau Kling AI.
                        </p>
                    </div>

                    <div v-else class="relative aspect-video rounded-3xl overflow-hidden border border-purple-500/30 bg-black shadow-2xl flex items-center justify-center group">
                        <img
                            :src="imageBDataUrl"
                            alt="End Frame Preview"
                            class="h-full w-full object-contain"
                        />
                        <div class="absolute bottom-3 left-3 flex items-center gap-1.5 pointer-events-none">
                            <span class="px-2.5 py-0.5 rounded-full bg-purple-950/80 border border-purple-500/30 text-[10px] font-medium text-purple-200 backdrop-blur">
                                Frame 2 (Akhir)
                            </span>
                        </div>
                        <div class="absolute top-3 right-3 flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="downloadImage(imageBDataUrl, 'end-frame')"
                                class="p-2 rounded-xl bg-black/70 hover:bg-black text-white border border-white/20 backdrop-blur transition shadow"
                                title="Download Frame 2"
                            >
                                <Download class="h-3.5 w-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="clearImageB"
                                class="p-2 rounded-xl bg-rose-600/80 hover:bg-rose-600 text-white border border-rose-400/30 backdrop-blur transition shadow"
                                title="Hapus Frame 2"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── KONTROL DETAIL ARSITEKTUR & DESKRIPSI ── -->
                <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-white/10 shadow-sm space-y-3">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-neutral-400 flex items-center gap-1.5">
                        <Sliders class="h-3.5 w-3.5 text-indigo-500" />
                        <span>Parameter Arsitektur Gambar</span>
                    </h4>

                    <div class="space-y-2 text-xs">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-neutral-400 mb-1">
                                Nama Ruangan / Objek Bangunan
                            </label>
                            <input
                                type="text"
                                v-model="roomName"
                                @input="generateDraftPrompt"
                                placeholder="Contoh: Fasad Rumah Tropis 2 Lantai, Dapur Modern"
                                class="w-full h-9 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-white/5 px-3 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-neutral-600 focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 dark:text-neutral-400 mb-1">
                                    Gaya Arsitektur
                                </label>
                                <select
                                    v-model="selectedStyle"
                                    class="w-full h-9 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-neutral-800 px-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="Modern Minimalist">Modern Minimalist</option>
                                    <option value="Tropical Contemporary">Tropical Contemporary</option>
                                    <option value="Japandi & Zen Wabi-Sabi">Japandi &amp; Zen</option>
                                    <option value="Industrial Brutalism">Industrial Brutalism</option>
                                    <option value="Classic European Luxury">Classic Luxury</option>
                                    <option value="Scandinavian Natural Wood">Scandinavian</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-slate-600 dark:text-neutral-400 mb-1">
                                    Kondisi Cahaya
                                </label>
                                <select
                                    v-model="selectedLighting"
                                    class="w-full h-9 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-neutral-800 px-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="Daylight">Siang Hari (Crisp Sun)</option>
                                    <option value="Golden Hour">Golden Hour (Sunset)</option>
                                    <option value="Twilight Blue Hour">Senja (Blue Hour)</option>
                                    <option value="Warm Interior Night">Malam (Warm Interior)</option>
                                    <option value="Overcast Diffuse">Mendung (Overcast Soft)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SIMPAN KE GALERI STUDIO SAYA (DEDICATED) ── -->
                <div class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-white/10 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-neutral-200 flex items-center gap-1.5">
                            <Bookmark class="h-3.5 w-3.5 text-purple-500" />
                            <span>Simpan ke Galeri Studio Anda</span>
                        </h4>
                        <span class="text-[10px] text-slate-400 dark:text-neutral-500">Pribadi</span>
                    </div>

                    <p class="text-[11px] text-slate-500 dark:text-neutral-400">
                        Simpan gambar yang di-upload beserta rumusan prompt-nya ke Galeri Studio pribadi Anda agar dapat dibuka atau disalin kembali kapan saja.
                    </p>

                    <div v-if="saveSuccessMessage" class="p-2.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-300 text-xs flex items-center gap-1.5">
                        <CheckCircle2 class="h-4 w-4 shrink-0" />
                        <span>{{ saveSuccessMessage }}</span>
                    </div>

                    <div v-if="saveErrorMessage" class="p-2.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-xs flex items-center gap-1.5">
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        <span>{{ saveErrorMessage }}</span>
                    </div>

                    <button
                        type="button"
                        :disabled="isSavingToGallery || !imageADataUrl"
                        @click="saveToStudioGallery"
                        class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-semibold flex items-center justify-center gap-2 transition shadow disabled:opacity-40"
                    >
                        <Loader2 v-if="isSavingToGallery" class="h-3.5 w-3.5 animate-spin" />
                        <Bookmark v-else class="h-3.5 w-3.5" />
                        <span>{{ isSavingToGallery ? 'Menyimpan...' : '💾 Simpan ke Galeri Studio' }}</span>
                    </button>
                </div>
            </div>

            <!-- ── KOLOM KANAN: GENERATOR PROMPT AI (LG: 7 COLS) ── -->
            <div class="lg:col-span-7 space-y-4">
                <!-- PRIMARY MODE SWITCHER: FOTO vs VIDEO -->
                <div class="flex items-center p-1.5 rounded-2xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-white/10 shadow-sm gap-1.5">
                    <button
                        type="button"
                        @click="capturePromptMode = 'image'; generateDraftPrompt()"
                        class="flex-1 py-2 px-4 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center justify-center gap-2"
                        :class="capturePromptMode === 'image'
                            ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md'
                            : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <Camera class="h-4 w-4" />
                        <span>📷 Foto Render Arsitektur</span>
                    </button>
                    <button
                        type="button"
                        @click="capturePromptMode = 'video'; generateDraftPrompt()"
                        class="flex-1 py-2 px-4 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center justify-center gap-2"
                        :class="capturePromptMode === 'video'
                            ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow-md'
                            : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                    >
                        <Film class="h-4 w-4" />
                        <span>🎬 Video Sinematik Kamera</span>
                    </button>
                </div>

                <!-- ── JIKA MODE FOTO: TARGET MODEL AI SYNTAX ── -->
                <div v-if="capturePromptMode === 'image'" class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-white/10 shadow-sm space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold uppercase tracking-wider text-slate-500 dark:text-neutral-400">
                            Target Model AI Gambar:
                        </span>
                        <span class="font-mono text-indigo-600 dark:text-indigo-300 font-semibold text-[11px]">
                            {{ captureSelectedSyntax === 'midjourney' ? 'Midjourney v6.1 (--iw 2.0)' : (captureSelectedSyntax === 'sdxl' ? 'SDXL / Fooocus / ControlNet' : (captureSelectedSyntax === 'flux' ? 'Flux.1 / Krea.ai' : 'Universal Prompt')) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-4 gap-1.5 p-1 rounded-2xl bg-slate-100 dark:bg-black/40 border border-slate-200 dark:border-white/10">
                        <button
                            type="button"
                            @click="captureSelectedSyntax = 'universal'"
                            class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                            :class="captureSelectedSyntax === 'universal' ? 'bg-indigo-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            Universal
                        </button>
                        <button
                            type="button"
                            @click="captureSelectedSyntax = 'midjourney'"
                            class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                            :class="captureSelectedSyntax === 'midjourney' ? 'bg-blue-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            Midjourney
                        </button>
                        <button
                            type="button"
                            @click="captureSelectedSyntax = 'sdxl'"
                            class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                            :class="captureSelectedSyntax === 'sdxl' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            SDXL / ControlNet
                        </button>
                        <button
                            type="button"
                            @click="captureSelectedSyntax = 'flux'"
                            class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                            :class="captureSelectedSyntax === 'flux' ? 'bg-emerald-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            Flux / Krea
                        </button>
                    </div>

                    <!-- Platform Tip Banner -->
                    <div v-if="captureSelectedSyntax === 'midjourney'" class="p-3 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-xs text-blue-700 dark:text-blue-200 leading-relaxed flex items-start gap-2">
                        <Info class="h-4 w-4 text-blue-500 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white">Panduan Midjourney v6:</span> Unggah gambar preview ke Discord, salin link gambarnya di awal prompt. Parameter <code class="px-1 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 font-mono text-amber-700 dark:text-amber-300 font-bold">--iw 2.0</code> (Image Weight maksimal) otomatis ditambahkan saat disalin untuk mengunci geometri arsitektur.
                        </div>
                    </div>
                    <div v-else-if="captureSelectedSyntax === 'sdxl'" class="p-3 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-xs text-purple-700 dark:text-purple-200 leading-relaxed flex items-start gap-2">
                        <Info class="h-4 w-4 text-purple-500 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white">Panduan SDXL &amp; Fooocus:</span> Gunakan mode Image-to-Image dengan Denoising Strength <span class="font-bold text-amber-600 dark:text-amber-300">0.35 - 0.45</span> agar bentuk bangunan tidak berubah.
                        </div>
                    </div>
                    <div v-else-if="captureSelectedSyntax === 'flux'" class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-700 dark:text-emerald-200 leading-relaxed flex items-start gap-2">
                        <Info class="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white">Panduan Flux.1 &amp; Krea.ai:</span> Gunakan mode Sketch/Image to Render dengan AI Strength 30% - 40% agar proporsi asli tetap presisi.
                        </div>
                    </div>
                </div>

                <!-- ── JIKA MODE VIDEO: GAYA KAMERA & TARGET VIDEO AI ── -->
                <div v-else class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-neutral-900 border border-slate-200 dark:border-white/10 shadow-sm space-y-4">
                    <!-- 1. Multi-Select Gerakan Kamera -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-300">
                                    Gaya Gerakan Kamera:
                                </span>
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-700 dark:text-purple-200 font-semibold">
                                    {{ captureVideoMotions.length }} Dipilih
                                </span>
                            </div>
                            <span class="font-mono text-[11px] text-slate-500 dark:text-neutral-400 truncate max-w-[220px]" :title="getSelectedMotionsLabel()">
                                {{ getSelectedMotionsLabel() }}
                            </span>
                        </div>

                        <!-- 15 Motion Buttons Grid -->
                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-1.5 p-1.5 rounded-2xl bg-slate-100 dark:bg-black/40 border border-slate-200 dark:border-white/10 text-xs">
                            <button
                                v-for="motion in motionOptions"
                                :key="motion.id"
                                type="button"
                                @click="toggleVideoMotion(motion.id)"
                                class="py-1.5 px-1 rounded-xl text-center font-medium transition-all flex items-center justify-center gap-1 text-[11px]"
                                :class="captureVideoMotions.includes(motion.id)
                                    ? 'bg-gradient-to-r from-purple-600 to-pink-600 text-white shadow font-semibold border border-purple-400/40'
                                    : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-white/5'"
                                :title="motion.title"
                            >
                                <span>{{ motion.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Parameter Kamera: Durasi, Pace, Ambience, Lensa -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                        <div class="space-y-1">
                            <label class="block text-[11px] font-medium text-slate-500 dark:text-neutral-400">Durasi Video</label>
                            <div class="grid grid-cols-2 gap-1 p-0.5 rounded-xl bg-slate-100 dark:bg-black/40 border border-slate-200 dark:border-white/10">
                                <button
                                    type="button"
                                    @click="captureVideoDuration = '5s'"
                                    class="py-1 rounded-lg text-center font-medium text-[11px] transition-all"
                                    :class="captureVideoDuration === '5s' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-slate-600 dark:text-neutral-400'"
                                >
                                    5 Detik
                                </button>
                                <button
                                    type="button"
                                    @click="captureVideoDuration = '10s'"
                                    class="py-1 rounded-lg text-center font-medium text-[11px] transition-all"
                                    :class="captureVideoDuration === '10s' ? 'bg-purple-600 text-white font-semibold shadow' : 'text-slate-600 dark:text-neutral-400'"
                                >
                                    10 Detik
                                </button>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[11px] font-medium text-slate-500 dark:text-neutral-400">Kecepatan</label>
                            <select
                                v-model="captureVideoPace"
                                class="w-full h-8 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-neutral-800 px-2 text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-purple-500"
                            >
                                <option value="slow">Slow (0.5x Sinematik)</option>
                                <option value="normal">Normal (1.0x Alami)</option>
                                <option value="dynamic">Dinamis (1.5x Cepat)</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[11px] font-medium text-slate-500 dark:text-neutral-400">Suasana Mood</label>
                            <select
                                v-model="captureVideoMood"
                                class="w-full h-8 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-neutral-800 px-2 text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-purple-500"
                            >
                                <option value="scene">Sesuai Gambar Asli</option>
                                <option value="golden_hour">Golden Hour (Sunset)</option>
                                <option value="overcast">Overcast (Minimalis)</option>
                                <option value="rainy">Rainy Dusk (Hujan Basah)</option>
                                <option value="night_luxury">Night Luxury (Lampu LED)</option>
                                <option value="misty_morning">Misty Morning (Embun)</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="block text-[11px] font-medium text-slate-500 dark:text-neutral-400">Profil Lensa</label>
                            <select
                                v-model="captureVideoLens"
                                class="w-full h-8 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-neutral-800 px-2 text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-purple-500"
                            >
                                <option value="anamorphic_35mm">35mm Anamorphic</option>
                                <option value="wide_16mm">16mm Ultra-Wide</option>
                                <option value="macro_50mm">50mm Bokeh Detail</option>
                            </select>
                        </div>
                    </div>

                    <!-- 3. Target Platform Video & Anti-Warping Toggle -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold uppercase tracking-wider text-slate-500 dark:text-neutral-400">Target AI Video:</span>
                            <label class="flex items-center gap-1.5 cursor-pointer select-none text-[11px] text-purple-600 dark:text-purple-300 hover:opacity-80">
                                <input
                                    type="checkbox"
                                    v-model="captureVideoStrictGeometry"
                                    class="rounded border-slate-300 dark:border-white/20 bg-slate-100 dark:bg-black/40 text-purple-600 focus:ring-0 h-3.5 w-3.5"
                                />
                                <span class="font-medium">Kunci Geometri 3D (Anti-Warping)</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-4 gap-1.5 p-1 rounded-2xl bg-slate-100 dark:bg-black/40 border border-slate-200 dark:border-white/10">
                            <button
                                type="button"
                                @click="captureVideoTarget = 'runway'"
                                class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                                :class="captureVideoTarget === 'runway' ? 'bg-purple-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                Runway Gen-3
                            </button>
                            <button
                                type="button"
                                @click="captureVideoTarget = 'kling'"
                                class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                                :class="captureVideoTarget === 'kling' ? 'bg-indigo-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                Kling AI
                            </button>
                            <button
                                type="button"
                                @click="captureVideoTarget = 'luma'"
                                class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                                :class="captureVideoTarget === 'luma' ? 'bg-pink-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                Luma
                            </button>
                            <button
                                type="button"
                                @click="captureVideoTarget = 'universal'"
                                class="py-1.5 px-2 rounded-xl text-center text-xs font-medium transition-all"
                                :class="captureVideoTarget === 'universal' ? 'bg-teal-600 text-white shadow font-semibold' : 'text-slate-600 dark:text-neutral-400 hover:text-slate-900 dark:hover:text-white'"
                            >
                                Universal
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── PROMPT BOX & ACTIONS ── -->
                <div class="p-4 sm:p-6 rounded-3xl bg-white dark:bg-neutral-900 border border-indigo-200 dark:border-indigo-500/30 shadow-lg space-y-3.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Sparkles class="h-4 w-4 text-indigo-500 dark:text-indigo-400" />
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                {{ capturePromptMode === 'video' ? 'Video AI Prompt' : (captureSelectedSyntax === 'midjourney' ? 'Midjourney Command' : 'Prompt Arsitektur') }}
                            </h3>
                            <span
                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                                :class="hasVisionAiGenerated
                                    ? 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-500/30'
                                    : 'bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border-indigo-500/30'"
                            >
                                {{ hasVisionAiGenerated ? '✨ Vision AI Aktif' : '⚡ Draft Spasial (0 Token)' }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Re-roll button -->
                            <button
                                v-if="hasVisionAiGenerated"
                                type="button"
                                :disabled="isGeneratingVisionPrompt || !imageADataUrl"
                                @click="requestVisionAiPrompt"
                                class="flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs text-slate-600 dark:text-neutral-300 hover:bg-slate-100 dark:hover:bg-white/10 transition disabled:opacity-40"
                                title="Generate ulang prompt"
                            >
                                <RefreshCw class="h-3.5 w-3.5" :class="isGeneratingVisionPrompt ? 'animate-spin' : ''" />
                                <span class="hidden sm:inline">Re-roll</span>
                            </button>

                            <!-- Copy Prompt CTA Button -->
                            <button
                                type="button"
                                @click="copyFormattedPrompt"
                                class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-md"
                                :class="copiedPrompt
                                    ? 'bg-emerald-500 text-white shadow-emerald-500/25'
                                    : (capturePromptMode === 'video' ? 'bg-purple-600 hover:bg-purple-500 text-white shadow-purple-500/25' : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-indigo-500/25')"
                            >
                                <Check v-if="copiedPrompt" class="h-3.5 w-3.5" />
                                <Copy v-else class="h-3.5 w-3.5" />
                                <span>{{ copiedPrompt ? 'Tersalin ke Clipboard!' : 'Salin Prompt' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- PRIMARY ACTION BUTTON: Dapatkan Prompt dengan Vision AI -->
                    <button
                        type="button"
                        :disabled="isGeneratingVisionPrompt || !imageADataUrl"
                        @click="requestVisionAiPrompt"
                        class="w-full py-3 px-4 rounded-2xl text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition-all shadow-md group disabled:opacity-50"
                        :class="capturePromptMode === 'video'
                            ? 'bg-gradient-to-r from-purple-600 via-pink-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white shadow-purple-500/25'
                            : 'bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 text-white shadow-indigo-500/25'"
                    >
                        <Sparkles class="h-4 w-4 text-amber-300" :class="isGeneratingVisionPrompt ? 'animate-spin' : 'group-hover:scale-110 transition-transform'" />
                        <span>
                            {{ isGeneratingVisionPrompt ? 'Menganalisis Gambar dengan Gemini Vision AI...' : (hasVisionAiGenerated ? '✨ Generate Ulang dengan Vision AI' : '✨ Dapatkan Prompt dengan Vision AI') }}
                        </span>
                    </button>

                    <!-- Textarea Editor for Prompt -->
                    <div class="relative">
                        <textarea
                            v-model="capturePrompt"
                            rows="6"
                            placeholder="Prompt visual arsitektur akan muncul di sini..."
                            class="w-full rounded-2xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-black/50 p-3.5 text-xs sm:text-sm text-slate-900 dark:text-neutral-200 placeholder:text-slate-400 dark:placeholder:text-neutral-500 focus:outline-none focus:border-indigo-500 leading-relaxed font-sans resize-y"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-neutral-400">
                        <span>💡 Dapat diedit bebas sesuai kebutuhan sebelum disalin.</span>
                        <span class="font-mono">{{ capturePrompt.length }} karakter</span>
                    </div>

                    <!-- SDXL Negative Prompt Section -->
                    <div v-if="capturePromptMode === 'image' && captureSelectedSyntax === 'sdxl'" class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-purple-500/20">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-300">
                                Negative Prompt (Wajib di SDXL):
                            </span>
                            <button
                                type="button"
                                @click="copyNegativePrompt"
                                class="flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-rose-500/15 text-rose-700 dark:text-rose-300 hover:bg-rose-500/25 border border-rose-500/30 transition-all"
                            >
                                <Check v-if="copiedNegative" class="h-3 w-3" />
                                <Copy v-else class="h-3 w-3" />
                                <span>{{ copiedNegative ? 'Tersalin!' : 'Copy Negative' }}</span>
                            </button>
                        </div>
                        <textarea
                            v-model="defaultNegativePrompt"
                            rows="2"
                            class="w-full rounded-xl border border-rose-300 dark:border-rose-500/30 bg-rose-50/50 dark:bg-black/60 p-2 text-xs text-rose-900 dark:text-rose-200 font-mono focus:outline-none focus:border-rose-500"
                        ></textarea>
                    </div>

                    <!-- Video AI Negative Prompt Section -->
                    <div v-if="capturePromptMode === 'video'" class="space-y-1.5 pt-2 border-t border-slate-200 dark:border-purple-500/20">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-300">
                                Negative Prompt Video (Anti-Warping):
                            </span>
                            <button
                                type="button"
                                @click="copyVideoNegativePrompt"
                                class="flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-purple-500/15 text-purple-700 dark:text-purple-300 hover:bg-purple-500/25 border border-purple-500/30 transition-all"
                            >
                                <Check v-if="copiedVideoNegative" class="h-3 w-3" />
                                <Copy v-else class="h-3 w-3" />
                                <span>{{ copiedVideoNegative ? 'Tersalin!' : 'Copy Negative Video' }}</span>
                            </button>
                        </div>
                        <textarea
                            v-model="defaultVideoNegativePrompt"
                            rows="2"
                            class="w-full rounded-xl border border-purple-300 dark:border-purple-500/30 bg-purple-50/50 dark:bg-black/60 p-2 text-xs text-purple-900 dark:text-purple-200 font-mono focus:outline-none focus:border-purple-500"
                        ></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════
             TAB 2: GALERI STUDIO SAYA (DEDICATED PRIVATE GALLERY)
        ═══════════════════════════════════════════════════════════════════════ -->
        <div v-show="activeViewTab === 'gallery'" class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Bookmark class="h-5 w-5 text-purple-500" />
                        <span>Koleksi Karya Studio Anda</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-neutral-400">
                        Daftar gambar dan prompt visual yang telah Anda simpan dari AI Vision Studio.
                    </p>
                </div>

                <button
                    type="button"
                    @click="activeViewTab = 'studio'"
                    class="self-start sm:self-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition flex items-center gap-1.5 shadow"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    <span>Buat Prompt Baru</span>
                </button>
            </div>

            <!-- Empty State -->
            <div
                v-if="savedCreations.length === 0"
                class="p-12 rounded-3xl border border-dashed border-slate-300 dark:border-white/10 text-center space-y-3 bg-white dark:bg-neutral-900"
            >
                <div class="h-16 w-16 mx-auto rounded-2xl bg-purple-500/10 text-purple-500 flex items-center justify-center">
                    <Bookmark class="h-8 w-8" />
                </div>
                <h4 class="text-sm font-semibold text-slate-800 dark:text-white">
                    Belum Ada Karya yang Disimpan
                </h4>
                <p class="text-xs text-slate-500 dark:text-neutral-400 max-w-sm mx-auto">
                    Unggah gambar arsitektur pada tab Studio, formulasikan prompt AI, lalu klik "Simpan ke Galeri Studio".
                </p>
                <button
                    type="button"
                    @click="activeViewTab = 'studio'"
                    class="mt-2 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold inline-flex items-center gap-1.5 transition shadow"
                >
                    <Sparkles class="h-3.5 w-3.5" />
                    <span>Mulai Sekarang</span>
                </button>
            </div>

            <!-- Grid of Saved Creations -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="creation in savedCreations"
                    :key="creation.id"
                    class="rounded-3xl border border-slate-200 dark:border-white/10 bg-white dark:bg-neutral-900 shadow-sm overflow-hidden flex flex-col group hover:border-purple-400/50 transition-all duration-200"
                >
                    <!-- Card Thumbnail -->
                    <div class="relative aspect-video bg-black overflow-hidden flex items-center justify-center">
                        <img
                            :src="creation.url"
                            :alt="creation.room_name"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        />

                        <!-- Badge Top-Left -->
                        <div class="absolute top-2.5 left-2.5 flex items-center gap-1">
                            <span
                                class="px-2 py-0.5 rounded-full text-[10px] font-semibold backdrop-blur text-white flex items-center gap-1 shadow"
                                :class="creation.prompt_type === 'video' ? 'bg-purple-600/80 border border-purple-400/30' : 'bg-blue-600/80 border border-blue-400/30'"
                            >
                                <Film v-if="creation.prompt_type === 'video'" class="h-3 w-3" />
                                <Camera v-else class="h-3 w-3" />
                                <span>{{ creation.prompt_type === 'video' ? 'Video' : 'Foto' }}</span>
                            </span>
                        </div>

                        <!-- Hover Action Buttons -->
                        <div class="absolute inset-0 bg-black/50 backdrop-blur-xs opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <button
                                type="button"
                                @click="openLightbox(creation.url)"
                                class="p-2 rounded-xl bg-white/20 hover:bg-white text-black hover:text-black transition"
                                title="Lihat Resolusi Penuh"
                            >
                                <Maximize2 class="h-4 w-4 text-white hover:text-black" />
                            </button>
                            <button
                                type="button"
                                @click="loadCreationToStudio(creation)"
                                class="px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-semibold transition flex items-center gap-1 shadow"
                                title="Buka dan Edit di Studio"
                            >
                                <span>Muat ke Studio</span>
                                <ArrowUpRight class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-bold text-slate-800 dark:text-white truncate">
                                    {{ creation.room_name }}
                                </span>
                                <span class="text-[10px] font-mono text-slate-400 dark:text-neutral-500">
                                    {{ new Date(creation.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) }}
                                </span>
                            </div>

                            <!-- Prompt Excerpt -->
                            <p class="text-[11px] text-slate-600 dark:text-neutral-400 line-clamp-3 leading-relaxed font-sans bg-slate-50 dark:bg-black/30 p-2 rounded-xl border border-slate-100 dark:border-white/5">
                                {{ creation.prompt || 'Tidak ada prompt tersimpan.' }}
                            </p>
                        </div>

                        <!-- Bottom Card Actions -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-white/5 text-xs">
                            <button
                                type="button"
                                @click="copyCreationPrompt(creation.prompt)"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center gap-1 text-[11px]"
                            >
                                <Copy class="h-3 w-3" />
                                <span>Salin Prompt</span>
                            </button>

                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="openEditModal(creation)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-white/10 transition"
                                    title="Edit Nama / Prompt"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    @click="downloadImage(creation.url, 'gallery')"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/10 transition"
                                    title="Download Gambar"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    @click="deleteCreation(creation)"
                                    class="p-1.5 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition"
                                    title="Hapus dari Galeri"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── EDIT CREATION MODAL ── -->
        <div
            v-if="editingCreation"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md overflow-y-auto"
            @click.self="editingCreation = null"
        >
            <div class="relative w-full max-w-lg rounded-3xl border border-slate-200 dark:border-white/15 bg-white dark:bg-neutral-900 shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-white/10 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Pencil class="h-4 w-4 text-indigo-500" />
                        <span>Edit Karya Studio</span>
                    </h3>
                    <button
                        type="button"
                        @click="editingCreation = null"
                        class="p-1.5 rounded-xl text-neutral-400 hover:text-white hover:bg-white/10 transition"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-neutral-400 mb-1">
                            Nama Ruangan / Judul Karya
                        </label>
                        <input
                            type="text"
                            v-model="editForm.room_name"
                            placeholder="Contoh: Fasad Rumah Modern"
                            class="w-full h-9 rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-black/40 px-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-neutral-400 mb-1">
                            Rumusan Prompt AI
                        </label>
                        <textarea
                            v-model="editForm.prompt"
                            rows="5"
                            placeholder="Tulis prompt..."
                            class="w-full rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-black/40 p-3 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 resize-y leading-relaxed font-sans"
                        ></textarea>
                    </div>

                    <div v-if="editForm.negative_prompt">
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-neutral-400 mb-1">
                            Negative Prompt
                        </label>
                        <textarea
                            v-model="editForm.negative_prompt"
                            rows="2"
                            class="w-full rounded-xl border border-slate-300 dark:border-white/10 bg-slate-50 dark:bg-black/40 p-2 text-xs text-slate-900 dark:text-white font-mono focus:outline-none focus:border-rose-500 resize-y"
                        ></textarea>
                    </div>

                    <div v-if="updateErrorMessage" class="p-2.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-xs flex items-center gap-1.5">
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        <span>{{ updateErrorMessage }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-white/10">
                    <button
                        type="button"
                        @click="editingCreation = null"
                        class="px-4 py-2 rounded-xl text-xs font-medium text-neutral-400 hover:text-white hover:bg-white/10 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="isUpdatingCreation"
                        @click="saveEditedCreation"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1.5 transition shadow disabled:opacity-50"
                    >
                        <Loader2 v-if="isUpdatingCreation" class="h-3.5 w-3.5 animate-spin" />
                        <Check v-else class="h-3.5 w-3.5" />
                        <span>{{ isUpdatingCreation ? 'Menyimpan...' : 'Simpan Perubahan' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── FULLSCREEN LIGHTBOX MODAL ── -->
        <div
            v-if="isLightboxOpen && lightboxImageUrl"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-xl"
            @click.self="isLightboxOpen = false"
        >
            <div class="relative max-w-5xl max-h-[90vh] flex flex-col items-center">
                <button
                    type="button"
                    @click="isLightboxOpen = false"
                    class="absolute -top-12 right-0 p-2 text-white/70 hover:text-white transition"
                >
                    <X class="h-6 w-6" />
                </button>
                <img :src="lightboxImageUrl" alt="Fullscreen View" class="max-h-[85vh] max-w-full rounded-2xl object-contain shadow-2xl" />
            </div>
        </div>
    </div>
</template>
