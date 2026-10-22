<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import Konva from 'konva';
import jsPDF from 'jspdf';
import FurnitureShopDrawingEditor from '@/components/drawing/FurnitureShopDrawingEditor.vue';
import {
    DraftingCompass,
    ArrowLeft,
    Save,
    Download,
    ZoomIn,
    ZoomOut,
    Maximize,
    Grid,
    MousePointer,
    Minus,
    Square,
    DoorClosed,
    DoorOpen,
    Columns,
    Type,
    Ruler,
    Trash2,
    Undo2,
    Redo2,
    Check,
    Loader2,
    Sliders,
    Eye,
    EyeOff,
    Layers,
    FileCode,
    Sparkles,
    Building2,
    Armchair,
    Sofa,
    Bath,
    Bed,
    Move,
    HelpCircle,
    LayoutTemplate,
    Infinity as InfinityIcon,
    RotateCw,
    Hand,
    Focus,
    Copy,
    Clipboard,
    ClipboardPaste,
    Files,
    Plus,
    ChevronDown,
    ChevronUp,
    ChevronRight,
    ChevronLeft,
    RotateCcw,
    Printer,
    FileText,
    Camera,
    Search,
    X,
    Settings,
    Menu,
    Compass,
    Crosshair,
    Circle as CircleIcon,
    Car,
    Trees,
    CookingPot,
    Droplets,
    ArrowUpRight,
    FlipHorizontal,
    FlipVertical,
    BringToFront,
    SendToBack,
    Hash,
    Bookmark,
    Split,
    SlidersHorizontal,
} from '@lucide/vue';

interface SheetData {
    id: string;
    project_id: string;
    title: string;
    type: string;
    scale: string;
    paper_size: string;
    orientation: string;
    width?: number | null;
    height?: number | null;
    grid_spacing: number;
    unit: string;
}

interface ProjectData {
    id: string;
    title: string;
    user_id: string;
    client_name?: string | null;
    client_address?: string | null;
}

interface ElementData {
    id: string;
    drawing_sheet_id: string;
    type: string;
    data: Record<string, any>;
    sort_order: number;
}

interface ProjectSheetItem {
    id: string;
    title: string;
    type: string;
    scale: string;
    sort_order: number;
}

interface ArchitectProfile {
    name: string;
    company_name?: string | null;
    company_type?: string | null;
    phone?: string | null;
    city_name?: string | null;
}

interface ProjectCaptureProp {
    id: string;
    code?: string | null;
    room_name: string;
    captured_hour: string;
    file_path: string;
    ai_rendered_path?: string | null;
    drawing_sheet_id?: string | null;
    room_element_id?: string | null;
    url?: string;
    ai_rendered_url?: string | null;
}

const props = defineProps<{
    sheet: SheetData;
    project: ProjectData;
    elements: ElementData[];
    projectSheets?: ProjectSheetItem[] | null;
    projectCaptures?: ProjectCaptureProp[] | null;
    canEdit: boolean;
    architect?: ArchitectProfile | null;
    initialMode?: 'shop_drawing' | 'cad' | null;
}>();

// Mode Editor: Gambar Kerja (Shop Drawing) vs CAD Denah Arsitektural
const urlParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
const requestedMode = urlParams?.get('mode');
const isShopDrawingRoute = typeof window !== 'undefined' && window.location.pathname.startsWith('/shop-drawings');

const activeEditorMode = ref<'shop_drawing' | 'cad'>(
    isShopDrawingRoute
        ? 'shop_drawing'
        : requestedMode === 'shop_drawing'
            ? 'shop_drawing'
            : requestedMode === 'cad'
                ? 'cad'
                : (props.initialMode === 'shop_drawing' ||
                   props.sheet.id === '01a16100-3421-70f5-ac75-f9db46e8c46f' ||
                   props.sheet.type === 'furniture' ||
                   props.sheet.type === 'shop_drawing')
                    ? 'shop_drawing'
                    : 'cad'
);

function switchEditorMode(mode: 'shop_drawing' | 'cad') {
    activeEditorMode.value = mode;
    if (mode === 'cad') {
        nextTick(() => {
            initCanvas();
            restoreFromState(elementList.value, true);
            setTimeout(zoomToFit, 100);
        });
    }
}

// Tool state
type ToolType =
    | 'select'
    | 'pan'
    // Struktur & Arsitektur
    | 'wall'
    | 'rect'
    | 'column'
    | 'grid_line'
    | 'stair'
    // Bukaan Pintu & Jendela
    | 'door'
    | 'double_door'
    | 'sliding_door'
    | 'window'
    // Perabot & Ruangan
    | 'sofa'
    | 'bed'
    | 'table'
    | 'kitchen'
    | 'toilet'
    | 'washbasin'
    | 'shower'
    | 'car'
    | 'tree'
    // Simbol Arsitektural & Anotasi
    | 'north_arrow'
    | 'elevation_marker'
    | 'section_marker'
    | 'leader_note'
    | 'dimension'
    | 'room_label'
    // Bentuk Geometri CAD
    | 'line'
    | 'circle'
    | 'arc'
    // Utilitas
    | 'eraser';

const activeTool = ref<ToolType>('select');
const activeToolCategory = ref<'all' | 'structure' | 'openings' | 'furniture' | 'symbols' | 'shapes'>('all');
const showCategoryMenu = ref(false);

const toolCategories = [
    { key: 'all', label: 'Semua Tools', icon: '☰' },
    { key: 'structure', label: 'Dinding & Kolom', icon: '🏛️' },
    { key: 'openings', label: 'Pintu & Jendela', icon: '🚪' },
    { key: 'furniture', label: 'Perabot & Sanitair', icon: '🛋️' },
    { key: 'symbols', label: 'Simbol & Notasi', icon: '📐' },
    { key: 'shapes', label: 'Bentuk CAD', icon: '⭕' },
] as const;

const currentCategoryObj = computed(() => {
    return toolCategories.find((c) => c.key === activeToolCategory.value) || toolCategories[0];
});

function selectCadTool(tool: ToolType) {
    activeTool.value = tool;
    showCategoryMenu.value = false;
}

// Canvas container
const containerRef = ref<HTMLDivElement | null>(null);
let stage: Konva.Stage | null = null;
let gridLayer: Konva.Layer | null = null;
let paperLayer: Konva.Layer | null = null;
let paperBgLayer: Konva.Layer | null = null;
let mainLayer: Konva.Layer | null = null;
let tempLayer: Konva.Layer | null = null;
let tr: Konva.Transformer | null = null;
let selectionOverlay: Konva.Rect | null = null;

// Header, Toolbar & Bottom HUD scroll indicators & export menu
const topBarContainerRef = ref<HTMLElement | null>(null);
const canScrollTopBarLeft = ref(false);
const canScrollTopBarRight = ref(false);

const headerContainerRef = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);

const leftToolbarRef = ref<HTMLElement | null>(null);
const canScrollToolbarUp = ref(false);
const canScrollToolbarDown = ref(false);

const bottomHudRef = ref<HTMLElement | null>(null);
const canScrollBottomHudLeft = ref(false);
const canScrollBottomHudRight = ref(false);

const showExportMenu = ref(false);
const showExportModal = ref(false);
const isExporting = ref(false);
let uiResizeObserver: ResizeObserver | null = null;

// ── CANVAS & PAPER DIMENSION STATE (FIGMA FRAME & AUTOCAD MODEL SPACE) ──────
type CanvasMode = 'sheet' | 'infinite';
const canvasMode = ref<CanvasMode>('sheet');
const lastPresetBeforeAutofit = ref<string>(props.sheet.paper_size || 'A3');

const orientation = ref<'landscape' | 'portrait'>(
    (props.sheet.orientation as 'landscape' | 'portrait') || 'landscape'
);
const paperSize = ref<string>(props.sheet.paper_size || 'A3');

const STANDARD_SIZES: Record<string, { w: number; h: number }> = {
    A0: { w: 3564, h: 2520 },
    A1: { w: 2520, h: 1782 },
    A2: { w: 1782, h: 1260 },
    A3: { w: 1260, h: 891 },
    A4: { w: 891, h: 630 },
};

const defaultInitialW = props.sheet.width || (orientation.value === 'portrait' ? 891 : 1260);
const defaultInitialH = props.sheet.height || (orientation.value === 'portrait' ? 1260 : 891);

// Dynamic Paper Position (Figma Frame Canvas Placement)
const paperX = ref<number>(60);
const paperY = ref<number>(60);
const paperWidth = ref<number>(defaultInitialW);
const paperHeight = ref<number>(defaultInitialH);
const isResizingSheet = ref(false);

// Local element memory list (initialized immediately from props for both CAD and Shop Drawing modes)
const elementList = ref<Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }>>(
    (props.elements || []).map((el: any, index: number) => ({
        id: el.id,
        type: el.type,
        data: typeof el.data === 'string' ? JSON.parse(el.data) : (el.data || {}),
        sort_order: el.sort_order ?? index,
    }))
);

// ── LOCALSTORAGE PAPER SIZES PERSISTENCE ────────────────────────────────────
const STORAGE_KEY_PAPERS = `cad_paper_sizes_${props.sheet.id}`;

interface StoredPaperSizes {
    main: {
        width: number;
        height: number;
        size: string;
        orientation: 'landscape' | 'portrait';
    };
    frames?: Record<string, {
        width: number;
        height: number;
        size?: string;
        orientation?: 'landscape' | 'portrait';
        x?: number;
        y?: number;
    }>;
    updatedAt: number;
}

function savePaperSizesToStorage() {
    if (typeof window === 'undefined' || typeof localStorage === 'undefined') return;
    try {
        const framesData: Record<string, any> = {};
        elementList.value
            .filter((el) => el.type === 'paper_frame')
            .forEach((el) => {
                const d = el.data || {};
                framesData[el.id] = {
                    width: d.width,
                    height: d.height,
                    size: d.size,
                    orientation: d.orientation,
                    x: d.x,
                    y: d.y,
                };
            });

        const payload: StoredPaperSizes = {
            main: {
                width: paperWidth.value,
                height: paperHeight.value,
                size: paperSize.value,
                orientation: orientation.value,
            },
            frames: framesData,
            updatedAt: Date.now(),
        };

        localStorage.setItem(STORAGE_KEY_PAPERS, JSON.stringify(payload));
    } catch (e) {
        console.warn('Failed to save paper sizes to localStorage:', e);
    }
}

function loadPaperSizesFromStorage() {
    if (typeof window === 'undefined' || typeof localStorage === 'undefined') return;
    try {
        const raw = localStorage.getItem(STORAGE_KEY_PAPERS);
        if (!raw) return;
        const parsed: StoredPaperSizes = JSON.parse(raw);
        if (parsed?.main) {
            if (typeof parsed.main.width === 'number' && parsed.main.width >= 300) {
                paperWidth.value = parsed.main.width;
            }
            if (typeof parsed.main.height === 'number' && parsed.main.height >= 300) {
                paperHeight.value = parsed.main.height;
            }
            if (parsed.main.size) {
                paperSize.value = parsed.main.size;
            }
            if (parsed.main.orientation === 'landscape' || parsed.main.orientation === 'portrait') {
                orientation.value = parsed.main.orientation;
            }
        }
        if (parsed?.frames && typeof parsed.frames === 'object') {
            elementList.value.forEach((el) => {
                if (el.type === 'paper_frame' && parsed.frames![el.id]) {
                    const saved = parsed.frames![el.id];
                    if (typeof saved.width === 'number' && saved.width >= 300) el.data.width = saved.width;
                    if (typeof saved.height === 'number' && saved.height >= 300) el.data.height = saved.height;
                    if (saved.size) el.data.size = saved.size;
                    if (saved.orientation) el.data.orientation = saved.orientation;
                    if (typeof saved.x === 'number') el.data.x = saved.x;
                    if (typeof saved.y === 'number') el.data.y = saved.y;
                }
            });
        }
    } catch (e) {
        console.warn('Failed to load paper sizes from localStorage:', e);
    }
}

loadPaperSizesFromStorage();

// Multi-Paper Frames on the same canvas (Figma / CAD Multi-Layout)
export interface PaperFrameData {
    id: string;
    title: string;
    x: number;
    y: number;
    width: number;
    height: number;
    size: string;
    orientation: 'landscape' | 'portrait';
    isMain: boolean;
}

const activeFocusedFrameId = ref<string>('main_frame');

function getPaperFrames(): PaperFrameData[] {
    const frames: PaperFrameData[] = [
        {
            id: 'main_frame',
            title: props.sheet.title || 'Lembar 1',
            x: paperX.value,
            y: paperY.value,
            width: paperWidth.value,
            height: paperHeight.value,
            size: paperSize.value,
            orientation: orientation.value,
            isMain: true,
        },
    ];

    elementList.value
        .filter((el) => el.type === 'paper_frame')
        .forEach((el, idx) => {
            const d = el.data || {};
            frames.push({
                id: el.id,
                title: d.title || `Lembar ${idx + 2}`,
                x: d.x ?? (paperX.value + (paperWidth.value + 120) * (idx + 1)),
                y: d.y ?? paperY.value,
                width: d.width || defaultInitialW,
                height: d.height || defaultInitialH,
                size: d.size || 'A3',
                orientation: d.orientation || 'landscape',
                isMain: false,
            });
        });

    return frames;
}

const canvasPaperFrames = computed(() => getPaperFrames());

function addPaperFrameToCanvas(customTitle?: string) {
    const existingFrames = getPaperFrames();
    const lastFrame = existingFrames[existingFrames.length - 1];

    const w = orientation.value === 'portrait' ? 891 : 1260;
    const h = orientation.value === 'portrait' ? 1260 : 891;

    // Tempatkan bersebelahan di sebelah kanan kertas sebelumnya dengan jarak 120px
    const nextX = lastFrame ? Math.round(lastFrame.x + lastFrame.width + 120) : 60;
    const nextY = lastFrame ? lastFrame.y : 60;

    const frameNumber = existingFrames.length + 1;
    const title = customTitle || `Lembar ${frameNumber}: Ruangan ${frameNumber}`;

    const newFrameElement = {
        id: 'frame_' + Date.now().toString(36) + Math.random().toString(36).substring(2, 6),
        type: 'paper_frame',
        data: {
            title,
            x: nextX,
            y: nextY,
            width: w,
            height: h,
            size: 'A3',
            orientation: orientation.value,
        },
        sort_order: elementList.value.length,
    };

    elementList.value.push(newFrameElement);
    drawPaperBoundary();
    savePaperSizesToStorage();
    focusOnFrame({
        id: newFrameElement.id,
        title,
        x: nextX,
        y: nextY,
        width: w,
        height: h,
        size: 'A3',
        orientation: orientation.value,
        isMain: false,
    });
    triggerAutoSaveDebounced();
    toast.success(`Berhasil menambahkan ${title} (${w}×${h} px) ke kanvas! Sekarang ada ${existingFrames.length + 1} lembar kertas berdampingan.`);
}

const showDeleteFrameModal = ref(false);
const frameToDelete = ref<PaperFrameData | null>(null);
const activeExportFrameId = ref<string>('main_frame');

const selectedExportFrame = computed(() => {
    const frames = canvasPaperFrames.value;
    return (
        frames.find((f) => f.id === activeExportFrameId.value) ||
        frames[0] || {
            id: 'main_frame',
            title: props.sheet.title,
            width: paperWidth.value,
            height: paperHeight.value,
            size: paperSize.value,
            orientation: orientation.value,
            isMain: true,
            x: paperX.value,
            y: paperY.value,
        }
    );
});

function confirmRemovePaperFrame(frame: PaperFrameData) {
    if (frame.isMain) return;
    frameToDelete.value = frame;
    showDeleteFrameModal.value = true;
}

function executeRemovePaperFrame() {
    if (!frameToDelete.value) return;
    const targetId = frameToDelete.value.id;
    showDeleteFrameModal.value = false;
    frameToDelete.value = null;
    removePaperFrame(targetId);
}

function removePaperFrame(frameId: string) {
    const idx = elementList.value.findIndex((el) => el.id === frameId && el.type === 'paper_frame');
    if (idx !== -1) {
        elementList.value.splice(idx, 1);
        if (activeFocusedFrameId.value === frameId) {
            activeFocusedFrameId.value = 'main_frame';
        }
        if (activeExportFrameId.value === frameId) {
            activeExportFrameId.value = 'main_frame';
        }
        drawPaperBoundary();
        savePaperSizesToStorage();
        zoomToFit();
        triggerAutoSaveDebounced();
        toast.success('Lembar kertas tambahan berhasil dihapus dari kanvas.');
    }
}

function focusOnFrame(frame: PaperFrameData) {
    activeFocusedFrameId.value = frame.id;
    if (canvasMode.value !== 'sheet') {
        canvasMode.value = 'sheet';
        drawGrid();
        drawPaperBoundary();
        restoreFromState(elementList.value, true);
    }
    if (!stage || !containerRef.value) return;

    const containerW = containerRef.value.clientWidth || 1000;
    const containerH = containerRef.value.clientHeight || 700;
    const padding = 70;

    const scaleX = (containerW - padding * 2) / frame.width;
    const scaleY = (containerH - padding * 2) / frame.height;
    const fitScale = Math.max(0.2, Math.min(1.5, Math.min(scaleX, scaleY)));

    zoomLevel.value = fitScale;
    stage.scale({ x: fitScale, y: fitScale });

    const centerX = frame.x + frame.width / 2;
    const centerY = frame.y + frame.height / 2;
    const newX = containerW / 2 - centerX * fitScale;
    const newY = containerH / 2 - centerY * fitScale;

    stage.position({ x: newX, y: newY });
    stage.batchDraw();
    toast.success(`Fokus ke ${frame.title}`);
}

// Canvas state
const isSnapToGrid = ref(true);
const gridSpacing = ref(props.sheet.grid_spacing || 20);
const zoomLevel = ref(1);
const isSaving = ref(false);
const saveSuccess = ref(false);
const isAutoSaving = ref(false);
const selectedNodeId = ref<string | null>(null);
const selectedElementData = ref<Record<string, any> | null>(null);

// ── Sheet Configuration & Settings State ─────────────────────────────────────
const currentSheet = ref<SheetData>({ ...props.sheet });
const showSheetSettingsModal = ref(false);
const isSavingSheetSettings = ref(false);
const sheetForm = ref({
    title: props.sheet.title,
    type: props.sheet.type,
    scale: props.sheet.scale || '1:100',
    unit: props.sheet.unit || 'm',
    paper_size: props.sheet.paper_size || 'A3',
});

function calculateDimensionText(data: Record<string, any>, unit: string = currentSheet.value.unit || 'm'): string {
    const x1 = data.x1 ?? 0;
    const y1 = data.y1 ?? 0;
    const x2 = data.x2 ?? 100;
    const y2 = data.y2 ?? 0;
    const distPx = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));

    if (unit === 'cm') {
        const val = Math.round((distPx / gridSpacing.value) * 50);
        return `${val} cm`;
    } else if (unit === 'mm') {
        const val = Math.round((distPx / gridSpacing.value) * 500);
        return `${val} mm`;
    } else {
        const val = ((distPx / gridSpacing.value) * 0.5).toFixed(2);
        return `${val} m`;
    }
}

function getAutoDimensionPlaceholder(data: Record<string, any>): string {
    return calculateDimensionText(data, currentSheet.value.unit);
}

function updateDimensionCustomText(newVal: string) {
    if (!selectedNodeId.value || !selectedElementData.value) return;
    selectedElementData.value.text = newVal;

    const el = elementList.value.find((x) => x.id === selectedNodeId.value);
    if (el) {
        el.data = { ...el.data, text: newVal };
    }

    if (mainLayer) {
        const node = mainLayer.findOne('#' + selectedNodeId.value) as Konva.Group;
        if (node) {
            const textNode = (node.findOne('.dimText') || node.findOne('Text')) as Konva.Text;
            if (textNode) {
                const autoVal = calculateDimensionText(selectedElementData.value, currentSheet.value.unit);
                textNode.text(newVal && newVal.trim() !== '' ? newVal : autoVal);
                mainLayer.batchDraw();
            }
        }
    }
    triggerAutoSaveDebounced();
}

function resetDimensionToAuto() {
    updateDimensionCustomText('');
}

async function saveSheetSettings() {
    if (!props.canEdit || isSavingSheetSettings.value) return;
    isSavingSheetSettings.value = true;
    try {
        const response = await fetch(`/drawings/${props.sheet.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                title: sheetForm.value.title,
                type: sheetForm.value.type,
                scale: sheetForm.value.scale,
                unit: sheetForm.value.unit,
                paper_size: sheetForm.value.paper_size,
            }),
        });

        if (response.ok) {
            const oldUnit = currentSheet.value.unit;
            currentSheet.value = {
                ...currentSheet.value,
                title: sheetForm.value.title,
                type: sheetForm.value.type as any,
                scale: sheetForm.value.scale,
                unit: sheetForm.value.unit,
                paper_size: sheetForm.value.paper_size,
            };
            showSheetSettingsModal.value = false;
            toast.success('Pengaturan lembar kerja berhasil diperbarui.');

            if (oldUnit !== sheetForm.value.unit) {
                restoreFromState(elementList.value, true);
            }
        } else {
            toast.error('Gagal memperbarui pengaturan lembar kerja.');
        }
    } catch (err) {
        console.error('[Editor] Failed to update sheet settings:', err);
        toast.error('Terjadi kesalahan jaringan.');
    } finally {
        isSavingSheetSettings.value = false;
    }
}

// ── 3D Capture Reference Synchronization ─────────────────────────────────────
const localProjectCaptures = ref<ProjectCaptureProp[]>([...(props.projectCaptures || [])]);
const captureSearchQuery = ref('');
const isCaptureDropdownOpen = ref(false);

const boundCapture = computed(() => {
    if (!selectedNodeId.value) return null;
    return (
        localProjectCaptures.value.find(
            (c) =>
                c.room_element_id === selectedNodeId.value ||
                (selectedElementData.value?.capture_id && c.id === selectedElementData.value.capture_id)
        ) || null
    );
});

const filteredCaptureReferences = computed(() => {
    const list = localProjectCaptures.value;
    const query = captureSearchQuery.value.trim().toLowerCase();
    if (!query) {
        return list.slice(0, 5);
    }
    return list
        .filter((c) => {
            const matchCode = (c.code || '').toLowerCase().includes(query);
            const matchRoom = (c.room_name || '').toLowerCase().includes(query);
            const matchHour = (c.captured_hour || '').toLowerCase().includes(query);
            return matchCode || matchRoom || matchHour;
        })
        .slice(0, 10);
});

function getCaptureUsage(cap: ProjectCaptureProp): { isUsed: boolean; isCurrent: boolean; roomLabel?: string } {
    const isCurrent = boundCapture.value?.id === cap.id;

    // Cek apakah tertaut ke salah satu elemen di lembar kerja saat ini
    const boundEl = elementList.value.find(
        (el) => el.data?.capture_id === cap.id || el.id === cap.room_element_id
    );

    if (boundEl) {
        const roomName = boundEl.data?.text ? boundEl.data.text.split('\n')[0].trim() : (boundEl.type || 'Elemen');
        return {
            isUsed: true,
            isCurrent,
            roomLabel: roomName,
        };
    }

    // Cek apakah tertaut ke elemen/lembar kerja lain di database
    if (cap.room_element_id || cap.drawing_sheet_id) {
        return {
            isUsed: true,
            isCurrent,
            roomLabel: cap.room_name || 'Ruangan lain',
        };
    }

    return {
        isUsed: false,
        isCurrent: false,
    };
}

async function bindCaptureToElement(capture: ProjectCaptureProp) {
    if (!selectedNodeId.value || !props.canEdit) return;

    if (selectedElementData.value) {
        selectedElementData.value.capture_id = capture.id;
        selectedElementData.value.capture_code = capture.code;
    }
    const found = elementList.value.find((x) => x.id === selectedNodeId.value);
    if (found) {
        found.data = {
            ...found.data,
            capture_id: capture.id,
            capture_code: capture.code,
        };
    }

    localProjectCaptures.value = localProjectCaptures.value.map((c) => {
        if (c.id === capture.id) {
            return { ...c, drawing_sheet_id: props.sheet.id, room_element_id: selectedNodeId.value };
        }
        if (c.room_element_id === selectedNodeId.value && c.id !== capture.id) {
            return { ...c, drawing_sheet_id: null, room_element_id: null };
        }
        return c;
    });

    isCaptureDropdownOpen.value = false;
    captureSearchQuery.value = '';

    try {
        await fetch(`/projects/${props.project.id}/captures/${capture.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                drawing_sheet_id: props.sheet.id,
                room_element_id: selectedNodeId.value,
            }),
        });
        toast.success(`Visual 3D [${capture.code || capture.room_name}] terhubung ke elemen!`);
    } catch (err) {
        console.error('[Editor] Failed to link capture:', err);
    }

    restoreFromState(elementList.value, true);
    triggerAutoSaveDebounced();
}

async function unbindCaptureFromElement() {
    if (!selectedNodeId.value || !props.canEdit) return;
    const currentCap = boundCapture.value;

    if (selectedElementData.value) {
        selectedElementData.value.capture_id = null;
        selectedElementData.value.capture_code = null;
    }
    const found = elementList.value.find((x) => x.id === selectedNodeId.value);
    if (found) {
        const newData = { ...found.data };
        delete newData.capture_id;
        delete newData.capture_code;
        found.data = newData;
    }

    if (currentCap) {
        localProjectCaptures.value = localProjectCaptures.value.map((c) => {
            if (c.id === currentCap.id) {
                return { ...c, drawing_sheet_id: null, room_element_id: null };
            }
            return c;
        });

        try {
            await fetch(`/projects/${props.project.id}/captures/${currentCap.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    drawing_sheet_id: null,
                    room_element_id: null,
                }),
            });
            toast.info(`Tautan visual 3D dilepas.`);
        } catch (err) {
            console.error('[Editor] Failed to unlink capture:', err);
        }
    }

    isCaptureDropdownOpen.value = false;
    restoreFromState(elementList.value, true);
    triggerAutoSaveDebounced();
}

// Pan state with spacebar & modifier keys for multi-select and clone-drag
const isSpacePressed = ref(false);
const isCtrlHeld = ref(false);
const isAltHeld = ref(false);

// Layer visibility manager
const layersVisibility = ref({
    walls: true,
    openings: true,
    structure: true,
    furniture: true,
    dimensions: true,
    annotations: true,
});
const showLayerPanel = ref(false);
const showHelpModal = ref(false);

// ── UNDO / REDO HISTORY STACK ───────────────────────────────────────────────
const historyStack = ref<string[]>([]);
const historyIndex = ref(-1);
let isPerformingHistoryAction = false;

function pushHistory() {
    if (isPerformingHistoryAction) return;

    // Truncate future if branched
    if (historyIndex.value < historyStack.value.length - 1) {
        historyStack.value = historyStack.value.slice(0, historyIndex.value + 1);
    }

    const snapshot = JSON.stringify(elementList.value);
    historyStack.value.push(snapshot);
    historyIndex.value = historyStack.value.length - 1;

    // Trigger auto-save debounce
    triggerAutoSaveDebounced();
}

function undo() {
    if (historyIndex.value > 0) {
        isPerformingHistoryAction = true;
        historyIndex.value--;
        const previousState = JSON.parse(historyStack.value[historyIndex.value]);
        restoreFromState(previousState);
        isPerformingHistoryAction = false;
        triggerAutoSaveDebounced();
    }
}

function redo() {
    if (historyIndex.value < historyStack.value.length - 1) {
        isPerformingHistoryAction = true;
        historyIndex.value++;
        const nextState = JSON.parse(historyStack.value[historyIndex.value]);
        restoreFromState(nextState);
        isPerformingHistoryAction = false;
        triggerAutoSaveDebounced();
    }
}

function drawSelectionHighlights() {
    if (!tempLayer) return;

    // Clear old individual highlights
    const oldHighlights = tempLayer.find('.individual-highlight');
    oldHighlights.forEach((h) => h.destroy());

    const nodes = tr?.nodes() || [];
    // Batasi individual highlight hanya jika objek sedikit (<= 30 objek)
    // Jika objek banyak (seperti saat Select All ratusan objek), selectionOverlay sudah cukup
    // membungkus seluruh denah tanpa menurunkan FPS canvas!
    if (nodes.length > 1 && nodes.length <= 30) {
        nodes.forEach((node) => {
            try {
                const box = node.getClientRect({ skipTransform: false, relativeTo: stage! });
                const pad = 4;
                const bx = box.x - pad;
                const by = box.y - pad;
                const bw = box.width + pad * 2;
                const bh = box.height + pad * 2;

                // 1. Dashed Cyan Outline on this object
                const highlight = new Konva.Rect({
                    name: 'individual-highlight',
                    x: bx,
                    y: by,
                    width: bw,
                    height: bh,
                    stroke: '#06b6d4', // Vibrant Cyan (AutoCAD Grip color)
                    strokeWidth: 2,
                    dash: [5, 3],
                    cornerRadius: 3,
                    listening: false,
                });
                tempLayer!.add(highlight);

                // 2. AutoCAD style Grips (small squares on corners)
                const gripSize = 6;
                const gripPositions = [
                    { x: bx - gripSize / 2, y: by - gripSize / 2 },
                    { x: bx + bw - gripSize / 2, y: by - gripSize / 2 },
                    { x: bx - gripSize / 2, y: by + bh - gripSize / 2 },
                    { x: bx + bw - gripSize / 2, y: by + bh - gripSize / 2 },
                ];
                gripPositions.forEach((pos) => {
                    tempLayer!.add(
                        new Konva.Rect({
                            name: 'individual-highlight',
                            x: pos.x,
                            y: pos.y,
                            width: gripSize,
                            height: gripSize,
                            fill: '#06b6d4',
                            stroke: '#ffffff',
                            strokeWidth: 1,
                            listening: false,
                        })
                    );
                });
            } catch {}
        });
    }

    tempLayer.batchDraw();
}

function updateSelectionOverlay() {
    if (!selectionOverlay || !tr || tr.nodes().length === 0 || !props.canEdit || activeTool.value !== 'select') {
        if (selectionOverlay) selectionOverlay.visible(false);
        if (tr) {
            tr.visible(false);
            tr.nodes([]);
        }
        drawSelectionHighlights();
        return;
    }

    const nodes = tr.nodes();

    // JIKA MULTI-SELEKSI (> 1 OBJEK ATAU SELECT ALL):
    // Hitung bounding box gabungan seluruh objek terpilih dan aktifkan selectionOverlay
    // agar pengguna bisa dengan sangat mudah mengklik dan men-drag dari mana saja di dalam area denah!
    if (nodes.length > 1) {
        tr.visible(false);
        tr.borderStrokeWidth(0);
        tr.resizeEnabled(false);
        tr.rotateEnabled(false);
        tr.enabledAnchors([]);

        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        nodes.forEach((node) => {
            try {
                const box = node.getClientRect({ skipTransform: false, relativeTo: mainLayer! });
                if (box.width > 0 || box.height > 0) {
                    minX = Math.min(minX, box.x);
                    minY = Math.min(minY, box.y);
                    maxX = Math.max(maxX, box.x + box.width);
                    maxY = Math.max(maxY, box.y + box.height);
                }
            } catch {}
        });

        if (minX !== Infinity && maxX !== -Infinity) {
            const pad = 12;
            selectionOverlay.setAttrs({
                x: Math.round(minX - pad),
                y: Math.round(minY - pad),
                width: Math.round(maxX - minX + pad * 2),
                height: Math.round(maxY - minY + pad * 2),
                visible: true,
                draggable: props.canEdit,
                fill: 'rgba(139, 92, 246, 0.05)',
                stroke: '#8b5cf6',
                strokeWidth: 1.5,
                dash: [6, 4],
                cornerRadius: 6,
                listening: true,
            });
            selectionOverlay.moveToTop();
        } else {
            selectionOverlay.visible(false);
        }
    } else {
        // Seleksi tunggal: tampilkan transformer normal dengan handle resize & rotate
        if (selectionOverlay) selectionOverlay.visible(false);
        tr.visible(true);
        tr.borderStrokeWidth(1.5);
        tr.borderStroke('#8b5cf6');
        tr.resizeEnabled(true);
        tr.rotateEnabled(true);
        tr.enabledAnchors(['top-left', 'top-center', 'top-right', 'middle-right', 'middle-left', 'bottom-left', 'bottom-center', 'bottom-right']);
        tr.moveToTop();
    }

    drawSelectionHighlights();
    mainLayer?.batchDraw();
}

function restoreFromState(
    state: Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }>,
    preserveSelection = false
) {
    const selectedIdsToKeep = preserveSelection && tr ? tr.nodes().map((n) => n.id()) : [];

    elementList.value = state;
    if (!mainLayer) return;

    // Clear existing nodes safely without mutating live array mid-iteration
    tr?.nodes([]);
    const existingChildren = [...mainLayer.getChildren()];
    existingChildren.forEach((n) => {
        if (n !== tr && n !== selectionOverlay) {
            n.destroy();
        }
    });

    const reSelectedNodes: Konva.Group[] = [];
    elementList.value.forEach((item) => {
        if (item.type === 'paper_frame') return;
        const node = createShapeFromElement(item);
        if (node) {
            applyLayerVisibilityToNode(node, item.type);
            mainLayer!.add(node);
            if (selectedIdsToKeep.includes(item.id)) {
                reSelectedNodes.push(node);
            }
        }
    });

    if (reSelectedNodes.length > 0 && tr) {
        tr.nodes(reSelectedNodes);
        tr.moveToTop();
        if (reSelectedNodes.length === 1) {
            selectedNodeId.value = reSelectedNodes[0].id();
            const found = elementList.value.find((x) => x.id === reSelectedNodes[0].id());
            selectedElementData.value = found ? { ...found.data, type: found.type } : null;
        } else {
            selectedNodeId.value = 'multi';
            selectedElementData.value = {
                type: `${reSelectedNodes.length} Objek Terpilih`,
                count: reSelectedNodes.length,
            };
        }
    } else {
        selectedNodeId.value = null;
        selectedElementData.value = null;
    }

    updateSelectionOverlay();
    mainLayer.batchDraw();
}

// ── AUTO-SAVE WITH DEBOUNCE ────────────────────────────────────────────────
let autoSaveTimer: any = null;
function triggerAutoSaveDebounced() {
    if (!props.canEdit) return;
    clearTimeout(autoSaveTimer);
    isAutoSaving.value = true;
    autoSaveTimer = setTimeout(() => {
        saveElements(true);
    }, 2500);
}

// Snapping helper
function snap(val: number): number {
    if (!isSnapToGrid.value) return val;
    return Math.round(val / gridSpacing.value) * gridSpacing.value;
}

// Map element types to CAD layer category
function getLayerCategory(type: string): keyof typeof layersVisibility.value {
    switch (type) {
        case 'wall':
        case 'rect':
            return 'walls';
        case 'door':
        case 'double_door':
        case 'sliding_door':
        case 'window':
            return 'openings';
        case 'column':
        case 'stair':
        case 'grid_line':
            return 'structure';
        case 'toilet':
        case 'bed':
        case 'table':
        case 'dining_table':
        case 'sofa':
        case 'kitchen':
        case 'washbasin':
        case 'shower':
        case 'car':
        case 'tree':
            return 'furniture';
        case 'dimension':
            return 'dimensions';
        case 'room_label':
        case 'text':
        case 'north_arrow':
        case 'elevation_marker':
        case 'section_marker':
        case 'leader_note':
        case 'line':
        case 'circle':
        case 'arc':
        default:
            return 'annotations';
    }
}

function applyLayerVisibilityToNode(node: Konva.Node, type: string) {
    const category = getLayerCategory(type);
    node.visible(layersVisibility.value[category]);
}

function toggleLayer(category: keyof typeof layersVisibility.value) {
    layersVisibility.value[category] = !layersVisibility.value[category];
    if (!mainLayer) return;

    mainLayer.getChildren().forEach((node) => {
        if (node === tr) return;
        const type = node.name();
        if (getLayerCategory(type) === category) {
            node.visible(layersVisibility.value[category]);
        }
    });

    mainLayer.batchDraw();
}

// ── CANVAS MODE & DYNAMIC SHEET SIZING HELPERS ─────────────────────────────
function setCanvasMode(mode: CanvasMode) {
    canvasMode.value = mode;
    drawGrid();
    drawPaperBoundary();
    restoreFromState(elementList.value, true);
    zoomToFit();
    nextTick(() => checkHeaderScroll());
    if (mode === 'infinite') {
        toast.info('Mode AutoCAD Model Space (∞) aktif. Seluruh denah dan frame kertas tampak di kanvas tak terbatas.');
    } else {
        toast.info('Mode Frame Kertas aktif. Lembar kerja siap untuk tata letak & cetak.');
    }
}

function applyPaperPreset(preset: string) {
    paperSize.value = preset;
    if (preset === 'Infinite') {
        canvasMode.value = 'infinite';
        drawPaperBoundary();
        return;
    }

    canvasMode.value = 'sheet';
    if (STANDARD_SIZES[preset]) {
        const dims = STANDARD_SIZES[preset];
        if (orientation.value === 'portrait') {
            paperWidth.value = dims.h;
            paperHeight.value = dims.w;
        } else {
            paperWidth.value = dims.w;
            paperHeight.value = dims.h;
        }
    }
    drawPaperBoundary();
    savePaperSizesToStorage();
    triggerAutoSaveDebounced();
}

function toggleOrientation() {
    orientation.value = orientation.value === 'landscape' ? 'portrait' : 'landscape';
    const temp = paperWidth.value;
    paperWidth.value = paperHeight.value;
    paperHeight.value = temp;
    drawPaperBoundary();
    savePaperSizesToStorage();
    triggerAutoSaveDebounced();
}

function onWidthInput(newVal: number) {
    if (newVal >= 300) {
        paperWidth.value = Math.round(newVal);
        paperSize.value = 'Custom';
        drawPaperBoundary();
        savePaperSizesToStorage();
        triggerAutoSaveDebounced();
    }
}

function onHeightInput(newVal: number) {
    if (newVal >= 300) {
        paperHeight.value = Math.round(newVal);
        paperSize.value = 'Custom';
        drawPaperBoundary();
        savePaperSizesToStorage();
        triggerAutoSaveDebounced();
    }
}

// ── OFFSET & ELEMENT MANIPULATION HELPERS ──────────────────────────────────
function applyOffsetToElement(item: { id: string; type: string; data: Record<string, any> }, dx: number, dy: number) {
    if (dx === 0 && dy === 0) return;
    const d = item.data;
    if (d.x1 !== undefined && d.x2 !== undefined) {
        d.x1 = Math.round(d.x1 + dx);
        d.x2 = Math.round(d.x2 + dx);
        d.y1 = Math.round(d.y1 + dy);
        d.y2 = Math.round(d.y2 + dy);
    }
    if (d.x !== undefined) {
        d.x = Math.round(d.x + dx);
        d.y = Math.round(d.y + dy);
    }
}

// ── SELECT ALL ELEMENTS & MOVE ENTIRE DRAWING (AUTOCAD "MOVE ALL") ─────────
function selectAllElements() {
    showCategoryMenu.value = false;
    if (!mainLayer || !tr || !props.canEdit) return;
    activeTool.value = 'select';
    const allShapes = mainLayer.getChildren((node) => node !== tr && node !== selectionOverlay) as Konva.Group[];
    if (allShapes.length === 0) return;

    allShapes.forEach((n) => n.draggable(props.canEdit));
    tr.nodes(allShapes);
    selectedNodeId.value = 'all';
    selectedElementData.value = {
        type: 'Semua Elemen Proyek (Multi-Seleksi)',
        count: allShapes.length,
    };
    updateSelectionOverlay();
    mainLayer.batchDraw();
    toast.info(`${allShapes.length} objek denah terpilih. Klik & geser kotak seleksi untuk memindahkan seluruh denah.`);
}

// ── COPY, PASTE & DUPLICATE (SALIN PER OBJEK ATAU SELURUH OBJEK) ───────────
const CLIPBOARD_KEY = 'pitcharch_cad_clipboard';
const clipboard = ref<Array<{ type: string; data: Record<string, any> }>>([]);
const isJustCopied = ref(false);
let copyTimer: any = null;
let pasteCount = 1;

function copySelected() {
    let itemsToCopy: Array<{ type: string; data: Record<string, any> }> = [];

    if (tr && tr.nodes().length > 0) {
        const ids = new Set(tr.nodes().map((n) => n.id()));
        itemsToCopy = elementList.value
            .filter((el) => ids.has(el.id))
            .map((el) => ({ type: el.type, data: JSON.parse(JSON.stringify(el.data)) }));
    } else if (selectedNodeId.value === 'all') {
        itemsToCopy = elementList.value.map((el) => ({
            type: el.type,
            data: JSON.parse(JSON.stringify(el.data)),
        }));
    } else if (selectedNodeId.value) {
        const found = elementList.value.find((el) => el.id === selectedNodeId.value);
        if (found) {
            itemsToCopy = [{ type: found.type, data: JSON.parse(JSON.stringify(found.data)) }];
        }
    }

    if (itemsToCopy.length === 0) {
        toast.info('Pilih satu atau beberapa objek terlebih dahulu untuk disalin (klik atau blok di kanvas).');
        return;
    }

    clipboard.value = itemsToCopy;
    try {
        sessionStorage.setItem(CLIPBOARD_KEY, JSON.stringify(itemsToCopy));
    } catch {}

    pasteCount = 1;
    isJustCopied.value = true;
    clearTimeout(copyTimer);
    copyTimer = setTimeout(() => {
        isJustCopied.value = false;
    }, 2500);

    toast.success(`${itemsToCopy.length} objek berhasil disalin! Tekan tombol "Tempel" atau Ctrl+V untuk menempel.`);
}

function pasteClipboard() {
    if (!props.canEdit || !mainLayer) return;

    let items = clipboard.value;
    if (items.length === 0) {
        try {
            const raw = sessionStorage.getItem(CLIPBOARD_KEY);
            if (raw) items = JSON.parse(raw);
        } catch {}
    }

    if (!items || items.length === 0) {
        toast.info('Clipboard kosong. Silakan salin objek (Ctrl+C) terlebih dahulu.');
        return;
    }

    const offset = pasteCount * snap(40);
    pasteCount++;

    const newNodes: Konva.Group[] = [];
    const newElements: Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }> = [];

    items.forEach((item) => {
        const newId = 'el_' + Math.random().toString(36).substring(2, 9);
        const clonedData = JSON.parse(JSON.stringify(item.data));
        const newItem = {
            id: newId,
            type: item.type,
            data: clonedData,
            sort_order: elementList.value.length + newElements.length,
        };

        applyOffsetToElement(newItem, offset, offset);

        newElements.push(newItem);
        elementList.value.push(newItem);

        const node = createShapeFromElement(newItem);
        applyLayerVisibilityToNode(node, newItem.type);
        mainLayer!.add(node);
        newNodes.push(node);
    });

    if (tr && newNodes.length > 0) {
        activeTool.value = 'select';
        newNodes.forEach((n) => n.draggable(props.canEdit));
        tr.nodes(newNodes);
        tr.moveToTop();
        if (newNodes.length === 1) {
            selectedNodeId.value = newNodes[0].id();
            const found = elementList.value.find((x) => x.id === newNodes[0].id());
            selectedElementData.value = found ? { ...found.data, type: found.type } : null;
        } else {
            selectedNodeId.value = 'multi';
            selectedElementData.value = {
                type: `${newNodes.length} Objek Ditempel (Terblok)`,
                count: newNodes.length,
            };
        }
        updateSelectionOverlay();
    }

    mainLayer.batchDraw();
    pushHistory();
    triggerAutoSaveDebounced();
    toast.success(`${newElements.length} objek berhasil ditempel & siap digeser.`);
}

function duplicateSelected() {
    if (!props.canEdit || !mainLayer) return;

    let itemsToDuplicate: Array<{ type: string; data: Record<string, any> }> = [];

    if (tr && tr.nodes().length > 0) {
        const ids = new Set(tr.nodes().map((n) => n.id()));
        itemsToDuplicate = elementList.value
            .filter((el) => ids.has(el.id))
            .map((el) => ({ type: el.type, data: JSON.parse(JSON.stringify(el.data)) }));
    } else if (selectedNodeId.value === 'all') {
        itemsToDuplicate = elementList.value.map((el) => ({
            type: el.type,
            data: JSON.parse(JSON.stringify(el.data)),
        }));
    } else if (selectedNodeId.value) {
        const found = elementList.value.find((el) => el.id === selectedNodeId.value);
        if (found) {
            itemsToDuplicate = [{ type: found.type, data: JSON.parse(JSON.stringify(found.data)) }];
        }
    }

    if (itemsToDuplicate.length === 0) {
        toast.info('Pilih satu atau beberapa objek terlebih dahulu untuk diduplikat.');
        return;
    }

    const offset = snap(40);
    const newNodes: Konva.Group[] = [];
    const newElements: Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }> = [];

    itemsToDuplicate.forEach((item) => {
        const newId = 'el_' + Math.random().toString(36).substring(2, 9);
        const clonedData = JSON.parse(JSON.stringify(item.data));
        const newItem = {
            id: newId,
            type: item.type,
            data: clonedData,
            sort_order: elementList.value.length + newElements.length,
        };

        applyOffsetToElement(newItem, offset, offset);

        newElements.push(newItem);
        elementList.value.push(newItem);

        const node = createShapeFromElement(newItem);
        applyLayerVisibilityToNode(node, newItem.type);
        mainLayer!.add(node);
        newNodes.push(node);
    });

    if (tr && newNodes.length > 0) {
        activeTool.value = 'select';
        newNodes.forEach((n) => n.draggable(props.canEdit));
        tr.nodes(newNodes);
        tr.moveToTop();
        if (newNodes.length === 1) {
            selectedNodeId.value = newNodes[0].id();
            const found = elementList.value.find((x) => x.id === newNodes[0].id());
            selectedElementData.value = found ? { ...found.data, type: found.type } : null;
        } else {
            selectedNodeId.value = 'multi';
            selectedElementData.value = {
                type: `${newNodes.length} Objek Diduplikat (Terblok)`,
                count: newNodes.length,
            };
        }
        updateSelectionOverlay();
    }

    mainLayer.batchDraw();
    pushHistory();
    triggerAutoSaveDebounced();
    toast.success(`${newElements.length} objek berhasil diduplikat (Ctrl+D).`);
}

function copyAllElements() {
    selectAllElements();
    copySelected();
}

function duplicateAllElements() {
    selectAllElements();
    duplicateSelected();
}

// ── CAD TRANSFORM TOOLS (ROTATE, FLIP, Z-ORDER, METADATA) ───────────────────
function rotateSelected(angleDeg: number = 90) {
    if (!props.canEdit || !tr) return;
    const selectedNodes = tr.nodes();
    if (selectedNodes.length === 0) return;

    const ids = new Set(selectedNodes.map((n) => n.id()));
    const targetElements = elementList.value.filter((el) => ids.has(el.id));
    if (targetElements.length === 0) return;

    const rad = (angleDeg * Math.PI) / 180;
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);

    const bboxes = targetElements.map(getElementBoundingBox);
    const minX = Math.min(...bboxes.map((b) => b.x));
    const maxX = Math.max(...bboxes.map((b) => b.x + b.width));
    const minY = Math.min(...bboxes.map((b) => b.y));
    const maxY = Math.max(...bboxes.map((b) => b.y + b.height));
    const groupCx = (minX + maxX) / 2;
    const groupCy = (minY + maxY) / 2;

    targetElements.forEach((el) => {
        const d = el.data;
        const selfBox = getElementBoundingBox(el);
        const selfCx = selfBox.x + selfBox.width / 2;
        const selfCy = selfBox.y + selfBox.height / 2;

        const pivotX = targetElements.length > 1 ? groupCx : selfCx;
        const pivotY = targetElements.length > 1 ? groupCy : selfCy;

        if (d.x1 !== undefined && d.x2 !== undefined && d.y1 !== undefined && d.y2 !== undefined) {
            const rx1 = pivotX + (d.x1 - pivotX) * cos - (d.y1 - pivotY) * sin;
            const ry1 = pivotY + (d.x1 - pivotX) * sin + (d.y1 - pivotY) * cos;
            const rx2 = pivotX + (d.x2 - pivotX) * cos - (d.y2 - pivotY) * sin;
            const ry2 = pivotY + (d.x2 - pivotX) * sin + (d.y2 - pivotY) * cos;
            d.x1 = Math.round(rx1);
            d.y1 = Math.round(ry1);
            d.x2 = Math.round(rx2);
            d.y2 = Math.round(ry2);
        } else if (d.x !== undefined && d.y !== undefined) {
            const rx = pivotX + (selfCx - pivotX) * cos - (selfCy - pivotY) * sin;
            const ry = pivotY + (selfCx - pivotX) * sin + (selfCy - pivotY) * cos;
            d.rotation = (((d.rotation || 0) + angleDeg) % 360 + 360) % 360;
            if (Math.abs(angleDeg % 180) === 90 && d.width !== undefined && d.height !== undefined && targetElements.length === 1) {
                const tmp = d.width;
                d.width = d.height;
                d.height = tmp;
            }
            const newBox = getElementBoundingBox(el);
            d.x = Math.round(rx - newBox.width / 2);
            d.y = Math.round(ry - newBox.height / 2);
        }
    });

    restoreFromState(elementList.value, true);
    pushHistory();
    triggerAutoSaveDebounced();
    toast.success(`Objek berhasil diputar ${angleDeg > 0 ? '+' : ''}${angleDeg}°`);
}

function flipSelected(direction: 'h' | 'v') {
    if (!props.canEdit || !tr) return;
    const selectedNodes = tr.nodes();
    if (selectedNodes.length === 0) return;

    const ids = new Set(selectedNodes.map((n) => n.id()));
    const targetElements = elementList.value.filter((el) => ids.has(el.id));
    if (targetElements.length === 0) return;

    const bboxes = targetElements.map(getElementBoundingBox);
    const minX = Math.min(...bboxes.map((b) => b.x));
    const maxX = Math.max(...bboxes.map((b) => b.x + b.width));
    const minY = Math.min(...bboxes.map((b) => b.y));
    const maxY = Math.max(...bboxes.map((b) => b.y + b.height));
    const pivotX = (minX + maxX) / 2;
    const pivotY = (minY + maxY) / 2;

    targetElements.forEach((el) => {
        const d = el.data;
        if (d.x1 !== undefined && d.x2 !== undefined && d.y1 !== undefined && d.y2 !== undefined) {
            if (direction === 'h') {
                d.x1 = Math.round(pivotX - (d.x1 - pivotX));
                d.x2 = Math.round(pivotX - (d.x2 - pivotX));
            } else {
                d.y1 = Math.round(pivotY - (d.y1 - pivotY));
                d.y2 = Math.round(pivotY - (d.y2 - pivotY));
            }
        } else if (d.x !== undefined && d.y !== undefined) {
            const selfBox = getElementBoundingBox(el);
            const selfCx = selfBox.x + selfBox.width / 2;
            const selfCy = selfBox.y + selfBox.height / 2;
            if (direction === 'h') {
                d.flipH = !d.flipH;
                const newCx = pivotX - (selfCx - pivotX);
                d.x = Math.round(newCx - selfBox.width / 2);
            } else {
                d.flipV = !d.flipV;
                const newCy = pivotY - (selfCy - pivotY);
                d.y = Math.round(newCy - selfBox.height / 2);
            }
        }
    });

    restoreFromState(elementList.value, true);
    pushHistory();
    triggerAutoSaveDebounced();
    toast.success(`Objek dicerminkan (${direction === 'h' ? 'Horizontal' : 'Vertikal'})`);
}

function bringSelectedToFront() {
    if (!props.canEdit || !selectedNodeId.value) return;
    const idx = elementList.value.findIndex((el) => el.id === selectedNodeId.value);
    if (idx < 0 || idx === elementList.value.length - 1) return;
    const [item] = elementList.value.splice(idx, 1);
    elementList.value.push(item);
    elementList.value.forEach((el, i) => (el.sort_order = i));
    restoreFromState(elementList.value, true);
    pushHistory();
    triggerAutoSaveDebounced();
    toast.success('Objek dipindahkan ke paling depan');
}

function sendSelectedToBack() {
    if (!props.canEdit || !selectedNodeId.value) return;
    const idx = elementList.value.findIndex((el) => el.id === selectedNodeId.value);
    if (idx <= 0) return;
    const [item] = elementList.value.splice(idx, 1);
    elementList.value.unshift(item);
    elementList.value.forEach((el, i) => (el.sort_order = i));
    restoreFromState(elementList.value, true);
    pushHistory();
    triggerAutoSaveDebounced();
    toast.success('Objek dipindahkan ke paling belakang');
}

function updateElementCustomText(newVal: string) {
    if (!selectedNodeId.value || !selectedElementData.value) return;
    selectedElementData.value.text = newVal;
    const el = elementList.value.find((x) => x.id === selectedNodeId.value);
    if (el) {
        el.data = { ...el.data, text: newVal };
    }
    restoreFromState(elementList.value, true);
    triggerAutoSaveDebounced();
}

function updateElementCustomCode(newVal: string) {
    if (!selectedNodeId.value || !selectedElementData.value) return;
    selectedElementData.value.code = newVal;
    const el = elementList.value.find((x) => x.id === selectedNodeId.value);
    if (el) {
        el.data = { ...el.data, code: newVal };
    }
    restoreFromState(elementList.value, true);
    triggerAutoSaveDebounced();
}

// ── RAPATKAN LEMBAR KERJA KE GAMBAR (ELIMINASI SEMUA JARAK KOSONG) ─────────
function autoFitSheetToDrawing() {
    showCategoryMenu.value = false;
    if (!stage) return;

    if (canvasMode.value !== 'sheet') {
        canvasMode.value = 'sheet';
    }

    const drawElements = elementList.value.filter((el) => el.type !== 'paper_frame');
    if (drawElements.length === 0) {
        toast.info('Belum ada objek denah arsitektur pada lembar ini. Silakan gambar dinding atau ruangan terlebih dahulu.');
        return;
    }

    const frames = getPaperFrames();
    const targetFrame = frames.find((f) => f.id === activeFocusedFrameId.value) || frames[0];

    // Temukan elemen yang berada di dalam atau berdekatan dengan frame aktif
    const frameElements = drawElements.filter((el) => {
        const box = getElementBoundingBox(el);
        const centerX = box.x + box.width / 2;
        return centerX >= (targetFrame.x - 100) && centerX <= (targetFrame.x + targetFrame.width + 100);
    });

    const elementsToFit = frameElements.length > 0 ? frameElements : drawElements;

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    elementsToFit.forEach((el) => {
        const box = getElementBoundingBox(el);
        minX = Math.min(minX, box.x);
        maxX = Math.max(maxX, box.x + box.width);
        minY = Math.min(minY, box.y);
        maxY = Math.max(maxY, box.y + box.height);
    });

    if (!isFinite(minX) || !isFinite(maxX)) {
        toast.info('Koordinat objek belum dapat diukur.');
        return;
    }

    if (paperSize.value !== 'Custom') {
        lastPresetBeforeAutofit.value = paperSize.value;
    }

    const padding = 50;
    const kopH = 120;
    const contentW = maxX - minX;
    const contentH = maxY - minY;

    const newW = Math.max(800, Math.round(contentW + padding * 2 + 80));
    const newH = Math.max(550, Math.round(contentH + padding * 2 + kopH + 40));
    const newX = Math.max(20, Math.round(minX - padding));
    const newY = Math.max(20, Math.round(minY - padding));

    if (targetFrame.isMain) {
        paperX.value = newX;
        paperY.value = newY;
        paperWidth.value = newW;
        paperHeight.value = newH;
        paperSize.value = 'Custom';
    } else {
        const frameEl = elementList.value.find((e) => e.id === targetFrame.id);
        if (frameEl && frameEl.data) {
            frameEl.data.x = newX;
            frameEl.data.y = newY;
            frameEl.data.width = newW;
            frameEl.data.height = newH;
            frameEl.data.size = 'Custom';
        }
    }

    drawGrid();
    drawPaperBoundary();
    restoreFromState(elementList.value, true);
    focusOnFrame({
        ...targetFrame,
        x: newX,
        y: newY,
        width: newW,
        height: newH,
    });
    savePaperSizesToStorage();
    triggerAutoSaveDebounced();
    toast.success(`Ukuran "${targetFrame.title}" berhasil dirapatkan presisi (${newW}×${newH} px).`);
}

function resetPaperToDefault() {
    if (canvasMode.value !== 'sheet') {
        canvasMode.value = 'sheet';
    }

    const preset = lastPresetBeforeAutofit.value || 'A3';
    paperSize.value = preset;
    const dims = STANDARD_SIZES[preset] || STANDARD_SIZES['A3'];
    const w = orientation.value === 'portrait' ? dims.h : dims.w;
    const h = orientation.value === 'portrait' ? dims.w : dims.h;

    const frames = getPaperFrames();
    const targetFrame = frames.find((f) => f.id === activeFocusedFrameId.value) || frames[0];

    if (targetFrame.isMain) {
        paperX.value = 60;
        paperY.value = 60;
        paperWidth.value = w;
        paperHeight.value = h;
    } else {
        const frameEl = elementList.value.find((e) => e.id === targetFrame.id);
        if (frameEl && frameEl.data) {
            frameEl.data.width = w;
            frameEl.data.height = h;
            frameEl.data.size = preset;
        }
    }

    drawGrid();
    drawPaperBoundary();
    restoreFromState(elementList.value, true);
    focusOnFrame({
        ...targetFrame,
        width: w,
        height: h,
    });
    savePaperSizesToStorage();
    triggerAutoSaveDebounced();
    toast.success(`Ukuran "${targetFrame.title}" dikembalikan ke standar ISO ${preset} (${w}×${h} px).`);
}

// ── PUSATKAN TAMPILAN KANVAS (AUTOCAD ZOOM EXTENTS) ─────────────────────────
function zoomToFit() {
    showCategoryMenu.value = false;
    if (!stage || !containerRef.value) return;

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    // 1. Kumpulkan seluruh frame kertas yang ada di kanvas
    const frames = getPaperFrames();
    frames.forEach((f) => {
        minX = Math.min(minX, f.x);
        maxX = Math.max(maxX, f.x + f.width);
        minY = Math.min(minY, f.y);
        maxY = Math.max(maxY, f.y + f.height);
    });

    // 2. Kumpulkan seluruh elemen gambar
    const drawElements = elementList.value.filter((el) => el.type !== 'paper_frame');
    drawElements.forEach((el) => {
        const box = getElementBoundingBox(el);
        minX = Math.min(minX, box.x);
        maxX = Math.max(maxX, box.x + box.width);
        minY = Math.min(minY, box.y);
        maxY = Math.max(maxY, box.y + box.height);
    });

    if (!isFinite(minX) || !isFinite(maxX)) {
        minX = 60;
        maxX = 1320;
        minY = 60;
        maxY = 950;
    }

    const boxW = Math.max(200, maxX - minX);
    const boxH = Math.max(200, maxY - minY);
    const containerW = containerRef.value.clientWidth || 1000;
    const containerH = containerRef.value.clientHeight || 700;

    const padding = 70;
    const scaleX = (containerW - padding * 2) / boxW;
    const scaleY = (containerH - padding * 2) / boxH;
    const fitScale = Math.max(0.06, Math.min(1.8, Math.min(scaleX, scaleY)));

    zoomLevel.value = fitScale;
    stage.scale({ x: fitScale, y: fitScale });

    const centerX = (minX + maxX) / 2;
    const centerY = (minY + maxY) / 2;
    const newX = containerW / 2 - centerX * fitScale;
    const newY = containerH / 2 - centerY * fitScale;

    stage.position({ x: newX, y: newY });
    stage.batchDraw();
    toast.success('Tampilan kanvas dipusatkan (Zoom Extents).');
}

// ── DRAW PAPER BOUNDARY & ARCHITECTURAL TITLE BLOCK (FIGMA FRAME / CAD) ─────
function drawPaperBoundary() {
    const curPaperLayer = paperLayer;
    if (!curPaperLayer) return;
    paperBgLayer?.destroyChildren();
    curPaperLayer.destroyChildren();

    const frames = getPaperFrames();

    // Mode Kanvas Tak Terbatas (AutoCAD Model Space)
    if (canvasMode.value === 'infinite') {
        // 1. Gambar AutoCAD UCS Coordinate Axis di titik (0, 0)
        const ucsGroup = new Konva.Group({ x: 0, y: 0, listening: false });
        ucsGroup.add(new Konva.Arrow({
            points: [0, 0, 80, 0],
            pointerLength: 8,
            pointerWidth: 6,
            fill: '#ef4444',
            stroke: '#ef4444',
            strokeWidth: 2,
        }));
        ucsGroup.add(new Konva.Text({
            x: 85,
            y: -6,
            text: 'X',
            fontSize: 12,
            fontStyle: 'bold',
            fill: '#ef4444',
        }));
        ucsGroup.add(new Konva.Arrow({
            points: [0, 0, 0, 80],
            pointerLength: 8,
            pointerWidth: 6,
            fill: '#22c55e',
            stroke: '#22c55e',
            strokeWidth: 2,
        }));
        ucsGroup.add(new Konva.Text({
            x: -6,
            y: 85,
            text: 'Y',
            fontSize: 12,
            fontStyle: 'bold',
            fill: '#22c55e',
        }));
        ucsGroup.add(new Konva.Circle({
            x: 0,
            y: 0,
            radius: 4,
            fill: '#38bdf8',
        }));
        ucsGroup.add(new Konva.Text({
            x: -40,
            y: -18,
            text: 'UCS (0, 0)',
            fontSize: 10,
            fontFamily: 'monospace',
            fill: '#94a3b8',
        }));
        curPaperLayer.add(ucsGroup);

        // 2. Gambar setiap frame kertas di Model Space (Batas Frame CAD)
        frames.forEach((frame) => {
            const fx = frame.x;
            const fy = frame.y;
            const fw = frame.width;
            const fh = frame.height;

            const bgRect = new Konva.Rect({
                x: fx,
                y: fy,
                width: fw,
                height: fh,
                fill: 'rgba(30, 41, 59, 0.45)',
                stroke: '#6366f1',
                strokeWidth: 2,
                dash: [12, 6],
                shadowColor: '#6366f1',
                shadowBlur: 14,
                shadowOpacity: 0.25,
                listening: false,
            });
            curPaperLayer.add(bgRect);

            // Badge Header Model Frame (Bisa digeser)
            const badgeGroup = new Konva.Group({
                x: fx,
                y: fy - 26,
                draggable: props.canEdit,
            });
            badgeGroup.add(new Konva.Tag({
                fill: '#312e81',
                cornerRadius: 6,
                stroke: '#6366f1',
                strokeWidth: 1,
            }));
            badgeGroup.add(new Konva.Text({
                text: `📐 Model Frame: ${frame.title} • ${fw}×${fh} px`,
                fontSize: 10,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: '#c7d2fe',
                padding: 4,
            }));

            if (props.canEdit) {
                badgeGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'move'; });
                badgeGroup.on('mouseleave', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
                badgeGroup.on('dragmove', () => {
                    const newX = Math.round(badgeGroup.x());
                    const newY = Math.round(badgeGroup.y() + 26);
                    if (frame.isMain) {
                        paperX.value = newX;
                        paperY.value = newY;
                    } else {
                        const el = elementList.value.find((e) => e.id === frame.id);
                        if (el && el.data) {
                            el.data.x = newX;
                            el.data.y = newY;
                        }
                    }
                    drawPaperBoundary();
                });
                badgeGroup.on('dragend', () => {
                    drawPaperBoundary();
                    triggerAutoSaveDebounced();
                });
            }
            curPaperLayer.add(badgeGroup);
        });

        curPaperLayer.batchDraw();
        return;
    }

    // Mode Frame Kertas Putih (Sheet Mode):
    frames.forEach((frame) => {
        const fx = frame.x;
        const fy = frame.y;
        const fw = frame.width;
        const fh = frame.height;

        // 1. Draggable Frame Header (Figma style)
        if (props.canEdit) {
            const headerGroup = new Konva.Group({
                x: fx,
                y: fy - 26,
                draggable: true,
            });

            headerGroup.add(new Konva.Tag({
                fill: frame.isMain ? '#4338ca' : '#059669',
                cornerRadius: 6,
                shadowColor: frame.isMain ? '#4338ca' : '#059669',
                shadowBlur: 8,
                shadowOpacity: 0.35,
            }));

            headerGroup.add(new Konva.Text({
                text: `✛ ${frame.title}  •  ${fw}×${fh} px (Tarik untuk geser)`,
                fontSize: 10,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: '#ffffff',
                padding: 4,
            }));

            headerGroup.on('mouseenter', () => {
                if (stage && !isSpacePressed.value) stage.container().style.cursor = 'move';
            });
            headerGroup.on('mouseleave', () => {
                if (stage && !isResizingSheet.value && !isSpacePressed.value) {
                    stage.container().style.cursor = activeTool.value === 'select' ? 'default' : (activeTool.value === 'pan' ? 'grab' : 'crosshair');
                }
            });
            headerGroup.on('dragmove', () => {
                const newX = Math.round(headerGroup.x());
                const newY = Math.round(headerGroup.y() + 26);
                if (frame.isMain) {
                    paperX.value = newX;
                    paperY.value = newY;
                } else {
                    const el = elementList.value.find((e) => e.id === frame.id);
                    if (el && el.data) {
                        el.data.x = newX;
                        el.data.y = newY;
                    }
                }
                drawPaperBoundary();
            });
            headerGroup.on('dragend', () => {
                drawPaperBoundary();
                triggerAutoSaveDebounced();
            });

            curPaperLayer.add(headerGroup);

            // Tombol Hapus Frame Tambahan (jika bukan frame utama)
            if (!frame.isMain) {
                const delGroup = new Konva.Group({
                    x: fx + fw - 95,
                    y: fy - 26,
                });
                delGroup.add(new Konva.Tag({
                    fill: '#dc2626',
                    cornerRadius: 6,
                    shadowColor: '#dc2626',
                    shadowBlur: 6,
                    shadowOpacity: 0.35,
                }));
                delGroup.add(new Konva.Text({
                    text: '✕ Hapus Kertas',
                    fontSize: 10,
                    fontFamily: 'sans-serif',
                    fontStyle: 'bold',
                    fill: '#ffffff',
                    padding: 4,
                }));
                delGroup.on('mouseenter', () => {
                    if (stage) stage.container().style.cursor = 'pointer';
                });
                delGroup.on('mouseleave', () => {
                    if (stage) stage.container().style.cursor = 'default';
                });
                delGroup.on('click tap', (e) => {
                    e.cancelBubble = true;
                    confirmRemovePaperFrame(frame);
                });
                curPaperLayer.add(delGroup);
            }
        }

        // 2. White Paper Rectangle
        const paperRect = new Konva.Rect({
            x: fx,
            y: fy,
            width: fw,
            height: fh,
            fill: '#ffffff',
            shadowColor: '#000000',
            shadowBlur: 30,
            shadowOpacity: 0.25,
            shadowOffset: { x: 0, y: 15 },
            listening: false,
        });

        // Add paperRect to paperBgLayer (placed below gridLayer)
        if (paperBgLayer) {
            paperBgLayer.add(paperRect);
        } else {
            curPaperLayer.add(paperRect);
        }

        // 3. Border Frame (Garis Tepi Arsitektur Standar ISO)
        const margin = 20;
        const frameRect = new Konva.Rect({
            x: fx + margin,
            y: fy + margin,
            width: Math.max(100, fw - margin * 2),
            height: Math.max(100, fh - margin * 2),
            stroke: '#0f172a',
            strokeWidth: 2,
            listening: false,
        });

        // 4. Kop Gambar (Title Block) Profesional Dinamis (Menempel di Sudut Kanan Bawah)
        const kopW = Math.min(360, Math.max(220, fw - margin * 2 - 20));
        const innerW = kopW - 28;

        const companyTitle = props.architect?.company_name
            ? `${props.architect?.company_type ? props.architect.company_type + ' ' : ''}${props.architect.company_name}`.toUpperCase()
            : 'STUDIO ARSITEKTUR';

        const kopStudio = new Konva.Text({
            text: companyTitle,
            fontSize: 11,
            fontStyle: 'bold',
            fontFamily: 'sans-serif',
            fill: '#4338ca',
            width: innerW,
            wrap: 'word',
            listening: false,
        });

        const kopTitle = new Konva.Text({
            text: frame.title || 'Lembar Kerja CAD',
            fontSize: 12.5,
            fontStyle: 'bold',
            fontFamily: 'sans-serif',
            fill: '#0f172a',
            width: innerW,
            wrap: 'word',
            lineHeight: 1.25,
            listening: false,
        });

        const kopProject = new Konva.Text({
            text: `PROYEK: ${props.project.title}`,
            fontSize: 10.5,
            fontFamily: 'sans-serif',
            fill: '#475569',
            width: innerW,
            wrap: 'word',
            lineHeight: 1.2,
            listening: false,
        });

        const todayDate = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        const kopMeta = new Konva.Text({
            text: `SKALA: ${props.sheet.scale}   |   KERTAS: ${frame.size} (${Math.round(fw)}×${Math.round(fh)}px)\nTGL: ${todayDate}   |   KOTA: ${props.architect?.city_name || 'Indonesia'}`,
            fontSize: 9.5,
            fontFamily: 'monospace',
            fill: '#64748b',
            width: innerW,
            wrap: 'word',
            lineHeight: 1.3,
            listening: false,
        });

        // Dynamic vertical layout based on wrapped text heights
        const offsetStudio = 10;
        const offsetTitle = offsetStudio + kopStudio.height() + 4;
        const offsetProject = offsetTitle + kopTitle.height() + 4;
        const offsetDivider = offsetProject + kopProject.height() + 6;
        const offsetMeta = offsetDivider + 6;
        const totalContentH = offsetMeta + kopMeta.height() + 10;

        const kopH = Math.min(Math.max(115, totalContentH), Math.max(90, fh - margin * 2 - 20));
        const kopX = fx + fw - margin - kopW;
        const kopY = fy + fh - margin - kopH;

        const kopRect = new Konva.Rect({
            x: kopX,
            y: kopY,
            width: kopW,
            height: kopH,
            stroke: '#0f172a',
            strokeWidth: 1.5,
            fill: '#ffffff',
            cornerRadius: 2,
            listening: false,
        });

        kopStudio.position({ x: kopX + 14, y: kopY + offsetStudio });
        kopTitle.position({ x: kopX + 14, y: kopY + offsetTitle });
        kopProject.position({ x: kopX + 14, y: kopY + offsetProject });

        const kopDivider = new Konva.Line({
            points: [kopX + 14, kopY + offsetDivider, kopX + kopW - 14, kopY + offsetDivider],
            stroke: '#cbd5e1',
            strokeWidth: 1,
            listening: false,
        });

        kopMeta.position({ x: kopX + 14, y: kopY + offsetMeta });

        curPaperLayer.add(frameRect);
        curPaperLayer.add(kopRect);
        curPaperLayer.add(kopStudio);
        curPaperLayer.add(kopTitle);
        curPaperLayer.add(kopProject);
        curPaperLayer.add(kopDivider);
        curPaperLayer.add(kopMeta);

        // Helper to update Kop live during resize drag
        const updateKopLive = (newW: number, newH: number) => {
            const curKopW = Math.min(360, Math.max(220, newW - margin * 2 - 20));
            const curInnerW = curKopW - 28;
            const curKopX = fx + newW - margin - curKopW;
            const curKopY = fy + newH - margin - kopH;

            kopRect.position({ x: curKopX, y: curKopY });
            kopRect.size({ width: curKopW, height: kopH });

            kopStudio.position({ x: curKopX + 14, y: curKopY + offsetStudio });
            kopStudio.width(curInnerW);
            kopTitle.position({ x: curKopX + 14, y: curKopY + offsetTitle });
            kopTitle.width(curInnerW);
            kopProject.position({ x: curKopX + 14, y: curKopY + offsetProject });
            kopProject.width(curInnerW);
            kopDivider.points([curKopX + 14, curKopY + offsetDivider, curKopX + curKopW - 14, curKopY + offsetDivider]);
            kopMeta.position({ x: curKopX + 14, y: curKopY + offsetMeta });
            kopMeta.width(curInnerW);
        };

        // Figma-style resize handles for editable frames (Main or Secondary)
        if (props.canEdit) {
            // 1. Handle Sudut Kanan-Bawah (Corner Handle)
            const cornerGroup = new Konva.Group({
                x: fx + fw,
                y: fy + fh,
                draggable: true,
                dragBoundFunc: (pos) => {
                    if (!stage) return pos;
                    const minAbsX = stage.x() + (fx + 300) * stage.scaleX();
                    const minAbsY = stage.y() + (fy + 300) * stage.scaleY();
                    return {
                        x: Math.max(minAbsX, pos.x),
                        y: Math.max(minAbsY, pos.y),
                    };
                },
            });

            // Invisible wide hit area (radius 20)
            cornerGroup.add(new Konva.Circle({
                radius: 20,
                fill: 'transparent',
            }));

            // Visible styled circle with glow
            cornerGroup.add(new Konva.Circle({
                radius: 9,
                fill: '#6366f1',
                stroke: '#ffffff',
                strokeWidth: 2.5,
                shadowColor: '#6366f1',
                shadowBlur: 10,
                shadowOpacity: 0.6,
            }));

            cornerGroup.add(new Konva.Circle({
                radius: 3,
                fill: '#ffffff',
                listening: false,
            }));

            cornerGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'nwse-resize'; });
            cornerGroup.on('mouseleave', () => { if (stage && !isResizingSheet.value && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
            cornerGroup.on('dragstart', () => { isResizingSheet.value = true; });
            cornerGroup.on('dragmove', () => {
                if (!stage) return;
                const absX = cornerGroup.getAbsolutePosition().x;
                const absY = cornerGroup.getAbsolutePosition().y;
                const localX = (absX - stage.x()) / stage.scaleX();
                const localY = (absY - stage.y()) / stage.scaleY();
                const newW = Math.max(300, Math.round(localX - fx));
                const newH = Math.max(300, Math.round(localY - fy));

                if (frame.isMain) {
                    paperWidth.value = newW;
                    paperHeight.value = newH;
                    paperSize.value = 'Custom';
                } else {
                    const targetEl = elementList.value.find((el) => el.id === frame.id);
                    if (targetEl) {
                        targetEl.data.width = newW;
                        targetEl.data.height = newH;
                    }
                }

                // Update visual shapes live
                paperRect.width(newW);
                paperRect.height(newH);
                frameRect.width(Math.max(100, newW - margin * 2));
                frameRect.height(Math.max(100, newH - margin * 2));

                updateKopLive(newW, newH);

                rightEdgeGroup.position({ x: fx + newW, y: fy + newH / 2 });
                bottomEdgeGroup.position({ x: fx + newW / 2, y: fy + newH });

                paperBgLayer?.batchDraw();
                curPaperLayer.batchDraw();
            });
            cornerGroup.on('dragend', () => {
                isResizingSheet.value = false;
                drawPaperBoundary();
                savePaperSizesToStorage();
                triggerAutoSaveDebounced();
            });

            curPaperLayer.add(cornerGroup);

            // 2. Handle Tepi Kanan (Right Edge Handle)
            const rightEdgeGroup = new Konva.Group({
                x: fx + fw,
                y: fy + fh / 2,
                draggable: true,
                dragBoundFunc: (pos) => {
                    if (!stage) return pos;
                    const curH = frame.isMain ? paperHeight.value : (elementList.value.find((el) => el.id === frame.id)?.data.height || fh);
                    const targetAbsY = stage.y() + (fy + curH / 2) * stage.scaleY();
                    const minAbsX = stage.x() + (fx + 300) * stage.scaleX();
                    return {
                        x: Math.max(minAbsX, pos.x),
                        y: targetAbsY,
                    };
                },
            });

            // Invisible wide grab box (width 32px, height 60px)
            rightEdgeGroup.add(new Konva.Rect({
                x: -16,
                y: -30,
                width: 32,
                height: 60,
                fill: 'transparent',
            }));

            // Sleek visible pill handle
            rightEdgeGroup.add(new Konva.Rect({
                x: -5,
                y: -22,
                width: 10,
                height: 44,
                cornerRadius: 5,
                fill: '#6366f1',
                stroke: '#ffffff',
                strokeWidth: 2,
                shadowColor: '#000000',
                shadowBlur: 8,
                shadowOpacity: 0.4,
            }));

            // Dual grip marks
            rightEdgeGroup.add(new Konva.Line({
                points: [-1.5, -8, -1.5, 8],
                stroke: '#ffffff',
                strokeWidth: 1.5,
                strokeCap: 'round',
                listening: false,
            }));
            rightEdgeGroup.add(new Konva.Line({
                points: [1.5, -8, 1.5, 8],
                stroke: '#ffffff',
                strokeWidth: 1.5,
                strokeCap: 'round',
                listening: false,
            }));

            rightEdgeGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'ew-resize'; });
            rightEdgeGroup.on('mouseleave', () => { if (stage && !isResizingSheet.value && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
            rightEdgeGroup.on('dragstart', () => { isResizingSheet.value = true; });
            rightEdgeGroup.on('dragmove', () => {
                if (!stage) return;
                const absX = rightEdgeGroup.getAbsolutePosition().x;
                const localX = (absX - stage.x()) / stage.scaleX();
                const newW = Math.max(300, Math.round(localX - fx));

                if (frame.isMain) {
                    paperWidth.value = newW;
                    paperSize.value = 'Custom';
                } else {
                    const targetEl = elementList.value.find((el) => el.id === frame.id);
                    if (targetEl) targetEl.data.width = newW;
                }

                const curH = frame.isMain ? paperHeight.value : (elementList.value.find((el) => el.id === frame.id)?.data.height || fh);

                paperRect.width(newW);
                frameRect.width(Math.max(100, newW - margin * 2));

                updateKopLive(newW, curH);

                cornerGroup.x(fx + newW);
                bottomEdgeGroup.x(fx + newW / 2);

                paperBgLayer?.batchDraw();
                curPaperLayer.batchDraw();
            });
            rightEdgeGroup.on('dragend', () => {
                isResizingSheet.value = false;
                drawPaperBoundary();
                savePaperSizesToStorage();
                triggerAutoSaveDebounced();
            });

            curPaperLayer.add(rightEdgeGroup);

            // 3. Handle Tepi Bawah (Bottom Edge Handle)
            const bottomEdgeGroup = new Konva.Group({
                x: fx + fw / 2,
                y: fy + fh,
                draggable: true,
                dragBoundFunc: (pos) => {
                    if (!stage) return pos;
                    const curW = frame.isMain ? paperWidth.value : (elementList.value.find((el) => el.id === frame.id)?.data.width || fw);
                    const targetAbsX = stage.x() + (fx + curW / 2) * stage.scaleX();
                    const minAbsY = stage.y() + (fy + 300) * stage.scaleY();
                    return {
                        x: targetAbsX,
                        y: Math.max(minAbsY, pos.y),
                    };
                },
            });

            // Invisible wide grab box (width 60px, height 32px)
            bottomEdgeGroup.add(new Konva.Rect({
                x: -30,
                y: -16,
                width: 60,
                height: 32,
                fill: 'transparent',
            }));

            // Sleek visible pill handle
            bottomEdgeGroup.add(new Konva.Rect({
                x: -22,
                y: -5,
                width: 44,
                height: 10,
                cornerRadius: 5,
                fill: '#6366f1',
                stroke: '#ffffff',
                strokeWidth: 2,
                shadowColor: '#000000',
                shadowBlur: 8,
                shadowOpacity: 0.4,
            }));

            // Dual grip marks
            bottomEdgeGroup.add(new Konva.Line({
                points: [-8, -1.5, 8, -1.5],
                stroke: '#ffffff',
                strokeWidth: 1.5,
                strokeCap: 'round',
                listening: false,
            }));
            bottomEdgeGroup.add(new Konva.Line({
                points: [-8, 1.5, 8, 1.5],
                stroke: '#ffffff',
                strokeWidth: 1.5,
                strokeCap: 'round',
                listening: false,
            }));

            bottomEdgeGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'ns-resize'; });
            bottomEdgeGroup.on('mouseleave', () => { if (stage && !isResizingSheet.value && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
            bottomEdgeGroup.on('dragstart', () => { isResizingSheet.value = true; });
            bottomEdgeGroup.on('dragmove', () => {
                if (!stage) return;
                const absY = bottomEdgeGroup.getAbsolutePosition().y;
                const localY = (absY - stage.y()) / stage.scaleY();
                const newH = Math.max(300, Math.round(localY - fy));

                if (frame.isMain) {
                    paperHeight.value = newH;
                    paperSize.value = 'Custom';
                } else {
                    const targetEl = elementList.value.find((el) => el.id === frame.id);
                    if (targetEl) targetEl.data.height = newH;
                }

                const curW = frame.isMain ? paperWidth.value : (elementList.value.find((el) => el.id === frame.id)?.data.width || fw);

                paperRect.height(newH);
                frameRect.height(Math.max(100, newH - margin * 2));

                updateKopLive(curW, newH);

                cornerGroup.y(fy + newH);
                rightEdgeGroup.y(fy + newH / 2);

                paperBgLayer?.batchDraw();
                curPaperLayer.batchDraw();
            });
            bottomEdgeGroup.on('dragend', () => {
                isResizingSheet.value = false;
                drawPaperBoundary();
                savePaperSizesToStorage();
                triggerAutoSaveDebounced();
            });

            curPaperLayer.add(bottomEdgeGroup);
        }
    });

    paperBgLayer?.batchDraw();
    curPaperLayer.batchDraw();
}

// ── DRAW GRID LINES (AUTOCAD INFINITE MODEL SPACE / SHEET GRID) ─────────────
function drawGrid() {
    if (!gridLayer || !stage) return;
    gridLayer.destroyChildren();

    const isCad = canvasMode.value === 'infinite';
    // Kanvas Arsitektur Luas (12000px × 10000px) untuk fleksibilitas CAD tak terbatas
    const w = 12000;
    const h = 10000;
    const step = gridSpacing.value;

    const majorStroke = isCad ? 'rgba(71, 85, 105, 0.45)' : '#cbd5e1';
    const minorStroke = isCad ? 'rgba(51, 65, 85, 0.22)' : '#f1f5f9';

    for (let x = 0; x <= w; x += step) {
        const isMajor = x % (step * 5) === 0;
        gridLayer.add(
            new Konva.Line({
                points: [x, 0, x, h],
                stroke: isMajor ? majorStroke : minorStroke,
                strokeWidth: isMajor ? 1 : 0.5,
                listening: false,
            })
        );
    }

    for (let y = 0; y <= h; y += step) {
        const isMajor = y % (step * 5) === 0;
        gridLayer.add(
            new Konva.Line({
                points: [0, y, w, y],
                stroke: isMajor ? majorStroke : minorStroke,
                strokeWidth: isMajor ? 1 : 0.5,
                listening: false,
            })
        );
    }

    gridLayer.batchDraw();
}

// ── PENDING SELECTION GESTURE STATE (AUTOCAD/FIGMA STYLE SELECTION & DRAG) ──
let pendingToggleOff: Konva.Group | null = null;
let pendingSingleSelect: Konva.Group | null = null;

// ── CREATE ARCHITECTURAL SYMBOLS (SHAPES) ──────────────────────────────────
function createShapeFromElement(el: { id: string; type: string; data: Record<string, any> }): Konva.Group {
    if (el.type === 'paper_frame') return null as any;
    const data = el.data || {};
    const group = new Konva.Group({
        id: el.id,
        name: el.type,
        draggable: props.canEdit && activeTool.value === 'select',
    });

    const isCad = canvasMode.value === 'infinite';

    if (data.rotation || data.flipH || data.flipV) {
        const bbox = getElementBoundingBox(el);
        const cx = bbox.x + bbox.width / 2;
        const cy = bbox.y + bbox.height / 2;
        group.offset({ x: cx, y: cy });
        group.position({ x: cx, y: cy });
        if (data.rotation) group.rotation(data.rotation);
        if (data.flipH) group.scaleX(-1);
        if (data.flipV) group.scaleY(-1);
    }

    switch (el.type) {
        case 'wall': {
            // Architectural wall: Double parallel line with dark or bright CAD infill
            const x1 = data.x1 ?? 0;
            const y1 = data.y1 ?? 0;
            const x2 = data.x2 ?? 100;
            const y2 = data.y2 ?? 0;
            const thickness = data.thickness ?? 12;

            const wallLine = new Konva.Line({
                points: [x1, y1, x2, y2],
                stroke: isCad ? '#f8fafc' : '#1e293b',
                strokeWidth: thickness,
                lineCap: 'square',
            });
            const centerLine = new Konva.Line({
                points: [x1, y1, x2, y2],
                stroke: isCad ? '#38bdf8' : '#e2e8f0',
                strokeWidth: 1.5,
                dash: [4, 4],
            });
            group.add(wallLine);
            group.add(centerLine);
            break;
        }

        case 'column': {
            // Kolom Struktur Beton (Kotak dengan arsir cross hatch)
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const size = data.size ?? 30;

            const box = new Konva.Rect({
                x,
                y,
                width: size,
                height: size,
                stroke: isCad ? '#ffffff' : '#0f172a',
                strokeWidth: 2,
                fill: isCad ? '#f43f5e' : '#334155',
            });
            const h1 = new Konva.Line({
                points: [x, y, x + size, y + size],
                stroke: isCad ? '#ffffff' : '#94a3b8',
                strokeWidth: 1.5,
            });
            const h2 = new Konva.Line({
                points: [x, y + size, x + size, y],
                stroke: isCad ? '#ffffff' : '#94a3b8',
                strokeWidth: 1.5,
            });
            group.add(box);
            group.add(h1);
            group.add(h2);
            break;
        }

        case 'door': {
            // Simbol Pintu Swing Arsitektur (Tunggal)
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const size = data.size ?? 80;

            const frame = new Konva.Line({
                points: [x, y, x, y + size],
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 3,
            });
            const swingArc = new Konva.Arc({
                x: x,
                y: y + size,
                innerRadius: size,
                outerRadius: size,
                angle: 90,
                rotation: -90,
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 1.5,
                dash: [4, 3],
            });
            group.add(frame);
            group.add(swingArc);
            break;
        }

        case 'double_door': {
            // Simbol Pintu Ganda / Kupu-Tarung Arsitektur
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 120;
            const leaf = w / 2;

            const frameL = new Konva.Line({
                points: [x, y, x, y + leaf],
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 3,
            });
            const swingArcL = new Konva.Arc({
                x: x,
                y: y + leaf,
                innerRadius: leaf,
                outerRadius: leaf,
                angle: 90,
                rotation: -90,
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 1.5,
                dash: [4, 3],
            });
            const frameR = new Konva.Line({
                points: [x + w, y, x + w, y + leaf],
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 3,
            });
            const swingArcR = new Konva.Arc({
                x: x + w,
                y: y + leaf,
                innerRadius: leaf,
                outerRadius: leaf,
                angle: 90,
                rotation: 180,
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 1.5,
                dash: [4, 3],
            });
            group.add(frameL);
            group.add(swingArcL);
            group.add(frameR);
            group.add(swingArcR);
            break;
        }

        case 'sliding_door': {
            // Simbol Pintu Geser / Sliding Door
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 120;
            const h = data.height ?? 16;

            const outer = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.15)' : '#f0f9ff',
            });
            const p1 = new Konva.Rect({
                x: x + 2,
                y: y + 2,
                width: w * 0.52,
                height: h / 2 - 2,
                stroke: isCad ? '#7dd3fc' : '#0369a1',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(56, 189, 248, 0.4)' : '#bae6fd',
            });
            const p2 = new Konva.Rect({
                x: x + w * 0.46,
                y: y + h / 2,
                width: w * 0.52,
                height: h / 2 - 2,
                stroke: isCad ? '#7dd3fc' : '#0369a1',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(56, 189, 248, 0.4)' : '#bae6fd',
            });
            const arrow = new Konva.Arrow({
                points: [x + w * 0.25, y + h / 4, x + w * 0.4, y + h / 4],
                pointerLength: 4,
                pointerWidth: 4,
                fill: isCad ? '#38bdf8' : '#0284c7',
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 1,
            });
            group.add(outer);
            group.add(p1);
            group.add(p2);
            group.add(arrow);
            break;
        }

        case 'window': {
            // Simbol Bukaan Jendela (Double glazed glass line)
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 100;
            const h = data.height ?? 16;

            const outer = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.2)' : '#f0f9ff',
            });
            const glass = new Konva.Line({
                points: [x, y + h / 2, x + w, y + h / 2],
                stroke: isCad ? '#7dd3fc' : '#0284c7',
                strokeWidth: 2,
            });
            group.add(outer);
            group.add(glass);
            break;
        }

        case 'stair': {
            // Simbol Tangga 2D dengan anak tangga dan panah "UP"
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 80;
            const h = data.height ?? 160;
            const steps = 8;
            const stepH = h / steps;

            const outerBox = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#e2e8f0' : '#475569',
                strokeWidth: 2,
                fill: isCad ? 'rgba(30, 41, 59, 0.75)' : '#f8fafc',
            });
            group.add(outerBox);

            for (let i = 1; i < steps; i++) {
                group.add(
                    new Konva.Line({
                        points: [x, y + i * stepH, x + w, y + i * stepH],
                        stroke: isCad ? '#cbd5e1' : '#94a3b8',
                        strokeWidth: 1.5,
                    })
                );
            }

            // Arrow Up
            const arrow = new Konva.Arrow({
                points: [x + w / 2, y + h - 15, x + w / 2, y + 20],
                pointerLength: 8,
                pointerWidth: 8,
                fill: isCad ? '#c084fc' : '#4338ca',
                stroke: isCad ? '#c084fc' : '#4338ca',
                strokeWidth: 2,
            });
            const textUp = new Konva.Text({
                x: x + w / 2 - 8,
                y: y + h - 28,
                text: 'UP',
                fontSize: 10,
                fontFamily: 'monospace',
                fontStyle: 'bold',
                fill: isCad ? '#c084fc' : '#4338ca',
            });
            group.add(arrow);
            group.add(textUp);
            break;
        }

        case 'sofa': {
            // Sofa Ruang Tamu 3-Seater
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 140;
            const h = data.height ?? 65;

            const body = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#fbbf24' : '#78350f',
                strokeWidth: 2,
                fill: isCad ? 'rgba(251, 191, 36, 0.15)' : '#fef3c7',
                cornerRadius: 6,
            });
            const back = new Konva.Rect({
                x: x + 6,
                y: y + 4,
                width: w - 12,
                height: 14,
                stroke: isCad ? '#fde047' : '#92400e',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(251, 191, 36, 0.3)' : '#fde68a',
                cornerRadius: 4,
            });
            const armL = new Konva.Rect({
                x: x + 4,
                y: y + 4,
                width: 14,
                height: h - 8,
                stroke: isCad ? '#fde047' : '#92400e',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(251, 191, 36, 0.3)' : '#fde68a',
                cornerRadius: 4,
            });
            const armR = new Konva.Rect({
                x: x + w - 18,
                y: y + 4,
                width: 14,
                height: h - 8,
                stroke: isCad ? '#fde047' : '#92400e',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(251, 191, 36, 0.3)' : '#fde68a',
                cornerRadius: 4,
            });
            group.add(body);
            group.add(back);
            group.add(armL);
            group.add(armR);

            const seatW = (w - 44) / 3;
            for (let i = 0; i < 3; i++) {
                group.add(
                    new Konva.Rect({
                        x: x + 20 + i * (seatW + 2),
                        y: y + 20,
                        width: seatW,
                        height: h - 26,
                        stroke: isCad ? '#fde047' : '#a16207',
                        strokeWidth: 1.5,
                        fill: isCad ? '#1e293b' : '#ffffff',
                        cornerRadius: 4,
                    })
                );
            }
            break;
        }

        case 'kitchen': {
            // Meja Kitchen Set (Double Sink + Kompor 2 Tungku)
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 150;
            const h = data.height ?? 60;

            const counter = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#94a3b8' : '#334155',
                strokeWidth: 2,
                fill: isCad ? 'rgba(148, 163, 184, 0.15)' : '#f1f5f9',
                cornerRadius: 3,
            });
            const sinkFrame = new Konva.Rect({
                x: x + 8,
                y: y + 8,
                width: 54,
                height: h - 16,
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(56, 189, 248, 0.2)' : '#e0f2fe',
                cornerRadius: 4,
            });
            const sinkDiv = new Konva.Line({
                points: [x + 35, y + 8, x + 35, y + h - 8],
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 1.5,
            });
            const drain1 = new Konva.Circle({
                x: x + 21,
                y: y + h / 2,
                radius: 3,
                fill: isCad ? '#38bdf8' : '#0284c7',
            });
            const drain2 = new Konva.Circle({
                x: x + 48,
                y: y + h / 2,
                radius: 3,
                fill: isCad ? '#38bdf8' : '#0284c7',
            });

            const stoveFrame = new Konva.Rect({
                x: x + 75,
                y: y + 8,
                width: 65,
                height: h - 16,
                stroke: isCad ? '#f87171' : '#b91c1c',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(239, 68, 68, 0.15)' : '#fee2e2',
                cornerRadius: 4,
            });
            const burner1 = new Konva.Circle({
                x: x + 92,
                y: y + h / 2,
                radius: 12,
                stroke: isCad ? '#ef4444' : '#dc2626',
                strokeWidth: 1.5,
            });
            const burner2 = new Konva.Circle({
                x: x + 123,
                y: y + h / 2,
                radius: 12,
                stroke: isCad ? '#ef4444' : '#dc2626',
                strokeWidth: 1.5,
            });
            const b1Center = new Konva.Circle({
                x: x + 92,
                y: y + h / 2,
                radius: 4,
                fill: isCad ? '#ef4444' : '#dc2626',
            });
            const b2Center = new Konva.Circle({
                x: x + 123,
                y: y + h / 2,
                radius: 4,
                fill: isCad ? '#ef4444' : '#dc2626',
            });

            group.add(counter);
            group.add(sinkFrame);
            group.add(sinkDiv);
            group.add(drain1);
            group.add(drain2);
            group.add(stoveFrame);
            group.add(burner1);
            group.add(burner2);
            group.add(b1Center);
            group.add(b2Center);
            break;
        }

        case 'toilet': {
            // Simbol Sanitair Kloset Duduk 2D
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const tank = new Konva.Rect({
                x,
                y,
                width: 50,
                height: 20,
                stroke: isCad ? '#38bdf8' : '#0369a1',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.25)' : '#f0f9ff',
                cornerRadius: 3,
            });
            const bowl = new Konva.Arc({
                x: x + 25,
                y: y + 20,
                innerRadius: 0,
                outerRadius: 22,
                angle: 180,
                rotation: 0,
                stroke: isCad ? '#38bdf8' : '#0369a1',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.35)' : '#e0f2fe',
            });
            group.add(tank);
            group.add(bowl);
            break;
        }

        case 'washbasin': {
            // Wastafel Kamar Mandi
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 50;
            const h = data.height ?? 40;

            const base = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#38bdf8' : '#0369a1',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.2)' : '#f0f9ff',
                cornerRadius: 6,
            });
            const bowl = new Konva.Ellipse({
                x: x + w / 2,
                y: y + h / 2 + 2,
                radiusX: w / 2 - 8,
                radiusY: h / 2 - 8,
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 1.5,
                fill: isCad ? 'rgba(56, 189, 248, 0.35)' : '#e0f2fe',
            });
            const faucet = new Konva.Circle({
                x: x + w / 2,
                y: y + 7,
                radius: 3,
                fill: isCad ? '#7dd3fc' : '#0284c7',
            });
            const drain = new Konva.Circle({
                x: x + w / 2,
                y: y + h / 2 + 3,
                radius: 2.5,
                fill: isCad ? '#7dd3fc' : '#0369a1',
            });

            group.add(base);
            group.add(bowl);
            group.add(faucet);
            group.add(drain);
            break;
        }

        case 'shower': {
            // Area Shower Box Kamar Mandi
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 80;
            const h = data.height ?? 80;

            const tray = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#22d3ee' : '#0891b2',
                strokeWidth: 2,
                fill: isCad ? 'rgba(34, 211, 238, 0.15)' : '#ecfeff',
                cornerRadius: 4,
            });
            const diag = new Konva.Line({
                points: [x, y + h - 22, x + 22, y + h],
                stroke: isCad ? '#67e8f9' : '#06b6d4',
                strokeWidth: 1.5,
            });
            const head = new Konva.Circle({
                x: x + 16,
                y: y + 16,
                radius: 6,
                stroke: isCad ? '#22d3ee' : '#0891b2',
                strokeWidth: 2,
                fill: isCad ? '#0e7490' : '#cffafe',
            });
            const s1 = new Konva.Line({ points: [x + 16, y + 24, x + 24, y + 36], stroke: isCad ? '#67e8f9' : '#0891b2', strokeWidth: 1, dash: [2, 2] });
            const s2 = new Konva.Line({ points: [x + 20, y + 20, x + 34, y + 28], stroke: isCad ? '#67e8f9' : '#0891b2', strokeWidth: 1, dash: [2, 2] });
            const s3 = new Konva.Line({ points: [x + 24, y + 16, x + 38, y + 20], stroke: isCad ? '#67e8f9' : '#0891b2', strokeWidth: 1, dash: [2, 2] });
            const drain = new Konva.Circle({
                x: x + w - 18,
                y: y + h - 18,
                radius: 3.5,
                fill: isCad ? '#06b6d4' : '#0891b2',
            });

            group.add(tray);
            group.add(diag);
            group.add(head);
            group.add(s1);
            group.add(s2);
            group.add(s3);
            group.add(drain);
            break;
        }

        case 'bed': {
            // Simbol Ranjang Tempat Tidur 2D
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 120;
            const h = data.height ?? 140;

            const frame = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#fbbf24' : '#854d0e',
                strokeWidth: 2,
                fill: isCad ? 'rgba(251, 191, 36, 0.15)' : '#fefce8',
                cornerRadius: 4,
            });
            const pillow1 = new Konva.Rect({
                x: x + 10,
                y: y + 10,
                width: 45,
                height: 30,
                stroke: isCad ? '#fde047' : '#a16207',
                strokeWidth: 1.5,
                fill: isCad ? '#1e293b' : '#ffffff',
                cornerRadius: 4,
            });
            const pillow2 = new Konva.Rect({
                x: x + 65,
                y: y + 10,
                width: 45,
                height: 30,
                stroke: isCad ? '#fde047' : '#a16207',
                strokeWidth: 1.5,
                fill: isCad ? '#1e293b' : '#ffffff',
                cornerRadius: 4,
            });
            const blanket = new Konva.Line({
                points: [x, y + 55, x + w, y + 55],
                stroke: isCad ? '#fde047' : '#a16207',
                strokeWidth: 1.5,
                dash: [4, 4],
            });
            group.add(frame);
            group.add(pillow1);
            group.add(pillow2);
            group.add(blanket);
            break;
        }

        case 'table':
        case 'dining_table': {
            // Meja Makan dengan Kursi
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 100;
            const h = data.height ?? 70;

            const table = new Konva.Rect({
                x,
                y,
                width: w,
                height: h,
                stroke: isCad ? '#e2e8f0' : '#3f3f46',
                strokeWidth: 2,
                fill: isCad ? 'rgba(148, 163, 184, 0.2)' : '#f4f4f5',
                cornerRadius: 4,
            });
            const chairStroke = isCad ? '#cbd5e1' : '#71717a';
            const c1 = new Konva.Rect({ x: x + 15, y: y - 10, width: 30, height: 8, stroke: chairStroke, strokeWidth: 1.5 });
            const c2 = new Konva.Rect({ x: x + 55, y: y - 10, width: 30, height: 8, stroke: chairStroke, strokeWidth: 1.5 });
            const c3 = new Konva.Rect({ x: x + 15, y: y + h + 2, width: 30, height: 8, stroke: chairStroke, strokeWidth: 1.5 });
            const c4 = new Konva.Rect({ x: x + 55, y: y + h + 2, width: 30, height: 8, stroke: chairStroke, strokeWidth: 1.5 });
            group.add(table);
            group.add(c1);
            group.add(c2);
            group.add(c3);
            group.add(c4);
            break;
        }

        case 'car': {
            // Simbol Mobil Carport / Garasi Tampak Atas
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const w = data.width ?? 160;
            const h = data.height ?? 75;

            const carBody = new Konva.Rect({
                x: x + 8,
                y: y + 6,
                width: w - 16,
                height: h - 12,
                stroke: isCad ? '#cbd5e1' : '#334155',
                strokeWidth: 2,
                fill: isCad ? 'rgba(71, 85, 105, 0.45)' : '#e2e8f0',
                cornerRadius: 16,
            });
            const windshield = new Konva.Line({
                points: [x + 42, y + 10, x + 56, y + 16, x + 56, y + h - 16, x + 42, y + h - 10],
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.3)' : '#bae6fd',
                closed: true,
            });
            const rearGlass = new Konva.Line({
                points: [x + w - 46, y + 16, x + w - 32, y + 11, x + w - 32, y + h - 11, x + w - 46, y + h - 16],
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.3)' : '#bae6fd',
                closed: true,
            });
            const roof = new Konva.Rect({
                x: x + 56,
                y: y + 15,
                width: w - 104,
                height: h - 30,
                stroke: isCad ? '#94a3b8' : '#475569',
                strokeWidth: 1.5,
                fill: isCad ? '#1e293b' : '#ffffff',
                cornerRadius: 4,
            });
            const w1 = new Konva.Rect({ x: x + 26, y: y, width: 22, height: 7, fill: '#0f172a', cornerRadius: 2 });
            const w2 = new Konva.Rect({ x: x + 26, y: y + h - 7, width: 22, height: 7, fill: '#0f172a', cornerRadius: 2 });
            const w3 = new Konva.Rect({ x: x + w - 46, y: y, width: 22, height: 7, fill: '#0f172a', cornerRadius: 2 });
            const w4 = new Konva.Rect({ x: x + w - 46, y: y + h - 7, width: 22, height: 7, fill: '#0f172a', cornerRadius: 2 });

            group.add(carBody);
            group.add(windshield);
            group.add(rearGlass);
            group.add(roof);
            group.add(w1);
            group.add(w2);
            group.add(w3);
            group.add(w4);
            break;
        }

        case 'tree': {
            // Simbol Pohon / Tanaman Landscape
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const r = data.radius ?? 40;

            const canopy = new Konva.Circle({
                x,
                y,
                radius: r,
                stroke: isCad ? '#4ade80' : '#16a34a',
                strokeWidth: 2,
                fill: isCad ? 'rgba(74, 222, 128, 0.2)' : '#dcfce7',
                dash: [6, 4],
            });
            const innerCanopy = new Konva.Circle({
                x,
                y,
                radius: r * 0.7,
                stroke: isCad ? '#86efac' : '#22c55e',
                strokeWidth: 1.5,
            });
            const trunk = new Konva.Circle({
                x,
                y,
                radius: 4,
                fill: isCad ? '#ffffff' : '#14532d',
            });
            group.add(canopy);
            group.add(innerCanopy);
            group.add(trunk);

            for (let i = 0; i < 6; i++) {
                const ang = (i * 60 * Math.PI) / 180;
                group.add(
                    new Konva.Line({
                        points: [x, y, x + Math.cos(ang) * (r * 0.65), y + Math.sin(ang) * (r * 0.65)],
                        stroke: isCad ? '#86efac' : '#15803d',
                        strokeWidth: 1.5,
                    })
                );
            }
            break;
        }

        case 'north_arrow': {
            // Arah Utara Arsitektur (North Arrow)
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const r = 30;

            const outerRing = new Konva.Circle({
                x,
                y,
                radius: r,
                stroke: isCad ? '#ffffff' : '#0f172a',
                strokeWidth: 2,
                fill: isCad ? 'rgba(15, 23, 42, 0.7)' : '#ffffff',
            });
            const northLeft = new Konva.Line({
                points: [x, y - r + 3, x - 9, y + 4, x, y],
                fill: isCad ? '#ffffff' : '#0f172a',
                stroke: isCad ? '#ffffff' : '#0f172a',
                strokeWidth: 1.5,
                closed: true,
            });
            const northRight = new Konva.Line({
                points: [x, y - r + 3, x + 9, y + 4, x, y],
                stroke: isCad ? '#ffffff' : '#0f172a',
                strokeWidth: 1.5,
                fill: 'transparent',
                closed: true,
            });
            const southTip = new Konva.Line({
                points: [x, y, x, y + r - 5],
                stroke: isCad ? '#94a3b8' : '#64748b',
                strokeWidth: 1.5,
            });
            const letterU = new Konva.Text({
                x: x - 6,
                y: y - r - 17,
                text: 'U',
                fontSize: 14,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: isCad ? '#f8fafc' : '#0f172a',
            });
            group.add(outerRing);
            group.add(northLeft);
            group.add(northRight);
            group.add(southTip);
            group.add(letterU);
            break;
        }

        case 'elevation_marker': {
            // Peil Ketinggian Lantai (±0.00)
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const r = 16;
            const elevText = data.text ?? '±0.00';

            const circle = new Konva.Circle({
                x,
                y,
                radius: r,
                stroke: isCad ? '#ffffff' : '#0f172a',
                strokeWidth: 1.5,
                fill: isCad ? '#0f172a' : '#ffffff',
            });
            const hLine = new Konva.Line({ points: [x - r - 4, y, x + r + 4, y], stroke: isCad ? '#ffffff' : '#0f172a', strokeWidth: 1.5 });
            const vLine = new Konva.Line({ points: [x, y - r - 4, x, y + r + 4], stroke: isCad ? '#ffffff' : '#0f172a', strokeWidth: 1.5 });

            const q1 = new Konva.Arc({
                x,
                y,
                innerRadius: 0,
                outerRadius: r,
                angle: 90,
                rotation: -180,
                fill: isCad ? '#38bdf8' : '#0f172a',
            });
            const q3 = new Konva.Arc({
                x,
                y,
                innerRadius: 0,
                outerRadius: r,
                angle: 90,
                rotation: 0,
                fill: isCad ? '#38bdf8' : '#0f172a',
            });

            const label = new Konva.Text({
                x: x + r + 8,
                y: y - 7,
                text: elevText,
                fontSize: 13,
                fontFamily: 'monospace',
                fontStyle: 'bold',
                fill: isCad ? '#38bdf8' : '#0f172a',
            });

            group.add(circle);
            group.add(q1);
            group.add(q3);
            group.add(hLine);
            group.add(vLine);
            group.add(label);
            break;
        }

        case 'section_marker': {
            // Garis Potongan Arsitektur A - A
            const x1 = data.x1 ?? 0;
            const y1 = data.y1 ?? 0;
            const x2 = data.x2 ?? 200;
            const y2 = data.y2 ?? 0;
            const label = data.text ?? 'A';

            const cutLine = new Konva.Line({
                points: [x1, y1, x2, y2],
                stroke: isCad ? '#c084fc' : '#7c3aed',
                strokeWidth: 3,
                dash: [16, 4, 3, 4],
            });

            const b1 = new Konva.Circle({
                x: x1,
                y: y1,
                radius: 16,
                stroke: isCad ? '#c084fc' : '#7c3aed',
                strokeWidth: 2,
                fill: isCad ? '#1e1b4b' : '#f5f3ff',
            });
            const t1 = new Konva.Text({
                x: x1 - 8,
                y: y1 - 7,
                width: 16,
                text: label,
                align: 'center',
                fontSize: 14,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: isCad ? '#ffffff' : '#6d28d9',
            });

            const b2 = new Konva.Circle({
                x: x2,
                y: y2,
                radius: 16,
                stroke: isCad ? '#c084fc' : '#7c3aed',
                strokeWidth: 2,
                fill: isCad ? '#1e1b4b' : '#f5f3ff',
            });
            const t2 = new Konva.Text({
                x: x2 - 8,
                y: y2 - 7,
                width: 16,
                text: label,
                align: 'center',
                fontSize: 14,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: isCad ? '#ffffff' : '#6d28d9',
            });

            const dx = x2 - x1;
            const dy = y2 - y1;
            const len = Math.sqrt(dx * dx + dy * dy) || 1;
            const nx = -dy / len;
            const ny = dx / len;

            const p1 = new Konva.Arrow({
                points: [x1, y1, x1 + nx * 28, y1 + ny * 28],
                pointerLength: 8,
                pointerWidth: 8,
                fill: isCad ? '#c084fc' : '#7c3aed',
                stroke: isCad ? '#c084fc' : '#7c3aed',
                strokeWidth: 2,
            });
            const p2 = new Konva.Arrow({
                points: [x2, y2, x2 + nx * 28, y2 + ny * 28],
                pointerLength: 8,
                pointerWidth: 8,
                fill: isCad ? '#c084fc' : '#7c3aed',
                stroke: isCad ? '#c084fc' : '#7c3aed',
                strokeWidth: 2,
            });

            group.add(cutLine);
            group.add(b1);
            group.add(t1);
            group.add(b2);
            group.add(t2);
            group.add(p1);
            group.add(p2);
            break;
        }

        case 'grid_line': {
            // As Kolom & Bangunan
            const x1 = data.x1 ?? 0;
            const y1 = data.y1 ?? 0;
            const x2 = data.x2 ?? 0;
            const y2 = data.y2 ?? 200;
            const code = data.code ?? '1';

            const line = new Konva.Line({
                points: [x1, y1, x2, y2],
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 1.5,
                dash: [12, 4, 2, 4],
            });

            const bubble = new Konva.Circle({
                x: x2,
                y: y2,
                radius: 14,
                stroke: isCad ? '#38bdf8' : '#0284c7',
                strokeWidth: 2,
                fill: isCad ? '#0f172a' : '#ffffff',
            });
            const text = new Konva.Text({
                x: x2 - 8,
                y: y2 - 6,
                width: 16,
                text: code,
                align: 'center',
                fontSize: 12,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: isCad ? '#38bdf8' : '#0284c7',
            });

            group.add(line);
            group.add(bubble);
            group.add(text);
            break;
        }

        case 'leader_note': {
            // Teks Catatan dengan Panah Penunjuk Spesifikasi
            const x1 = data.x1 ?? 0;
            const y1 = data.y1 ?? 0;
            const x2 = data.x2 ?? 60;
            const y2 = data.y2 ?? -40;
            const noteText = data.text ?? 'Catatan Spesifikasi';
            const shoulderDir = x2 >= x1 ? 1 : -1;
            const shoulderLen = 45;

            const arrow = new Konva.Arrow({
                points: [x2, y2, x1, y1],
                pointerLength: 7,
                pointerWidth: 6,
                fill: isCad ? '#facc15' : '#d97706',
                stroke: isCad ? '#facc15' : '#d97706',
                strokeWidth: 1.5,
            });
            const shoulder = new Konva.Line({
                points: [x2, y2, x2 + shoulderDir * shoulderLen, y2],
                stroke: isCad ? '#facc15' : '#d97706',
                strokeWidth: 1.5,
            });
            const text = new Konva.Text({
                x: shoulderDir > 0 ? x2 + 4 : x2 - shoulderLen - 4,
                y: y2 - 14,
                text: noteText,
                fontSize: 11,
                fontFamily: 'sans-serif',
                fontStyle: 'bold',
                fill: isCad ? '#fef08a' : '#92400e',
            });

            group.add(arrow);
            group.add(shoulder);
            group.add(text);
            break;
        }

        case 'circle': {
            // Lingkaran CAD
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const r = data.radius ?? 40;

            const circle = new Konva.Circle({
                x,
                y,
                radius: r,
                stroke: isCad ? '#38bdf8' : '#1e293b',
                strokeWidth: 2,
                fill: isCad ? 'rgba(56, 189, 248, 0.15)' : 'rgba(241, 245, 249, 0.4)',
            });
            const cross1 = new Konva.Line({ points: [x - 5, y, x + 5, y], stroke: isCad ? '#7dd3fc' : '#64748b', strokeWidth: 1 });
            const cross2 = new Konva.Line({ points: [x, y - 5, x, y + 5], stroke: isCad ? '#7dd3fc' : '#64748b', strokeWidth: 1 });

            group.add(circle);
            group.add(cross1);
            group.add(cross2);
            break;
        }

        case 'arc': {
            // Busur Lengkung CAD
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const r = data.radius ?? 50;
            const angle = data.angle ?? 90;

            const arc = new Konva.Arc({
                x,
                y,
                innerRadius: r,
                outerRadius: r,
                angle: angle,
                rotation: data.rotation ?? 0,
                stroke: isCad ? '#f8fafc' : '#1e293b',
                strokeWidth: 2,
            });
            group.add(arc);
            break;
        }

        case 'rect': {
            const rect = new Konva.Rect({
                x: data.x ?? 0,
                y: data.y ?? 0,
                width: data.width ?? 120,
                height: data.height ?? 100,
                stroke: isCad ? '#38bdf8' : '#1e293b',
                strokeWidth: 3,
                fill: isCad ? 'rgba(56, 189, 248, 0.15)' : (data.fill ?? 'rgba(241, 245, 249, 0.5)'),
            });
            group.add(rect);
            break;
        }

        case 'dimension': {
            const x1 = data.x1 ?? 0;
            const y1 = data.y1 ?? 0;
            const x2 = data.x2 ?? 100;
            const y2 = data.y2 ?? 0;
            const autoText = calculateDimensionText(data, currentSheet.value.unit);
            const displayText = (data.text !== undefined && data.text !== null && String(data.text).trim() !== '')
                ? String(data.text)
                : autoText;

            const dimColor = isCad ? '#facc15' : '#d97706';
            const line = new Konva.Line({
                points: [x1, y1, x2, y2],
                stroke: dimColor,
                strokeWidth: 1.5,
            });
            const tick1 = new Konva.Line({
                points: [x1 - 5, y1 - 5, x1 + 5, y1 + 5],
                stroke: dimColor,
                strokeWidth: 2,
            });
            const tick2 = new Konva.Line({
                points: [x2 - 5, y2 - 5, x2 + 5, y2 + 5],
                stroke: dimColor,
                strokeWidth: 2,
            });
            const text = new Konva.Text({
                name: 'dimText',
                x: (x1 + x2) / 2 - 25,
                y: (y1 + y2) / 2 - 16,
                text: displayText,
                fontSize: 11,
                fontFamily: 'monospace',
                fill: isCad ? '#fef08a' : '#b45309',
                fontStyle: 'bold',
            });
            group.add(line);
            group.add(tick1);
            group.add(tick2);
            group.add(text);
            break;
        }

        case 'room_label': {
            const x = data.x ?? 0;
            const y = data.y ?? 0;
            const labelText = data.text ?? 'R. UTAMA\n±0.00';
            const boundCode = data.capture_code || localProjectCaptures.value.find(c => c.room_element_id === el.id)?.code;

            const bg = new Konva.Rect({
                x: x - 45,
                y: y - 18,
                width: 90,
                height: 36,
                fill: isCad ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.9)',
                stroke: boundCode ? '#ea580c' : (isCad ? '#38bdf8' : '#94a3b8'),
                strokeWidth: boundCode ? 2 : 1.5,
                cornerRadius: 4,
            });
            const text = new Konva.Text({
                x: x - 40,
                y: y - 12,
                text: labelText,
                fontSize: 11,
                fontFamily: 'sans-serif',
                align: 'center',
                fill: isCad ? '#f8fafc' : '#0f172a',
                fontStyle: 'bold',
            });
            group.add(bg);
            group.add(text);

            if (boundCode) {
                const badge = new Konva.Rect({
                    x: x - 45,
                    y: y - 36,
                    width: 90,
                    height: 16,
                    fill: '#ea580c',
                    cornerRadius: 3,
                });
                const badgeText = new Konva.Text({
                    x: x - 45,
                    y: y - 33,
                    width: 90,
                    text: `📷 ${boundCode}`,
                    fontSize: 9,
                    fontFamily: 'monospace',
                    align: 'center',
                    fill: '#ffffff',
                    fontStyle: 'bold',
                });
                group.add(badge);
                group.add(badgeText);
            }
            break;
        }

        case 'line':
        default: {
            const line = new Konva.Line({
                points: [data.x1 ?? 0, data.y1 ?? 0, data.x2 ?? 100, data.y2 ?? 0],
                stroke: data.stroke ?? (isCad ? '#f8fafc' : '#475569'),
                strokeWidth: data.strokeWidth ?? 2,
            });
            group.add(line);
            break;
        }
    }

    let groupStartPositions = new Map<Konva.Node, { x: number; y: number }>();
    let isMultiDragging = false;
    let isGroupCloneDrag = false;

    group.on('dragstart', (e) => {
        pendingToggleOff = null;
        pendingSingleSelect = null;
        isGroupCloneDrag = Boolean(isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey);
        groupStartPositions.clear();
        isMultiDragging = Boolean(tr && tr.nodes().length > 1 && tr.nodes().includes(group));
        if (isMultiDragging && tr) {
            tr.nodes().forEach((n) => {
                groupStartPositions.set(n, { x: n.x(), y: n.y() });
            });
            if (selectionOverlay && selectionOverlay.visible()) {
                groupStartPositions.set(selectionOverlay, { x: selectionOverlay.x(), y: selectionOverlay.y() });
            }
            tempLayer?.visible(false);
        }
        if (isGroupCloneDrag && stage) {
            stage.container().style.cursor = 'copy';
        }
    });

    group.on('dragmove', (e) => {
        if (isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey) {
            isGroupCloneDrag = true;
            if (stage) stage.container().style.cursor = 'copy';
        }
        if (isMultiDragging && tr) {
            const dx = group.x();
            const dy = group.y();
            tr.nodes().forEach((n) => {
                if (n !== group) {
                    const orig = groupStartPositions.get(n);
                    if (orig) {
                        n.x(orig.x + dx);
                        n.y(orig.y + dy);
                    }
                }
            });
            if (selectionOverlay && selectionOverlay.visible()) {
                const origOverlay = groupStartPositions.get(selectionOverlay);
                if (origOverlay) {
                    selectionOverlay.x(origOverlay.x + dx);
                    selectionOverlay.y(origOverlay.y + dy);
                }
            }
            mainLayer?.batchDraw();
        }
    });

    group.on('dragend', (e) => {
        tempLayer?.visible(true);
        const totalDx = group.x();
        const totalDy = group.y();
        const isClone = isGroupCloneDrag || isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey;
        if (totalDx === 0 && totalDy === 0) {
            updateSelectionOverlay();
            return;
        }

        if (isClone) {
            // Ctrl + Drag: Buat objek salinan di posisi baru, pertahankan posisi objek asli!
            let itemsToClone: Array<{ id: string; type: string; data: Record<string, any> }> = [];
            if (isMultiDragging && tr) {
                const ids = new Set(tr.nodes().map((n) => n.id()));
                itemsToClone = elementList.value.filter((x) => ids.has(x.id));
            } else {
                const found = elementList.value.find((x) => x.id === el.id);
                if (found) itemsToClone = [found];
            }

            const newElements: Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }> = [];
            itemsToClone.forEach((item) => {
                const newId = 'el_' + Math.random().toString(36).substring(2, 9);
                const clonedData = JSON.parse(JSON.stringify(item.data));
                const newItem = {
                    id: newId,
                    type: item.type,
                    data: clonedData,
                    sort_order: elementList.value.length + newElements.length,
                };
                applyOffsetToElement(newItem, totalDx, totalDy);
                newElements.push(newItem);
                elementList.value.push(newItem);
            });

            restoreFromState(elementList.value, true);

            if (tr && newElements.length > 0) {
                const newIds = new Set(newElements.map((x) => x.id));
                const newNodes = mainLayer!.getChildren((node) => newIds.has(node.id())) as Konva.Group[];
                newNodes.forEach((n) => n.draggable(props.canEdit));
                tr.nodes(newNodes);
                tr.moveToTop();
                if (newNodes.length === 1) {
                    selectedNodeId.value = newNodes[0].id();
                    const found = elementList.value.find((x) => x.id === newNodes[0].id());
                    selectedElementData.value = found ? { ...found.data, type: found.type } : null;
                } else {
                    selectedNodeId.value = 'multi';
                    selectedElementData.value = {
                        type: `${newNodes.length} Objek Disalin (Ctrl+Drag)`,
                        count: newNodes.length,
                    };
                }
                updateSelectionOverlay();
            }

            pushHistory();
            triggerAutoSaveDebounced();
            toast.success(`${newElements.length} objek berhasil disalin & digeser (Ctrl+Drag)!`);
        } else {
            if (isMultiDragging && tr) {
                tr.nodes().forEach((n) => {
                    const f = elementList.value.find((x) => x.id === n.id());
                    if (f) applyOffsetToElement(f, totalDx, totalDy);
                });
            } else {
                const found = elementList.value.find((x) => x.id === el.id);
                if (found) applyOffsetToElement(found, totalDx, totalDy);
            }

            restoreFromState(elementList.value, true);
            updateSelectionOverlay();
            pushHistory();
            triggerAutoSaveDebounced();
        }

        isGroupCloneDrag = false;
        if (stage && containerRef.value) {
            containerRef.value.style.cursor = activeTool.value === 'select' ? 'default' : 'crosshair';
        }
    });

    return group;
}

// ── INITIALIZE KONVA STAGE ─────────────────────────────────────────────────
function initCanvas() {
    if (!containerRef.value) return;

    const width = containerRef.value.clientWidth || window.innerWidth;
    const height = containerRef.value.clientHeight || 700;

    stage = new Konva.Stage({
        container: containerRef.value,
        width,
        height,
    });

    paperBgLayer = new Konva.Layer();
    gridLayer = new Konva.Layer();
    paperLayer = new Konva.Layer();
    mainLayer = new Konva.Layer();
    tempLayer = new Konva.Layer();

    tr = new Konva.Transformer({
        rotateEnabled: true,
        shouldOverdrawWholeArea: false,
        borderStroke: '#8b5cf6',
        borderStrokeWidth: 1.5,
        anchorStroke: '#8b5cf6',
        anchorFill: '#ffffff',
        anchorSize: 8,
    });
    mainLayer.add(tr);

    selectionOverlay = new Konva.Rect({
        visible: false,
        draggable: true,
        fill: 'rgba(139, 92, 246, 0.06)',
        stroke: 'rgba(139, 92, 246, 0.35)',
        strokeWidth: 1,
        dash: [4, 4],
        cornerRadius: 4,
        listening: true,
    });
    mainLayer.add(selectionOverlay);

    let prevOverlayPos = { x: 0, y: 0 };
    let totalOverlayDx = 0;
    let totalOverlayDy = 0;
    let overlayStartNodePositions = new Map<Konva.Node, { x: number; y: number }>();

    selectionOverlay.on('mouseenter', () => {
        if (stage && !isSpacePressed.value) stage.container().style.cursor = 'move';
    });
    selectionOverlay.on('mouseleave', () => {
        if (stage && !isSpacePressed.value) {
            stage.container().style.cursor = activeTool.value === 'select' ? 'default' : (activeTool.value === 'pan' ? 'grab' : 'crosshair');
        }
    });

    let isOverlayCloneDrag = false;

    selectionOverlay.on('dragstart', (e) => {
        pendingSingleSelect = null;
        pendingToggleOff = null;
        isOverlayCloneDrag = Boolean(isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey);
        prevOverlayPos = { x: selectionOverlay!.x(), y: selectionOverlay!.y() };
        totalOverlayDx = 0;
        totalOverlayDy = 0;
        overlayStartNodePositions.clear();
        if (tr) {
            tr.nodes().forEach((n) => {
                overlayStartNodePositions.set(n, { x: n.x(), y: n.y() });
            });
        }
        tempLayer?.visible(false);
        if (isOverlayCloneDrag && stage) {
            stage.container().style.cursor = 'copy';
        }
    });

    selectionOverlay.on('dragmove', (e) => {
        if (isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey) {
            isOverlayCloneDrag = true;
            if (stage) stage.container().style.cursor = 'copy';
        }
        const curX = selectionOverlay!.x();
        const curY = selectionOverlay!.y();
        const dx = curX - prevOverlayPos.x;
        const dy = curY - prevOverlayPos.y;
        prevOverlayPos = { x: curX, y: curY };
        totalOverlayDx += dx;
        totalOverlayDy += dy;

        if (tr && tr.nodes().length > 0) {
            tr.nodes().forEach((n) => {
                const orig = overlayStartNodePositions.get(n);
                if (orig) {
                    n.x(orig.x + totalOverlayDx);
                    n.y(orig.y + totalOverlayDy);
                }
            });
            mainLayer?.batchDraw();
        }
    });

    selectionOverlay.on('dragend', (e) => {
        tempLayer?.visible(true);
        const isClone = isOverlayCloneDrag || isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey;
        if (totalOverlayDx !== 0 || totalOverlayDy !== 0) {
            if (isClone && tr && tr.nodes().length > 0) {
                // Ctrl + Drag: Salin dan tempel objek baru di posisi baru, pertahankan objek lama!
                const selectedIds = new Set(tr.nodes().map((n) => n.id()));
                const itemsToClone = elementList.value.filter((el) => selectedIds.has(el.id));
                const newElements: Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }> = [];

                itemsToClone.forEach((item) => {
                    const newId = 'el_' + Math.random().toString(36).substring(2, 9);
                    const clonedData = JSON.parse(JSON.stringify(item.data));
                    const newItem = {
                        id: newId,
                        type: item.type,
                        data: clonedData,
                        sort_order: elementList.value.length + newElements.length,
                    };
                    applyOffsetToElement(newItem, totalOverlayDx, totalOverlayDy);
                    newElements.push(newItem);
                    elementList.value.push(newItem);
                });

                restoreFromState(elementList.value, true);

                if (tr && newElements.length > 0) {
                    const newIds = new Set(newElements.map((x) => x.id));
                    const newNodes = mainLayer!.getChildren((node) => newIds.has(node.id())) as Konva.Group[];
                    newNodes.forEach((n) => n.draggable(props.canEdit));
                    tr.nodes(newNodes);
                    tr.moveToTop();
                    if (newNodes.length === 1) {
                        selectedNodeId.value = newNodes[0].id();
                        const found = elementList.value.find((x) => x.id === newNodes[0].id());
                        selectedElementData.value = found ? { ...found.data, type: found.type } : null;
                    } else {
                        selectedNodeId.value = 'multi';
                        selectedElementData.value = {
                            type: `${newNodes.length} Objek Disalin (Ctrl+Drag)`,
                            count: newNodes.length,
                        };
                    }
                    updateSelectionOverlay();
                }

                pushHistory();
                triggerAutoSaveDebounced();
                toast.success(`${newElements.length} objek berhasil disalin & digeser (Ctrl+Drag)!`);
            } else {
                if (tr && tr.nodes().length > 0) {
                    tr.nodes().forEach((n) => {
                        const f = elementList.value.find((x) => x.id === n.id());
                        if (f) applyOffsetToElement(f, totalOverlayDx, totalOverlayDy);
                    });
                }
                restoreFromState(elementList.value, true);
                updateSelectionOverlay();
                pushHistory();
                triggerAutoSaveDebounced();
            }
        } else {
            updateSelectionOverlay();
        }
        isOverlayCloneDrag = false;
        if (stage && containerRef.value) {
            containerRef.value.style.cursor = activeTool.value === 'select' ? 'default' : 'crosshair';
        }
    });

    tr.on('transform', () => {
        updateSelectionOverlay();
    });
    tr.on('transformend', () => {
        updateSelectionOverlay();
    });

    stage.add(paperBgLayer);
    stage.add(gridLayer);
    stage.add(paperLayer);
    stage.add(mainLayer);
    stage.add(tempLayer);

    drawPaperBoundary();
    drawGrid();
    loadExistingElements();
    setupDrawingInteractions();
    setupZoomAndPanEvents();

    // Initial history state
    historyStack.value = [JSON.stringify(elementList.value)];
    historyIndex.value = 0;
}

function loadExistingElements() {
    if (!mainLayer) return;
    elementList.value = [];

    props.elements.forEach((el, index) => {
        const parsedData = typeof el.data === 'string' ? JSON.parse(el.data) : el.data;
        const item = {
            id: el.id,
            type: el.type,
            data: parsedData,
            sort_order: el.sort_order ?? index,
        };
        elementList.value.push(item);

        if (item.type !== 'paper_frame') {
            const node = createShapeFromElement(item);
            if (node) {
                applyLayerVisibilityToNode(node, item.type);
                mainLayer!.add(node);
            }
        }
    });

    drawPaperBoundary();
    mainLayer.batchDraw();
}

// ── AUTOCAD MARQUEE BOX SELECTION HELPERS ─────────────────────────────────
let isBoxSelecting = false;
let boxStartPt = { x: 0, y: 0 };
let selectionBoxRect: Konva.Rect | null = null;

function getElementBoundingBox(el: { id: string; type: string; data: Record<string, any> }) {
    const d = el.data || {};
    if (d.x1 !== undefined && d.x2 !== undefined && d.y1 !== undefined && d.y2 !== undefined) {
        return {
            x: Math.min(d.x1, d.x2),
            y: Math.min(d.y1, d.y2),
            width: Math.abs(d.x2 - d.x1) || 6,
            height: Math.abs(d.y2 - d.y1) || 6,
        };
    }
    const w = d.width || d.size || 50;
    const h = d.height || d.size || 50;
    return {
        x: d.x ?? 0,
        y: d.y ?? 0,
        width: w,
        height: h,
    };
}

function rectsIntersect(
    r1: { x: number; y: number; width: number; height: number },
    r2: { x: number; y: number; width: number; height: number }
) {
    return !(
        r2.x > r1.x + r1.width ||
        r2.x + r2.width < r1.x ||
        r2.y > r1.y + r1.height ||
        r2.y + r2.height < r1.y
    );
}

function rectEncloses(
    outer: { x: number; y: number; width: number; height: number },
    inner: { x: number; y: number; width: number; height: number }
) {
    return (
        inner.x >= outer.x &&
        inner.y >= outer.y &&
        inner.x + inner.width <= outer.x + outer.width &&
        inner.y + inner.height <= outer.y + outer.height
    );
}

function finishBoxSelection(e?: any) {
    if (!isBoxSelecting && !selectionBoxRect) {
        tempLayer?.destroyChildren();
        tempLayer?.batchDraw();
        return;
    }

    isBoxSelecting = false;
    const pos = stage?.getPointerPosition();
    let endPt = boxStartPt;
    if (pos && stage) {
        try {
            const transform = stage.getAbsoluteTransform().copy().invert();
            endPt = transform.point(pos);
        } catch {}
    }

    const boxX = Math.min(boxStartPt.x, endPt.x);
    const boxY = Math.min(boxStartPt.y, endPt.y);
    const boxW = Math.abs(endPt.x - boxStartPt.x);
    const boxH = Math.abs(endPt.y - boxStartPt.y);
    const isCrossing = endPt.x < boxStartPt.x;

    if (selectionBoxRect) {
        selectionBoxRect.destroy();
        selectionBoxRect = null;
    }
    tempLayer?.destroyChildren();
    tempLayer?.batchDraw();

    // Jika kotak ditarik lebih dari 6px, lakukan filtering elemen
    if (boxW > 6 && boxH > 6 && mainLayer) {
        const selectionRect = { x: boxX, y: boxY, width: boxW, height: boxH };

        const matchedElements = elementList.value.filter((el) => {
            if (el.type === 'paper_frame') return false;
            const elBox = getElementBoundingBox(el);
            return isCrossing
                ? rectsIntersect(selectionRect, elBox)
                : rectEncloses(selectionRect, elBox);
        });

        const matchedIds = new Set(matchedElements.map((el) => el.id));
        const matchedNodes = mainLayer.getChildren((node) =>
            node !== tr && node !== selectionOverlay && matchedIds.has(node.id())
        ) as Konva.Group[];

        let finalNodes: Konva.Node[] = matchedNodes;
        const isShiftOrCtrl = Boolean(e?.shiftKey || e?.ctrlKey || (e?.evt && (e.evt.shiftKey || e.evt.ctrlKey)));
        if (isShiftOrCtrl) {
            const currentNodes = tr?.nodes() || [];
            const combined = [...currentNodes];
            matchedNodes.forEach((n) => {
                if (!combined.includes(n)) combined.push(n);
            });
            finalNodes = combined;
        }

        tr?.nodes(finalNodes);

        if (finalNodes.length === 1) {
            selectedNodeId.value = finalNodes[0].id();
            const found = elementList.value.find((x) => x.id === finalNodes[0].id());
            selectedElementData.value = found ? { ...found.data, type: found.type } : null;
        } else if (finalNodes.length > 1) {
            selectedNodeId.value = 'multi';
            const typeCounts = new Map<string, number>();
            finalNodes.forEach((n) => {
                const found = elementList.value.find((x) => x.id === n.id());
                if (found) {
                    typeCounts.set(found.type, (typeCounts.get(found.type) || 0) + 1);
                }
            });
            const breakdown = Array.from(typeCounts.entries())
                .map(([t, c]) => `${c}× ${t}`)
                .join(', ');

            selectedElementData.value = {
                type: `${finalNodes.length} Objek Terblok`,
                count: finalNodes.length,
                detail: breakdown,
            };
        } else if (!isShiftOrCtrl) {
            selectedNodeId.value = null;
            selectedElementData.value = null;
        }

        updateSelectionOverlay();
        mainLayer.batchDraw();
    }
}

// ── DRAWING INTERACTIONS & TOOL SWITCHING ──────────────────────────────────
let isDrawing = false;
let startX = 0;
let startY = 0;
let previewShape: Konva.Line | Konva.Rect | null = null;

function getElementGroupFromTarget(target: Konva.Node | null): Konva.Group | null {
    if (!target || target === stage || target === selectionOverlay || target === tr) return null;
    if (target.getLayer() !== mainLayer) return null;

    let curr: Konva.Node | null = target;
    while (curr && curr.parent && curr.parent !== mainLayer) {
        curr = curr.parent;
    }
    if (curr && curr instanceof Konva.Group && curr !== (tr as any) && (curr as any) !== selectionOverlay) {
        return curr;
    }
    return null;
}

function setupDrawingInteractions() {
    const curStage = stage;
    if (!curStage || !tempLayer || !mainLayer) return;

    curStage.on('mousedown touchstart', (e) => {
        if (!props.canEdit || isSpacePressed.value) return;
        if (e.target.getLayer() === paperLayer || e.target.getLayer() === paperBgLayer || isResizingSheet.value) return;

        // Middle mouse click OR Pan Tool = start pan
        if (e.evt.button === 1 || activeTool.value === 'pan') {
            curStage.draggable(true);
            if (containerRef.value) containerRef.value.style.cursor = 'grabbing';
            return;
        }

        // Selection Tool (AutoCAD Click Selection + AutoCAD Box Drag Selection)
        if (activeTool.value === 'select') {
            const clickedOnTransformer = e.target.getParent()?.className === 'Transformer' || e.target.className === 'Transformer';
            if (clickedOnTransformer) return;

            const isMultiKey = Boolean(e.evt.shiftKey || e.evt.ctrlKey || e.evt.metaKey || isCtrlHeld.value);

            let parentGroup = getElementGroupFromTarget(e.target);

            // If clicked on selectionOverlay, check if an element is directly under the pointer
            if (!parentGroup && e.target === selectionOverlay) {
                selectionOverlay.visible(false);
                const pos = curStage.getPointerPosition();
                if (pos) {
                    const hit = curStage.getIntersection(pos);
                    parentGroup = getElementGroupFromTarget(hit);
                }
                selectionOverlay.visible(Boolean(tr && tr.nodes().length > 0));
            }

            if (parentGroup) {
                if (isMultiKey) {
                    const currentNodes = [...(tr?.nodes() || [])];
                    const idx = currentNodes.indexOf(parentGroup);
                    if (idx >= 0) {
                        // Objek ini sudah ada di seleksi!
                        // JANGAN langsung dihapus, karena user mungkin ingin melakukan Ctrl + Drag (salin seleksi)!
                        // Kita jadikan pendingToggleOff. Jika user melepas mouse tanpa drag, baru di-deselect di mouseup.
                        pendingToggleOff = parentGroup;
                        pendingSingleSelect = null;
                        return;
                    } else {
                        // Objek baru: tambahkan ke multi-seleksi
                        pendingToggleOff = null;
                        pendingSingleSelect = null;
                        currentNodes.push(parentGroup);
                        currentNodes.forEach((n) => n.draggable(props.canEdit));
                        tr?.nodes(currentNodes);
                        tr?.moveToTop();
                        if (currentNodes.length === 1) {
                            selectedNodeId.value = currentNodes[0].id();
                            const found = elementList.value.find((x) => x.id === currentNodes[0].id());
                            selectedElementData.value = found ? { ...found.data, type: found.type } : null;
                        } else {
                            selectedNodeId.value = 'multi';
                            const typeCounts = new Map<string, number>();
                            currentNodes.forEach((n) => {
                                const found = elementList.value.find((x) => x.id === n.id());
                                if (found) {
                                    typeCounts.set(found.type, (typeCounts.get(found.type) || 0) + 1);
                                }
                            });
                            const breakdown = Array.from(typeCounts.entries())
                                .map(([t, c]) => `${c}× ${t}`)
                                .join(', ');

                            selectedElementData.value = {
                                type: `${currentNodes.length} Objek Terpilih`,
                                count: currentNodes.length,
                                detail: breakdown,
                            };
                        }
                    }
                } else {
                    const currentNodes = tr?.nodes() || [];
                    if (currentNodes.length > 1 && currentNodes.includes(parentGroup)) {
                        // User mengklik salah satu objek yang sudah terpilih di antara multi-seleksi tanpa Ctrl
                        // Jika di-drag, geser bersama! Jika dilepas tanpa drag, jadikan single select.
                        pendingSingleSelect = parentGroup;
                        pendingToggleOff = null;
                        updateSelectionOverlay();
                        return;
                    } else {
                        // Klik tunggal: pilih hanya objek ini
                        pendingToggleOff = null;
                        pendingSingleSelect = null;
                        selectedNodeId.value = parentGroup.id();
                        parentGroup.draggable(props.canEdit);
                        tr?.nodes([parentGroup]);
                        tr?.moveToTop();
                        const found = elementList.value.find((x) => x.id === parentGroup.id());
                        selectedElementData.value = found ? { ...found.data, type: found.type } : null;
                    }
                }
                updateSelectionOverlay();
                mainLayer?.batchDraw();
                return;
            }

            // Jika mengklik area kosong di dalam selectionOverlay tanpa Ctrl, biarkan untuk di-drag
            if (e.target === selectionOverlay && !isMultiKey) {
                return;
            }

            // Klik di area kosong luar: Hapus seleksi jika tidak menekan Ctrl/Shift
            if (!isMultiKey) {
                pendingToggleOff = null;
                pendingSingleSelect = null;
                selectedNodeId.value = null;
                selectedElementData.value = null;
                tr?.nodes([]);
                updateSelectionOverlay();
                mainLayer?.batchDraw();
            }

            const pos = curStage.getPointerPosition();
            if (pos) {
                const transform = curStage.getAbsoluteTransform().copy().invert();
                const pt = transform.point(pos);
                isBoxSelecting = true;
                boxStartPt = { x: pt.x, y: pt.y };

                selectionBoxRect = new Konva.Rect({
                    x: pt.x,
                    y: pt.y,
                    width: 0,
                    height: 0,
                    fill: 'rgba(59, 130, 246, 0.22)',
                    stroke: '#2563eb',
                    strokeWidth: 1.5,
                    dash: [],
                    listening: false,
                });
                tempLayer?.add(selectionBoxRect);
                tempLayer?.batchDraw();
            }
            return;
        }

        // Eraser Tool
        if (activeTool.value === 'eraser') {
            const parentGroup = getElementGroupFromTarget(e.target);
            if (parentGroup) {
                const id = parentGroup.id();
                parentGroup.destroy();
                tr?.nodes([]);
                elementList.value = elementList.value.filter((x) => x.id !== id);
                mainLayer?.batchDraw();
                pushHistory();
            }
            return;
        }

        // CAD Creation Tools
        const pos = curStage.getPointerPosition();
        if (!pos) return;

        const transform = curStage.getAbsoluteTransform().copy().invert();
        const pt = transform.point(pos);

        startX = snap(pt.x);
        startY = snap(pt.y);
        isDrawing = true;

        const isCad = canvasMode.value === 'infinite';
        if (
            activeTool.value === 'wall' ||
            activeTool.value === 'line' ||
            activeTool.value === 'dimension' ||
            activeTool.value === 'grid_line' ||
            activeTool.value === 'section_marker' ||
            activeTool.value === 'leader_note'
        ) {
            const previewStroke = activeTool.value === 'wall'
                ? (isCad ? '#f8fafc' : '#1e293b')
                : (activeTool.value === 'dimension' || activeTool.value === 'leader_note')
                    ? (isCad ? '#facc15' : '#d97706')
                    : activeTool.value === 'grid_line'
                        ? (isCad ? '#38bdf8' : '#0284c7')
                        : activeTool.value === 'section_marker'
                            ? (isCad ? '#c084fc' : '#7c3aed')
                            : (isCad ? '#f8fafc' : '#64748b');

            previewShape = new Konva.Line({
                points: [startX, startY, startX, startY],
                stroke: previewStroke,
                strokeWidth: activeTool.value === 'wall' ? 12 : (activeTool.value === 'section_marker' ? 3 : 2),
                dash: [4, 4],
            });
            tempLayer?.add(previewShape);
        } else if (activeTool.value === 'rect') {
            previewShape = new Konva.Rect({
                x: startX,
                y: startY,
                width: 0,
                height: 0,
                stroke: isCad ? '#38bdf8' : '#1e293b',
                strokeWidth: 2,
                dash: [4, 4],
            });
            tempLayer?.add(previewShape);
        } else if (activeTool.value === 'circle') {
            previewShape = new Konva.Circle({
                x: startX,
                y: startY,
                radius: 0,
                stroke: isCad ? '#38bdf8' : '#1e293b',
                strokeWidth: 2,
                dash: [4, 4],
            });
            tempLayer?.add(previewShape);
        }
    });

    curStage.on('mousemove touchmove', () => {
        // Handle AutoCAD Marquee Box Selection Preview
        if (isBoxSelecting && selectionBoxRect && stage) {
            const pos = stage.getPointerPosition();
            if (pos) {
                const transform = stage.getAbsoluteTransform().copy().invert();
                const pt = transform.point(pos);

                const x = Math.min(boxStartPt.x, pt.x);
                const y = Math.min(boxStartPt.y, pt.y);
                const w = Math.abs(pt.x - boxStartPt.x);
                const h = Math.abs(pt.y - boxStartPt.y);

                // AutoCAD Crossing (kanan ke kiri -> Hijau putus-putus) vs Window (kiri ke kanan -> Biru solid)
                const isCrossing = pt.x < boxStartPt.x;

                selectionBoxRect.setAttrs({
                    x,
                    y,
                    width: w,
                    height: h,
                    fill: isCrossing ? 'rgba(34, 197, 94, 0.22)' : 'rgba(59, 130, 246, 0.22)',
                    stroke: isCrossing ? '#16a34a' : '#2563eb',
                    dash: isCrossing ? [6, 4] : [],
                });
                tempLayer?.batchDraw();
            }
            return;
        }

        if (!isDrawing || !previewShape || !stage) return;

        const pos = stage.getPointerPosition();
        if (!pos) return;

        const transform = stage.getAbsoluteTransform().copy().invert();
        const pt = transform.point(pos);
        const currentX = snap(pt.x);
        const currentY = snap(pt.y);

        if (previewShape instanceof Konva.Line) {
            previewShape.points([startX, startY, currentX, currentY]);
        } else if (previewShape instanceof Konva.Rect) {
            previewShape.width(currentX - startX);
            previewShape.height(currentY - startY);
        } else if (previewShape instanceof Konva.Circle) {
            const r = Math.round(Math.sqrt((currentX - startX) ** 2 + (currentY - startY) ** 2));
            previewShape.radius(r);
        }

        tempLayer?.batchDraw();
    });

    curStage.on('mouseup touchend', (e) => {
        if ((e.evt.button === 1 || activeTool.value === 'pan') && !isSpacePressed.value) {
            stage?.draggable(false);
            if (containerRef.value) {
                containerRef.value.style.cursor = activeTool.value === 'pan' ? 'grab' : 'crosshair';
            }
        }

        // Handle pending multi-selection toggle off (jika klik tanpa drag pada objek yang sudah terpilih dengan Ctrl/Shift)
        if (pendingToggleOff) {
            const targetGroup = pendingToggleOff;
            pendingToggleOff = null;
            pendingSingleSelect = null;
            const currentNodes = (tr?.nodes() || []).filter((n) => n !== targetGroup);
            tr?.nodes(currentNodes);
            if (currentNodes.length === 1) {
                selectedNodeId.value = currentNodes[0].id();
                const found = elementList.value.find((x) => x.id === currentNodes[0].id());
                selectedElementData.value = found ? { ...found.data, type: found.type } : null;
            } else if (currentNodes.length > 1) {
                selectedNodeId.value = 'multi';
                const typeCounts = new Map<string, number>();
                currentNodes.forEach((n) => {
                    const found = elementList.value.find((x) => x.id === n.id());
                    if (found) {
                        typeCounts.set(found.type, (typeCounts.get(found.type) || 0) + 1);
                    }
                });
                const breakdown = Array.from(typeCounts.entries())
                    .map(([t, c]) => `${c}× ${t}`)
                    .join(', ');

                selectedElementData.value = {
                    type: `${currentNodes.length} Objek Terpilih`,
                    count: currentNodes.length,
                    detail: breakdown,
                };
            } else {
                selectedNodeId.value = null;
                selectedElementData.value = null;
            }
            updateSelectionOverlay();
            mainLayer?.batchDraw();
            return;
        }

        // Handle pending single select (jika user mengklik objek dalam grup multi-select tanpa Ctrl dan tanpa drag)
        if (pendingSingleSelect) {
            const targetGroup = pendingSingleSelect;
            pendingSingleSelect = null;
            pendingToggleOff = null;
            selectedNodeId.value = targetGroup.id();
            targetGroup.draggable(props.canEdit);
            tr?.nodes([targetGroup]);
            tr?.moveToTop();
            const found = elementList.value.find((x) => x.id === targetGroup.id());
            selectedElementData.value = found ? { ...found.data, type: found.type } : null;
            updateSelectionOverlay();
            mainLayer?.batchDraw();
            return;
        }

        // Selesaikan seleksi kotak AutoCAD Marquee Box Selection
        if (isBoxSelecting || selectionBoxRect) {
            finishBoxSelection(e);
            return;
        }

        if (!isDrawing || !stage) return;
        isDrawing = false;

        const pos = stage.getPointerPosition();
        if (!pos) return;

        const transform = stage.getAbsoluteTransform().copy().invert();
        const pt = transform.point(pos);
        const endX = snap(pt.x);
        const endY = snap(pt.y);

        if (previewShape) {
            previewShape.destroy();
            previewShape = null;
            tempLayer?.batchDraw();
        }

        // Generate element
        const id = 'el_' + Math.random().toString(36).substring(2, 9);
        let newElem: { id: string; type: string; data: Record<string, any>; sort_order: number } | null = null;

        if (
            activeTool.value === 'wall' ||
            activeTool.value === 'line' ||
            activeTool.value === 'dimension' ||
            activeTool.value === 'grid_line' ||
            activeTool.value === 'section_marker' ||
            activeTool.value === 'leader_note'
        ) {
            const effectiveEndX = (startX === endX && startY === endY)
                ? (activeTool.value === 'grid_line' ? startX : startX + 160)
                : endX;
            const effectiveEndY = (startX === endX && startY === endY)
                ? (activeTool.value === 'grid_line' ? startY + 160 : (activeTool.value === 'leader_note' ? startY - 45 : startY))
                : endY;

            newElem = {
                id,
                type: activeTool.value,
                data: {
                    x1: startX,
                    y1: startY,
                    x2: effectiveEndX,
                    y2: effectiveEndY,
                    ...(activeTool.value === 'grid_line' ? { code: '1' } : {}),
                    ...(activeTool.value === 'section_marker' ? { text: 'A' } : {}),
                    ...(activeTool.value === 'leader_note' ? { text: 'Catatan Spesifikasi' } : {}),
                },
                sort_order: elementList.value.length,
            };
        } else if (activeTool.value === 'circle') {
            const dist = Math.round(Math.sqrt((endX - startX) ** 2 + (endY - startY) ** 2));
            const radius = Math.max(20, dist || 40);
            newElem = {
                id,
                type: 'circle',
                data: { x: startX, y: startY, radius },
                sort_order: elementList.value.length,
            };
        } else if (activeTool.value === 'rect') {
            const w = Math.abs(endX - startX) || 120;
            const h = Math.abs(endY - startY) || 100;
            newElem = {
                id,
                type: 'rect',
                data: { x: Math.min(startX, endX), y: Math.min(startY, endY), width: w, height: h },
                sort_order: elementList.value.length,
            };
        } else if (activeTool.value === 'arc') {
            newElem = { id, type: 'arc', data: { x: startX, y: startY, radius: 50, angle: 90 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'column') {
            newElem = { id, type: 'column', data: { x: startX, y: startY, size: 30 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'door') {
            newElem = { id, type: 'door', data: { x: startX, y: startY, size: 80 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'double_door') {
            newElem = { id, type: 'double_door', data: { x: startX, y: startY, width: 120 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'sliding_door') {
            newElem = { id, type: 'sliding_door', data: { x: startX, y: startY, width: 120, height: 16 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'window') {
            newElem = { id, type: 'window', data: { x: startX, y: startY, width: 100, height: 16 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'stair') {
            newElem = { id, type: 'stair', data: { x: startX, y: startY, width: 80, height: 160 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'sofa') {
            newElem = { id, type: 'sofa', data: { x: startX, y: startY, width: 140, height: 65 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'bed') {
            newElem = { id, type: 'bed', data: { x: startX, y: startY, width: 120, height: 140 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'table') {
            newElem = { id, type: 'table', data: { x: startX, y: startY, width: 100, height: 70 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'kitchen') {
            newElem = { id, type: 'kitchen', data: { x: startX, y: startY, width: 150, height: 60 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'toilet') {
            newElem = { id, type: 'toilet', data: { x: startX, y: startY, width: 50, height: 45 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'washbasin') {
            newElem = { id, type: 'washbasin', data: { x: startX, y: startY, width: 50, height: 40 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'shower') {
            newElem = { id, type: 'shower', data: { x: startX, y: startY, width: 80, height: 80 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'car') {
            newElem = { id, type: 'car', data: { x: startX, y: startY, width: 160, height: 75 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'tree') {
            newElem = { id, type: 'tree', data: { x: startX, y: startY, radius: 40 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'north_arrow') {
            newElem = { id, type: 'north_arrow', data: { x: startX, y: startY }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'elevation_marker') {
            newElem = { id, type: 'elevation_marker', data: { x: startX, y: startY, text: '±0.00' }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'room_label') {
            const labelText = prompt('Nama Ruangan (contoh: R. Tamu ±0.00):', 'R. Tamu\n±0.00');
            if (labelText) {
                newElem = { id, type: 'room_label', data: { x: startX, y: startY, text: labelText }, sort_order: elementList.value.length };
            }
        }

        if (newElem && mainLayer) {
            elementList.value.push(newElem);
            const shapeNode = createShapeFromElement(newElem);
            applyLayerVisibilityToNode(shapeNode, newElem.type);
            mainLayer.add(shapeNode);
            mainLayer.batchDraw();
            pushHistory();
            triggerAutoSaveDebounced();

            // Auto-select objek baru agar langsung aktif dan terlihat propertinya
            if (tr) {
                tr.nodes([shapeNode]);
                selectedNodeId.value = newElem.id;
                selectedElementData.value = { ...newElem.data, type: newElem.type };
                updateSelectionOverlay();
                mainLayer.batchDraw();
            }
        }
    });
}

// ── MOUSE WHEEL ZOOM & PAN (FIGMA / AUTOCAD STYLE) ─────────────────────────
function setupZoomAndPanEvents() {
    if (!stage) return;

    stage.on('wheel', (e) => {
        e.evt.preventDefault();

        // 1. Tekan Ctrl + Scroll Mouse => Scroll Y (Geser Layar Atas ke Bawah)
        if (e.evt.ctrlKey || e.evt.metaKey) {
            const dy = e.evt.deltaY;
            stage!.y(stage!.y() - dy);
            stage!.batchDraw();
            return;
        }

        // 2. Tekan Shift + Scroll Mouse => Scroll X (Geser Layar Samping Kiri dan Kanan)
        if (e.evt.shiftKey) {
            const dx = e.evt.deltaY || e.evt.deltaX;
            stage!.x(stage!.x() - dx);
            stage!.batchDraw();
            return;
        }

        // 3. Normal Scroll Mouse => Zoom Presisi Kursor (AutoCAD / Figma Zoom)
        const oldScale = stage!.scaleX();
        const pointer = stage!.getPointerPosition();
        if (!pointer) return;

        const mousePointTo = {
            x: (pointer.x - stage!.x()) / oldScale,
            y: (pointer.y - stage!.y()) / oldScale,
        };

        const direction = e.evt.deltaY > 0 ? -1 : 1;
        const scaleBy = 1.1;
        const newScale = direction > 0 ? oldScale * scaleBy : oldScale / scaleBy;
        const clampedScale = Math.max(0.2, Math.min(5, newScale));

        zoomLevel.value = clampedScale;
        stage!.scale({ x: clampedScale, y: clampedScale });

        const newPos = {
            x: pointer.x - mousePointTo.x * clampedScale,
            y: pointer.y - mousePointTo.y * clampedScale,
        };

        stage!.position(newPos);
        stage!.batchDraw();
    });
}

// Keyboard shortcuts listener
function handleKeyDown(e: KeyboardEvent) {
    if (activeEditorMode.value === 'shop_drawing') {
        return;
    }

    if (e.key === 'Control' || e.ctrlKey || e.metaKey) {
        isCtrlHeld.value = true;
    }
    if (e.key === 'Alt' || e.altKey) {
        isAltHeld.value = true;
    }

    // If typing in input/textarea, ignore shortcuts
    const activeEl = document.activeElement;
    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
        return;
    }

    // Escape key closes modals/menus, cancels box selection, and clears selection
    if (e.key === 'Escape') {
        if (showHelpModal.value) {
            showHelpModal.value = false;
            return;
        }
        if (showNewSheetModal.value) {
            showNewSheetModal.value = false;
            return;
        }
        if (showExportModal.value) {
            showExportModal.value = false;
            return;
        }
        if (showExportMenu.value) {
            showExportMenu.value = false;
            return;
        }
        if (isBoxSelecting || selectionBoxRect) {
            isBoxSelecting = false;
            if (selectionBoxRect) {
                selectionBoxRect.destroy();
                selectionBoxRect = null;
            }
            tempLayer?.destroyChildren();
            tempLayer?.batchDraw();
            return;
        }
        if (tr && tr.nodes().length > 0) {
            tr.nodes([]);
            selectedNodeId.value = null;
            selectedElementData.value = null;
            updateSelectionOverlay();
            mainLayer?.batchDraw();
        }
        activeTool.value = 'select';
        return;
    }

    // Spacebar hold for Pan
    if (e.code === 'Space' && !isSpacePressed.value) {
        e.preventDefault();
        isSpacePressed.value = true;
        if (stage) {
            stage.draggable(true);
            containerRef.value!.style.cursor = 'grab';
        }
    }

    // Select All (Ctrl+A / Cmd+A) - Geser Seluruh Proyek
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
        e.preventDefault();
        selectAllElements();
        return;
    }

    // Copy (Ctrl+C / Cmd+C) - Salin per objek atau banyak objek
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'c' || e.code === 'KeyC')) {
        e.preventDefault();
        copySelected();
        return;
    }

    // Paste (Ctrl+V / Cmd+V) - Tempel objek dari clipboard
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'v' || e.code === 'KeyV')) {
        e.preventDefault();
        pasteClipboard();
        return;
    }

    // Duplicate (Ctrl+D / Cmd+D) - Duplikat cepat objek
    if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'd' || e.code === 'KeyD')) {
        e.preventDefault();
        duplicateSelected();
        return;
    }

    // Undo / Redo
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
        e.preventDefault();
        if (e.shiftKey) {
            redo();
        } else {
            undo();
        }
        return;
    }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
        e.preventDefault();
        redo();
        return;
    }

    // Close category menu on Escape
    if (e.key === 'Escape') {
        if (showCategoryMenu.value) {
            showCategoryMenu.value = false;
            return;
        }
    }

    // Delete selected element
    if (e.key === 'Delete' || e.key === 'Backspace') {
        deleteSelected();
        return;
    }

    // Tool hotkeys CAD Lengkap
    if (!e.ctrlKey && !e.metaKey && !e.altKey) {
        if (e.shiftKey) {
            // Kombinasi Shift + Tombol
            switch (e.key.toLowerCase()) {
                case 'd': selectCadTool('double_door'); break;       // Shift+D: Pintu Ganda / 2 Daun
                case 'p': selectCadTool('sliding_door'); break;      // Shift+P: Pintu Geser / Sliding
                case 't': selectCadTool('table'); break;             // Shift+T: Meja Makan & Kursi
                case 'b': selectCadTool('washbasin'); break;         // Shift+B: Wastafel Kamar Mandi
                case 's': selectCadTool('shower'); break;            // Shift+S: Area Shower Box
                case 'c': selectCadTool('car'); break;               // Shift+C: Mobil Carport / Garasi
                case 'r': selectCadTool('tree'); break;              // Shift+R: Pohon / Tanaman
                case 'e': selectCadTool('elevation_marker'); break;  // Shift+E: Peil Ketinggian Lantai
            }
        } else {
            // Tombol Tunggal (Single Key)
            switch (e.key.toLowerCase()) {
                // Navigasi & Seleksi
                case 'v': selectCadTool('select'); break;
                case 'h': selectCadTool('pan'); break;
                case 'z': zoomToFit(); showCategoryMenu.value = false; break;

                // Struktur Bangunan & Dinding
                case 'w': selectCadTool('wall'); break;
                case 'r': selectCadTool('rect'); break;
                case 'c': selectCadTool('column'); break;
                case 'g': selectCadTool('grid_line'); break;
                case 's': selectCadTool('stair'); break;

                // Bukaan Pintu & Jendela
                case 'd': selectCadTool('door'); break;
                case 'p': selectCadTool('sliding_door'); break;
                case 'j': selectCadTool('window'); break;

                // Perabot & Sanitair
                case 'f': selectCadTool('sofa'); break;
                case 'b': selectCadTool('bed'); break;
                case 'k': selectCadTool('kitchen'); break;
                case 'u': selectCadTool('toilet'); break;

                // Simbol & Notasi Arsitektural
                case 'n': selectCadTool('north_arrow'); break;
                case 'x': selectCadTool('section_marker'); break;
                case 'q': selectCadTool('leader_note'); break;
                case 'm': selectCadTool('dimension'); break;
                case 't': selectCadTool('room_label'); break;

                // Bentuk Geometri CAD
                case 'l': selectCadTool('line'); break;
                case 'o': selectCadTool('circle'); break;
                case 'a': selectCadTool('arc'); break;

                // Penghapus
                case 'e': selectCadTool('eraser'); break;
            }
        }
    }
}

function handleKeyUp(e: KeyboardEvent) {
    if (e.code === 'Space') {
        isSpacePressed.value = false;
        if (stage && containerRef.value) {
            stage.draggable(false);
            containerRef.value.style.cursor = activeTool.value === 'pan' ? 'grab' : (activeTool.value === 'select' ? 'default' : 'crosshair');
        }
    }
    if (e.key === 'Control' || (!e.ctrlKey && !e.metaKey)) {
        isCtrlHeld.value = false;
    }
    if (e.key === 'Alt' || !e.altKey) {
        isAltHeld.value = false;
    }
}

watch(activeTool, (tool) => {
    if (!stage || !mainLayer) return;

    if (tool === 'select') {
        if (containerRef.value) containerRef.value.style.cursor = 'default';
        mainLayer.getChildren((node) => node !== tr && node !== selectionOverlay).forEach((n) => n.draggable(props.canEdit));
        updateSelectionOverlay();
    } else if (tool === 'pan') {
        if (containerRef.value) containerRef.value.style.cursor = 'grab';
        tr?.nodes([]);
        selectedNodeId.value = null;
        selectedElementData.value = null;
        selectionOverlay?.visible(false);
        mainLayer.getChildren((node) => node !== tr && node !== selectionOverlay).forEach((n) => n.draggable(false));
    } else {
        if (containerRef.value) containerRef.value.style.cursor = 'crosshair';
        tr?.nodes([]);
        selectedNodeId.value = null;
        selectedElementData.value = null;
        selectionOverlay?.visible(false);
        mainLayer.getChildren((node) => node !== tr && node !== selectionOverlay).forEach((n) => n.draggable(false));
    }
    mainLayer.batchDraw();
});

// Manual or Auto-Save
async function saveElements(isAuto = false) {
    if (!props.canEdit || isSaving.value) return;

    savePaperSizesToStorage();
    if (!isAuto) isSaving.value = true;
    saveSuccess.value = false;

    try {
        const response = await fetch(`/drawings/${props.sheet.id}/sync`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify({
                elements: elementList.value,
                sheet: {
                    width: paperWidth.value,
                    height: paperHeight.value,
                    paper_size: paperSize.value,
                    orientation: orientation.value,
                },
            }),
        });

        if (response.ok) {
            saveSuccess.value = true;
            isAutoSaving.value = false;
            setTimeout(() => {
                saveSuccess.value = false;
            }, 3000);

            // Auto-update thumbnail snapshot ke server di latar belakang
            captureThumbnailSnapshot();
        }
    } catch (err) {
        console.error('Error saving drawing elements:', err);
    } finally {
        isSaving.value = false;
    }
}

function captureThumbnailSnapshot() {
    if (!stage) return;
    try {
        const frames = getPaperFrames();
        const targetFrame = frames[0];
        let dataURL = '';
        if (targetFrame && targetFrame.width > 0 && targetFrame.height > 0) {
            const origX = stage.x();
            const origY = stage.y();
            const origScaleX = stage.scaleX();
            const origScaleY = stage.scaleY();

            stage.position({ x: -targetFrame.x, y: -targetFrame.y });
            stage.scale({ x: 1, y: 1 });
            if (gridLayer) gridLayer.visible(false);
            tempLayer?.batchDraw();
            stage.batchDraw();

            dataURL = stage.toDataURL({
                x: 0,
                y: 0,
                width: targetFrame.width,
                height: targetFrame.height,
                pixelRatio: 0.75,
            });

            stage.position({ x: origX, y: origY });
            stage.scale({ x: origScaleX, y: origScaleY });
            if (gridLayer) gridLayer.visible(true);
            stage.batchDraw();
        } else {
            dataURL = stage.toDataURL({ pixelRatio: 0.5 });
        }

        if (dataURL && dataURL.length > 100) {
            fetch(`/drawings/${props.sheet.id}/image`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ image: dataURL }),
            }).catch(() => {});
        }
    } catch (e) {
        // silent fail
    }
}

function deleteSelected() {
    if (!mainLayer || !tr) return;

    if (tr.nodes().length > 0) {
        const nodesToDelete = [...tr.nodes()];
        const idsToDelete = new Set(nodesToDelete.map((n) => n.id()));

        nodesToDelete.forEach((n) => n.destroy());
        elementList.value = elementList.value.filter((x) => !idsToDelete.has(x.id));
        tr.nodes([]);
        selectedNodeId.value = null;
        selectedElementData.value = null;
        updateSelectionOverlay();
        mainLayer.batchDraw();
        pushHistory();
        triggerAutoSaveDebounced();
        return;
    }

    if (selectedNodeId.value === 'all') {
        const toDelete = [...mainLayer.getChildren((node) => node !== tr && node !== selectionOverlay)];
        toDelete.forEach((n) => n.destroy());
        elementList.value = [];
        tr.nodes([]);
        selectedNodeId.value = null;
        selectedElementData.value = null;
        updateSelectionOverlay();
        mainLayer.batchDraw();
        pushHistory();
        triggerAutoSaveDebounced();
        return;
    }

    if (selectedNodeId.value) {
        const node = stage?.findOne('#' + selectedNodeId.value);
        if (node) {
            node.destroy();
            tr.nodes([]);
            elementList.value = elementList.value.filter((x) => x.id !== selectedNodeId.value);
            selectedNodeId.value = null;
            selectedElementData.value = null;
            mainLayer.batchDraw();
            pushHistory();
            triggerAutoSaveDebounced();
        }
    }
}

function exportPng(options?: boolean | { frameId?: string; fullCanvas?: boolean }) {
    if (!stage) return;
    showExportMenu.value = false;
    showExportModal.value = false;
    const isFullCanvas = typeof options === 'boolean' ? !options : (options?.fullCanvas ?? false);
    const targetFrameId = typeof options === 'object' ? options?.frameId : undefined;

    const oldNodes = tr?.nodes() || [];
    tr?.nodes([]);
    if (selectionOverlay) selectionOverlay.visible(false);
    const oldHighlights = tempLayer?.find('.individual-highlight') || [];
    oldHighlights.forEach((h) => h.destroy());

    // Simpan posisi & zoom panggung kanvas saat ini
    const savedPos = { x: stage.x(), y: stage.y() };
    const savedScale = { x: stage.scaleX(), y: stage.scaleY() };

    const frames = getPaperFrames();
    const targetFrame = frames.find((f) => f.id === (targetFrameId || activeExportFrameId.value || activeFocusedFrameId.value)) || frames[0];

    try {
        let dataURL = '';
        if (isFullCanvas || canvasMode.value === 'infinite') {
            tempLayer?.batchDraw();
            stage.batchDraw();
            dataURL = stage.toDataURL({ pixelRatio: 2 });
        } else {
            // Sembunyikan grid agar kertas putih bersih dari dot/garis grid hitam
            if (gridLayer) gridLayer.visible(false);

            // Geser panggung sementara agar titik (targetFrame.x, targetFrame.y) berada tepat di (0,0) dengan skala 1:1
            stage.position({ x: -targetFrame.x, y: -targetFrame.y });
            stage.scale({ x: 1, y: 1 });
            tempLayer?.batchDraw();
            stage.batchDraw();

            dataURL = stage.toDataURL({
                x: 0,
                y: 0,
                width: targetFrame.width,
                height: targetFrame.height,
                pixelRatio: 2,
            });
        }

        const link = document.createElement('a');
        link.download = `${targetFrame?.title || props.sheet.title || 'gambar_kerja'}.png`;
        link.href = dataURL;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success(`Gambar PNG "${targetFrame?.title || 'Lembar'}" berhasil diunduh!`);
    } catch (err) {
        console.error('Export PNG failed:', err);
        toast.error('Gagal mengunduh gambar PNG.');
    } finally {
        if (gridLayer) gridLayer.visible(true);
        stage.position(savedPos);
        stage.scale(savedScale);
        if (oldNodes.length > 0 && tr) {
            tr.nodes(oldNodes);
            updateSelectionOverlay();
        }
        stage.batchDraw();
    }
}

async function exportWithSave(type: 'dxf' | 'svg', frameId?: string) {
    const targetId = frameId || activeExportFrameId.value || activeFocusedFrameId.value || 'main_frame';
    const frames = getPaperFrames();
    const targetFrame = frames.find((f) => f.id === targetId) || frames[0];
    const frameTitle = targetFrame?.title || props.sheet.title || 'gambar_kerja';

    isExporting.value = true;
    toast.info(`Mempersiapkan download file ${type.toUpperCase()}...`);
    try {
        if (props.canEdit) {
            await saveElements(true);
        }

        const queryParam = targetId && targetId !== 'main_frame' ? `?frame_id=${encodeURIComponent(targetId)}` : '';
        const url = (type === 'dxf' ? `/drawings/${props.sheet.id}/export-dxf` : `/drawings/${props.sheet.id}/export-svg`) + queryParam;
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const blob = await response.blob();
        const downloadUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = `${frameTitle}.${type}`;
        document.body.appendChild(link);
        link.click();

        setTimeout(() => {
            if (link.parentNode) link.parentNode.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);
        }, 600);

        toast.success(`File ${type.toUpperCase()} "${frameTitle}" berhasil diunduh!`);
    } catch (err) {
        console.error(`Export ${type} failed:`, err);
        // Fallback standard direct download
        const queryParam = targetId && targetId !== 'main_frame' ? `?frame_id=${encodeURIComponent(targetId)}` : '';
        const fallbackUrl = (type === 'dxf' ? `/drawings/${props.sheet.id}/export-dxf` : `/drawings/${props.sheet.id}/export-svg`) + queryParam;
        const link = document.createElement('a');
        link.href = fallbackUrl;
        link.setAttribute('download', `${frameTitle}.${type}`);
        document.body.appendChild(link);
        link.click();
        setTimeout(() => {
            if (link.parentNode) link.parentNode.removeChild(link);
        }, 500);
        toast.info(`Mengunduh file ${type.toUpperCase()}...`);
    } finally {
        isExporting.value = false;
        showExportModal.value = false;
        showExportMenu.value = false;
    }
}

async function exportDirectPdf(frameId?: string) {
    if (!stage) return;
    showExportMenu.value = false;
    showExportModal.value = false;
    toast.info('Membuat dokumen PDF...');

    const oldNodes = tr?.nodes() || [];
    tr?.nodes([]);
    if (selectionOverlay) selectionOverlay.visible(false);
    const oldHighlights = tempLayer?.find('.individual-highlight') || [];
    oldHighlights.forEach((h) => h.destroy());

    const savedPos = { x: stage.x(), y: stage.y() };
    const savedScale = { x: stage.scaleX(), y: stage.scaleY() };

    const frames = getPaperFrames();
    const targetFrame = frames.find((f) => f.id === (frameId || activeExportFrameId.value || activeFocusedFrameId.value)) || frames[0];
    const isPortrait = targetFrame.height > targetFrame.width;
    const orientation = isPortrait ? 'portrait' : 'landscape';

    try {
        if (gridLayer) gridLayer.visible(false);

        // Posisikan kanvas tepat di lembar kertas pada skala 1:1
        stage.position({ x: -targetFrame.x, y: -targetFrame.y });
        stage.scale({ x: 1, y: 1 });
        tempLayer?.batchDraw();
        stage.batchDraw();

        // Ambil dataURL resolusi tinggi 2.5x retina
        const dataURL = stage.toDataURL({
            x: 0,
            y: 0,
            width: targetFrame.width,
            height: targetFrame.height,
            pixelRatio: 2.5,
        });

        // Ukuran standar PDF mm (A3: 420x297, A4: 297x210)
        const pdf = new jsPDF({
            orientation: orientation,
            unit: 'mm',
            format: isPortrait ? [297, 420] : [420, 297],
        });

        const pdfW = pdf.internal.pageSize.getWidth();
        const pdfH = pdf.internal.pageSize.getHeight();

        pdf.addImage(dataURL, 'PNG', 0, 0, pdfW, pdfH, undefined, 'FAST');
        const fileName = `${targetFrame.title || props.sheet.title || 'gambar_kerja'}.pdf`;
        pdf.save(fileName);

        toast.success(`Dokumen PDF "${fileName}" berhasil diunduh!`);
    } catch (err) {
        console.error('Export PDF failed:', err);
        toast.error('Gagal membuat dokumen PDF.');
    } finally {
        if (gridLayer) gridLayer.visible(true);
        stage.position(savedPos);
        stage.scale(savedScale);
        if (oldNodes.length > 0 && tr) {
            tr.nodes(oldNodes);
            updateSelectionOverlay();
        }
        stage.batchDraw();
    }
}

function exportPdfOrPrint(frameId?: string) {
    if (!stage) return;
    showExportMenu.value = false;
    showExportModal.value = false;

    const oldNodes = tr?.nodes() || [];
    tr?.nodes([]);
    if (selectionOverlay) selectionOverlay.visible(false);
    const oldHighlights = tempLayer?.find('.individual-highlight') || [];
    oldHighlights.forEach((h) => h.destroy());

    const savedPos = { x: stage.x(), y: stage.y() };
    const savedScale = { x: stage.scaleX(), y: stage.scaleY() };

    const frames = getPaperFrames();
    const targetFrame = frames.find((f) => f.id === (frameId || activeExportFrameId.value || activeFocusedFrameId.value)) || frames[0];
    const isPortrait = targetFrame.height > targetFrame.width;

    try {
        if (gridLayer) gridLayer.visible(false);

        stage.position({ x: -targetFrame.x, y: -targetFrame.y });
        stage.scale({ x: 1, y: 1 });
        tempLayer?.batchDraw();
        stage.batchDraw();

        const dataURL = stage.toDataURL({
            x: 0,
            y: 0,
            width: targetFrame.width,
            height: targetFrame.height,
            pixelRatio: 2.5,
        });

        // Buka iframe terisolasi yang HANYA berisi gambar kertas kerja
        let printIframe = document.getElementById('cad-print-frame') as HTMLIFrameElement;
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'cad-print-frame';
            printIframe.style.position = 'fixed';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            printIframe.style.width = '0';
            printIframe.style.height = '0';
            printIframe.style.border = '0';
            document.body.appendChild(printIframe);
        }

        const doc = printIframe.contentWindow?.document;
        if (doc) {
            doc.open();
            doc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>${targetFrame.title || props.sheet.title || 'Lembar Kerja CAD'}</title>
                    <style>
                        @page {
                            size: ${isPortrait ? 'portrait' : 'landscape'};
                            margin: 0mm;
                        }
                        * {
                            box-sizing: border-box;
                            margin: 0;
                            padding: 0;
                        }
                        html, body {
                            width: 100%;
                            height: 100%;
                            background: #ffffff;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            overflow: hidden;
                        }
                        img {
                            width: 100%;
                            height: 100%;
                            object-fit: contain;
                            display: block;
                        }
                    </style>
                </head>
                <body>
                    <img src="${dataURL}" />
                </body>
                </html>
            `);
            doc.close();

            // Tunggu gambar di iframe ter-load sempurna lalu jalankan cetak
            setTimeout(() => {
                printIframe.contentWindow?.focus();
                printIframe.contentWindow?.print();
            }, 300);
        }
    } catch (err) {
        console.error('Print preview failed:', err);
        toast.error('Gagal mempersiapkan dokumen cetak.');
    } finally {
        if (gridLayer) gridLayer.visible(true);
        stage.position(savedPos);
        stage.scale(savedScale);
        if (oldNodes.length > 0 && tr) {
            tr.nodes(oldNodes);
            updateSelectionOverlay();
        }
        stage.batchDraw();
    }
}

function zoom(direction: 'in' | 'out' | 'reset') {
    if (!stage) return;

    if (direction === 'reset') {
        zoomToFit();
    } else {
        const factor = direction === 'in' ? 1.2 : 0.8;
        const newZoom = Math.max(0.2, Math.min(5, zoomLevel.value * factor));
        zoomLevel.value = newZoom;
        stage.scale({ x: newZoom, y: newZoom });
    }

    stage.batchDraw();
}

// ── NEW DRAWING SHEET (BUAT KERTAS BARU LANGSUNG DI EDITOR) ────────────────
const showNewSheetModal = ref(false);
const isCreatingSheet = ref(false);
const newSheetForm = ref({
    title: '',
    type: 'plan' as 'plan' | 'elevation' | 'section',
    scale: '1:100',
    paper_size: 'A3',
    orientation: 'landscape' as 'landscape' | 'portrait',
});

function openNewSheetModal() {
    newSheetForm.value = {
        title: '',
        type: 'plan',
        scale: '1:100',
        paper_size: 'A3',
        orientation: 'landscape',
    };
    showNewSheetModal.value = true;
}

function submitNewSheet() {
    if (!newSheetForm.value.title.trim()) {
        toast.error('Silakan isi judul lembar kerja baru.');
        return;
    }

    isCreatingSheet.value = true;
    router.post(
        '/drawings',
        {
            project_id: props.project.id,
            title: newSheetForm.value.title.trim(),
            type: newSheetForm.value.type,
            scale: newSheetForm.value.scale,
            paper_size: newSheetForm.value.paper_size,
            orientation: newSheetForm.value.orientation,
        },
        {
            onSuccess: () => {
                showNewSheetModal.value = false;
                toast.success('Lembar kerja baru berhasil dibuat!');
            },
            onError: () => {
                toast.error('Gagal membuat lembar kerja baru. Periksa data kembali.');
            },
            onFinish: () => {
                isCreatingSheet.value = false;
            },
        }
    );
}

function switchSheet(e: Event) {
    const target = e.target as HTMLSelectElement;
    if (target.value === '__ADD_PAPER_FRAME__') {
        target.value = props.sheet.id;
        addPaperFrameToCanvas();
        return;
    }
    if (target.value === '__NEW_SHEET__') {
        target.value = props.sheet.id;
        openNewSheetModal();
        return;
    }
    if (target.value && target.value !== props.sheet.id) {
        navigateToSheet(target.value);
    }
}

function navigateToSheet(targetId: string) {
    if (targetId === props.sheet.id) {
        if (canvasMode.value !== 'sheet') {
            setCanvasMode('sheet');
        } else {
            zoomToFit();
        }
        return;
    }
    router.visit(`/drawings/${targetId}`);
}

function checkTopBarScroll() {
    if (!topBarContainerRef.value) return;
    const el = topBarContainerRef.value;
    canScrollTopBarLeft.value = el.scrollLeft > 8;
    canScrollTopBarRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

function scrollTopBar(direction: 'left' | 'right') {
    if (!topBarContainerRef.value) return;
    const step = 280;
    topBarContainerRef.value.scrollBy({
        left: direction === 'right' ? step : -step,
        behavior: 'smooth',
    });
    setTimeout(checkTopBarScroll, 320);
}

function checkHeaderScroll() {
    if (!headerContainerRef.value) return;
    const el = headerContainerRef.value;
    canScrollLeft.value = el.scrollLeft > 8;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

function scrollHeader(direction: 'left' | 'right') {
    if (!headerContainerRef.value) return;
    const step = 280;
    headerContainerRef.value.scrollBy({
        left: direction === 'right' ? step : -step,
        behavior: 'smooth',
    });
    setTimeout(checkHeaderScroll, 320);
}

function checkBottomHudScroll() {
    if (!bottomHudRef.value) return;
    const el = bottomHudRef.value;
    canScrollBottomHudLeft.value = el.scrollLeft > 8;
    canScrollBottomHudRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

function scrollBottomHud(direction: 'left' | 'right') {
    if (!bottomHudRef.value) return;
    const step = 220;
    bottomHudRef.value.scrollBy({
        left: direction === 'right' ? step : -step,
        behavior: 'smooth',
    });
    setTimeout(checkBottomHudScroll, 280);
}

function checkLeftToolbarScroll() {
    if (!leftToolbarRef.value) return;
    const el = leftToolbarRef.value;
    canScrollToolbarUp.value = el.scrollTop > 8;
    canScrollToolbarDown.value = el.scrollTop + el.clientHeight < el.scrollHeight - 8;
}

function scrollToolbar(direction: 'up' | 'down') {
    if (!leftToolbarRef.value) return;
    const step = 160;
    leftToolbarRef.value.scrollBy({
        top: direction === 'down' ? step : -step,
        behavior: 'smooth',
    });
    setTimeout(checkLeftToolbarScroll, 280);
}

const handleDocumentClick = (e: MouseEvent) => {
    const target = e.target as HTMLElement;
    if (!target.closest('.export-menu-container')) {
        showExportMenu.value = false;
    }
};

const handleGlobalMouseUp = (e: MouseEvent | TouchEvent) => {
    if (isBoxSelecting || selectionBoxRect) {
        finishBoxSelection(e);
    }
};

onMounted(() => {
    loadPaperSizesFromStorage();
    nextTick(() => {
        initCanvas();
        checkTopBarScroll();
        checkHeaderScroll();
        checkBottomHudScroll();
        checkLeftToolbarScroll();
        setTimeout(() => {
            zoomToFit();
            checkTopBarScroll();
            checkHeaderScroll();
            checkBottomHudScroll();
            checkLeftToolbarScroll();
        }, 150);
    });

    if (typeof ResizeObserver !== 'undefined') {
        uiResizeObserver = new ResizeObserver(() => {
            checkTopBarScroll();
            checkHeaderScroll();
            checkBottomHudScroll();
            checkLeftToolbarScroll();
        });
        if (topBarContainerRef.value) uiResizeObserver.observe(topBarContainerRef.value);
        if (headerContainerRef.value) uiResizeObserver.observe(headerContainerRef.value);
        if (bottomHudRef.value) uiResizeObserver.observe(bottomHudRef.value);
        if (leftToolbarRef.value) uiResizeObserver.observe(leftToolbarRef.value);
    }

    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);
    window.addEventListener('click', handleDocumentClick);
    window.addEventListener('mouseup', handleGlobalMouseUp);
    window.addEventListener('touchend', handleGlobalMouseUp);

    const handleResize = () => {
        if (!containerRef.value || !stage) return;
        stage.width(containerRef.value.clientWidth);
        stage.height(containerRef.value.clientHeight);
        stage.batchDraw();
        checkTopBarScroll();
        checkHeaderScroll();
        checkBottomHudScroll();
        checkLeftToolbarScroll();
    };

    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    uiResizeObserver?.disconnect();
    uiResizeObserver = null;
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('keyup', handleKeyUp);
    window.removeEventListener('click', handleDocumentClick);
    window.removeEventListener('mouseup', handleGlobalMouseUp);
    window.removeEventListener('touchend', handleGlobalMouseUp);
    stage?.destroy();
    clearTimeout(autoSaveTimer);
});
</script>

<template>
    <Head :title="`${sheet.title} — ${activeEditorMode === 'shop_drawing' ? 'Gambar Kerja' : 'Lembar Kerja CAD 2D'}`" />

    <!-- TAMPILAN GAMBAR KERJA (SHOP DRAWING & DAFTAR POTONG INTERIOR / MOBILE-FRIENDLY) -->
    <FurnitureShopDrawingEditor
        v-if="activeEditorMode === 'shop_drawing'"
        :sheet="currentSheet"
        :project="project"
        :elements="elementList"
        :canEdit="canEdit"
        :architect="architect"
        @switchMode="switchEditorMode"
    />

    <!-- TAMPILAN CAD DENAH ARSITEKTUR TRADISIONAL (KONVA JS) -->
    <div
        v-else
        class="h-screen w-screen flex flex-col bg-slate-900 text-slate-100 overflow-hidden select-none"
    >
        <!-- TOP CAD HEADER BAR -->
        <!-- TOP CAD 2-ROW HEADER & RIBBON TOOLBAR -->
        <header class="relative z-30 shrink-0 bg-slate-950 border-b border-white/10 flex flex-col">
            <!-- BARIS 1: NAVIGASI, JUDUL FILE, STATUS & ACTIONS UTAMA -->
            <div class="relative bg-slate-950 border-b border-white/5">
                <!-- Petunjuk Gulir Kiri Baris 1 -->
                <button
                    v-if="canScrollTopBarLeft"
                    @click="scrollTopBar('left')"
                    type="button"
                    class="absolute left-0 top-0 bottom-0 z-20 flex items-center pl-2 pr-4 bg-gradient-to-r from-slate-950 via-slate-900/95 to-transparent text-slate-300 hover:text-white transition-all cursor-pointer group"
                    title="Geser menu navigasi ke kiri"
                >
                    <div class="w-6 h-6 rounded-full bg-slate-800/90 border border-white/20 flex items-center justify-center shadow-lg group-hover:bg-violet-600 transition-colors">
                        <ChevronLeft class="w-4 h-4 text-white" />
                    </div>
                </button>

                <!-- Scrollable Baris 1 Container -->
                <div
                    ref="topBarContainerRef"
                    @scroll="checkTopBarScroll"
                    class="h-11 px-3 sm:px-4 flex items-center justify-between gap-4 overflow-x-auto scrollbar-none scroll-smooth min-w-0"
                >
                    <!-- Kiri: Back, Icon, Judul Lembar, Tipe, Dropdown Switcher, File Baru -->
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                        <Link
                            href="/drawings"
                            class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-colors shrink-0"
                            title="Kembali ke Daftar Lembar Kerja"
                        >
                            <ArrowLeft class="w-4 h-4" />
                        </Link>

                        <div class="w-7 h-7 rounded-lg bg-violet-500/20 text-violet-400 flex items-center justify-center border border-violet-500/30 shrink-0">
                            <DraftingCompass class="w-3.5 h-3.5" />
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <h2 class="text-xs sm:text-sm font-bold text-white tracking-tight truncate max-w-[150px] sm:max-w-xs">{{ currentSheet.title }}</h2>

                            <button
                                v-if="canEdit"
                                type="button"
                                @click="showSheetSettingsModal = true"
                                class="px-1.5 py-0.5 rounded text-[9px] font-mono font-semibold bg-violet-500/20 text-violet-300 border border-violet-500/30 hover:bg-violet-500/40 transition flex items-center gap-1 cursor-pointer shrink-0"
                                title="Klik untuk ubah Kategori / Skala / Unit"
                            >
                                <span>{{ currentSheet.type.toUpperCase() }}</span>
                                <Settings class="w-2.5 h-2.5 text-violet-300" />
                            </button>
                            <span v-else class="px-1.5 py-0.5 rounded text-[9px] font-mono font-semibold bg-violet-500/20 text-violet-300 border border-violet-500/30 shrink-0">
                                {{ currentSheet.type.toUpperCase() }}
                            </span>
                            <span class="hidden md:inline-block text-[11px] text-slate-400 truncate max-w-[180px]">
                                • {{ project.title }}
                            </span>

                            <!-- Sheet Switcher Dropdown (Pilih Lembar Kerja / Ruangan) -->
                            <div v-if="projectSheets && projectSheets.length > 0" class="relative inline-block shrink-0">
                                <select
                                    :value="sheet.id"
                                    @change="switchSheet"
                                    class="bg-slate-900 border border-violet-500/40 text-violet-300 hover:text-white hover:border-violet-400 text-[11px] rounded-lg px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-violet-400 font-medium cursor-pointer transition-colors shadow-sm max-w-[140px] sm:max-w-[190px] truncate [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                                    title="Pilih Lembar Kerja Lainnya"
                                >
                                    <option
                                        v-for="(s, idx) in projectSheets"
                                        :key="s.id"
                                        :value="s.id"
                                        class="bg-slate-900 text-white py-1"
                                    >
                                        📄 Lembar {{ idx + 1 }}: {{ s.title }} ({{ s.scale }})
                                    </option>
                                    <option v-if="canEdit" value="__NEW_SHEET__" class="bg-violet-950 text-violet-300 font-medium py-1">
                                        📄 + Buat File Lembar Baru...
                                    </option>
                                </select>
                            </div>

                            <!-- Tombol Tambah Lembar / Kertas Baru Langsung -->
                            <button
                                v-if="canEdit"
                                @click="openNewSheetModal"
                                type="button"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-violet-600/25 hover:bg-violet-600 text-violet-300 hover:text-white border border-violet-500/30 transition-all cursor-pointer shadow-sm shrink-0"
                                title="Buat Lembar Kerja / File Kertas Baru di Proyek Ini"
                            >
                                <Plus class="w-3 h-3" />
                                <span>File Baru</span>
                            </button>
                        </div>
                    </div>

                <!-- Kanan: Undo / Redo, Copy / Paste, Bantuan, Status Auto-save / Simpan, Export Menu -->
                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <!-- Undo / Redo -->
                    <div class="flex items-center bg-white/5 border border-white/10 rounded-xl p-0.5 shrink-0">
                        <button
                            @click="undo"
                            :disabled="historyIndex <= 0"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors cursor-pointer"
                            title="Urungkan / Undo (Ctrl+Z)"
                        >
                            <Undo2 class="w-3.5 h-3.5" />
                        </button>
                        <button
                            @click="redo"
                            :disabled="historyIndex >= historyStack.length - 1"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors cursor-pointer"
                            title="Ulangi / Redo (Ctrl+Y)"
                        >
                            <Redo2 class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Copy / Paste / Duplicate -->
                    <div v-if="canEdit" class="hidden sm:flex items-center bg-white/5 border border-white/10 rounded-xl p-0.5 shrink-0">
                        <button
                            @click="copySelected"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                            title="Salin Objek Terpilih (Ctrl+C)"
                        >
                            <Copy class="w-3.5 h-3.5" />
                        </button>
                        <button
                            @click="pasteClipboard"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                            title="Tempel Objek dari Clipboard (Ctrl+V)"
                        >
                            <Clipboard class="w-3.5 h-3.5" />
                        </button>
                        <button
                            @click="duplicateSelected"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                            title="Duplikat Cepat Objek (Ctrl+D)"
                        >
                            <Files class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Help Button -->
                    <button
                        @click="showHelpModal = true"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
                        title="Petunjuk Hotkeys CAD"
                    >
                        <HelpCircle class="w-4 h-4" />
                    </button>

                    <!-- Save Status & Button -->
                    <button
                        v-if="canEdit"
                        @click="saveElements(false)"
                        :disabled="isSaving"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm shrink-0',
                            saveSuccess
                                ? 'bg-emerald-600 text-white'
                                : isAutoSaving
                                    ? 'bg-amber-600/80 text-white'
                                    : 'bg-violet-600 hover:bg-violet-500 text-white shadow-violet-600/30'
                        ]"
                    >
                        <Loader2 v-if="isSaving || isAutoSaving" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else-if="saveSuccess" class="w-3.5 h-3.5" />
                        <Save v-else class="w-3.5 h-3.5" />
                        <span>{{ isSaving ? 'Menyimpan...' : (isAutoSaving ? 'Auto-Save...' : (saveSuccess ? 'Tersimpan!' : 'Simpan')) }}</span>
                    </button>

                    <!-- Mode Gambar Kerja Furniture (Shop Drawing) -->
                    <button
                        type="button"
                        @click="switchEditorMode('shop_drawing')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-sky-500/15 hover:bg-sky-500/25 text-sky-300 border border-sky-500/30 transition-all cursor-pointer shrink-0"
                        title="Buka Tampilan Gambar Kerja Furniture & Interior"
                    >
                        <Armchair class="w-3.5 h-3.5 text-sky-400" />
                        <span class="hidden sm:inline">Mode Gambar Kerja</span>
                    </button>

                    <!-- Booklet PDF Link Button -->
                    <Link
                        :href="`/projects/${project.id}/booklet`"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white shadow-sm transition-all cursor-pointer shrink-0"
                        title="Buka Lembar Kerja & Booklet PDF Standar Studio"
                    >
                        <Layers class="w-3.5 h-3.5" />
                        <span>📄 Booklet PDF</span>
                    </Link>

                    <!-- Compact Export Dropdown Menu (DXF, SVG, PNG, PDF, Print) -->
                    <div class="relative export-menu-container shrink-0">
                        <button
                            @click="showExportMenu = !showExportMenu"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition-colors cursor-pointer shrink-0"
                            title="Pilihan Download & Export Gambar CAD"
                        >
                            <Download class="w-3.5 h-3.5 text-sky-400" />
                            <span>Export</span>
                            <ChevronDown class="w-3 h-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': showExportMenu }" />
                        </button>

                        <div
                            v-if="showExportMenu"
                            class="fixed right-3 sm:right-4 top-12 w-64 bg-slate-950 border border-white/15 rounded-2xl shadow-2xl p-2 z-50 flex flex-col gap-1 text-xs text-slate-200 backdrop-blur-xl animate-in fade-in zoom-in-95 duration-150"
                        >
                            <div class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-white/10 mb-1">
                                Ekspor Lembar Kerja
                            </div>

                            <!-- Booklet PDF Standar Arsitektur -->
                            <Link
                                :href="`/projects/${project.id}/booklet`"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-orange-600/15 hover:bg-orange-600/25 text-orange-300 hover:text-white transition-colors text-left w-full cursor-pointer group"
                            >
                                <div class="w-7 h-7 rounded-lg bg-orange-500/20 text-orange-400 flex items-center justify-center border border-orange-500/30 shrink-0 group-hover:bg-orange-500 group-hover:text-white transition-colors">
                                    <FileText class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <div class="font-semibold text-white">Booklet PDF Arsitek</div>
                                    <div class="text-[10px] text-slate-400">3D + Key Plan + Draf RAB</div>
                                </div>
                            </Link>

                            <!-- Unduh Langsung PDF Asli -->
                            <button
                                @click="exportDirectPdf(activeExportFrameId)"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group"
                                title="Unduh langsung dokumen PDF ukuran kertas standar"
                            >
                                <div class="w-7 h-7 rounded-lg bg-rose-500/15 text-rose-400 flex items-center justify-center border border-rose-500/25 shrink-0 group-hover:bg-rose-500/25">
                                    <FileText class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <div class="font-semibold text-white">Dokumen PDF (.PDF)</div>
                                    <div class="text-[10px] text-slate-400">Unduh PDF skala 1:1 langsung</div>
                                </div>
                            </button>

                            <!-- Unduh DXF -->
                            <button
                                @click="exportWithSave('dxf')"
                                :disabled="isExporting"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group disabled:opacity-50"
                                title="Download file AutoCAD .DXF standar"
                            >
                                <div class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center border border-amber-500/25 shrink-0 group-hover:bg-amber-500/25">
                                    <FileCode class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <div class="font-semibold text-white">AutoCAD (.DXF)</div>
                                    <div class="text-[10px] text-slate-400">Kompatibel AutoCAD / CAD 2D</div>
                                </div>
                            </button>

                            <!-- Unduh SVG -->
                            <button
                                @click="exportWithSave('svg')"
                                :disabled="isExporting"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group disabled:opacity-50"
                                title="Download format vektor .SVG"
                            >
                                <div class="w-7 h-7 rounded-lg bg-sky-500/15 text-sky-400 flex items-center justify-center border border-sky-500/25 shrink-0 group-hover:bg-sky-500/25">
                                    <Download class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <div class="font-semibold text-white">Vektor (.SVG)</div>
                                    <div class="text-[10px] text-slate-400">Grafik vektor resolusi tajam</div>
                                </div>
                            </button>

                            <!-- Unduh PNG -->
                            <button
                                @click="exportPng({ frameId: activeExportFrameId, fullCanvas: false })"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group"
                                title="Download sebagai gambar PNG resolusi tinggi"
                            >
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center border border-emerald-500/25 shrink-0 group-hover:bg-emerald-500/25">
                                    <Sparkles class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <div class="font-semibold text-white">Gambar (.PNG)</div>
                                    <div class="text-[10px] text-slate-400">Snapshot lembar kertas 2×</div>
                                </div>
                            </button>

                            <!-- Cetak ke Printer Fisik (Isolated Iframe) -->
                            <button
                                @click="exportPdfOrPrint(activeExportFrameId)"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group"
                                title="Cetak langsung ke printer fisik"
                            >
                                <div class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-400 flex items-center justify-center border border-indigo-500/25 shrink-0 group-hover:bg-indigo-500/25">
                                    <Printer class="w-3.5 h-3.5" />
                                </div>
                                <div>
                                    <div class="font-semibold text-white">Cetak ke Printer</div>
                                    <div class="text-[10px] text-slate-400">Buka dialog cetak bersih</div>
                                </div>
                            </button>

                            <div class="h-px bg-white/10 my-1"></div>

                            <button
                                @click="showExportMenu = false; showExportModal = true"
                                class="flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl bg-violet-600/20 hover:bg-violet-600/30 text-violet-300 hover:text-white transition-colors text-center w-full cursor-pointer font-medium text-[11px]"
                            >
                                <Sliders class="w-3.5 h-3.5" />
                                <span>Dialog Ekspor Lengkap...</span>
                            </button>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Petunjuk Gulir Kanan Baris 1: Notifikasi visual bila layar kurang lebar -->
                <div
                    v-if="canScrollTopBarRight"
                    class="absolute right-0 top-0 bottom-0 z-20 flex items-center pr-2 pl-6 bg-gradient-to-l from-slate-950 via-slate-900/95 to-transparent pointer-events-none"
                >
                    <button
                        @click="scrollTopBar('right')"
                        type="button"
                        class="pointer-events-auto flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-violet-600/95 hover:bg-violet-500 text-white shadow-xl border border-white/20 transition-all cursor-pointer animate-pulse text-xs font-semibold"
                        title="Masih ada tools lainnya di kanan, klik untuk scroll"
                    >
                        <span>Tools Lainnya</span>
                        <ChevronRight class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- BARIS 2: TOOLBAR PITA CAD / LEMBAR KERJA (QUICK ACTIONS, 1-CLICK) -->
            <div class="relative bg-slate-900/90 border-t border-white/5">
                <!-- Petunjuk Gulir Kiri -->
                <button
                    v-if="canScrollLeft"
                    @click="scrollHeader('left')"
                    type="button"
                    class="absolute left-0 top-0 bottom-0 z-20 flex items-center pl-2 pr-4 bg-gradient-to-r from-slate-950 via-slate-900/95 to-transparent text-slate-300 hover:text-white transition-all cursor-pointer group"
                    title="Geser pita tools ke kiri"
                >
                    <div class="w-6 h-6 rounded-full bg-slate-800/90 border border-white/20 flex items-center justify-center shadow-lg group-hover:bg-violet-600 transition-colors">
                        <ChevronLeft class="w-4 h-4 text-white" />
                    </div>
                </button>

                <!-- Scrollable Toolbar Items Container -->
                <div
                    ref="headerContainerRef"
                    @scroll="checkHeaderScroll"
                    class="h-9 px-3 sm:px-4 flex items-center justify-between gap-4 text-xs overflow-x-auto scrollbar-none scroll-smooth"
                >
                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Mode Kanvas: Frame Lembar Kerja vs AutoCAD Model Space -->
                        <div class="flex items-center bg-white/5 p-0.5 rounded-xl border border-white/10 shrink-0">
                            <button
                                @click="setCanvasMode('sheet')"
                                type="button"
                                :class="[
                                    'flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-medium transition-all cursor-pointer',
                                    canvasMode === 'sheet'
                                        ? 'bg-violet-600 text-white shadow-sm font-semibold'
                                        : 'text-slate-400 hover:text-white'
                                ]"
                                title="Mode Frame Lembar Kerja Dinamis (Paper Space)"
                            >
                                <LayoutTemplate class="w-3.5 h-3.5" />
                                <span>Frame Kertas</span>
                            </button>
                            <button
                                @click="setCanvasMode('infinite')"
                                type="button"
                                :class="[
                                    'flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-medium transition-all cursor-pointer',
                                    canvasMode === 'infinite'
                                        ? 'bg-violet-600 text-white shadow-sm font-semibold'
                                        : 'text-slate-400 hover:text-white'
                                ]"
                                title="Mode Kanvas Tak Terbatas (AutoCAD Model Space ∞)"
                            >
                                <InfinityIcon class="w-3.5 h-3.5" />
                                <span>Model Space (∞)</span>
                            </button>
                        </div>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <!-- Kontrol Kertas (Aktif saat mode Frame Kertas) -->
                        <template v-if="canvasMode === 'sheet'">
                            <!-- Preset Kertas ISO -->
                            <select
                                :value="paperSize"
                                @change="applyPaperPreset(($event.target as HTMLSelectElement).value)"
                                class="bg-slate-900 border border-white/10 rounded-lg px-2 py-0.5 text-xs text-white focus:outline-none focus:ring-1 focus:ring-violet-500 cursor-pointer shrink-0 [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                                title="Preset Ukuran Kertas Standar ISO"
                            >
                                <option value="A0" class="bg-slate-900 text-white">A0 (3564 × 2520)</option>
                                <option value="A1" class="bg-slate-900 text-white">A1 (2520 × 1782)</option>
                                <option value="A2" class="bg-slate-900 text-white">A2 (1782 × 1260)</option>
                                <option value="A3" class="bg-slate-900 text-white">A3 (1260 × 891)</option>
                                <option value="A4" class="bg-slate-900 text-white">A4 (891 × 630)</option>
                                <option value="Custom" class="bg-slate-900 text-white">Custom (Dinamis)</option>
                            </select>

                            <!-- Numeric Input Width & Height -->
                            <div class="flex items-center gap-1 text-xs font-mono shrink-0">
                                <span class="text-slate-400 text-[10px]">W:</span>
                                <input
                                    type="number"
                                    :value="paperWidth"
                                    @change="onWidthInput(Number(($event.target as HTMLInputElement).value))"
                                    class="w-14 bg-white/5 border border-white/10 rounded-lg px-1.5 py-0.5 text-xs text-white text-center focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                                    min="400"
                                    max="20000"
                                    step="50"
                                    title="Lebar Kertas Dinamis (px)"
                                />
                                <span class="text-slate-500 text-[10px]">×</span>
                                <span class="text-slate-400 text-[10px]">H:</span>
                                <input
                                    type="number"
                                    :value="paperHeight"
                                    @change="onHeightInput(Number(($event.target as HTMLInputElement).value))"
                                    class="w-14 bg-white/5 border border-white/10 rounded-lg px-1.5 py-0.5 text-xs text-white text-center focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                                    min="300"
                                    max="20000"
                                    step="50"
                                    title="Tinggi Kertas Dinamis (px)"
                                />
                            </div>

                            <!-- Putar Orientasi -->
                            <button
                                @click="toggleOrientation"
                                type="button"
                                class="flex items-center gap-1 px-2 py-0.5 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors cursor-pointer shrink-0"
                                :title="`Putar Orientasi (${orientation === 'landscape' ? 'Landscape ke Portrait' : 'Portrait ke Landscape'})`"
                            >
                                <RotateCw class="w-3.5 h-3.5" />
                                <span class="capitalize">{{ orientation }}</span>
                            </button>
                        </template>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <!-- Tombol Cepat: Rapatkan, Reset ISO, Pusatkan -->
                        <button
                            @click="autoFitSheetToDrawing"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-indigo-500/15 hover:bg-indigo-500/30 text-indigo-300 hover:text-white border border-indigo-500/30 transition-all cursor-pointer shrink-0 shadow-sm"
                            title="Rapatkan Lembar Kerja ke Gambar (Hilangkan Semua Jarak Kosong)"
                        >
                            <Sparkles class="w-3.5 h-3.5 text-indigo-400" />
                            <span>Rapatkan</span>
                        </button>

                        <button
                            @click="resetPaperToDefault"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-amber-500/15 hover:bg-amber-500/30 text-amber-300 hover:text-white border border-amber-500/30 transition-all cursor-pointer shrink-0 shadow-sm"
                            title="Kembalikan ukuran kertas ke standar ISO awal"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-amber-400" />
                            <span>Reset ISO</span>
                        </button>

                        <button
                            @click="zoomToFit"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-sky-500/15 hover:bg-sky-500/30 text-sky-300 hover:text-white border border-sky-500/30 transition-all cursor-pointer shrink-0 shadow-sm"
                            title="Pusatkan Seluruh Gambar & Kertas ke Layar (Zoom Extents)"
                        >
                            <Focus class="w-3.5 h-3.5 text-sky-400" />
                            <span>Pusatkan</span>
                        </button>

                        <!-- + Tambah Kertas ke Kanvas -->
                        <button
                            v-if="canEdit"
                            @click="addPaperFrameToCanvas()"
                            type="button"
                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-emerald-600/30 hover:bg-emerald-600 text-emerald-200 hover:text-white border border-emerald-500/40 transition-all cursor-pointer shadow-sm shrink-0"
                            title="Tambah Area Kertas Baru ke Kanvas Ini"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>+ Kertas ke Kanvas</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Layers Panel Button -->
                        <button
                            @click="showLayerPanel = !showLayerPanel"
                            :class="[
                                'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-semibold border transition-colors cursor-pointer shrink-0',
                                showLayerPanel
                                    ? 'bg-violet-600/20 text-violet-300 border-violet-500/40'
                                    : 'bg-white/5 text-slate-300 border-white/10 hover:bg-white/10'
                            ]"
                            title="Buka / Tutup Panel Layer Arsitektur"
                        >
                            <Layers class="w-3.5 h-3.5" />
                            <span>Layer</span>
                        </button>
                        <button
                            v-if="canEdit"
                            type="button"
                            @click="showSheetSettingsModal = true"
                            class="text-[11px] font-mono text-slate-400 hover:text-white px-2 py-0.5 rounded-lg hover:bg-white/5 border border-transparent hover:border-white/10 transition flex items-center gap-1.5 cursor-pointer"
                            title="Pengaturan Lembar Kerja (Skala, Unit, Kategori)"
                        >
                            <span>Skala <strong class="text-slate-200">{{ currentSheet.scale }}</strong> • Unit <strong class="text-amber-300">{{ currentSheet.unit }}</strong></span>
                            <Settings class="w-3 h-3 text-slate-400" />
                        </button>
                        <span v-else class="text-[11px] font-mono text-slate-400 pl-2">
                            Skala <strong class="text-slate-200">{{ currentSheet.scale }}</strong> • Unit <strong class="text-slate-200">{{ currentSheet.unit }}</strong>
                        </span>
                    </div>
                </div>

                <!-- Petunjuk Gulir Kanan: Notifikasi visual bila layar kurang lebar -->
                <div
                    v-if="canScrollRight"
                    class="absolute right-0 top-0 bottom-0 z-20 flex items-center pr-2 pl-6 bg-gradient-to-l from-slate-950 via-slate-900/95 to-transparent pointer-events-none"
                >
                    <button
                        @click="scrollHeader('right')"
                        type="button"
                        class="pointer-events-auto inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-violet-600/90 hover:bg-violet-500 text-white shadow-lg shadow-violet-600/30 border border-violet-400/40 transition-all cursor-pointer animate-pulse hover:animate-none"
                        title="Masih ada tools lainnya di kanan, klik untuk menggeser"
                    >
                        <span>Menu Lainnya</span>
                        <ChevronRight class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        </header>

        <!-- WORKSPACE BODY (TOOLBAR + CANVAS + INSPECTOR + LAYER PANEL) -->
        <div class="flex-1 flex relative overflow-hidden">
            <!-- LEFT FLOATING CAD TOOLBAR (RESPONSIVE: SLIM & COMPACT CAD PALETTE) -->
            <div class="absolute left-2 sm:left-4 top-3 sm:top-4 bottom-18 sm:bottom-20 z-50 flex flex-col pointer-events-none w-12 sm:w-[50px]">
                <!-- Invisible backdrop to dismiss popup when clicking anywhere outside -->
                <div
                    v-if="showCategoryMenu"
                    class="fixed inset-0 z-40 pointer-events-auto"
                    @click="showCategoryMenu = false"
                ></div>

                <div class="relative flex-1 flex flex-col min-h-0 pointer-events-auto w-full z-50">
                    <!-- Top Scroll Indicator (if scrolled down) -->
                    <button
                        v-if="canScrollToolbarUp"
                        @click="scrollToolbar('up')"
                        type="button"
                        class="absolute -top-2.5 left-1/2 -translate-x-1/2 z-50 flex items-center justify-center w-6 h-6 rounded-full bg-violet-600 hover:bg-violet-500 text-white shadow-lg border border-white/20 transition-all cursor-pointer animate-pulse"
                        title="Gulir menu tools ke atas"
                    >
                        <ChevronUp class="w-3.5 h-3.5" />
                    </button>

                    <!-- Category Filter Dropdown Button (Compact Single Icon Pill) -->
                    <div class="relative mb-1 shrink-0">
                        <button
                            type="button"
                            @click="showCategoryMenu = !showCategoryMenu"
                            :class="[
                                'w-9 h-9 mx-auto flex items-center justify-center rounded-xl bg-slate-950/95 border shadow-lg transition-all cursor-pointer relative group',
                                showCategoryMenu
                                    ? 'border-violet-400 text-white bg-violet-600/40 ring-2 ring-violet-500/30'
                                    : 'border-white/15 hover:border-violet-400 text-violet-400 hover:text-white hover:bg-violet-600/30'
                            ]"
                            :title="`Menu Filter Tools: ${currentCategoryObj.label} (Klik untuk buka/tutup)`"
                        >
                            <!-- Hamburger Icon Tetap Selalu Ada -->
                            <Menu class="w-4 h-4 transition-transform group-hover:scale-110" />

                            <!-- Badge Kategori Terpilih -->
                            <span
                                v-if="activeToolCategory !== 'all'"
                                class="absolute -top-1 -right-1 flex items-center justify-center w-4 h-4 rounded-full bg-violet-600 border border-slate-900 text-[10px] leading-none shadow-md select-none"
                                :title="`Filter aktif: ${currentCategoryObj.label}`"
                            >
                                {{ currentCategoryObj.icon }}
                            </span>
                        </button>

                        <!-- Flyout Menu Popover to the Right -->
                        <div
                            v-if="showCategoryMenu"
                            class="absolute left-full top-0 ml-2 z-50 py-1.5 px-1 rounded-xl bg-slate-950/98 border border-white/20 shadow-2xl backdrop-blur-lg w-44 space-y-0.5"
                        >
                            <div class="px-2.5 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-white/10 mb-1 flex items-center justify-between">
                                <span>Filter Tools</span>
                                <button
                                    type="button"
                                    @click="showCategoryMenu = false"
                                    class="text-slate-400 hover:text-white p-0.5 rounded cursor-pointer transition-colors"
                                    title="Tutup Menu Filter"
                                >
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <button
                                v-for="cat in toolCategories"
                                :key="cat.key"
                                type="button"
                                @click="activeToolCategory = cat.key as any"
                                :class="[
                                    'w-full flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors text-left cursor-pointer',
                                    activeToolCategory === cat.key
                                        ? 'bg-violet-600 text-white font-semibold shadow-sm'
                                        : 'text-slate-300 hover:text-white hover:bg-white/10'
                                ]"
                            >
                                <span class="text-base shrink-0">{{ cat.icon }}</span>
                                <span class="truncate">{{ cat.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Tool Buttons Column -->
                    <div
                        ref="leftToolbarRef"
                        @scroll="checkLeftToolbarScroll"
                        class="cad-toolbar flex-1 flex flex-col items-center gap-1 p-1 rounded-2xl bg-slate-950/95 backdrop-blur-md border border-white/10 shadow-2xl overflow-y-auto overflow-x-hidden custom-scroll scroll-smooth max-h-full w-full"
                    >
                        <!-- NAVIGASI & SELEKSI -->
                        <template v-if="activeToolCategory === 'all'">
                            <!-- Select Tool -->
                            <button
                                @click="selectCadTool('select')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'select' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Pilih & Pindahkan Objek / Select (V)"
                            >
                                <MousePointer class="w-4 h-4" />
                            </button>

                            <!-- Pan / Hand Tool -->
                            <button
                                @click="selectCadTool('pan')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'pan' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Geser Kanvas Bebas / Pan (H / Spasi)"
                            >
                                <Hand class="w-4 h-4 text-emerald-400" />
                            </button>

                            <!-- Select All / Move Entire Project -->
                            <button
                                @click="selectAllElements"
                                class="w-9 h-9 mx-auto flex items-center justify-center rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all cursor-pointer relative group shrink-0"
                                title="Pilih Semua & Geser Seluruh Proyek (Ctrl+A)"
                            >
                                <Move class="w-4 h-4 text-amber-400" />
                            </button>

                            <!-- Zoom to Fit / Extents -->
                            <button
                                @click="zoomToFit"
                                class="w-9 h-9 mx-auto flex items-center justify-center rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all cursor-pointer relative group shrink-0"
                                title="Pusatkan Gambar ke Layar / Zoom Extents (Z)"
                            >
                                <Focus class="w-4 h-4 text-sky-400" />
                            </button>

                            <!-- Auto Fit Sheet to Drawing -->
                            <button
                                v-if="canvasMode === 'sheet'"
                                @click="autoFitSheetToDrawing"
                                class="w-9 h-9 mx-auto flex items-center justify-center rounded-xl text-indigo-400 hover:text-white hover:bg-indigo-600/20 transition-all cursor-pointer relative group shrink-0"
                                title="Rapatkan Lembar Kerja ke Gambar (Hilangkan Jarak Kosong)"
                            >
                                <Sparkles class="w-4 h-4 text-indigo-400" />
                            </button>

                            <div class="h-px w-6 bg-white/15 my-0.5 shrink-0"></div>
                        </template>

                        <!-- STRUKTUR & DINDING -->
                        <template v-if="activeToolCategory === 'all' || activeToolCategory === 'structure'">
                            <!-- Wall Tool -->
                            <button
                                @click="selectCadTool('wall')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'wall' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Dinding Tebal / Wall (W)"
                            >
                                <Columns class="w-4 h-4 text-slate-200" />
                            </button>

                            <!-- Room / Rect Tool -->
                            <button
                                @click="selectCadTool('rect')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'rect' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Kotak Ruangan / Room Rect (R)"
                            >
                                <Square class="w-4 h-4 text-sky-300" />
                            </button>

                            <!-- Column Tool -->
                            <button
                                @click="selectCadTool('column')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'column' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Kolom Struktur Beton (C)"
                            >
                                <Building2 class="w-4 h-4 text-purple-400" />
                            </button>

                            <!-- Grid Line Tool -->
                            <button
                                @click="selectCadTool('grid_line')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'grid_line' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Garis As Bangunan & Kolom (G)"
                            >
                                <Hash class="w-4 h-4 text-cyan-400" />
                            </button>

                            <!-- Stair Symbol -->
                            <button
                                @click="selectCadTool('stair')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'stair' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Tangga Arsitektur 2D (S)"
                            >
                                <Move class="w-4 h-4 text-indigo-400 rotate-45" />
                            </button>

                            <div v-if="activeToolCategory === 'all'" class="h-px w-6 bg-white/15 my-0.5 shrink-0"></div>
                        </template>

                        <!-- BUKAAN PINTU & JENDELA -->
                        <template v-if="activeToolCategory === 'all' || activeToolCategory === 'openings'">
                            <!-- Single Swing Door -->
                            <button
                                @click="selectCadTool('door')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'door' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Pintu Swing Tunggal 1 Arah (D)"
                            >
                                <DoorClosed class="w-4 h-4 text-emerald-400" />
                            </button>

                            <!-- Double Swing Door -->
                            <button
                                @click="selectCadTool('double_door')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'double_door' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Pintu Ganda / Kupu-Tarung 2 Daun (Shift+D)"
                            >
                                <DoorOpen class="w-4 h-4 text-emerald-400" />
                            </button>

                            <!-- Sliding Door -->
                            <button
                                @click="selectCadTool('sliding_door')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'sliding_door' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Pintu Geser / Sliding Door (P)"
                            >
                                <SlidersHorizontal class="w-4 h-4 text-teal-400" />
                            </button>

                            <!-- Window Symbol -->
                            <button
                                @click="selectCadTool('window')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'window' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Bukaan Jendela (J)"
                            >
                                <Columns class="w-4 h-4 text-sky-400 rotate-90" />
                            </button>

                            <div v-if="activeToolCategory === 'all'" class="h-px w-6 bg-white/15 my-0.5 shrink-0"></div>
                        </template>

                        <!-- PERABOT & RUANGAN -->
                        <template v-if="activeToolCategory === 'all' || activeToolCategory === 'furniture'">
                            <!-- Sofa Ruang Tamu -->
                            <button
                                @click="selectCadTool('sofa')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'sofa' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Sofa Ruang Tamu 3-Seater (F)"
                            >
                                <Sofa class="w-4 h-4 text-amber-400" />
                            </button>

                            <!-- Meja Makan -->
                            <button
                                @click="selectCadTool('table')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'table' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Meja Makan & Kursi / Table (Shift+T)"
                            >
                                <Armchair class="w-4 h-4 text-rose-300" />
                            </button>

                            <!-- Tempat Tidur -->
                            <button
                                @click="selectCadTool('bed')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'bed' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Tempat Tidur / Bed (B)"
                            >
                                <Bed class="w-4 h-4 text-amber-300" />
                            </button>

                            <!-- Dapur & Kitchen Set -->
                            <button
                                @click="selectCadTool('kitchen')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'kitchen' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Meja Dapur: Bak Cuci Piring & Kompor Gas (K)"
                            >
                                <CookingPot class="w-4 h-4 text-orange-400" />
                            </button>

                            <!-- Toilet -->
                            <button
                                @click="selectCadTool('toilet')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'toilet' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Kloset Duduk / Toilet WC (U)"
                            >
                                <Bath class="w-4 h-4 text-cyan-400" />
                            </button>

                            <!-- Wastafel Kamar Mandi -->
                            <button
                                @click="selectCadTool('washbasin')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'washbasin' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Wastafel Cuci Tangan Kamar Mandi (Shift+B)"
                            >
                                <Droplets class="w-4 h-4 text-sky-400" />
                            </button>

                            <!-- Area Shower Box -->
                            <button
                                @click="selectCadTool('shower')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'shower' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Area Shower Box Kamar Mandi (Shift+S)"
                            >
                                <Sparkles class="w-4 h-4 text-cyan-300" />
                            </button>

                            <!-- Mobil Carport -->
                            <button
                                @click="selectCadTool('car')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'car' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Mobil Carport / Garasi 2D (Shift+C)"
                            >
                                <Car class="w-4 h-4 text-slate-300" />
                            </button>

                            <!-- Pohon Landscape -->
                            <button
                                @click="selectCadTool('tree')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'tree' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Pohon / Tanaman Landscape Taman (Shift+R)"
                            >
                                <Trees class="w-4 h-4 text-green-400" />
                            </button>

                            <div v-if="activeToolCategory === 'all'" class="h-px w-6 bg-white/15 my-0.5 shrink-0"></div>
                        </template>

                        <!-- SIMBOL ARSITEKTUR & ANOTASI -->
                        <template v-if="activeToolCategory === 'all' || activeToolCategory === 'symbols'">
                            <!-- North Arrow -->
                            <button
                                @click="selectCadTool('north_arrow')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'north_arrow' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Arah Mata Angin / Utara - North Arrow (N)"
                            >
                                <Compass class="w-4 h-4 text-indigo-400" />
                            </button>

                            <!-- Elevation Marker -->
                            <button
                                @click="selectCadTool('elevation_marker')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'elevation_marker' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Peil Ketinggian Lantai / Level (Shift+E)"
                            >
                                <Crosshair class="w-4 h-4 text-sky-400" />
                            </button>

                            <!-- Section Marker -->
                            <button
                                @click="selectCadTool('section_marker')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'section_marker' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Garis Potongan Arsitektur (X)"
                            >
                                <Split class="w-4 h-4 text-purple-400" />
                            </button>

                            <!-- Leader Note -->
                            <button
                                @click="selectCadTool('leader_note')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'leader_note' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Teks Catatan Panah Penunjuk Spesifikasi (Q)"
                            >
                                <ArrowUpRight class="w-4 h-4 text-amber-400" />
                            </button>

                            <!-- Dimension Tool -->
                            <button
                                @click="selectCadTool('dimension')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'dimension' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Penggaris Ukuran / Dimensi (M)"
                            >
                                <Ruler class="w-4 h-4 text-amber-400" />
                            </button>

                            <!-- Room Label Tool -->
                            <button
                                @click="selectCadTool('room_label')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'room_label' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Label Ruangan / Text (T)"
                            >
                                <Type class="w-4 h-4 text-white" />
                            </button>

                            <div v-if="activeToolCategory === 'all'" class="h-px w-6 bg-white/15 my-0.5 shrink-0"></div>
                        </template>

                        <!-- BENTUK GEOMETRI CAD -->
                        <template v-if="activeToolCategory === 'all' || activeToolCategory === 'shapes'">
                            <!-- Line Tool -->
                            <button
                                @click="selectCadTool('line')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'line' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Garis Bebas / Line (L)"
                            >
                                <Minus class="w-4 h-4" />
                            </button>

                            <!-- Circle Tool -->
                            <button
                                @click="selectCadTool('circle')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'circle' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Lingkaran CAD / Circle (O)"
                            >
                                <CircleIcon class="w-4 h-4 text-sky-400" />
                            </button>

                            <!-- Arc Tool -->
                            <button
                                @click="selectCadTool('arc')"
                                :class="[
                                    'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                    activeTool === 'arc' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                                ]"
                                title="Busur Lengkung CAD / Arc (A)"
                            >
                                <RotateCw class="w-4 h-4 text-indigo-400" />
                            </button>

                            <div v-if="activeToolCategory === 'all'" class="h-px w-6 bg-white/15 my-0.5 shrink-0"></div>
                        </template>

                        <!-- Penghapus (Selalu Tampil) -->
                        <button
                            @click="selectCadTool('eraser')"
                            :class="[
                                'w-9 h-9 mx-auto flex items-center justify-center rounded-xl transition-all cursor-pointer relative group shrink-0',
                                activeTool === 'eraser' ? 'bg-red-600 text-white shadow-md' : 'text-slate-400 hover:text-red-400 hover:bg-white/5'
                            ]"
                            title="Penghapus Elemen (E)"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

            <!-- Bottom Scroll Indicator (if more tools below on small screen) -->
            <button
                v-if="canScrollToolbarDown"
                @click="scrollToolbar('down')"
                type="button"
                class="absolute -bottom-2.5 left-1/2 -translate-x-1/2 z-50 flex items-center justify-center w-6 h-6 rounded-full bg-violet-600 hover:bg-violet-500 text-white shadow-lg border border-white/20 transition-all cursor-pointer animate-bounce hover:animate-none"
                title="Masih ada tools lainnya di bawah, klik untuk scroll"
            >
                <ChevronDown class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>

    <!-- FLOATING LAYER VISIBILITY MANAGER -->
    <div
        v-if="showLayerPanel"
        class="absolute left-16 sm:left-20 top-3 sm:top-4 z-40 w-60 p-4 rounded-2xl bg-slate-950/95 backdrop-blur-md border border-white/10 shadow-2xl space-y-3"
    >
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <div class="flex items-center gap-2">
                        <Layers class="w-4 h-4 text-violet-400" />
                        <span class="text-xs font-bold text-white uppercase tracking-wider">Layer Arsitektur</span>
                    </div>
                    <button @click="showLayerPanel = false" class="text-slate-400 hover:text-white text-xs">✕</button>
                </div>

                <div class="space-y-1 text-xs">
                    <div
                        v-for="l in [
                            { key: 'walls', label: 'Dinding (Walls)', color: 'text-red-400' },
                            { key: 'openings', label: 'Pintu & Jendela', color: 'text-emerald-400' },
                            { key: 'structure', label: 'Struktur (Kolom & Tangga)', color: 'text-purple-400' },
                            { key: 'furniture', label: 'Perabot & Sanitair', color: 'text-sky-400' },
                            { key: 'dimensions', label: 'Dimensi Ukuran', color: 'text-amber-400' },
                            { key: 'annotations', label: 'Teks & Label Ruang', color: 'text-slate-300' },
                        ]"
                        :key="l.key"
                        class="flex items-center justify-between p-2 rounded-xl hover:bg-white/5 transition-colors"
                    >
                        <span :class="['font-medium', l.color]">{{ l.label }}</span>
                        <button
                            @click="toggleLayer(l.key as any)"
                            class="p-1 rounded text-slate-400 hover:text-white cursor-pointer"
                        >
                            <Eye v-if="layersVisibility[l.key as keyof typeof layersVisibility]" class="w-3.5 h-3.5 text-violet-400" />
                            <EyeOff v-else class="w-3.5 h-3.5 text-slate-500" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- BOTTOM CANVAS VIEWPORT CONTROLS (RESPONSIVE WITH SCROLL CUES) -->
            <div class="cad-bottom-hud absolute left-1/2 -translate-x-1/2 bottom-2 sm:bottom-4 z-20 max-w-[calc(100vw-1.5rem)] sm:max-w-[calc(100vw-4rem)]">
                <div class="relative bg-slate-950/90 backdrop-blur-md border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
                    <!-- Petunjuk Gulir Kiri -->
                    <button
                        v-if="canScrollBottomHudLeft"
                        @click="scrollBottomHud('left')"
                        type="button"
                        class="absolute left-0 top-0 bottom-0 z-20 flex items-center pl-1.5 pr-3 bg-gradient-to-r from-slate-950 via-slate-950/95 to-transparent text-slate-300 hover:text-white transition-all cursor-pointer group"
                        title="Geser tools ke kiri"
                    >
                        <div class="w-5 h-5 rounded-full bg-slate-800 border border-white/20 flex items-center justify-center shadow-lg group-hover:bg-violet-600 transition-colors">
                            <ChevronLeft class="w-3.5 h-3.5 text-white" />
                        </div>
                    </button>

                    <!-- Scrollable Inner Row -->
                    <div
                        ref="bottomHudRef"
                        @scroll="checkBottomHudScroll"
                        class="flex items-center gap-2 px-3 py-1.5 sm:py-2 text-xs overflow-x-auto scrollbar-none scroll-smooth whitespace-nowrap"
                    >
                        <!-- Snap to grid toggle -->
                        <button
                            @click="isSnapToGrid = !isSnapToGrid"
                            :class="[
                                'flex items-center gap-1.5 px-2.5 py-1 rounded-xl font-medium transition-colors cursor-pointer shrink-0',
                                isSnapToGrid ? 'bg-violet-500/20 text-violet-300 border border-violet-500/30' : 'text-slate-400 hover:text-white'
                            ]"
                            title="Snap to Grid"
                        >
                            <Grid class="w-3.5 h-3.5" />
                            <span>Snap Grid ({{ gridSpacing }}px)</span>
                        </button>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <!-- Zoom buttons -->
                        <button
                            @click="zoom('out')"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
                            title="Zoom Out"
                        >
                            <ZoomOut class="w-4 h-4" />
                        </button>

                        <span class="font-mono text-slate-300 w-10 sm:w-12 text-center text-[11px] shrink-0">
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>

                        <button
                            @click="zoom('in')"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
                            title="Zoom In"
                        >
                            <ZoomIn class="w-4 h-4" />
                        </button>

                        <button
                            @click="zoom('reset')"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
                            title="Reset Zoom 100%"
                        >
                            <Maximize class="w-4 h-4" />
                        </button>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <!-- 3 Tombol Utama di Bottom Bar -->
                        <button
                            @click="autoFitSheetToDrawing"
                            class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold bg-indigo-500/15 hover:bg-indigo-500/30 text-indigo-300 hover:text-white border border-indigo-500/30 transition-all cursor-pointer shadow-sm shrink-0"
                            title="Rapatkan Kertas ke Gambar (Hilangkan Jarak Kosong)"
                        >
                            <Sparkles class="w-3.5 h-3.5 text-indigo-400" />
                            <span>Rapatkan</span>
                        </button>

                        <button
                            @click="resetPaperToDefault"
                            class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold bg-amber-500/15 hover:bg-amber-500/30 text-amber-300 hover:text-white border border-amber-500/30 transition-all cursor-pointer shadow-sm shrink-0"
                            title="Reset Kertas ke Standar ISO (A3)"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-amber-400" />
                            <span>Reset ISO</span>
                        </button>

                        <button
                            @click="zoomToFit"
                            class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold bg-sky-500/15 hover:bg-sky-500/30 text-sky-300 hover:text-white border border-sky-500/30 transition-all cursor-pointer shadow-sm shrink-0"
                            title="Pusatkan Gambar ke Layar / Zoom Extents (Z)"
                        >
                            <Focus class="w-3.5 h-3.5 text-sky-400" />
                            <span>Pusatkan</span>
                        </button>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <!-- Dynamic Sheet Dimensions or Model Space Badge -->
                        <span v-if="canvasMode === 'sheet'" class="text-[10px] text-slate-400 flex items-center gap-1.5 shrink-0">
                            <span class="px-1.5 py-0.5 rounded bg-violet-500/20 text-violet-300 font-mono text-[9px] border border-violet-500/30">
                                {{ paperSize }} • {{ paperWidth }}×{{ paperHeight }}px
                            </span>
                            <span class="hidden sm:inline text-slate-400">Tarik sudut lembar untuk ubah ukuran (Figma)</span>
                        </span>
                        <span v-else class="text-[10px] text-indigo-300 flex items-center gap-1 font-mono shrink-0">
                            <span class="px-1.5 py-0.5 rounded bg-indigo-500/20 border border-indigo-500/30">AutoCAD Model Space (∞)</span>
                        </span>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <span class="text-[10px] text-slate-400 flex items-center gap-1 shrink-0">
                            <span class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-[9px]">Spasi+Drag</span>
                            <span>Geser Kanvas</span>
                        </span>
                    </div>

                    <!-- Petunjuk Gulir Kanan: Notifikasi bila ada tools lainnya (Layar Kecil / Mobile) -->
                    <div
                        v-if="canScrollBottomHudRight"
                        class="absolute right-0 top-0 bottom-0 z-20 flex items-center pr-1.5 pl-6 bg-gradient-to-l from-slate-950 via-slate-950/95 to-transparent pointer-events-none"
                    >
                        <button
                            @click="scrollBottomHud('right')"
                            type="button"
                            class="pointer-events-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-violet-600/90 hover:bg-violet-500 text-white shadow-lg shadow-violet-600/30 border border-violet-400/40 transition-all cursor-pointer animate-pulse hover:animate-none"
                            title="Masih ada tools lainnya di kanan, klik untuk menggeser"
                        >
                            <span>Lainnya</span>
                            <ChevronRight class="w-3 h-3" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT SELECTION PROPERTY INSPECTOR -->
            <div
                v-if="selectedNodeId"
                class="cad-inspector absolute right-4 top-4 z-20 w-64 p-4 rounded-2xl bg-slate-950/90 backdrop-blur-md border border-white/10 shadow-2xl space-y-4"
            >
                <div class="flex items-center justify-between border-b border-white/10 pb-2">
                    <span class="text-xs font-bold text-white uppercase tracking-wider">Properti Elemen</span>
                    <button @click="deleteSelected" class="text-red-400 hover:text-red-300 p-1 cursor-pointer" title="Hapus Elemen">
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Tipe:</span>
                        <span class="font-mono text-white capitalize">{{ selectedElementData?.type || 'Objek' }}</span>
                    </div>
                    <div v-if="selectedElementData?.count" class="flex justify-between text-slate-400">
                        <span>Jumlah:</span>
                        <span class="font-mono text-emerald-400 font-bold">{{ selectedElementData.count }} elemen</span>
                    </div>
                    <div v-if="selectedElementData?.detail" class="flex justify-between text-slate-400">
                        <span>Rincian:</span>
                        <span class="font-mono text-sky-300 font-semibold text-right max-w-[140px] truncate" :title="selectedElementData.detail">{{ selectedElementData.detail }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>ID:</span>
                        <span class="font-mono text-slate-300 truncate max-w-[120px]">{{ selectedNodeId }}</span>
                    </div>
                    <div v-if="selectedElementData?.count" class="p-2 rounded-xl bg-violet-500/10 border border-violet-500/20 text-[11px] text-violet-300 leading-relaxed space-y-1">
                        <div>💡 <strong>Ctrl + Klik</strong> objek lain untuk menambah/melepas dari blok.</div>
                        <div>✨ <strong>Tarik (drag)</strong> objek untuk menggeser semuanya bersamaan.</div>
                    </div>
                    <!-- Input Khusus Dimensi (AutoCAD Dimension Custom Text) -->
                    <div v-if="selectedElementData?.type === 'dimension'" class="space-y-1.5 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-amber-300 flex items-center gap-1.5">
                                <Ruler class="w-3.5 h-3.5 text-amber-400" />
                                Ukuran Penggaris (AutoCAD)
                            </span>
                            <span v-if="selectedElementData.text" class="text-[9px] font-mono text-amber-400 bg-amber-500/10 border border-amber-500/20 px-1.5 py-0.5 rounded">
                                Custom
                            </span>
                            <span v-else class="text-[9px] font-mono text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-1.5 py-0.5 rounded">
                                Auto
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <input
                                :value="selectedElementData.text || ''"
                                @input="updateDimensionCustomText(($event.target as HTMLInputElement).value)"
                                :placeholder="getAutoDimensionPlaceholder(selectedElementData)"
                                class="flex-1 text-xs px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 font-mono"
                            />
                            <button
                                v-if="selectedElementData.text"
                                type="button"
                                @click="resetDimensionToAuto"
                                class="px-2 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-[10px] text-slate-300 hover:text-white transition-colors cursor-pointer shrink-0"
                                title="Reset ke ukuran otomatis"
                            >
                                Reset
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 leading-tight">
                            Bisa diedit manual (misal: <code>4.00 m</code> atau <code>AS 1-2</code>). Kosongkan untuk kembali ke hitungan otomatis.
                        </p>
                    </div>

                    <!-- Input Khusus Simbol Elevasi Lantai (Peil ±0.00) -->
                    <div v-else-if="selectedElementData?.type === 'elevation_marker'" class="space-y-2 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-emerald-400 flex items-center gap-1.5">
                                <Crosshair class="w-3.5 h-3.5 text-emerald-400" />
                                Nilai Elevasi Peil Lantai
                            </span>
                        </div>
                        <input
                            :value="selectedElementData.text || '±0.00'"
                            @input="updateElementCustomText(($event.target as HTMLInputElement).value)"
                            placeholder="±0.00"
                            class="w-full text-xs px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 font-mono"
                        />
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="preset in ['±0.00', '+0.05', '+0.10', '+0.50', '-0.15', '-0.20', '+1.00', '+3.50']"
                                :key="preset"
                                type="button"
                                @click="updateElementCustomText(preset)"
                                class="px-2 py-0.5 rounded text-[10px] font-mono bg-white/5 hover:bg-emerald-500/20 text-slate-300 hover:text-emerald-300 border border-white/10 transition-colors cursor-pointer"
                            >
                                {{ preset }}
                            </button>
                        </div>
                    </div>

                    <!-- Input Khusus Garis Grid Kolom (As A, B, 1, 2) -->
                    <div v-else-if="selectedElementData?.type === 'grid_line'" class="space-y-2 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-cyan-400 flex items-center gap-1.5">
                                <Hash class="w-3.5 h-3.5 text-cyan-400" />
                                Label Grid Kolom / As
                            </span>
                        </div>
                        <input
                            :value="selectedElementData.code || 'A'"
                            @input="updateElementCustomCode(($event.target as HTMLInputElement).value)"
                            placeholder="A"
                            class="w-full text-xs px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 font-mono font-bold"
                        />
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="preset in ['A', 'B', 'C', 'D', 'E', '1', '2', '3', '4', '5']"
                                :key="preset"
                                type="button"
                                @click="updateElementCustomCode(preset)"
                                class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white/5 hover:bg-cyan-500/20 text-slate-300 hover:text-cyan-300 border border-white/10 transition-colors cursor-pointer"
                            >
                                {{ preset }}
                            </button>
                        </div>
                    </div>

                    <!-- Input Khusus Notasi Garis Potongan (Section Marker) -->
                    <div v-else-if="selectedElementData?.type === 'section_marker'" class="space-y-2 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-amber-400 flex items-center gap-1.5">
                                <Split class="w-3.5 h-3.5 text-amber-400" />
                                Label Simbol Potongan
                            </span>
                        </div>
                        <input
                            :value="selectedElementData.text || 'A'"
                            @input="updateElementCustomText(($event.target as HTMLInputElement).value)"
                            placeholder="A"
                            class="w-full text-xs px-2.5 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500 font-mono font-bold"
                        />
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="preset in ['A', 'B', 'C', 'D', 'POTONGAN A-A', 'POTONGAN B-B']"
                                :key="preset"
                                type="button"
                                @click="updateElementCustomText(preset)"
                                class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-white/5 hover:bg-amber-500/20 text-slate-300 hover:text-amber-300 border border-white/10 transition-colors cursor-pointer"
                            >
                                {{ preset }}
                            </button>
                        </div>
                    </div>

                    <!-- Input Khusus Leader Note (Panah Keterangan Teknis) -->
                    <div v-else-if="selectedElementData?.type === 'leader_note'" class="space-y-2 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-indigo-400 flex items-center gap-1.5">
                                <ArrowUpRight class="w-3.5 h-3.5 text-indigo-400" />
                                Teks Catatan Teknis (Leader)
                            </span>
                        </div>
                        <textarea
                            :value="selectedElementData.text || 'Catatan Arsitektur'"
                            @input="updateElementCustomText(($event.target as HTMLTextAreaElement).value)"
                            placeholder="Ketik spesifikasi bahan atau detail..."
                            class="w-full text-xs p-2 rounded-xl bg-white/5 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                            rows="2"
                        ></textarea>
                        <div class="space-y-1">
                            <span class="text-[10px] text-slate-400 block">Preset Spesifikasi Cepat:</span>
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-for="preset in ['Plester + Aci finishing cat', 'Pintu Kayu Kamper Samarinda', 'Lantai Granit Tile 60x60', 'Plafon Gypsum Board 9mm', 'Kusen Aluminium 4 inch', 'Dinding Bata Ringan t=10cm']"
                                    :key="preset"
                                    type="button"
                                    @click="updateElementCustomText(preset)"
                                    class="px-2 py-0.5 rounded text-[10px] bg-white/5 hover:bg-indigo-500/20 text-slate-300 hover:text-indigo-300 border border-white/10 transition-colors cursor-pointer truncate max-w-[200px]"
                                    :title="preset"
                                >
                                    {{ preset }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Input Teks Label untuk Ruangan / Label Teks Umum -->
                    <div v-else-if="selectedElementData?.type === 'room_label' || selectedElementData?.text" class="space-y-1 pt-1">
                        <span class="text-slate-400 block">Teks Label:</span>
                        <textarea
                            v-model="selectedElementData.text"
                            @input="triggerAutoSaveDebounced"
                            class="w-full text-xs p-2 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-1 focus:ring-violet-500"
                            rows="2"
                        ></textarea>
                    </div>

                    <!-- Referensi Visual 3D (Sync Snapshot Gallery) -->
                    <div v-if="selectedNodeId && selectedNodeId !== 'multi' && selectedNodeId !== 'all'" class="space-y-2 pt-2 border-t border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-amber-300 flex items-center gap-1.5">
                                <Camera class="w-3.5 h-3.5 text-amber-400" />
                                Referensi Visual 3D
                            </span>
                            <span v-if="boundCapture" class="text-[9px] font-mono text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-1.5 py-0.5 rounded">
                                Terhubung ✓
                            </span>
                        </div>

                        <!-- Current Bound Capture Card -->
                        <div v-if="boundCapture" class="p-2 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <img
                                    :src="boundCapture.ai_rendered_url || boundCapture.url"
                                    :alt="boundCapture.room_name"
                                    class="w-12 h-9 object-cover rounded-lg border border-amber-500/30 bg-black shrink-0"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1">
                                        <span class="font-mono text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-500 text-black">
                                            {{ boundCapture.code || '3D' }}
                                        </span>
                                        <span class="text-xs font-semibold text-white truncate">{{ boundCapture.room_name }}</span>
                                    </div>
                                    <span class="text-[10px] text-neutral-400 font-mono">{{ boundCapture.captured_hour }}</span>
                                </div>
                                <button
                                    v-if="canEdit"
                                    type="button"
                                    @click="unbindCaptureFromElement"
                                    class="p-1 rounded-lg text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors cursor-pointer"
                                    title="Lepas Tautan"
                                >
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Searchable Select2 / Combobox Dropdown -->
                        <div v-if="canEdit" class="relative">
                            <div class="relative">
                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <input
                                    v-model="captureSearchQuery"
                                    @focus="isCaptureDropdownOpen = true"
                                    type="text"
                                    :placeholder="boundCapture ? 'Ganti referensi visual...' : 'Cari kode (R-01) atau nama...'"
                                    class="w-full text-xs pl-8 pr-3 py-1.5 rounded-xl bg-white/5 border border-white/10 text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                                />
                            </div>

                            <!-- Dropdown Menu (Default top 5, or filtered results) -->
                            <div
                                v-if="isCaptureDropdownOpen"
                                class="absolute left-0 right-0 top-full mt-1 z-30 max-h-56 overflow-y-auto rounded-xl bg-slate-900 border border-white/15 shadow-2xl p-1.5 space-y-1 backdrop-blur"
                            >
                                <div class="px-2 py-1 text-[10px] text-slate-400 font-medium flex items-center justify-between border-b border-white/5">
                                    <span>{{ captureSearchQuery ? 'Hasil Pencarian' : 'Visual 3D Proyek (Top 5)' }}</span>
                                    <button @click="isCaptureDropdownOpen = false" class="text-slate-400 hover:text-white cursor-pointer">✕</button>
                                </div>

                                <div v-if="filteredCaptureReferences.length === 0" class="py-3 text-center text-xs text-slate-500">
                                    Tidak ada visual 3D yang cocok.
                                </div>

                                <div
                                    v-for="cap in filteredCaptureReferences"
                                    :key="cap.id"
                                    @click="bindCaptureToElement(cap)"
                                    class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-amber-500/15 cursor-pointer transition-colors group"
                                    :class="boundCapture?.id === cap.id ? 'bg-amber-500/20 border border-amber-500/30' : ''"
                                >
                                    <img
                                        :src="cap.ai_rendered_url || cap.url"
                                        :alt="cap.room_name"
                                        class="w-10 h-7 object-cover rounded border border-white/10 bg-black shrink-0"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="font-mono text-[9px] font-bold px-1 rounded bg-amber-500/80 text-black shrink-0">
                                                    {{ cap.code || '3D' }}
                                                </span>
                                                <span class="text-xs text-white truncate font-medium group-hover:text-amber-300">{{ cap.room_name }}</span>
                                            </div>

                                            <!-- Label Status: Sudah Digunakan -->
                                            <span
                                                v-if="getCaptureUsage(cap).isUsed"
                                                class="text-[9px] font-semibold px-1.5 py-0.5 rounded border shrink-0 flex items-center gap-0.5"
                                                :class="getCaptureUsage(cap).isCurrent
                                                    ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                                    : 'bg-amber-500/15 text-amber-300 border-amber-500/30'"
                                            >
                                                <Check v-if="getCaptureUsage(cap).isCurrent" class="w-2.5 h-2.5" />
                                                <span>{{ getCaptureUsage(cap).isCurrent ? 'Sedang Dipakai' : 'Sudah Digunakan' }}</span>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-[9px] text-slate-400 font-mono mt-0.5">
                                            <span>{{ cap.captured_hour }}</span>
                                            <span
                                                v-if="getCaptureUsage(cap).isUsed && !getCaptureUsage(cap).isCurrent && getCaptureUsage(cap).roomLabel"
                                                class="text-neutral-400 truncate max-w-[100px]"
                                                :title="'Digunakan di: ' + getCaptureUsage(cap).roomLabel"
                                            >
                                                Di: {{ getCaptureUsage(cap).roomLabel }}
                                            </span>
                                        </div>
                                    </div>
                                    <Check v-if="boundCapture?.id === cap.id" class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CAD Object Transform Cluster (Putar, Cermin, Z-Order) -->
                <div v-if="selectedNodeId" class="space-y-1.5 pt-2 border-t border-white/10">
                    <span class="text-[11px] font-semibold text-slate-300 flex items-center gap-1.5">
                        <SlidersHorizontal class="w-3.5 h-3.5 text-violet-400" />
                        Transformasi CAD
                    </span>

                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            type="button"
                            @click="rotateSelected(90)"
                            class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 hover:border-violet-500/40 transition-colors cursor-pointer"
                            title="Putar Searah Jarum Jam (+90°)"
                        >
                            <RotateCw class="w-3.5 h-3.5 text-violet-400" />
                            <span>Putar +90°</span>
                        </button>

                        <button
                            type="button"
                            @click="rotateSelected(-90)"
                            class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 hover:border-violet-500/40 transition-colors cursor-pointer"
                            title="Putar Berlawanan Jarum Jam (-90°)"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-violet-400" />
                            <span>Putar -90°</span>
                        </button>

                        <button
                            type="button"
                            @click="flipSelected('h')"
                            class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 hover:border-sky-500/40 transition-colors cursor-pointer"
                            title="Cermin Mendatar (Flip Horizontal)"
                        >
                            <FlipHorizontal class="w-3.5 h-3.5 text-sky-400" />
                            <span>Flip Horiz</span>
                        </button>

                        <button
                            type="button"
                            @click="flipSelected('v')"
                            class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 hover:border-sky-500/40 transition-colors cursor-pointer"
                            title="Cermin Tegak (Flip Vertical)"
                        >
                            <FlipVertical class="w-3.5 h-3.5 text-sky-400" />
                            <span>Flip Vert</span>
                        </button>

                        <button
                            type="button"
                            @click="bringSelectedToFront"
                            class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 hover:border-emerald-500/40 transition-colors cursor-pointer"
                            title="Bawa Objek ke Lapisan Terdepan"
                        >
                            <BringToFront class="w-3.5 h-3.5 text-emerald-400" />
                            <span>Ke Depan</span>
                        </button>

                        <button
                            type="button"
                            @click="sendSelectedToBack"
                            class="flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 hover:border-amber-500/40 transition-colors cursor-pointer"
                            title="Kirim Objek ke Lapisan Terbelakang"
                        >
                            <SendToBack class="w-3.5 h-3.5 text-amber-400" />
                            <span>Ke Belakang</span>
                        </button>
                    </div>
                </div>

                <!-- Quick Copy, Paste & Duplicate Buttons in Inspector -->
                <div class="grid grid-cols-3 gap-1.5 pt-1">
                    <button
                        @click="copySelected"
                        :class="[
                            'flex items-center justify-center gap-1 py-2 px-1 rounded-xl text-xs font-semibold transition-all cursor-pointer',
                            isJustCopied
                                ? 'bg-emerald-500/25 text-emerald-300 border border-emerald-500/50 shadow-md shadow-emerald-950/50'
                                : 'bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10'
                        ]"
                        title="Salin Objek ke Clipboard (Ctrl+C)"
                    >
                        <Check v-if="isJustCopied" class="w-3.5 h-3.5 text-emerald-400" />
                        <Copy v-else class="w-3.5 h-3.5 text-violet-400" />
                        <span>{{ isJustCopied ? 'Tersalin!' : 'Salin' }}</span>
                    </button>

                    <button
                        @click="pasteClipboard"
                        class="flex items-center justify-center gap-1 py-2 px-1 rounded-xl text-xs font-medium bg-sky-500/10 hover:bg-sky-500/20 text-sky-300 border border-sky-500/20 transition-colors cursor-pointer"
                        title="Tempel Objek dari Clipboard (Ctrl+V)"
                    >
                        <ClipboardPaste class="w-3.5 h-3.5 text-sky-400" />
                        <span>Tempel</span>
                    </button>

                    <button
                        @click="duplicateSelected"
                        class="flex items-center justify-center gap-1 py-2 px-1 rounded-xl text-xs font-medium bg-violet-600/20 hover:bg-violet-600/30 text-violet-300 border border-violet-500/30 transition-colors cursor-pointer"
                        title="Duplikat Cepat Objek (Ctrl+D)"
                    >
                        <Files class="w-3.5 h-3.5 text-violet-400" />
                        <span>Duplikat</span>
                    </button>
                </div>

                <div v-if="isJustCopied" class="text-[11px] font-medium text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 p-2 rounded-xl text-center">
                    ✓ Objek berhasil disalin! Tekan tombol <strong>Tempel</strong> atau <strong>Ctrl+V</strong>.
                </div>

                <button
                    @click="deleteSelected"
                    class="w-full py-2 rounded-xl text-xs font-medium bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 transition-colors cursor-pointer"
                >
                    Hapus Objek Terpilih (Del)
                </button>
            </div>

            <!-- KONVA STAGE CONTAINER -->
            <div
                ref="containerRef"
                class="w-full h-full bg-[#0b0f19] cursor-crosshair overflow-hidden"
            ></div>
        </div>

        <!-- AUTOCAD BOTTOM LAYOUT & SHEET TAB BAR -->
        <footer class="h-9 bg-slate-950 border-t border-white/10 px-2 sm:px-4 flex items-center justify-between text-xs shrink-0 select-none z-20 overflow-x-auto scrollbar-none">
            <div class="flex items-center gap-1 shrink-0 overflow-x-auto scrollbar-none">
                <!-- AutoCAD Model Space Tab -->
                <button
                    @click="setCanvasMode('infinite')"
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-1 rounded-t-lg font-mono text-[11px] font-semibold border-b-2 transition-all cursor-pointer',
                        canvasMode === 'infinite'
                            ? 'bg-violet-600/20 text-violet-300 border-violet-500'
                            : 'text-slate-400 hover:text-white border-transparent hover:bg-white/5'
                    ]"
                    title="AutoCAD Model Space (Kanvas Tak Terbatas - Lihat semua denah & frame kertas)"
                >
                    <InfinityIcon class="w-3.5 h-3.5" />
                    <span>Model (∞)</span>
                </button>

                <div class="h-4 w-px bg-white/10 mx-1 shrink-0"></div>

                <!-- In-Canvas Paper Frames Tabs (Lembar 1, Lembar 2, dst yang ada di kanvas ini) -->
                <div
                    v-for="(f, idx) in canvasPaperFrames"
                    :key="f.id"
                    class="flex items-center shrink-0"
                >
                    <button
                        @click="focusOnFrame(f)"
                        type="button"
                        :class="[
                            'flex items-center gap-1.5 px-3 py-1 rounded-t-lg font-mono text-[11px] border-b-2 transition-all cursor-pointer max-w-[170px] truncate',
                            canvasMode === 'sheet' && activeFocusedFrameId === f.id
                                ? 'bg-white/10 text-white font-bold border-emerald-400'
                                : 'text-slate-400 hover:text-slate-200 border-transparent hover:bg-white/5'
                        ]"
                        :title="`Fokus ke ${f.title} (${f.width}×${f.height}px)`"
                    >
                        <LayoutTemplate class="w-3 h-3 text-emerald-400 shrink-0" />
                        <span class="truncate">{{ f.title }}</span>
                    </button>
                    <!-- Tombol Hapus Kertas (Hanya untuk kertas tambahan, bukan kertas utama) -->
                    <button
                        v-if="!f.isMain && canEdit"
                        @click.stop="confirmRemovePaperFrame(f)"
                        type="button"
                        class="p-1 -ml-1 mr-1 text-slate-500 hover:text-red-400 hover:bg-red-500/15 rounded-md transition-colors cursor-pointer"
                        :title="`Hapus ${f.title} dari kanvas`"
                    >
                        <Trash2 class="w-3 h-3" />
                    </button>
                </div>

                <!-- + Tambah Kertas ke Kanvas Ini (Langsung Berdampingan) -->
                <button
                    v-if="canEdit"
                    @click="addPaperFrameToCanvas()"
                    type="button"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-emerald-600/30 hover:bg-emerald-600 text-emerald-200 hover:text-white border border-emerald-500/40 transition-all cursor-pointer ml-1 shrink-0 shadow-sm"
                    title="Tambah Area Kertas Baru ke Kanvas Ini (Berdampingan sehingga 1 lembar kerja ada 2 kertas)"
                >
                    <Plus class="w-3 h-3" />
                    <span>+ Kertas ke Kanvas</span>
                </button>

                <!-- Separator if projectSheets exist -->
                <div v-if="projectSheets && projectSheets.length > 1" class="h-4 w-px bg-white/10 mx-1.5 shrink-0"></div>

                <!-- Project File Switcher (File Lembar Lain dalam Proyek) -->
                <template v-if="projectSheets && projectSheets.length > 1">
                    <button
                        v-for="(s, idx) in projectSheets.filter(p => p.id !== sheet.id)"
                        :key="s.id"
                        @click="navigateToSheet(s.id)"
                        type="button"
                        class="flex items-center gap-1.5 px-2.5 py-1 rounded-t-lg font-mono text-[10px] text-slate-400 hover:text-white hover:bg-white/5 border-b-2 border-transparent transition-all cursor-pointer shrink-0 max-w-[140px] truncate opacity-75 hover:opacity-100"
                        :title="`Buka File Lembar Lain: ${s.title} (${s.scale})`"
                    >
                        <Files class="w-3 h-3 text-slate-500 shrink-0" />
                        <span class="truncate">{{ s.title }}</span>
                    </button>
                </template>

                <!-- + Buat Lembar Dokumen Baru -->
                <button
                    v-if="canEdit"
                    @click="openNewSheetModal"
                    type="button"
                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-medium bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white border border-white/10 transition-all cursor-pointer ml-1 shrink-0"
                    title="Buat Dokumen / File Lembar Kerja Baru di Proyek Ini"
                >
                    <Plus class="w-3 h-3" />
                    <span class="hidden xl:inline">File Baru</span>
                </button>
            </div>

            <!-- Status Info CAD (Kanan) -->
            <div class="hidden md:flex items-center gap-3 font-mono text-[11px] text-slate-400 shrink-0 pl-3">
                <button
                    v-if="canEdit"
                    type="button"
                    @click="showSheetSettingsModal = true"
                    class="hover:text-amber-300 transition flex items-center gap-1 cursor-pointer"
                    title="Ubah Satuan Unit (m, cm, mm)"
                >
                    <span>Unit: <strong class="text-amber-300 underline underline-offset-2">{{ currentSheet.unit }}</strong></span>
                </button>
                <span v-else>Unit: <strong class="text-slate-200">{{ currentSheet.unit }}</strong></span>

                <button
                    v-if="canEdit"
                    type="button"
                    @click="showSheetSettingsModal = true"
                    class="hover:text-white transition flex items-center gap-1 cursor-pointer"
                    title="Ubah Skala Lembar Kerja"
                >
                    <span>Skala: <strong class="text-slate-200 underline underline-offset-2">{{ currentSheet.scale }}</strong></span>
                </button>
                <span v-else>Skala: <strong class="text-slate-200">{{ currentSheet.scale }}</strong></span>

                <span>Zoom: <strong class="text-violet-300">{{ Math.round(zoomLevel * 100) }}%</strong></span>
                <span v-if="canvasMode === 'sheet'">Kertas: <strong class="text-emerald-400">{{ paperSize }}</strong></span>
                <span v-else class="text-amber-400 font-semibold">Mode Model (∞)</span>
            </div>
        </footer>

        <!-- HELP & HOTKEYS MODAL (RESPONSIVE & FIXED CLOSE BUTTON) -->
        <div v-if="showHelpModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/70 backdrop-blur-sm">
            <div class="bg-slate-950 w-full max-w-3xl max-h-[90vh] sm:max-h-[85vh] rounded-3xl border border-white/10 shadow-2xl flex flex-col text-xs text-slate-300 overflow-hidden">
                <!-- Sticky Header with Close Button -->
                <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between shrink-0 bg-slate-950">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-violet-500/20 text-violet-400 flex items-center justify-center border border-violet-500/30 shrink-0">
                            <HelpCircle class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Panduan & Pintasan Tombol (Hotkeys) CAD</h3>
                            <p class="text-[11px] text-slate-400">Pintasan keyboard & kontrol navigasi kanvas 2D arsitek</p>
                        </div>
                    </div>
                    <button
                        @click="showHelpModal = false"
                        type="button"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer text-base leading-none"
                        title="Tutup (Esc)"
                    >
                        ✕
                    </button>
                </div>

                <!-- Scrollable Body with Responsive Multi-Column Grid -->
                <div class="p-4 sm:p-6 overflow-y-auto custom-scroll flex-1 space-y-4">
                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Navigasi Kanvas & Seleksi Objek</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 font-mono">
                        <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex flex-col gap-1 col-span-1 sm:col-span-2 lg:col-span-3">
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-300 font-semibold">AutoCAD Box Select (Blok Garis/Objek)</span>
                                <span class="text-emerald-400 font-bold">Klik & Drag di Kanvas</span>
                            </div>
                            <div class="text-[11px] text-slate-400 space-y-0.5 mt-1">
                                <div>• <strong class="text-emerald-400">Kanan ke Kiri (Hijau Putus-putus):</strong> Memilih semua garis/objek yang tersentuh kotak (Crossing).</div>
                                <div>• <strong class="text-sky-400">Kiri ke Kanan (Biru Solid):</strong> Memilih hanya garis/objek yang berada di dalam kotak (Window).</div>
                                <div>• <strong class="text-violet-400">Ctrl / Shift + Klik:</strong> Menambah / mengurangi pilihan multi-seleksi objek.</div>
                                <div>• <strong class="text-emerald-400">Ctrl + Drag Objek:</strong> Salin dan gandakan objek langsung ke posisi baru (Copy & Drag).</div>
                                <div>• <strong class="text-amber-400">Tarik Objek:</strong> Geser langsung di dalam area objek yang terpilih untuk memindahkan seluruhnya bersamaan.</div>
                            </div>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between col-span-1 sm:col-span-2 lg:col-span-3">
                            <span class="text-slate-400">Salin & Gandakan Langsung (Clone Drag)</span>
                            <span class="text-emerald-400 font-bold">Ctrl + Drag Objek</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between col-span-1 sm:col-span-2 lg:col-span-3">
                            <span class="text-slate-400">Multi-Seleksi Tambah/Kurang</span>
                            <span class="text-sky-400 font-bold">Ctrl + Klik Kiri</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between col-span-1 sm:col-span-2 lg:col-span-3">
                            <span class="text-slate-400">Scroll Layar Atas / Bawah</span>
                            <span class="text-violet-400 font-bold">Ctrl + Scroll Mouse</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between col-span-1 sm:col-span-2 lg:col-span-3">
                            <span class="text-slate-400">Scroll Layar Samping Kiri / Kanan</span>
                            <span class="text-violet-400 font-bold">Shift + Scroll Mouse</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Geser Kanvas (Pan)</span>
                            <span class="text-emerald-400 font-bold">H / Spasi</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pusatkan Gambar</span>
                            <span class="text-sky-400 font-bold">Z</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pilih Semua Objek</span>
                            <span class="text-amber-400 font-bold">Ctrl+A</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Zoom Presisi</span>
                            <span class="text-violet-400 font-bold">Scroll Mouse</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Salin Objek (Copy)</span>
                            <span class="text-violet-400 font-bold">Ctrl+C</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Tempel (Paste)</span>
                            <span class="text-violet-400 font-bold">Ctrl+V</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Duplikat Cepat</span>
                            <span class="text-violet-400 font-bold">Ctrl+D</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Undo</span>
                            <span class="text-violet-400 font-bold">Ctrl+Z</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Redo</span>
                            <span class="text-violet-400 font-bold">Ctrl+Y / Ctrl+Shift+Z</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Hapus Objek Terpilih</span>
                            <span class="text-rose-400 font-bold">Delete / Backspace</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Batal / Tutup Menu</span>
                            <span class="text-amber-400 font-bold">Esc</span>
                        </div>
                    </div>

                    <!-- KATEGORI 1: STRUKTUR & RUANGAN -->
                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider pt-2 flex items-center gap-1.5">
                        <span>🏛️</span>
                        <span>Struktur & Bangunan (Structure)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 font-mono">
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Dinding Tebal</span>
                            <span class="text-violet-400 font-bold">W</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Ruangan Kotak</span>
                            <span class="text-violet-400 font-bold">R</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Kolom Beton</span>
                            <span class="text-violet-400 font-bold">C</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Garis As (Grid)</span>
                            <span class="text-violet-400 font-bold">G</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Tangga 2D</span>
                            <span class="text-violet-400 font-bold">S</span>
                        </div>
                    </div>

                    <!-- KATEGORI 2: BUKAAN PINTU & JENDELA -->
                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider pt-2 flex items-center gap-1.5">
                        <span>🚪</span>
                        <span>Bukaan Pintu & Jendela (Openings)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono">
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pintu Swing Tunggal</span>
                            <span class="text-emerald-400 font-bold">D</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pintu Ganda (2 Daun)</span>
                            <span class="text-emerald-400 font-bold">Shift+D</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pintu Geser (Sliding)</span>
                            <span class="text-emerald-400 font-bold">P</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Bukaan Jendela</span>
                            <span class="text-emerald-400 font-bold">J</span>
                        </div>
                    </div>

                    <!-- KATEGORI 3: PERABOT & RUANGAN -->
                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider pt-2 flex items-center gap-1.5">
                        <span>🛋️</span>
                        <span>Perabot & Sanitair (Furniture & Fixtures)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 font-mono">
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Sofa Ruang Tamu</span>
                            <span class="text-amber-400 font-bold">F</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Meja Makan & Kursi</span>
                            <span class="text-amber-400 font-bold">Shift+T</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Tempat Tidur</span>
                            <span class="text-amber-400 font-bold">B</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Dapur (Kitchen Set)</span>
                            <span class="text-amber-400 font-bold">K</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Kloset WC Duduk</span>
                            <span class="text-cyan-400 font-bold">U</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Wastafel Cuci</span>
                            <span class="text-cyan-400 font-bold">Shift+B</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Shower Box</span>
                            <span class="text-cyan-400 font-bold">Shift+S</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-300">Mobil Garasi</span>
                            <span class="text-slate-300 font-bold">Shift+C</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pohon Taman</span>
                            <span class="text-green-400 font-bold">Shift+R</span>
                        </div>
                    </div>

                    <!-- KATEGORI 4: SIMBOL ARSITEKTUR & ANOTASI -->
                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider pt-2 flex items-center gap-1.5">
                        <span>📐</span>
                        <span>Simbol & Anotasi (Annotations & Symbols)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 font-mono">
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Arah Utara</span>
                            <span class="text-sky-400 font-bold">N</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Peil Level</span>
                            <span class="text-sky-400 font-bold">Shift+E</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Potongan (A-A)</span>
                            <span class="text-purple-400 font-bold">X</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Panah Catatan</span>
                            <span class="text-amber-400 font-bold">Q</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Dimensi Ukuran</span>
                            <span class="text-violet-400 font-bold">M</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Teks / Label</span>
                            <span class="text-violet-400 font-bold">T</span>
                        </div>
                    </div>

                    <!-- KATEGORI 5: GEOMETRI CAD & UTILITAS -->
                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider pt-2 flex items-center gap-1.5">
                        <span>⭕</span>
                        <span>Bentuk Geometri CAD & Utilitas</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono">
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Garis Bebas (Line)</span>
                            <span class="text-violet-400 font-bold">L</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Lingkaran (Circle)</span>
                            <span class="text-sky-400 font-bold">O</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Busur (Arc)</span>
                            <span class="text-indigo-400 font-bold">A</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Penghapus (Eraser)</span>
                            <span class="text-rose-400 font-bold">E</span>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer with Always-Visible Close Button -->
                <div class="px-5 py-3 border-t border-white/10 flex items-center justify-between bg-slate-950 shrink-0">
                    <span class="text-[11px] text-slate-500 hidden sm:inline">Tekan Esc atau klik tombol untuk menutup</span>
                    <button
                        @click="showHelpModal = false"
                        type="button"
                        class="w-full sm:w-auto px-5 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-medium text-xs transition-colors cursor-pointer shadow-md shadow-violet-600/30"
                    >
                        Tutup Panduan (Esc)
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL BUAT LEMBAR KERJA / KERTAS BARU LANGSUNG DARI EDITOR -->
        <div v-if="showNewSheetModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div class="bg-slate-950 w-full max-w-md rounded-3xl border border-white/10 p-6 shadow-2xl space-y-4 text-slate-100">
                <div class="flex items-center justify-between border-b border-white/10 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-violet-500/20 text-violet-400 flex items-center justify-center border border-violet-500/30">
                            <Plus class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Buat Lembar Kerja / Kertas Baru</h3>
                            <p class="text-[11px] text-slate-400">Proyek: {{ project.title }}</p>
                        </div>
                    </div>
                    <button @click="showNewSheetModal = false" class="text-slate-400 hover:text-white p-1">✕</button>
                </div>

                <!-- OPSI CEPAT: TAMBAH KERTAS BERDAMPINGAN KE KANVAS SAAT INI -->
                <div class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-emerald-300">Tambah ke Kanvas Ini (Berdampingan)?</div>
                        <div class="text-[11px] text-slate-400 truncate">Buat 2 kertas dalam 1 lembar kerja tanpa pindah halaman.</div>
                    </div>
                    <button
                        type="button"
                        @click="addPaperFrameToCanvas(newSheetForm.title || undefined); showNewSheetModal = false;"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shrink-0 cursor-pointer shadow-md shadow-emerald-900/40 transition-all"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>+ Ke Kanvas Ini</span>
                    </button>
                </div>

                <form @submit.prevent="submitNewSheet" class="space-y-3.5">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider -mb-1">Atau Buat File Lembar Kerja Terpisah:</div>
                    <!-- Judul Lembar Kerja -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">
                            Judul / Nama Ruangan
                        </label>
                        <input
                            v-model="newSheetForm.title"
                            type="text"
                            required
                            placeholder="Contoh: Denah Lantai 2, Kamar Utama, R. Santai"
                            class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-violet-400"
                            autofocus
                        />
                    </div>

                    <!-- Tipe Gambar -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">
                            Tipe Gambar
                        </label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                v-for="t in [
                                    { key: 'plan', label: 'Denah' },
                                    { key: 'elevation', label: 'Tampak' },
                                    { key: 'section', label: 'Potongan' },
                                ]"
                                :key="t.key"
                                @click="newSheetForm.type = t.key as any"
                                :class="[
                                    'py-1.5 px-2 rounded-xl text-xs font-medium border text-center transition-all cursor-pointer',
                                    newSheetForm.type === t.key
                                        ? 'bg-violet-600/30 text-violet-300 border-violet-500 font-semibold'
                                        : 'bg-white/5 text-slate-400 border-white/10 hover:text-white',
                                ]"
                            >
                                {{ t.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Skala & Ukuran Kertas -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">
                                Skala
                            </label>
                            <select
                                v-model="newSheetForm.scale"
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:ring-1 focus:ring-violet-400 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="1:50" class="bg-slate-900 text-white">1:50 (Detail)</option>
                                <option value="1:100" class="bg-slate-900 text-white">1:100 (Standar)</option>
                                <option value="1:200" class="bg-slate-900 text-white">1:200 (Site)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">
                                Ukuran Kertas
                            </label>
                            <select
                                v-model="newSheetForm.paper_size"
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:ring-1 focus:ring-violet-400 cursor-pointer [color-scheme:dark] [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="A0" class="bg-slate-900 text-white">A0</option>
                                <option value="A1" class="bg-slate-900 text-white">A1</option>
                                <option value="A2" class="bg-slate-900 text-white">A2</option>
                                <option value="A3" class="bg-slate-900 text-white">A3</option>
                                <option value="A4" class="bg-slate-900 text-white">A4</option>
                            </select>
                        </div>
                    </div>

                    <!-- Orientasi Kertas -->
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">
                            Orientasi Kertas
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="newSheetForm.orientation = 'landscape'"
                                :class="[
                                    'py-1.5 px-3 rounded-xl text-xs font-medium border text-center transition-all cursor-pointer',
                                    newSheetForm.orientation === 'landscape'
                                        ? 'bg-violet-600/30 text-violet-300 border-violet-500 font-semibold'
                                        : 'bg-white/5 text-slate-400 border-white/10 hover:text-white',
                                ]"
                            >
                                Landscape (Mendatar)
                            </button>
                            <button
                                type="button"
                                @click="newSheetForm.orientation = 'portrait'"
                                :class="[
                                    'py-1.5 px-3 rounded-xl text-xs font-medium border text-center transition-all cursor-pointer',
                                    newSheetForm.orientation === 'portrait'
                                        ? 'bg-violet-600/30 text-violet-300 border-violet-500 font-semibold'
                                        : 'bg-white/5 text-slate-400 border-white/10 hover:text-white',
                                ]"
                            >
                                Portrait (Tegak)
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/10">
                        <button
                            type="button"
                            @click="showNewSheetModal = false"
                            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="isCreatingSheet"
                            class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white transition-all cursor-pointer disabled:opacity-50 shadow-md shadow-violet-600/30"
                        >
                            <Loader2 v-if="isCreatingSheet" class="w-3.5 h-3.5 animate-spin" />
                            <Plus v-else class="w-3.5 h-3.5" />
                            <span>{{ isCreatingSheet ? 'Membuat...' : 'Buat & Buka Kertas' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EXPORT MODAL DIALOG (LENGKAP DENGAN PREVIEW METADATA & SEMUA FORMAT) -->
        <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-950 w-full max-w-xl rounded-3xl border border-white/10 shadow-2xl flex flex-col text-xs text-slate-300 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between shrink-0 bg-slate-950">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center border border-sky-500/30 shrink-0">
                            <Download class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Ekspor Lembar Kerja CAD</h3>
                            <p class="text-[11px] text-slate-400">{{ sheet.title }} • {{ project.title }}</p>
                        </div>
                    </div>
                    <button
                        @click="showExportModal = false"
                        type="button"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer text-base leading-none"
                        title="Tutup (Esc)"
                    >
                        ✕
                    </button>
                </div>

                <!-- Body -->
                <div class="p-5 sm:p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scroll">
                    <!-- Paper Frame Selector (jika kanvas memiliki lebih dari 1 kertas) -->
                    <div v-if="canvasPaperFrames.length > 1" class="p-3.5 rounded-2xl bg-white/5 border border-white/10 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-slate-300">Pilih Lembar Kertas untuk Diekspor:</span>
                            <span class="text-[10px] text-slate-500 font-mono">{{ canvasPaperFrames.length }} Lembar di Kanvas</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="f in canvasPaperFrames"
                                :key="f.id"
                                type="button"
                                @click="activeExportFrameId = f.id"
                                :class="[
                                    'px-3 py-1.5 rounded-xl text-xs font-mono transition-all cursor-pointer border flex items-center gap-1.5',
                                    activeExportFrameId === f.id
                                        ? 'bg-violet-600/30 text-violet-200 border-violet-500 font-bold shadow-sm'
                                        : 'bg-white/5 text-slate-400 border-white/10 hover:text-white hover:bg-white/10'
                                ]"
                            >
                                <LayoutTemplate class="w-3.5 h-3.5 text-emerald-400" />
                                <span>{{ f.title }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Sheet Metadata Summary -->
                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="text-slate-400">Lembar:</span>
                            <span class="text-emerald-400 font-semibold ml-1">{{ selectedExportFrame.title }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Skala:</span>
                            <span class="text-white font-semibold ml-1">{{ sheet.scale }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Ukuran:</span>
                            <span class="text-white font-semibold ml-1">{{ selectedExportFrame.size || paperSize }} ({{ selectedExportFrame.orientation || orientation }})</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Dimensi:</span>
                            <span class="text-white font-semibold ml-1">{{ selectedExportFrame.width }} × {{ selectedExportFrame.height }} px</span>
                        </div>
                    </div>

                    <!-- Export Options Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- 1. DXF Option -->
                        <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 hover:border-amber-500/40 transition-all flex flex-col justify-between gap-3 group">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center border border-amber-500/25">
                                        <FileCode class="w-3.5 h-3.5" />
                                    </div>
                                    <span class="font-bold text-white text-xs">AutoCAD (.DXF)</span>
                                </div>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    Format CAD R12 ASCII standar layer arsitektur (WALLS, DOORS, WINDOWS, STRUCTURE, DIMENSIONS).
                                </p>
                            </div>
                            <button
                                @click="exportWithSave('dxf', activeExportFrameId)"
                                :disabled="isExporting"
                                class="w-full py-2 px-3 rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-300 hover:text-white font-semibold border border-amber-500/30 transition-all flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                            >
                                <Loader2 v-if="isExporting" class="w-3.5 h-3.5 animate-spin" />
                                <Download v-else class="w-3.5 h-3.5" />
                                <span>Unduh DXF</span>
                            </button>
                        </div>

                        <!-- 2. SVG Option -->
                        <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 hover:border-sky-500/40 transition-all flex flex-col justify-between gap-3 group">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-sky-500/15 text-sky-400 flex items-center justify-center border border-sky-500/25">
                                        <Download class="w-3.5 h-3.5" />
                                    </div>
                                    <span class="font-bold text-white text-xs">Vektor (.SVG)</span>
                                </div>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    Grafik vektor tajam resolusi tak terhingga. Kompatibel Illustrator, Corel, Figma, atau dokumen web.
                                </p>
                            </div>
                            <button
                                @click="exportWithSave('svg', activeExportFrameId)"
                                :disabled="isExporting"
                                class="w-full py-2 px-3 rounded-xl bg-sky-500/15 hover:bg-sky-500/25 text-sky-300 hover:text-white font-semibold border border-sky-500/30 transition-all flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                            >
                                <Loader2 v-if="isExporting" class="w-3.5 h-3.5 animate-spin" />
                                <Download v-else class="w-3.5 h-3.5" />
                                <span>Unduh SVG</span>
                            </button>
                        </div>

                        <!-- 3. PNG Option -->
                        <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 hover:border-emerald-500/40 transition-all flex flex-col justify-between gap-3 group">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center border border-emerald-500/25">
                                        <Sparkles class="w-3.5 h-3.5" />
                                    </div>
                                    <span class="font-bold text-white text-xs">Gambar HD (.PNG)</span>
                                </div>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    Gambar resolusi tajam 2× (retina). Pilih area kertas saja atau seluruh tampilan kanvas saat ini.
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="exportPng({ frameId: activeExportFrameId, fullCanvas: false })"
                                    class="flex-1 py-2 px-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 hover:text-white font-semibold border border-emerald-500/30 transition-all flex items-center justify-center gap-1 cursor-pointer text-[11px]"
                                    title="Unduh area frame kertas saja"
                                >
                                    <Sparkles class="w-3.5 h-3.5" />
                                    <span>Kertas Saja</span>
                                </button>
                                <button
                                    @click="exportPng({ fullCanvas: true })"
                                    class="flex-1 py-2 px-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white font-semibold border border-white/10 transition-all flex items-center justify-center gap-1 cursor-pointer text-[11px]"
                                    title="Unduh seluruh kanvas saat ini"
                                >
                                    <span>Seluruh Kanvas</span>
                                </button>
                            </div>
                        </div>

                        <!-- 4. PDF Document Option (Unduh Langsung) -->
                        <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 hover:border-rose-500/40 transition-all flex flex-col justify-between gap-3 group">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-rose-500/15 text-rose-400 flex items-center justify-center border border-rose-500/25">
                                        <FileText class="w-3.5 h-3.5" />
                                    </div>
                                    <span class="font-bold text-white text-xs">Dokumen PDF (.PDF)</span>
                                </div>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    Unduh langsung file PDF standar skala 1:1 tajam tanpa perlu membuka dialog cetak browser.
                                </p>
                            </div>
                            <button
                                @click="exportDirectPdf(activeExportFrameId)"
                                class="w-full py-2 px-3 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 hover:text-white font-semibold border border-rose-500/30 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Download class="w-3.5 h-3.5" />
                                <span>Unduh File PDF</span>
                            </button>
                        </div>

                        <!-- 5. Print to Printer Option (Bersih via Iframe) -->
                        <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 hover:border-indigo-500/40 transition-all flex flex-col justify-between gap-3 group">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-400 flex items-center justify-center border border-indigo-500/25">
                                        <Printer class="w-3.5 h-3.5" />
                                    </div>
                                    <span class="font-bold text-white text-xs">Cetak ke Printer</span>
                                </div>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    Buka dialog cetak browser dengan lembar bersih (tanpa toolbar/UI) untuk dicetak ke kertas fisik.
                                </p>
                            </div>
                            <button
                                @click="exportPdfOrPrint(activeExportFrameId)"
                                class="w-full py-2 px-3 rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-300 hover:text-white font-semibold border border-indigo-500/30 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Printer class="w-3.5 h-3.5" />
                                <span>Buka Dialog Cetak</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-5 py-3 border-t border-white/10 flex items-center justify-between bg-slate-950 shrink-0">
                    <span class="text-[11px] text-slate-500">Tekan Esc atau klik tombol untuk menutup</span>
                    <button
                        @click="showExportModal = false"
                        type="button"
                        class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white font-medium text-xs transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- DELETE PAPER FRAME CONFIRMATION MODAL -->
        <div v-if="showDeleteFrameModal && frameToDelete" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="bg-slate-950 w-full max-w-md rounded-3xl border border-red-500/25 shadow-2xl p-6 text-xs text-slate-300 space-y-4 animate-in fade-in zoom-in-95 duration-150">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-red-500/15 text-red-400 flex items-center justify-center border border-red-500/25 shrink-0">
                        <Trash2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Hapus Kertas dari Kanvas?</h3>
                        <p class="text-[11px] text-slate-400">Lembar: {{ frameToDelete.title }}</p>
                    </div>
                </div>

                <p class="text-slate-300 leading-relaxed text-xs">
                    Apakah Anda yakin ingin menghapus area kertas <strong>{{ frameToDelete.title }}</strong> dari kanvas kerja ini?
                    Objek denah di dalamnya tidak akan terhapus dari Model Space, tetapi batas cetak kertas ini akan dihilangkan.
                </p>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-white/10">
                    <button
                        type="button"
                        @click="showDeleteFrameModal = false; frameToDelete = null"
                        class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeRemovePaperFrame"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-semibold bg-red-600 hover:bg-red-500 text-white transition-all cursor-pointer shadow-md shadow-red-600/30"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Ya, Hapus Kertas</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL PENGATURAN LEMBAR KERJA (SHEET SETTINGS) -->
        <div v-if="showSheetSettingsModal" class="no-print fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-950 border border-white/10 rounded-3xl w-full max-w-md p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <Settings class="w-4 h-4 text-violet-400" />
                        Pengaturan Lembar Kerja CAD
                    </h3>
                    <button
                        type="button"
                        @click="showSheetSettingsModal = false"
                        class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-white/5 transition-colors cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveSheetSettings" class="space-y-4 text-xs">
                    <div>
                        <label class="block text-slate-300 font-mono mb-1 font-semibold">JUDUL LEMBAR KERJA</label>
                        <input
                            v-model="sheetForm.title"
                            type="text"
                            required
                            placeholder="Contoh: DENAH LT. 1, TAMPAK DEPAN, POTONGAN A-A"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-violet-500 font-medium"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-mono mb-1 font-semibold">KATEGORI GAMBAR</label>
                            <select
                                v-model="sheetForm.type"
                                class="w-full bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-violet-500 cursor-pointer [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="plan" class="bg-slate-900 text-white">Denah (Floor Plan)</option>
                                <option value="elevation" class="bg-slate-900 text-white">Tampak (Elevation)</option>
                                <option value="section" class="bg-slate-900 text-white">Potongan (Section)</option>
                            </select>
                            <span class="text-[10px] text-slate-400 mt-1 block">Kategori 'Denah' otomatis jadi master plan Booklet</span>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-mono mb-1 font-semibold">SATUAN UKURAN (UNIT)</label>
                            <select
                                v-model="sheetForm.unit"
                                class="w-full bg-slate-900 border border-amber-500/40 rounded-xl px-3 py-2 text-amber-300 font-bold focus:outline-none focus:ring-1 focus:ring-amber-500 cursor-pointer [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="m" class="bg-slate-900 text-white">Meter (m) - Arsitektur</option>
                                <option value="cm" class="bg-slate-900 text-white">Centimeter (cm) - Sipil</option>
                                <option value="mm" class="bg-slate-900 text-white">Milimeter (mm) - Detail</option>
                            </select>
                            <span class="text-[10px] text-amber-400/80 mt-1 block">Satuan penggaris dimensi</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-slate-300 font-mono mb-1 font-semibold">SKALA GAMBAR</label>
                            <select
                                v-model="sheetForm.scale"
                                class="w-full bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-violet-500 cursor-pointer font-mono [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="1:20" class="bg-slate-900 text-white">1:20 (Detail Khusus)</option>
                                <option value="1:50" class="bg-slate-900 text-white">1:50 (Detail Ruang)</option>
                                <option value="1:100" class="bg-slate-900 text-white">1:100 (Standar Denah)</option>
                                <option value="1:200" class="bg-slate-900 text-white">1:200 (Siteplan)</option>
                                <option value="1:500" class="bg-slate-900 text-white">1:500 (Masterplan)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-slate-300 font-mono mb-1 font-semibold">UKURAN KERTAS</label>
                            <select
                                v-model="sheetForm.paper_size"
                                class="w-full bg-slate-900 border border-white/10 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-1 focus:ring-violet-500 cursor-pointer font-mono [&>option]:bg-slate-900 [&>option]:text-white"
                            >
                                <option value="A4" class="bg-slate-900 text-white">A4 (297 × 210 mm)</option>
                                <option value="A3" class="bg-slate-900 text-white">A3 (420 × 297 mm)</option>
                                <option value="A2" class="bg-slate-900 text-white">A2 (594 × 420 mm)</option>
                                <option value="A1" class="bg-slate-900 text-white">A1 (841 × 594 mm)</option>
                                <option value="A0" class="bg-slate-900 text-white">A0 (1189 × 841 mm)</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-3 flex items-center justify-between border-t border-white/10">
                        <span class="text-[11px] text-slate-400">Tersimpan ke database lembar kerja</span>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="showSheetSettingsModal = false"
                                class="px-3.5 py-1.5 rounded-xl border border-white/10 text-slate-300 hover:text-white text-xs cursor-pointer hover:bg-white/5 transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="isSavingSheetSettings"
                                class="px-4 py-1.5 bg-violet-600 hover:bg-violet-500 text-white rounded-xl font-bold text-xs flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-lg shadow-violet-600/30 transition-all"
                            >
                                <Loader2 v-if="isSavingSheetSettings" class="w-3.5 h-3.5 animate-spin" />
                                <span>{{ isSavingSheetSettings ? 'Menyimpan...' : 'Simpan Pengaturan' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<style>
/* Custom sleek dark scrollbar for CAD toolbar and modals */
.custom-scroll, .cad-toolbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
.custom-scroll::-webkit-scrollbar, .cad-toolbar::-webkit-scrollbar {
    width: 3px;
    height: 3px;
}
.custom-scroll::-webkit-scrollbar-track, .cad-toolbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scroll::-webkit-scrollbar-thumb, .cad-toolbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.25);
    border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover, .cad-toolbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.45);
}

@media print {
    .cad-header, .cad-toolbar, .cad-bottom-hud, .cad-inspector, .inspector-panel, .layer-panel, .konva-overlay, .no-print {
        display: none !important;
    }
    body, html {
        background: #ffffff !important;
        color: #000000 !important;
    }
}
</style>
