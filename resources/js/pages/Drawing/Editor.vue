<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import Konva from 'konva';
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
    ChevronRight,
    ChevronLeft,
    RotateCcw,
    Printer,
    FileText,
} from '@lucide/vue';

interface SheetData {
    id: string;
    project_id: string;
    title: string;
    type: 'plan' | 'elevation' | 'section';
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

const props = defineProps<{
    sheet: SheetData;
    project: ProjectData;
    elements: ElementData[];
    projectSheets?: ProjectSheetItem[] | null;
    canEdit: boolean;
    architect?: ArchitectProfile | null;
}>();

// Tool state
type ToolType =
    | 'select'
    | 'pan'
    | 'wall'
    | 'rect'
    | 'door'
    | 'window'
    | 'column'
    | 'stair'
    | 'toilet'
    | 'bed'
    | 'table'
    | 'dimension'
    | 'room_label'
    | 'line'
    | 'eraser';

const activeTool = ref<ToolType>('select');

// Canvas container
const containerRef = ref<HTMLDivElement | null>(null);
let stage: Konva.Stage | null = null;
let gridLayer: Konva.Layer | null = null;
let paperLayer: Konva.Layer | null = null;
let mainLayer: Konva.Layer | null = null;
let tempLayer: Konva.Layer | null = null;
let tr: Konva.Transformer | null = null;
let selectionOverlay: Konva.Rect | null = null;

// Header scroll indicators & export menu
const headerContainerRef = ref<HTMLElement | null>(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const showExportMenu = ref(false);
const showExportModal = ref(false);
const isExporting = ref(false);

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

function removePaperFrame(frameId: string) {
    const idx = elementList.value.findIndex((el) => el.id === frameId && el.type === 'paper_frame');
    if (idx !== -1) {
        elementList.value.splice(idx, 1);
        drawPaperBoundary();
        zoomToFit();
        triggerAutoSaveDebounced();
        toast.success('Lembar kertas tambahan berhasil dihapus.');
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

// Local element memory list
const elementList = ref<Array<{ id: string; type: string; data: Record<string, any>; sort_order: number }>>([]);

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
    // If 2 or more nodes are selected, draw an individual dashed cyan outline and corner grips on EACH one!
    if (nodes.length > 1) {
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
        if (tr) tr.visible(false);
        drawSelectionHighlights();
        return;
    }

    const nodes = tr.nodes();

    // JIKA MULTI-SELEKSI (> 1 OBJEK):
    // Matikan transformer Konva secara TOTAL (visible: false) agar TIDAK ADA box ungu raksasa,
    // tidak ada handle resize, dan tidak ada handle rotasi di sekeliling ruangan!
    if (nodes.length > 1) {
        tr.visible(false);
        tr.borderStrokeWidth(0);
        tr.resizeEnabled(false);
        tr.rotateEnabled(false);
        tr.enabledAnchors([]);
        selectionOverlay.visible(false); // Hilangkan kotak overlay raksasa
    } else {
        // Seleksi tunggal: tampilkan transformer normal dengan handle resize & rotate
        tr.visible(true);
        tr.borderStrokeWidth(1.5);
        tr.borderStroke('#8b5cf6');
        tr.resizeEnabled(true);
        tr.rotateEnabled(true);
        tr.enabledAnchors(['top-left', 'top-center', 'top-right', 'middle-right', 'middle-left', 'bottom-left', 'bottom-center', 'bottom-right']);
        selectionOverlay.visible(false);
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
            return 'walls';
        case 'door':
        case 'window':
            return 'openings';
        case 'column':
        case 'stair':
            return 'structure';
        case 'toilet':
        case 'bed':
        case 'table':
        case 'dining_table':
            return 'furniture';
        case 'dimension':
            return 'dimensions';
        case 'room_label':
        case 'text':
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
    triggerAutoSaveDebounced();
}

function toggleOrientation() {
    orientation.value = orientation.value === 'landscape' ? 'portrait' : 'landscape';
    const temp = paperWidth.value;
    paperWidth.value = paperHeight.value;
    paperHeight.value = temp;
    drawPaperBoundary();
    triggerAutoSaveDebounced();
}

function onWidthInput(newVal: number) {
    if (newVal >= 300) {
        paperWidth.value = Math.round(newVal);
        paperSize.value = 'Custom';
        drawPaperBoundary();
        triggerAutoSaveDebounced();
    }
}

function onHeightInput(newVal: number) {
    if (newVal >= 300) {
        paperHeight.value = Math.round(newVal);
        paperSize.value = 'Custom';
        drawPaperBoundary();
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
    if (!mainLayer || !tr || !props.canEdit) return;
    activeTool.value = 'select';
    const allShapes = mainLayer.getChildren((node) => node !== tr && node !== selectionOverlay) as Konva.Group[];
    if (allShapes.length === 0) return;

    tr.nodes(allShapes);
    selectedNodeId.value = 'all';
    selectedElementData.value = {
        type: 'Semua Elemen Proyek (Multi-Seleksi)',
        count: allShapes.length,
    };
    updateSelectionOverlay();
    mainLayer.batchDraw();
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

// ── RAPATKAN LEMBAR KERJA KE GAMBAR (ELIMINASI SEMUA JARAK KOSONG) ─────────
function autoFitSheetToDrawing() {
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
    triggerAutoSaveDebounced();
    toast.success(`Ukuran "${targetFrame.title}" dikembalikan ke standar ISO ${preset} (${w}×${h} px).`);
}

// ── PUSATKAN TAMPILAN KANVAS (AUTOCAD ZOOM EXTENTS) ─────────────────────────
function zoomToFit() {
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
    if (!paperLayer) return;
    paperLayer.destroyChildren();

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
        paperLayer.add(ucsGroup);

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
            paperLayer.add(bgRect);

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
            paperLayer.add(badgeGroup);
        });

        paperLayer.batchDraw();
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

            // Tombol Hapus Frame Tambahan (jika bukan frame utama)
            if (!frame.isMain) {
                const delBtn = new Konva.Label({
                    x: Math.min(220, fw - 40),
                    y: 2,
                });
                delBtn.add(new Konva.Tag({
                    fill: '#dc2626',
                    cornerRadius: 4,
                }));
                delBtn.add(new Konva.Text({
                    text: '✕ Hapus',
                    fontSize: 9,
                    fontStyle: 'bold',
                    fill: '#ffffff',
                    padding: 3,
                }));
                delBtn.on('click tap', (e) => {
                    e.cancelBubble = true;
                    removePaperFrame(frame.id);
                });
                headerGroup.add(delBtn);
            }

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

            paperLayer.add(headerGroup);
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
        const kopW = Math.min(340, Math.max(200, fw - margin * 2 - 20));
        const kopH = Math.min(110, Math.max(80, fh - margin * 2 - 20));
        const kopX = fx + fw - margin - kopW;
        const kopY = fy + fh - margin - kopH;

        const kopRect = new Konva.Rect({
            x: kopX,
            y: kopY,
            width: kopW,
            height: kopH,
            stroke: '#0f172a',
            strokeWidth: 1.5,
            fill: '#f8fafc',
            listening: false,
        });

        const companyTitle = props.architect?.company_name
            ? `${props.architect?.company_type ? props.architect.company_type + ' ' : ''}${props.architect.company_name}`.toUpperCase()
            : 'STUDIO ARSITEKTUR';

        const kopStudio = new Konva.Text({
            x: kopX + 12,
            y: kopY + 12,
            text: companyTitle,
            fontSize: 12,
            fontStyle: 'bold',
            fontFamily: 'sans-serif',
            fill: '#4338ca',
            listening: false,
        });

        const kopTitle = new Konva.Text({
            x: kopX + 12,
            y: kopY + 30,
            text: frame.title,
            fontSize: 13,
            fontStyle: 'bold',
            fontFamily: 'sans-serif',
            fill: '#0f172a',
            listening: false,
        });

        const kopProject = new Konva.Text({
            x: kopX + 12,
            y: kopY + 50,
            text: `PROYEK: ${props.project.title}`,
            fontSize: 11,
            fontFamily: 'sans-serif',
            fill: '#475569',
            listening: false,
        });

        const todayDate = new Date().toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        const kopMeta = new Konva.Text({
            x: kopX + 12,
            y: kopY + 70,
            text: `SKALA: ${props.sheet.scale}   |   KERTAS: ${frame.size} (${fw}×${fh}px)\nTGL: ${todayDate}   |   KOTA: ${props.architect?.city_name || 'Indonesia'}`,
            fontSize: 10,
            fontFamily: 'monospace',
            fill: '#64748b',
            listening: false,
        });

        paperLayer.add(paperRect);
        paperLayer.add(frameRect);
        paperLayer.add(kopRect);
        paperLayer.add(kopStudio);
        paperLayer.add(kopTitle);
        paperLayer.add(kopProject);
        paperLayer.add(kopMeta);

        // Figma resize handle for main frame
        if (frame.isMain && props.canEdit) {
            const cornerGroup = new Konva.Group({
                x: fx + fw,
                y: fy + fh,
                draggable: true,
            });

            cornerGroup.add(new Konva.Circle({
                radius: 8,
                fill: '#6366f1',
                stroke: '#ffffff',
                strokeWidth: 2.5,
                shadowColor: '#6366f1',
                shadowBlur: 10,
                shadowOpacity: 0.6,
            }));

            cornerGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'nwse-resize'; });
            cornerGroup.on('mouseleave', () => { if (stage && !isResizingSheet.value && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
            cornerGroup.on('dragstart', () => { isResizingSheet.value = true; });
            cornerGroup.on('dragmove', () => {
                paperWidth.value = Math.max(400, Math.round(cornerGroup.x() - paperX.value));
                paperHeight.value = Math.max(300, Math.round(cornerGroup.y() - paperY.value));
                paperSize.value = 'Custom';
                drawPaperBoundary();
            });
            cornerGroup.on('dragend', () => {
                isResizingSheet.value = false;
                drawPaperBoundary();
                triggerAutoSaveDebounced();
            });

            paperLayer.add(cornerGroup);

            // Tepi Kanan
            const rightEdgeGroup = new Konva.Group({
                x: fx + fw,
                y: fy + fh / 2,
                draggable: true,
                dragBoundFunc: (pos) => ({
                    x: pos.x,
                    y: stage!.y() + (fy + fh / 2) * stage!.scaleY(),
                }),
            });
            rightEdgeGroup.add(new Konva.Rect({
                x: -3,
                y: -15,
                width: 6,
                height: 30,
                cornerRadius: 3,
                fill: '#818cf8',
                stroke: '#ffffff',
                strokeWidth: 1.5,
            }));
            rightEdgeGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'ew-resize'; });
            rightEdgeGroup.on('mouseleave', () => { if (stage && !isResizingSheet.value && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
            rightEdgeGroup.on('dragmove', () => {
                paperWidth.value = Math.max(400, Math.round(rightEdgeGroup.x() - paperX.value));
                paperSize.value = 'Custom';
                drawPaperBoundary();
            });
            rightEdgeGroup.on('dragend', () => {
                drawPaperBoundary();
                triggerAutoSaveDebounced();
            });
            paperLayer.add(rightEdgeGroup);

            // Tepi Bawah
            const bottomEdgeGroup = new Konva.Group({
                x: fx + fw / 2,
                y: fy + fh,
                draggable: true,
                dragBoundFunc: (pos) => ({
                    x: stage!.x() + (fx + fw / 2) * stage!.scaleX(),
                    y: pos.y,
                }),
            });
            bottomEdgeGroup.add(new Konva.Rect({
                x: -15,
                y: -3,
                width: 30,
                height: 6,
                cornerRadius: 3,
                fill: '#818cf8',
                stroke: '#ffffff',
                strokeWidth: 1.5,
            }));
            bottomEdgeGroup.on('mouseenter', () => { if (stage && !isSpacePressed.value) stage.container().style.cursor = 'ns-resize'; });
            bottomEdgeGroup.on('mouseleave', () => { if (stage && !isResizingSheet.value && !isSpacePressed.value) stage.container().style.cursor = 'default'; });
            bottomEdgeGroup.on('dragmove', () => {
                paperHeight.value = Math.max(300, Math.round(bottomEdgeGroup.y() - paperY.value));
                paperSize.value = 'Custom';
                drawPaperBoundary();
            });
            bottomEdgeGroup.on('dragend', () => {
                drawPaperBoundary();
                triggerAutoSaveDebounced();
            });
            paperLayer.add(bottomEdgeGroup);
        }
    });

    paperLayer.batchDraw();
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
            // Simbol Pintu Swing Arsitektur
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
            const distPx = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
            const distMeters = ((distPx / gridSpacing.value) * 0.5).toFixed(2);

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
                x: (x1 + x2) / 2 - 20,
                y: (y1 + y2) / 2 - 16,
                text: `${distMeters} m`,
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

            const bg = new Konva.Rect({
                x: x - 45,
                y: y - 18,
                width: 90,
                height: 36,
                fill: isCad ? 'rgba(15, 23, 42, 0.95)' : 'rgba(255, 255, 255, 0.9)',
                stroke: isCad ? '#38bdf8' : '#94a3b8',
                strokeWidth: 1.5,
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
            tr.update();
            drawSelectionHighlights();
            mainLayer?.batchDraw();
        }
    });

    group.on('dragend', (e) => {
        const totalDx = group.x();
        const totalDy = group.y();
        const isClone = isGroupCloneDrag || isCtrlHeld.value || isAltHeld.value || e.evt?.ctrlKey || e.evt?.altKey || e.evt?.metaKey;
        if (totalDx === 0 && totalDy === 0) return;

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

    paperLayer = new Konva.Layer();
    gridLayer = new Konva.Layer();
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
            tr.update();
            drawSelectionHighlights();
            mainLayer?.batchDraw();
        }
    });

    selectionOverlay.on('dragend', (e) => {
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

    stage.add(paperLayer);
    stage.add(gridLayer);
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

        let finalNodes = matchedNodes;
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
    if (curr && curr instanceof Konva.Group && curr !== (tr as any) && curr !== selectionOverlay) {
        return curr;
    }
    return null;
}

function setupDrawingInteractions() {
    if (!stage || !tempLayer || !mainLayer) return;

    stage.on('mousedown touchstart', (e) => {
        if (!props.canEdit || isSpacePressed.value) return;
        if (e.target.getLayer() === paperLayer || isResizingSheet.value) return;

        // Middle mouse click OR Pan Tool = start pan
        if (e.evt.button === 1 || activeTool.value === 'pan') {
            stage?.draggable(true);
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
                const pos = stage.getPointerPosition();
                if (pos) {
                    const hit = stage.getIntersection(pos);
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

            const pos = stage.getPointerPosition();
            if (pos) {
                const transform = stage.getAbsoluteTransform().copy().invert();
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
        const pos = stage.getPointerPosition();
        if (!pos) return;

        const transform = stage.getAbsoluteTransform().copy().invert();
        const pt = transform.point(pos);

        startX = snap(pt.x);
        startY = snap(pt.y);
        isDrawing = true;

        const isCad = canvasMode.value === 'infinite';
        if (activeTool.value === 'wall' || activeTool.value === 'line' || activeTool.value === 'dimension') {
            const previewStroke = activeTool.value === 'wall'
                ? (isCad ? '#f8fafc' : '#1e293b')
                : activeTool.value === 'dimension'
                    ? (isCad ? '#facc15' : '#d97706')
                    : (isCad ? '#f8fafc' : '#64748b');

            previewShape = new Konva.Line({
                points: [startX, startY, startX, startY],
                stroke: previewStroke,
                strokeWidth: activeTool.value === 'wall' ? 12 : 2,
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
        }
    });

    stage.on('mousemove touchmove', () => {
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
        }

        tempLayer?.batchDraw();
    });

    stage.on('mouseup touchend', (e) => {
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

        if (activeTool.value === 'wall' || activeTool.value === 'line' || activeTool.value === 'dimension') {
            if (startX !== endX || startY !== endY) {
                newElem = {
                    id,
                    type: activeTool.value,
                    data: { x1: startX, y1: startY, x2: endX, y2: endY },
                    sort_order: elementList.value.length,
                };
            }
        } else if (activeTool.value === 'rect') {
            const w = Math.abs(endX - startX);
            const h = Math.abs(endY - startY);
            if (w > 10 && h > 10) {
                newElem = {
                    id,
                    type: 'rect',
                    data: { x: Math.min(startX, endX), y: Math.min(startY, endY), width: w, height: h },
                    sort_order: elementList.value.length,
                };
            }
        } else if (activeTool.value === 'column') {
            newElem = { id, type: 'column', data: { x: startX, y: startY, size: 30 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'door') {
            newElem = { id, type: 'door', data: { x: startX, y: startY, size: 80 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'window') {
            newElem = { id, type: 'window', data: { x: startX, y: startY, width: 100, height: 16 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'stair') {
            newElem = { id, type: 'stair', data: { x: startX, y: startY, width: 80, height: 160 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'toilet') {
            newElem = { id, type: 'toilet', data: { x: startX, y: startY, width: 50, height: 45 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'bed') {
            newElem = { id, type: 'bed', data: { x: startX, y: startY, width: 120, height: 140 }, sort_order: elementList.value.length };
        } else if (activeTool.value === 'table') {
            newElem = { id, type: 'table', data: { x: startX, y: startY, width: 100, height: 70 }, sort_order: elementList.value.length };
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

    // Delete selected element
    if (e.key === 'Delete' || e.key === 'Backspace') {
        deleteSelected();
        return;
    }

    // Tool hotkeys
    switch (e.key.toLowerCase()) {
        case 'v': activeTool.value = 'select'; break;
        case 'h': activeTool.value = 'pan'; break;
        case 'z': if (!e.ctrlKey && !e.metaKey) { zoomToFit(); } break;
        case 'w': activeTool.value = 'wall'; break;
        case 'r': activeTool.value = 'rect'; break;
        case 'd': activeTool.value = 'door'; break;
        case 'j': activeTool.value = 'window'; break;
        case 'c': activeTool.value = 'column'; break;
        case 's': activeTool.value = 'stair'; break;
        case 'm': activeTool.value = 'dimension'; break;
        case 't': activeTool.value = 'room_label'; break;
        case 'l': activeTool.value = 'line'; break;
        case 'e': activeTool.value = 'eraser'; break;
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
        }
    } catch (err) {
        console.error('Error saving drawing elements:', err);
    } finally {
        isSaving.value = false;
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

function exportPng(exportPaperOnly = false) {
    if (!stage) return;
    showExportMenu.value = false;
    showExportModal.value = false;
    const oldNodes = tr?.nodes() || [];
    tr?.nodes([]);
    if (selectionOverlay) selectionOverlay.visible(false);
    const oldHighlights = tempLayer?.find('.individual-highlight') || [];
    oldHighlights.forEach((h) => h.destroy());
    tempLayer?.batchDraw();
    stage.batchDraw();

    try {
        let dataURL = '';
        if (exportPaperOnly && canvasMode.value === 'sheet') {
            const scale = stage.scaleX() || 1;
            const px = stage.x() + paperX.value * scale;
            const py = stage.y() + paperY.value * scale;
            const pw = paperWidth.value * scale;
            const ph = paperHeight.value * scale;
            const pixelRatio = Math.max(2, 2 / scale);

            dataURL = stage.toDataURL({
                x: px,
                y: py,
                width: pw,
                height: ph,
                pixelRatio,
            });
        } else {
            dataURL = stage.toDataURL({ pixelRatio: 2 });
        }

        const link = document.createElement('a');
        link.download = `${props.sheet.title || 'gambar_kerja'}.png`;
        link.href = dataURL;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toast.success('Gambar PNG resolusi tinggi berhasil diunduh!');
    } catch (err) {
        console.error('Export PNG failed:', err);
        toast.error('Gagal mengunduh gambar PNG.');
    } finally {
        if (oldNodes.length > 0 && tr) {
            tr.nodes(oldNodes);
            updateSelectionOverlay();
        }
        stage.batchDraw();
    }
}

async function exportWithSave(type: 'dxf' | 'svg') {
    isExporting.value = true;
    toast.info(`Mempersiapkan download file ${type.toUpperCase()}...`);
    try {
        if (props.canEdit) {
            await saveElements(true);
        }

        const url = type === 'dxf' ? `/drawings/${props.sheet.id}/export-dxf` : `/drawings/${props.sheet.id}/export-svg`;
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
        link.download = `${props.sheet.title || 'gambar_kerja'}.${type}`;
        document.body.appendChild(link);
        link.click();

        setTimeout(() => {
            if (link.parentNode) link.parentNode.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);
        }, 600);

        toast.success(`File ${type.toUpperCase()} berhasil diunduh!`);
    } catch (err) {
        console.error(`Export ${type} failed:`, err);
        // Fallback standard direct download
        const fallbackUrl = type === 'dxf' ? `/drawings/${props.sheet.id}/export-dxf` : `/drawings/${props.sheet.id}/export-svg`;
        const link = document.createElement('a');
        link.href = fallbackUrl;
        link.setAttribute('download', `${props.sheet.title || 'gambar_kerja'}.${type}`);
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

function exportPdfOrPrint() {
    showExportMenu.value = false;
    showExportModal.value = false;
    const oldNodes = tr?.nodes() || [];
    tr?.nodes([]);
    if (selectionOverlay) selectionOverlay.visible(false);
    const oldHighlights = tempLayer?.find('.individual-highlight') || [];
    oldHighlights.forEach((h) => h.destroy());
    tempLayer?.batchDraw();
    stage?.batchDraw();

    setTimeout(() => {
        window.print();
        if (oldNodes.length > 0 && tr) {
            tr.nodes(oldNodes);
            updateSelectionOverlay();
        }
        stage?.batchDraw();
    }, 250);
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

function checkHeaderScroll() {
    if (!headerContainerRef.value) return;
    const el = headerContainerRef.value;
    canScrollLeft.value = el.scrollLeft > 15;
    canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 15;
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
    nextTick(() => {
        initCanvas();
        checkHeaderScroll();
        setTimeout(() => {
            zoomToFit();
            checkHeaderScroll();
        }, 150);
    });

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
        checkHeaderScroll();
    };

    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
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
    <Head :title="`${sheet.title} — Lembar Kerja CAD 2D`" />

    <div class="h-screen w-screen flex flex-col bg-slate-900 text-slate-100 overflow-hidden select-none">
        <!-- TOP CAD HEADER BAR -->
        <header class="relative z-30 shrink-0 h-14 px-3 sm:px-4 flex items-center justify-between gap-2 sm:gap-3 bg-slate-950 border-b border-white/10">
            <!-- 1. Left Fixed Brand & Sheet Controls -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <Link
                    href="/drawings"
                    class="p-1.5 sm:p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-colors shrink-0"
                    title="Kembali ke Daftar Lembar Kerja"
                >
                    <ArrowLeft class="w-5 h-5" />
                </Link>

                <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-violet-500/20 text-violet-400 flex items-center justify-center border border-violet-500/30 shrink-0">
                        <DraftingCompass class="w-4 h-4" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <h2 class="text-xs sm:text-sm font-bold text-white tracking-tight truncate max-w-[110px] sm:max-w-[160px] md:max-w-xs">{{ sheet.title }}</h2>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-semibold bg-violet-500/20 text-violet-300 border border-violet-500/30 shrink-0">
                                {{ sheet.type.toUpperCase() }}
                            </span>

                            <!-- Sheet Switcher Dropdown (Pilih Lembar Kerja / Ruangan) -->
                            <div v-if="projectSheets && projectSheets.length > 0" class="relative inline-block ml-0.5 shrink-0">
                                <select
                                    :value="sheet.id"
                                    @change="switchSheet"
                                    class="bg-slate-900 border border-violet-500/40 text-violet-300 hover:text-white hover:border-violet-400 text-[11px] rounded-lg px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-violet-400 font-medium cursor-pointer transition-colors shadow-sm max-w-[130px] sm:max-w-[180px] truncate"
                                    title="Pilih Lembar Kerja Lainnya atau Buat Kertas Baru"
                                >
                                    <option
                                        v-for="(s, idx) in projectSheets"
                                        :key="s.id"
                                        :value="s.id"
                                        class="bg-slate-900 text-white py-1"
                                    >
                                        📄 Lembar {{ idx + 1 }}: {{ s.title }} ({{ s.scale }})
                                    </option>
                                    <option v-if="canEdit" value="__ADD_PAPER_FRAME__" class="bg-emerald-950 text-emerald-300 font-bold py-1">
                                        ➕ + Tambah Kertas ke Kanvas Ini
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
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[11px] font-semibold bg-violet-600/30 hover:bg-violet-600 text-violet-200 hover:text-white border border-violet-500/40 transition-all cursor-pointer shadow-sm shrink-0"
                                title="Buat Lembar Kerja / File Kertas Baru di Proyek Ini"
                            >
                                <Plus class="w-3 h-3" />
                                <span class="hidden sm:inline">Kertas Baru</span>
                            </button>

                            <!-- Tombol Tambah Kertas ke Kanvas Ini (Berdampingan sehingga 1 lembar kerja ada 2 kertas) -->
                            <button
                                v-if="canEdit"
                                @click="addPaperFrameToCanvas()"
                                type="button"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[11px] font-semibold bg-emerald-600/30 hover:bg-emerald-600 text-emerald-200 hover:text-white border border-emerald-500/40 transition-all cursor-pointer shadow-sm shrink-0"
                                title="Tambah Area Kertas Baru ke Kanvas Ini (Berdampingan sehingga dalam 1 lembar kerja ada 2 kertas atau lebih)"
                            >
                                <Plus class="w-3 h-3" />
                                <span>+ Kertas ke Kanvas</span>
                            </button>
                        </div>
                        <p class="text-[10px] sm:text-[11px] text-slate-400 -mt-0.5 hidden md:block truncate max-w-[200px] lg:max-w-sm">
                            {{ project.title }} • Skala {{ sheet.scale }} • Kertas {{ paperSize }} ({{ orientation }})
                        </p>
                    </div>
                </div>
            </div>

            <!-- 2. Middle Scrollable Frame & Canvas Controls -->
            <div class="relative flex-1 min-w-0 hidden lg:flex items-center">
                <!-- Scroll Indicator Left -->
                <button
                    v-if="canScrollLeft"
                    @click="scrollHeader('left')"
                    type="button"
                    class="absolute -left-1 top-1/2 -translate-y-1/2 z-20 p-1 rounded-lg bg-slate-800/95 hover:bg-slate-700 text-white shadow-lg border border-white/15 backdrop-blur-md transition-all cursor-pointer flex items-center"
                    title="Geser melihat tools di sebelah kiri"
                >
                    <ChevronLeft class="w-3.5 h-3.5" />
                </button>

                <!-- Scrollable Tools Wrapper -->
                <div
                    ref="headerContainerRef"
                    @scroll="checkHeaderScroll"
                    class="w-full flex items-center justify-center gap-1.5 xl:gap-2.5 overflow-x-auto scrollbar-none py-1 px-2"
                >
                    <!-- DYNAMIC FRAME & CANVAS CONTROLS (FIGMA RESIZABLE SHEET & AUTOCAD MODEL SPACE) -->
                    <div class="flex items-center gap-1.5 xl:gap-2.5 bg-slate-900 border border-white/10 rounded-2xl px-2.5 py-1 shrink-0">
                        <!-- Mode Switch: Frame Lembar Kerja vs AutoCAD Model Space -->
                        <div class="flex items-center bg-white/5 p-0.5 rounded-xl border border-white/10 shrink-0">
                            <button
                                @click="setCanvasMode('sheet')"
                                type="button"
                                :class="[
                                    'flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium transition-all cursor-pointer',
                                    canvasMode === 'sheet'
                                        ? 'bg-violet-600 text-white shadow-sm font-semibold'
                                        : 'text-slate-400 hover:text-white'
                                ]"
                                title="Mode Frame Lembar Kerja Dinamis (Figma & Paper Space)"
                            >
                                <LayoutTemplate class="w-3.5 h-3.5" />
                                <span><span class="hidden xl:inline">Frame </span>Kertas</span>
                            </button>
                            <button
                                @click="setCanvasMode('infinite')"
                                type="button"
                                :class="[
                                    'flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium transition-all cursor-pointer',
                                    canvasMode === 'infinite'
                                        ? 'bg-violet-600 text-white shadow-sm font-semibold'
                                        : 'text-slate-400 hover:text-white'
                                ]"
                                title="Mode Kanvas Tak Terbatas (AutoCAD Model Space ∞)"
                            >
                                <InfinityIcon class="w-3.5 h-3.5" />
                                <span><span class="hidden xl:inline">AutoCAD </span>Model<span class="hidden xl:inline"> (∞)</span></span>
                            </button>
                        </div>

                        <!-- Paper Frame Controls (Aktif saat mode Frame Kertas) -->
                        <template v-if="canvasMode === 'sheet'">
                            <div class="h-4 w-px bg-white/10 shrink-0"></div>

                            <!-- Preset Ukuran Kertas ISO -->
                            <select
                                :value="paperSize"
                                @change="applyPaperPreset(($event.target as HTMLSelectElement).value)"
                                class="bg-white/5 border border-white/10 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-violet-500 cursor-pointer shrink-0"
                                title="Preset Ukuran Kertas Standar ISO"
                            >
                                <option value="A0" class="bg-slate-900 text-white">A0 (3564 × 2520)</option>
                                <option value="A1" class="bg-slate-900 text-white">A1 (2520 × 1782)</option>
                                <option value="A2" class="bg-slate-900 text-white">A2 (1782 × 1260)</option>
                                <option value="A3" class="bg-slate-900 text-white">A3 (1260 × 891)</option>
                                <option value="A4" class="bg-slate-900 text-white">A4 (891 × 630)</option>
                                <option value="Custom" class="bg-slate-900 text-white">Custom (Dinamis)</option>
                            </select>

                            <!-- Numeric Input Width (W) & Height (H) ala Figma -->
                            <div class="hidden 2xl:flex items-center gap-1.5 text-xs font-mono shrink-0">
                                <span class="text-slate-400 text-[10px]">W:</span>
                                <input
                                    type="number"
                                    :value="paperWidth"
                                    @change="onWidthInput(Number(($event.target as HTMLInputElement).value))"
                                    class="w-16 bg-white/5 border border-white/10 rounded-lg px-1.5 py-1 text-xs text-white text-center focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
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
                                    class="w-16 bg-white/5 border border-white/10 rounded-lg px-1.5 py-1 text-xs text-white text-center focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                                    min="300"
                                    max="20000"
                                    step="50"
                                    title="Tinggi Kertas Dinamis (px)"
                                />
                            </div>

                            <!-- Putar Orientasi (Landscape / Portrait) -->
                            <button
                                @click="toggleOrientation"
                                type="button"
                                class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
                                :title="`Putar Orientasi (${orientation === 'landscape' ? 'Landscape ke Portrait' : 'Portrait ke Landscape'})`"
                            >
                                <RotateCw class="w-3.5 h-3.5" />
                            </button>
                        </template>

                        <div class="h-4 w-px bg-white/10 shrink-0"></div>

                        <!-- 3 TOMBOL KONTROL UTAMA: RAPATKAN, RESET ISO, PUSATKAN (TERSEDIA DI SEMUA MODE) -->
                        <!-- 1. Rapatkan Lembar ke Gambar (Hilangkan Jarak Kosong) -->
                        <button
                            @click="autoFitSheetToDrawing"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-500/15 hover:bg-indigo-500/30 text-indigo-300 hover:text-white border border-indigo-500/30 transition-all cursor-pointer shrink-0 shadow-sm"
                            title="Rapatkan Lembar Kerja ke Gambar (Hilangkan Semua Jarak Kosong)"
                        >
                            <Sparkles class="w-3.5 h-3.5 text-indigo-400" />
                            <span class="hidden sm:inline">Rapatkan Kertas</span>
                        </button>

                        <!-- 2. Kembalikan Ukuran Kertas ke Standar ISO -->
                        <button
                            @click="resetPaperToDefault"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-500/15 hover:bg-amber-500/30 text-amber-300 hover:text-white border border-amber-500/30 transition-all cursor-pointer shrink-0 shadow-sm"
                            title="Kembalikan ukuran kertas ke standar ISO awal (A3 / Preset)"
                        >
                            <RotateCcw class="w-3.5 h-3.5 text-amber-400" />
                            <span class="hidden sm:inline">Reset ISO</span>
                        </button>

                        <!-- 3. Pusatkan Tampilan (AutoCAD Zoom Extents) -->
                        <button
                            @click="zoomToFit"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-sky-500/15 hover:bg-sky-500/30 text-sky-300 hover:text-white border border-sky-500/30 transition-all cursor-pointer shrink-0 shadow-sm"
                            title="Pusatkan Seluruh Gambar & Kertas ke Layar (AutoCAD Zoom Extents / Z)"
                        >
                            <Focus class="w-3.5 h-3.5 text-sky-400" />
                            <span class="hidden sm:inline">Pusatkan</span>
                        </button>
                    </div>
                </div>

                <!-- Scroll Indicator Right -->
                <button
                    v-if="canScrollRight"
                    @click="scrollHeader('right')"
                    type="button"
                    class="absolute -right-1 top-1/2 -translate-y-1/2 z-20 p-1 rounded-lg bg-violet-600/95 hover:bg-violet-600 text-white shadow-lg border border-violet-400/40 backdrop-blur-md transition-all cursor-pointer flex items-center animate-pulse"
                    title="Masih ada tools lainnya — Klik untuk geser"
                >
                    <ChevronRight class="w-3.5 h-3.5" />
                </button>
            </div>

            <!-- 3. Right Fixed Actions Section (Unclipped & Always Accessible) -->
            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                <!-- Undo / Redo Buttons -->
                <div class="flex items-center bg-white/5 border border-white/10 rounded-xl p-0.5 shrink-0">
                    <button
                        @click="undo"
                        :disabled="historyIndex <= 0"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors cursor-pointer"
                        title="Urungkan / Undo (Ctrl+Z)"
                    >
                        <Undo2 class="w-4 h-4" />
                    </button>
                    <button
                        @click="redo"
                        :disabled="historyIndex >= historyStack.length - 1"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors cursor-pointer"
                        title="Ulangi / Redo (Ctrl+Y)"
                    >
                        <Redo2 class="w-4 h-4" />
                    </button>
                </div>

                <!-- Copy / Paste / Duplicate Button Group -->
                <div v-if="canEdit" class="hidden md:flex items-center bg-white/5 border border-white/10 rounded-xl p-0.5 shrink-0">
                    <button
                        @click="copySelected"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                        title="Salin Objek Terpilih (Ctrl+C)"
                    >
                        <Copy class="w-4 h-4" />
                    </button>
                    <button
                        @click="pasteClipboard"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                        title="Tempel Objek dari Clipboard (Ctrl+V)"
                    >
                        <Clipboard class="w-4 h-4" />
                    </button>
                    <button
                        @click="duplicateSelected"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                        title="Duplikat Cepat Objek (Ctrl+D)"
                    >
                        <Files class="w-4 h-4" />
                    </button>
                </div>

                <!-- Layers Panel Button -->
                <button
                    @click="showLayerPanel = !showLayerPanel"
                    :class="[
                        'inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-semibold border transition-colors cursor-pointer shrink-0',
                        showLayerPanel
                            ? 'bg-violet-600/20 text-violet-300 border-violet-500/40'
                            : 'bg-white/5 text-slate-300 border-white/10 hover:bg-white/10'
                    ]"
                    title="Buka Panel Layer Arsitektur"
                >
                    <Layers class="w-3.5 h-3.5" />
                    <span class="hidden sm:inline">Layer</span>
                </button>

                <!-- Help Button -->
                <button
                    @click="showHelpModal = true"
                    class="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
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
                        'inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer shadow-sm shrink-0',
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

                <!-- Compact Export Dropdown Menu (DXF, SVG, PNG, PDF) -->
                <div class="relative export-menu-container shrink-0">
                    <button
                        @click="showExportMenu = !showExportMenu"
                        class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-xl text-xs font-semibold bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition-colors cursor-pointer shrink-0"
                        title="Pilihan Download & Export Gambar CAD"
                    >
                        <Download class="w-3.5 h-3.5 text-sky-400" />
                        <span class="hidden sm:inline">Export</span>
                        <ChevronDown class="w-3 h-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': showExportMenu }" />
                    </button>

                    <div
                        v-if="showExportMenu"
                        class="absolute right-0 top-full mt-2 w-64 bg-slate-950 border border-white/15 rounded-2xl shadow-2xl p-2 z-50 flex flex-col gap-1 text-xs text-slate-200 backdrop-blur-xl animate-in fade-in zoom-in-95 duration-150"
                    >
                        <div class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-white/10 mb-1">
                            Ekspor Lembar Kerja
                        </div>

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

                        <button
                            @click="exportPng(false)"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group"
                            title="Download sebagai gambar PNG resolusi tinggi"
                        >
                            <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-400 flex items-center justify-center border border-emerald-500/25 shrink-0 group-hover:bg-emerald-500/25">
                                <Sparkles class="w-3.5 h-3.5" />
                            </div>
                            <div>
                                <div class="font-semibold text-white">Gambar (.PNG)</div>
                                <div class="text-[10px] text-slate-400">Snapshot resolusi tinggi 2×</div>
                            </div>
                        </button>

                        <button
                            @click="exportPdfOrPrint"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-xl hover:bg-white/10 transition-colors text-left w-full cursor-pointer group"
                            title="Cetak atau Simpan sebagai PDF"
                        >
                            <div class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-400 flex items-center justify-center border border-indigo-500/25 shrink-0 group-hover:bg-indigo-500/25">
                                <Printer class="w-3.5 h-3.5" />
                            </div>
                            <div>
                                <div class="font-semibold text-white">Cetak / PDF</div>
                                <div class="text-[10px] text-slate-400">Simpan ke PDF via Print Dialog</div>
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
        </header>

        <!-- WORKSPACE BODY (TOOLBAR + CANVAS + INSPECTOR + LAYER PANEL) -->
        <div class="flex-1 flex relative overflow-hidden">
            <!-- LEFT FLOATING CAD TOOLBAR -->
            <div class="absolute left-4 top-4 z-20 flex flex-col gap-1 p-2 rounded-2xl bg-slate-950/95 backdrop-blur-md border border-white/10 shadow-2xl max-h-[85vh] overflow-y-auto custom-scroll">
                <!-- Select Tool -->
                <button
                    @click="activeTool = 'select'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'select' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Pilih & Pindahkan Objek / Select (V)"
                >
                    <MousePointer class="w-4 h-4" />
                </button>

                <!-- Pan / Hand Tool -->
                <button
                    @click="activeTool = 'pan'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'pan' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Geser Kanvas Bebas / Pan (H)"
                >
                    <Hand class="w-4 h-4 text-emerald-400" />
                </button>

                <!-- Select All / Move Entire Project -->
                <button
                    @click="selectAllElements"
                    class="p-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all cursor-pointer relative group"
                    title="Pilih Semua & Geser Seluruh Proyek (Ctrl+A)"
                >
                    <Move class="w-4 h-4 text-amber-400" />
                </button>

                <!-- Zoom to Fit / Extents -->
                <button
                    @click="zoomToFit"
                    class="p-2.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/5 transition-all cursor-pointer relative group"
                    title="Pusatkan Gambar ke Layar / Zoom Extents (Z)"
                >
                    <Focus class="w-4 h-4 text-sky-400" />
                </button>

                <!-- Auto Fit Sheet to Drawing -->
                <button
                    v-if="canvasMode === 'sheet'"
                    @click="autoFitSheetToDrawing"
                    class="p-2.5 rounded-xl text-indigo-400 hover:text-white hover:bg-indigo-600/20 transition-all cursor-pointer relative group"
                    title="Rapatkan Lembar Kerja ke Gambar (Hilangkan Jarak Kosong)"
                >
                    <Sparkles class="w-4 h-4 text-indigo-400" />
                </button>

                <div class="h-px w-full bg-white/10 my-0.5"></div>

                <!-- Wall Tool -->
                <button
                    @click="activeTool = 'wall'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'wall' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Dinding Tebal / Wall (W)"
                >
                    <Columns class="w-4 h-4" />
                </button>

                <!-- Room / Rect Tool -->
                <button
                    @click="activeTool = 'rect'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'rect' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Kotak Ruangan / Room Rect (R)"
                >
                    <Square class="w-4 h-4" />
                </button>

                <!-- Column Tool -->
                <button
                    @click="activeTool = 'column'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'column' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Kolom Struktur Beton (C)"
                >
                    <Building2 class="w-4 h-4 text-purple-400" />
                </button>

                <!-- Door Symbol -->
                <button
                    @click="activeTool = 'door'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'door' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Simbol Pintu Swing (D)"
                >
                    <DoorClosed class="w-4 h-4 text-emerald-400" />
                </button>

                <!-- Window Symbol -->
                <button
                    @click="activeTool = 'window'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'window' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Bukaan Jendela (J)"
                >
                    <Columns class="w-4 h-4 text-sky-400 rotate-90" />
                </button>

                <!-- Stair Symbol -->
                <button
                    @click="activeTool = 'stair'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'stair' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Tangga Arsitektur 2D (S)"
                >
                    <Move class="w-4 h-4 text-indigo-400 rotate-45" />
                </button>

                <div class="h-px w-full bg-white/10 my-0.5"></div>

                <!-- Sanitair: Toilet -->
                <button
                    @click="activeTool = 'toilet'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'toilet' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Kloset Duduk / Toilet (WC)"
                >
                    <Bath class="w-4 h-4 text-cyan-400" />
                </button>

                <!-- Furniture: Bed -->
                <button
                    @click="activeTool = 'bed'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'bed' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Tempat Tidur / Bed"
                >
                    <Bed class="w-4 h-4 text-amber-300" />
                </button>

                <!-- Furniture: Table -->
                <button
                    @click="activeTool = 'table'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'table' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Meja Makan / Table"
                >
                    <Armchair class="w-4 h-4 text-rose-300" />
                </button>

                <div class="h-px w-full bg-white/10 my-0.5"></div>

                <!-- Dimension Tool -->
                <button
                    @click="activeTool = 'dimension'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'dimension' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Penggaris Ukuran / Dimensi (M)"
                >
                    <Ruler class="w-4 h-4 text-amber-400" />
                </button>

                <!-- Room Label Tool -->
                <button
                    @click="activeTool = 'room_label'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'room_label' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Label Ruangan / Text (T)"
                >
                    <Type class="w-4 h-4" />
                </button>

                <!-- Line Tool -->
                <button
                    @click="activeTool = 'line'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'line' ? 'bg-violet-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-white/5'
                    ]"
                    title="Garis Bebas / Line (L)"
                >
                    <Minus class="w-4 h-4" />
                </button>

                <div class="h-px w-full bg-white/10 my-0.5"></div>

                <!-- Eraser Tool -->
                <button
                    @click="activeTool = 'eraser'"
                    :class="[
                        'p-2.5 rounded-xl transition-all cursor-pointer relative group',
                        activeTool === 'eraser' ? 'bg-red-600 text-white shadow-md' : 'text-slate-400 hover:text-red-400 hover:bg-white/5'
                    ]"
                    title="Penghapus Elemen (E)"
                >
                    <Trash2 class="w-4 h-4" />
                </button>
            </div>

            <!-- FLOATING LAYER VISIBILITY MANAGER -->
            <div
                v-if="showLayerPanel"
                class="absolute left-20 top-4 z-20 w-60 p-4 rounded-2xl bg-slate-950/95 backdrop-blur-md border border-white/10 shadow-2xl space-y-3"
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

            <!-- BOTTOM CANVAS VIEWPORT CONTROLS -->
            <div class="absolute left-1/2 -translate-x-1/2 bottom-4 z-20 flex items-center gap-2 px-3 py-2 rounded-2xl bg-slate-950/90 backdrop-blur-md border border-white/10 shadow-2xl text-xs">
                <!-- Snap to grid toggle -->
                <button
                    @click="isSnapToGrid = !isSnapToGrid"
                    :class="[
                        'flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl font-medium transition-colors cursor-pointer',
                        isSnapToGrid ? 'bg-violet-500/20 text-violet-300 border border-violet-500/30' : 'text-slate-400 hover:text-white'
                    ]"
                    title="Snap to Grid"
                >
                    <Grid class="w-3.5 h-3.5" />
                    <span>Snap Grid ({{ gridSpacing }}px)</span>
                </button>

                <div class="h-4 w-px bg-white/10"></div>

                <!-- Zoom buttons -->
                <button
                    @click="zoom('out')"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                    title="Zoom Out"
                >
                    <ZoomOut class="w-4 h-4" />
                </button>

                <span class="font-mono text-slate-300 w-12 text-center text-[11px]">
                    {{ Math.round(zoomLevel * 100) }}%
                </span>

                <button
                    @click="zoom('in')"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                    title="Zoom In"
                >
                    <ZoomIn class="w-4 h-4" />
                </button>

                <button
                    @click="zoom('reset')"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer"
                    title="Reset Zoom 100%"
                >
                    <Maximize class="w-4 h-4" />
                </button>

                <div class="h-4 w-px bg-white/10"></div>

                <!-- 3 Tombol Utama di Bottom Bar -->
                <button
                    @click="autoFitSheetToDrawing"
                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold bg-indigo-500/15 hover:bg-indigo-500/30 text-indigo-300 hover:text-white border border-indigo-500/30 transition-all cursor-pointer shadow-sm"
                    title="Rapatkan Kertas ke Gambar (Hilangkan Jarak Kosong)"
                >
                    <Sparkles class="w-3.5 h-3.5 text-indigo-400" />
                    <span>Rapatkan</span>
                </button>

                <button
                    @click="resetPaperToDefault"
                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold bg-amber-500/15 hover:bg-amber-500/30 text-amber-300 hover:text-white border border-amber-500/30 transition-all cursor-pointer shadow-sm"
                    title="Reset Kertas ke Standar ISO (A3)"
                >
                    <RotateCcw class="w-3.5 h-3.5 text-amber-400" />
                    <span>Reset ISO</span>
                </button>

                <button
                    @click="zoomToFit"
                    class="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold bg-sky-500/15 hover:bg-sky-500/30 text-sky-300 hover:text-white border border-sky-500/30 transition-all cursor-pointer shadow-sm"
                    title="Pusatkan Gambar ke Layar / Zoom Extents (Z)"
                >
                    <Focus class="w-3.5 h-3.5 text-sky-400" />
                    <span>Pusatkan</span>
                </button>

                <div class="h-4 w-px bg-white/10"></div>

                <!-- Dynamic Sheet Dimensions or Model Space Badge -->
                <span v-if="canvasMode === 'sheet'" class="text-[10px] text-slate-400 flex items-center gap-1.5">
                    <span class="px-1.5 py-0.5 rounded bg-violet-500/20 text-violet-300 font-mono text-[9px] border border-violet-500/30">
                        {{ paperSize }} • {{ paperWidth }}×{{ paperHeight }}px
                    </span>
                    <span class="hidden sm:inline text-slate-400">Tarik sudut lembar untuk ubah ukuran (Figma)</span>
                </span>
                <span v-else class="text-[10px] text-indigo-300 flex items-center gap-1 font-mono">
                    <span class="px-1.5 py-0.5 rounded bg-indigo-500/20 border border-indigo-500/30">AutoCAD Model Space (∞)</span>
                </span>

                <div class="h-4 w-px bg-white/10"></div>

                <span class="text-[10px] text-slate-400 flex items-center gap-1">
                    <span class="px-1.5 py-0.5 rounded bg-white/10 font-mono text-[9px]">Spasi+Drag</span>
                    <span>Geser Kanvas</span>
                </span>
            </div>

            <!-- RIGHT SELECTION PROPERTY INSPECTOR -->
            <div
                v-if="selectedNodeId"
                class="absolute right-4 top-4 z-20 w-64 p-4 rounded-2xl bg-slate-950/90 backdrop-blur-md border border-white/10 shadow-2xl space-y-4"
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
                    <div v-if="selectedElementData?.text" class="space-y-1 pt-1">
                        <span class="text-slate-400 block">Teks Label:</span>
                        <textarea
                            v-model="selectedElementData.text"
                            @input="triggerAutoSaveDebounced"
                            class="w-full text-xs p-2 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-1 focus:ring-violet-500"
                            rows="2"
                        ></textarea>
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
                <button
                    v-for="(f, idx) in canvasPaperFrames"
                    :key="f.id"
                    @click="focusOnFrame(f)"
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-1 rounded-t-lg font-mono text-[11px] border-b-2 transition-all cursor-pointer shrink-0 max-w-[170px] truncate',
                        canvasMode === 'sheet' && activeFocusedFrameId === f.id
                            ? 'bg-white/10 text-white font-bold border-emerald-400'
                            : 'text-slate-400 hover:text-slate-200 border-transparent hover:bg-white/5'
                    ]"
                    :title="`Fokus ke ${f.title} (${f.width}×${f.height}px)`"
                >
                    <LayoutTemplate class="w-3 h-3 text-emerald-400 shrink-0" />
                    <span class="truncate">{{ f.title }}</span>
                </button>

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
                <span>Unit: <strong class="text-slate-200">{{ sheet.unit }}</strong></span>
                <span>Skala: <strong class="text-slate-200">{{ sheet.scale }}</strong></span>
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
                            <span class="text-violet-400 font-bold">Ctrl+Y</span>
                        </div>
                    </div>

                    <div class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider pt-2">Pintasan Alat Gambar (Drawing Tools)</div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 font-mono">
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pilih (Select)</span>
                            <span class="text-violet-400 font-bold">V</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Dinding Tebal</span>
                            <span class="text-violet-400 font-bold">W</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Ruangan Kotak</span>
                            <span class="text-violet-400 font-bold">R</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Pintu Swing</span>
                            <span class="text-violet-400 font-bold">D</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Jendela</span>
                            <span class="text-violet-400 font-bold">J</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Kolom Beton</span>
                            <span class="text-violet-400 font-bold">C</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Tangga 2D</span>
                            <span class="text-violet-400 font-bold">S</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Dimensi Ukuran</span>
                            <span class="text-violet-400 font-bold">M</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Teks / Label</span>
                            <span class="text-violet-400 font-bold">T</span>
                        </div>
                        <div class="p-2 rounded-xl bg-white/5 flex justify-between">
                            <span class="text-slate-400">Penghapus</span>
                            <span class="text-violet-400 font-bold">E</span>
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
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:ring-1 focus:ring-violet-400 cursor-pointer"
                            >
                                <option value="1:50">1:50 (Detail)</option>
                                <option value="1:100">1:100 (Standar)</option>
                                <option value="1:200">1:200 (Site)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-300 mb-1">
                                Ukuran Kertas
                            </label>
                            <select
                                v-model="newSheetForm.paper_size"
                                class="w-full px-3 py-2 rounded-xl bg-slate-900 border border-white/10 text-xs text-white focus:outline-none focus:ring-1 focus:ring-violet-400 cursor-pointer"
                            >
                                <option value="A0">A0</option>
                                <option value="A1">A1</option>
                                <option value="A2">A2</option>
                                <option value="A3">A3</option>
                                <option value="A4">A4</option>
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
                    <!-- Sheet Metadata Summary -->
                    <div class="p-3.5 rounded-2xl bg-white/5 border border-white/10 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div>
                            <span class="text-slate-400">Skala:</span>
                            <span class="text-white font-semibold ml-1">{{ sheet.scale }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Ukuran Kertas:</span>
                            <span class="text-white font-semibold ml-1">{{ paperSize }} ({{ orientation }})</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Dimensi:</span>
                            <span class="text-white font-semibold ml-1">{{ paperWidth }} × {{ paperHeight }} px</span>
                        </div>
                        <div>
                            <span class="text-slate-400">Objek:</span>
                            <span class="text-white font-semibold ml-1">{{ elementList.length }} Elemen</span>
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
                                @click="exportWithSave('dxf')"
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
                                @click="exportWithSave('svg')"
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
                                    @click="exportPng(true)"
                                    class="flex-1 py-2 px-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 hover:text-white font-semibold border border-emerald-500/30 transition-all flex items-center justify-center gap-1 cursor-pointer text-[11px]"
                                    title="Unduh area frame kertas saja"
                                >
                                    <Sparkles class="w-3.5 h-3.5" />
                                    <span>Kertas Saja</span>
                                </button>
                                <button
                                    @click="exportPng(false)"
                                    class="flex-1 py-2 px-2 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white font-semibold border border-white/10 transition-all flex items-center justify-center gap-1 cursor-pointer text-[11px]"
                                    title="Unduh seluruh kanvas saat ini"
                                >
                                    <span>Seluruh Kanvas</span>
                                </button>
                            </div>
                        </div>

                        <!-- 4. Print / PDF Option -->
                        <div class="p-4 rounded-2xl bg-slate-900 border border-white/10 hover:border-indigo-500/40 transition-all flex flex-col justify-between gap-3 group">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-500/15 text-indigo-400 flex items-center justify-center border border-indigo-500/25">
                                        <Printer class="w-3.5 h-3.5" />
                                    </div>
                                    <span class="font-bold text-white text-xs">Cetak & PDF</span>
                                </div>
                                <p class="text-[11px] text-slate-400 leading-relaxed">
                                    Buka dialog cetak browser untuk langsung mencetak lembar kerja ke printer atau simpan sebagai file PDF.
                                </p>
                            </div>
                            <button
                                @click="exportPdfOrPrint"
                                class="w-full py-2 px-3 rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-300 hover:text-white font-semibold border border-indigo-500/30 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Printer class="w-3.5 h-3.5" />
                                <span>Cetak / Simpan PDF</span>
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
    </div>
</template>

<style>
@media print {
    header, footer, .cad-toolbar, .inspector-panel, .layer-panel, .konva-overlay {
        display: none !important;
    }
    body, html {
        background: #ffffff !important;
        color: #000000 !important;
    }
}
</style>
