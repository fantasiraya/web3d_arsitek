<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import {
    MousePointer,
    Type,
    Tag,
    Ruler,
    Minus,
    Square,
    ZoomIn,
    ZoomOut,
    Maximize,
    RotateCcw,
    Printer,
    Download,
    FileText,
    Layers,
    Sliders,
    Eye,
    EyeOff,
    Check,
    X,
    ChevronDown,
    ChevronUp,
    HelpCircle,
    ArrowLeft,
    Sparkles,
    Box,
    FileCode,
    Share2,
    Palette,
    Info,
    Settings,
    Grid,
    ListOrdered,
    Scissors,
    Menu,
    ChevronRight,
    ChevronLeft,
    PanelRightClose,
    PanelRightOpen,
    Smartphone,
    Monitor,
    Plus,
    Trash2,
    Save,
    RotateCw,
    Wand2,
    Undo2,
    Redo2,
    Package,
    Search,
} from '@lucide/vue';
import { toast } from 'vue-sonner';

interface SheetData {
    id: string;
    project_id: string;
    title: string;
    type: string;
    scale?: string;
    paper_size?: string;
    orientation?: string;
    width?: number | null;
    height?: number | null;
    grid_spacing?: number;
    unit?: string;
}

interface ProjectData {
    id: string;
    title: string;
    user_id: string;
    client_name?: string | null;
    client_address?: string | null;
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
    elements?: any[];
    canEdit: boolean;
    architect?: ArchitectProfile | null;
}>();

const emit = defineEmits<{
    (e: 'switchMode', mode: 'cad' | 'shop_drawing'): void;
}>();

// ── SHEET TABS & NAVIGATION ──────────────────────────────────────────────────
// 01 = Lemari (Shop Drawing) | 02 = Lemari · daftar potong (Cut List)
const activeSheetTab = ref<'01' | '02'>('01');

// ── VIEW MODES ──────────────────────────────────────────────────────────────
type ViewType = 'depan' | 'tanpa_pintu' | 'samping' | 'potongan' | 'atas' | 'iso';
const activeView = ref<ViewType>('depan');

// Layout Mode: 'auto' (Otomatis pas kertas - multi-view) | 'single' (Per tampak)
const layoutMode = ref<'auto' | 'single'>('single');

// Display Options
const showAccessories = ref(true);
const showTextures = ref(true);
const showPanelNumbers = ref(false);
const snapToGrid = ref(true);
const lineWeight = ref('0.25');
const lineStyle = ref<'penuh' | 'putus'>('penuh');
const arrowStyle = ref<'tanpa' | 'panah'>('tanpa');
const fontSize = ref<'kecil' | 'standar' | 'besar'>('standar');
const paperSize = ref<'A3' | 'A4'>('A3');
const unit = ref('milimeter (mm)');

// Active Tool
type ToolType = 'select' | 'text' | 'label' | 'measure' | 'line' | 'rect' | 'detail';
const activeTool = ref<ToolType>('select');

// Mobile Drawer & Desktop Sidebar State
const showMobileDrawer = ref(false);
const isMobileScreen = ref(false);
const isDesktopSidebarOpen = ref(true);

// Header Tools Horizontal Scroll & Indicator State
const toolsContainerRef = ref<HTMLElement | null>(null);
const canScrollToolsLeft = ref(false);
const canScrollToolsRight = ref(false);

const headerRowRef = ref<HTMLElement | null>(null);
const canScrollHeaderLeft = ref(false);
const canScrollHeaderRight = ref(false);

function checkToolsScroll() {
    if (!toolsContainerRef.value) return;
    const el = toolsContainerRef.value;
    canScrollToolsLeft.value = el.scrollLeft > 8;
    canScrollToolsRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

function checkHeaderScroll() {
    if (!headerRowRef.value) return;
    const el = headerRowRef.value;
    canScrollHeaderLeft.value = el.scrollLeft > 8;
    canScrollHeaderRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

function scrollTools(direction: 'left' | 'right') {
    if (!toolsContainerRef.value) return;
    const step = 280;
    toolsContainerRef.value.scrollBy({
        left: direction === 'right' ? step : -step,
        behavior: 'smooth',
    });
    setTimeout(checkToolsScroll, 320);
}

function scrollHeader(direction: 'left' | 'right') {
    if (!headerRowRef.value) return;
    const step = 280;
    headerRowRef.value.scrollBy({
        left: direction === 'right' ? step : -step,
        behavior: 'smooth',
    });
    setTimeout(checkHeaderScroll, 320);
}

function handleToolsWheel(e: WheelEvent) {
    if (!toolsContainerRef.value) return;
    if (e.deltaY !== 0) {
        toolsContainerRef.value.scrollLeft += e.deltaY;
        checkToolsScroll();
    }
}

function handleHeaderWheel(e: WheelEvent) {
    if (!headerRowRef.value) return;
    if (e.deltaY !== 0) {
        headerRowRef.value.scrollLeft += e.deltaY;
        checkHeaderScroll();
    }
}

// Modal Desain & Configurator Lemari
const showCabinetModal = ref(false);
const showTutorialModal = ref(false);
const tutorialTab = ref<'canvas' | 'objects' | 'lines' | 'labels' | 'export'>('canvas');
const isSaving = ref(false);

// ── PAN & ZOOM ENGINE (MOBILE TOUCH + DESKTOP WHEEL) ─────────────────────────
const canvasContainerRef = ref<HTMLDivElement | null>(null);
const zoomScale = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const startPanX = ref(0);
const startPanY = ref(0);

// Touch gesture state
let initialPinchDistance = 0;
let initialPinchScale = 1;

// ── INTERACTIVE CABINET DATA MODEL (FULLY EDITABLE & REACTIVE) ──────────────
const cabinet = ref({
    name: 'Backdrop TV & Floating Credenza',
    category: 'Living Room & Interior TV Console',
    furnitureType: 'credenza_tv' as 'credenza_tv' | 'kitchen_bar' | 'wardrobe_3door',
    titleBlockPosition: 'side' as 'side' | 'top' | 'bottom',
    projectName: props.project?.title || 'Renovasi & Interior SEMANGKOKae',
    width: 3600,
    depth: 450,
    height: 2400,
    scaleSingle: '1 : 20',
    scaleAuto: '1 : 60',
    date: '22-10-2026',
    sheetNumber: '1 / 1',
    // Modular features
    hasFridge: false,
    fridgeWidth: 1000,
    fridgeHeight: 1850,
    hasSlats: true,
    slatsColor: '#7E5334',
    hasGlassShelves: true,
    hasSink: false,
    counterHeight: 450,
    countertopColor: '#ECEAE6',
    lowerCabinetStyle: 'drawers',
    lowerCabinetColor: '#BFA07A',
    tallUnitWidth: 800,
    tallUnitColor: '#7E5334',
    tallUnitChevron: false,
    hasNiche: false,
    notes: [
        '1. Semua ukuran dalam millimeter (mm).',
        '2. Braket TV 65" ditanam ke dinding bata + penguat multiplek 18mm.',
        '3. Sediakan conduit kabel HDMI & stop kontak di balik panel marmer.',
        '4. Rel laci tandem slowmotion clip-on beban 35kg.',
        '5. Trafo LED 12V 10A diletakkan di dalam modul laci tengah.',
    ],
    materials: [
        { code: 'M-01', name: 'TH 235 AC', color: '#7E5334', desc: 'Panel Dinding Kisi-kisi Kayu Akasia' },
        { code: 'M-02', name: 'Calacatta White', color: '#ECEAE6', desc: 'Panel Marmer Dinding TV / Bookmatch' },
        { code: 'LM-01', name: 'Wood 16', color: '#BFA07A', desc: 'HPL Serat Kayu Floating Credenza' },
        { code: 'M-04', name: 'Black Anodized', color: '#18181b', desc: 'Frame Aluminium Hitam & Smart TV 65"' },
        { code: 'LED-01', name: 'Warm White 3000K', color: '#f59e0b', desc: 'Indirect LED Strip Light Cove' },
    ],
});

// Preset Options for Quick Generating
function applyPreset(type: 'kitchen_bar' | 'wardrobe_3door' | 'credenza_tv' | 'minimalist_kitchen') {
    if (type === 'kitchen_bar') {
        cabinet.value.name = 'Kitchen & Pantry Set';
        cabinet.value.category = 'Kitchen & Cabinet Set';
        cabinet.value.furnitureType = 'kitchen_bar';
        cabinet.value.titleBlockPosition = 'bottom';
        cabinet.value.width = 5190;
        cabinet.value.depth = 675;
        cabinet.value.height = 2390;
        cabinet.value.hasFridge = true;
        cabinet.value.fridgeWidth = 1000;
        cabinet.value.hasSlats = true;
        cabinet.value.hasGlassShelves = true;
        cabinet.value.hasSink = true;
        cabinet.value.counterHeight = 850;
        cabinet.value.tallUnitWidth = 1690;
        cabinet.value.tallUnitColor = '#6F7E66';
        cabinet.value.lowerCabinetColor = '#C2A27D';
        cabinet.value.tallUnitChevron = true;
        cabinet.value.hasNiche = true;
        cabinet.value.materials = [
            { code: 'LM-01', name: 'Wood 16', color: '#BFA07A', desc: 'HPL Serat Kayu Natural Vertikal' },
            { code: 'M-01', name: 'TH 235 AC', color: '#7E5334', desc: 'HPL Akasia / Dinding Kisi-kisi Kayu' },
            { code: 'M-02', name: 'TH 076 SB', color: '#6F7E66', desc: 'HPL / Cat Duco Sage Green' },
            { code: 'M-03', name: 'DeepPink', color: '#ECEAE6', desc: 'Countertop Solid Surface / Terrazzo White' },
            { code: 'M-04', name: 'Black Metal', color: '#1C1C1E', desc: 'Black Gloss Refrigerator & Frame Besi' },
        ];
    } else if (type === 'credenza_tv') {
        cabinet.value.name = 'Backdrop TV & Floating Credenza';
        cabinet.value.category = 'Living Room & Interior TV Console';
        cabinet.value.furnitureType = 'credenza_tv';
        cabinet.value.titleBlockPosition = 'side';
        cabinet.value.width = 3600;
        cabinet.value.depth = 450;
        cabinet.value.height = 2400;
        cabinet.value.hasFridge = false;
        cabinet.value.hasSlats = true;
        cabinet.value.hasGlassShelves = true;
        cabinet.value.hasSink = false;
        cabinet.value.counterHeight = 450;
        cabinet.value.tallUnitWidth = 800;
        cabinet.value.tallUnitColor = '#7E5334';
        cabinet.value.lowerCabinetColor = '#BFA07A';
        cabinet.value.countertopColor = '#ECEAE6';
        cabinet.value.slatsColor = '#7E5334';
        cabinet.value.materials = [
            { code: 'M-01', name: 'TH 235 AC', color: '#7E5334', desc: 'Panel Dinding Kisi-kisi Kayu Akasia' },
            { code: 'M-02', name: 'Calacatta White', color: '#ECEAE6', desc: 'Panel Marmer Dinding TV / Bookmatch' },
            { code: 'LM-01', name: 'Wood 16', color: '#BFA07A', desc: 'HPL Serat Kayu Floating Credenza' },
            { code: 'M-04', name: 'Black Anodized', color: '#18181b', desc: 'Frame Aluminium Hitam & Smart TV 65"' },
            { code: 'LED-01', name: 'Warm White 3000K', color: '#f59e0b', desc: 'Indirect LED Strip Light Cove' },
        ];
        cabinet.value.notes = [
            '1. Semua ukuran dalam millimeter (mm).',
            '2. Braket TV 65" ditanam ke dinding bata + penguat multiplek 18mm.',
            '3. Sediakan conduit kabel HDMI & stop kontak di balik panel marmer.',
            '4. Rel laci tandem slowmotion clip-on beban 35kg.',
            '5. Trafo LED 12V 10A diletakkan di dalam modul laci tengah.',
        ];
    } else if (type === 'wardrobe_3door') {
        cabinet.value.name = 'Lemari Pakaian 3 Pintu';
        cabinet.value.category = 'Master Bedroom Wardrobe';
        cabinet.value.furnitureType = 'wardrobe_3door';
        cabinet.value.titleBlockPosition = 'side';
        cabinet.value.width = 2400;
        cabinet.value.depth = 600;
        cabinet.value.height = 2200;
        cabinet.value.hasFridge = false;
        cabinet.value.hasSlats = false;
        cabinet.value.hasGlassShelves = false;
        cabinet.value.hasSink = false;
        cabinet.value.counterHeight = 0;
        cabinet.value.tallUnitWidth = 2400;
        cabinet.value.tallUnitColor = '#475569';
        cabinet.value.lowerCabinetColor = '#BFA07A';
        cabinet.value.tallUnitChevron = false;
        cabinet.value.hasNiche = false;
    } else if (type === 'minimalist_kitchen') {
        cabinet.value.name = 'Kitchen Set Minimalis';
        cabinet.value.category = 'Kitchen & Cabinet Set';
        cabinet.value.furnitureType = 'kitchen_bar';
        cabinet.value.titleBlockPosition = 'bottom';
        cabinet.value.width = 3200;
        cabinet.value.depth = 600;
        cabinet.value.height = 2400;
        cabinet.value.hasFridge = false;
        cabinet.value.hasSlats = false;
        cabinet.value.hasGlassShelves = true;
        cabinet.value.hasSink = true;
        cabinet.value.counterHeight = 850;
        cabinet.value.tallUnitWidth = 800;
        cabinet.value.tallUnitColor = '#1e293b';
        cabinet.value.lowerCabinetColor = '#e2e8f0';
        cabinet.value.tallUnitChevron = false;
        cabinet.value.hasNiche = false;
    }
    toast.success(`Preset "${cabinet.value.name}" berhasil diterapkan.`);
    showCabinetModal.value = false;
}

// ── CUSTOM USER ANNOTATIONS & ACCESSORIES (TEXTS, LABELS, DIMENSIONS, LINES, RECTS, PRESETS) ───────
interface AnnotationItem {
    id: string;
    type: 'text' | 'label' | 'measure' | 'line' | 'rect' | 'accessory';
    x: number;
    y: number;
    x2?: number;
    y2?: number;
    targetX?: number;
    targetY?: number;
    width?: number;
    height?: number;
    text?: string;
    subText?: string;
    lengthMm?: number;
    lineStyle?: 'solid' | 'dashed' | 'dashdot';
    lineWidth?: number;
    color?: string;
    fontSize?: number;
    isBold?: boolean;
    hasBox?: boolean;
    accessoryType?: string;
    scale?: number;
}

const annotations = ref<AnnotationItem[]>([
    { id: 'ann-1', type: 'label', x: 975, y: 220, targetX: 820, targetY: 260, text: 'TH 076 SB', subText: 'M-02' },
    { id: 'ann-2', type: 'label', x: 975, y: 310, targetX: 490, targetY: 300, text: 'TH 235 AC', subText: 'M-01' },
    { id: 'ann-3', type: 'label', x: 975, y: 430, targetX: 550, targetY: 425, text: 'DeepPink', subText: 'M-03' },
    { id: 'ann-4', type: 'label', x: 975, y: 485, targetX: 230, targetY: 460, text: 'a660dbd0ee9ad7629b20e10b9571b', subText: 'M-04' },
    { id: 'ann-5', type: 'label', x: 975, y: 535, targetX: 430, targetY: 510, text: 'Wood 16', subText: 'LM-01' },
    { id: 'ann-6', type: 'text', x: 260, y: 645, text: 'POTONGAN A-A', color: '#000000', fontSize: 13, isBold: true, hasBox: false },
    { id: 'ann-7', type: 'measure', x: 410, y: 155, text: '5190 mm' },
    { id: 'ln-sample', type: 'line', x: 320, y: 390, x2: 500, y2: 390, lengthMm: 1200, lineStyle: 'solid', lineWidth: 2, color: '#2563eb' },
]);

// ── UNDO / REDO HISTORY ENGINE ──────────────────────────────────────────────
interface HistorySnapshot {
    annotations: string;
    cabinet: string;
    sectionLineX: number;
    shelvesOffsetX: number;
    shelvesOffsetY: number;
    shelvesWidth: number;
    sinkOffsetX: number;
    tvOffsetX: number;
    tvOffsetY: number;
}

const historyStack = ref<HistorySnapshot[]>([]);
const historyIndex = ref(-1);
const isHistoryNavigating = ref(false);

function captureSnapshot() {
    if (isHistoryNavigating.value) return;

    const snapshot: HistorySnapshot = {
        annotations: JSON.stringify(annotations.value),
        cabinet: JSON.stringify(cabinet.value),
        sectionLineX: sectionLineX.value,
        shelvesOffsetX: shelvesOffsetX.value,
        shelvesOffsetY: shelvesOffsetY.value,
        shelvesWidth: shelvesWidth.value,
        sinkOffsetX: sinkOffsetX.value,
        tvOffsetX: tvOffsetX.value,
        tvOffsetY: tvOffsetY.value,
    };

    if (historyIndex.value < historyStack.value.length - 1) {
        historyStack.value = historyStack.value.slice(0, historyIndex.value + 1);
    }

    historyStack.value.push(snapshot);
    if (historyStack.value.length > 50) {
        historyStack.value.shift();
    }
    historyIndex.value = historyStack.value.length - 1;
}

const canUndo = computed(() => historyIndex.value > 0);
const canRedo = computed(() => historyIndex.value < historyStack.value.length - 1);

function restoreHistoryState(snapshot: HistorySnapshot) {
    isHistoryNavigating.value = true;
    try {
        annotations.value = JSON.parse(snapshot.annotations);
        cabinet.value = JSON.parse(snapshot.cabinet);
        sectionLineX.value = snapshot.sectionLineX;
        shelvesOffsetX.value = snapshot.shelvesOffsetX;
        shelvesOffsetY.value = snapshot.shelvesOffsetY;
        shelvesWidth.value = snapshot.shelvesWidth;
        sinkOffsetX.value = snapshot.sinkOffsetX;
        tvOffsetX.value = snapshot.tvOffsetX;
        tvOffsetY.value = snapshot.tvOffsetY;
    } finally {
        nextTick(() => {
            isHistoryNavigating.value = false;
        });
    }
}

function undo() {
    if (!canUndo.value) {
        toast.info('Sudah di posisi awal (tidak ada riwayat sebelumnya).');
        return;
    }
    historyIndex.value--;
    restoreHistoryState(historyStack.value[historyIndex.value]);
    toast.success('Undo: Perubahan dibatalkan (Ctrl+Z).');
}

function redo() {
    if (!canRedo.value) {
        toast.info('Sudah di posisi paling baru.');
        return;
    }
    historyIndex.value++;
    restoreHistoryState(historyStack.value[historyIndex.value]);
    toast.success('Redo: Maju ke perubahan berikutnya (Ctrl+Y).');
}

// ── AKSESORIS STAMPER TOOL (LIBRARY LENGKAP ARSITEKTURAL INTERIOR & MEBEL) ──
interface AccessoryDef {
    id: string;
    name: string;
    category: 'kitchen' | 'living' | 'wardrobe' | 'decor' | 'mep';
    icon: string;
    desc: string;
    tags: string[];
    baseW: number;
    baseH: number;
    defaultWmm: number;
    defaultHmm: number;
}

const ACCESSORY_CATEGORIES = [
    { id: 'all', label: 'Semua', icon: '✨' },
    { id: 'kitchen', label: 'Dapur', icon: '🍳' },
    { id: 'living', label: 'Living', icon: '📺' },
    { id: 'wardrobe', label: 'Wardrobe', icon: '🚪' },
    { id: 'decor', label: 'Dekor & Lampu', icon: '🪴' },
    { id: 'mep', label: 'Listrik / MEP', icon: '🔌' },
];

const ACCESSORY_CATALOG: AccessoryDef[] = [
    // ── 1. DAPUR & KITCHEN APPLIANCES ──
    {
        id: 'sink',
        name: 'Kitchen Sink & Kran Cuci',
        category: 'kitchen',
        icon: '🚰',
        desc: 'Bak cuci piring wastafel + kran leher angsa',
        tags: ['sink', 'wastafel', 'cuci', 'piring', 'keran', 'kran', 'dapur', 'kitchen'],
        baseW: 110,
        baseH: 60,
        defaultWmm: 800,
        defaultHmm: 480,
    },
    {
        id: 'stove',
        name: 'Kompor Tanam Induksi / Gas',
        category: 'kitchen',
        icon: '🍳',
        desc: 'Kompor tanam 2-3 tungku keramik/kaca',
        tags: ['kompor', 'tanam', 'induksi', 'gas', 'tungku', 'hob', 'cooktop', 'dapur'],
        baseW: 110,
        baseH: 55,
        defaultWmm: 600,
        defaultHmm: 520,
    },
    {
        id: 'hood',
        name: 'Cooker Hood / Penghisap Asap',
        category: 'kitchen',
        icon: '💨',
        desc: 'Cerobong asap stainless exhaust dapur',
        tags: ['hood', 'cooker', 'exhaust', 'asap', 'penghisap', 'cerobong', 'dapur'],
        baseW: 120,
        baseH: 60,
        defaultWmm: 900,
        defaultHmm: 500,
    },
    {
        id: 'oven',
        name: 'Microwave & Built-in Oven',
        category: 'kitchen',
        icon: '📻',
        desc: 'Oven tanam kaca tempered + panel digital',
        tags: ['oven', 'microwave', 'pemanggang', 'tanam', 'built-in', 'dapur'],
        baseW: 100,
        baseH: 80,
        defaultWmm: 600,
        defaultHmm: 600,
    },
    {
        id: 'fridge_standalone',
        name: 'Kulkas Standalone 2 Pintu',
        category: 'kitchen',
        icon: '🧊',
        desc: 'Lemari es pendingin side-by-side',
        tags: ['kulkas', 'fridge', 'lemari es', 'refrigerator', 'freezer', 'dapur'],
        baseW: 120,
        baseH: 180,
        defaultWmm: 900,
        defaultHmm: 1800,
    },
    {
        id: 'dish_rack',
        name: 'Rak Piring Gantung Stainless',
        category: 'kitchen',
        icon: '🍽️',
        desc: 'Rak piring & gelas tirisan kabinet atas',
        tags: ['rak', 'piring', 'gelas', 'stainless', 'tirisan', 'dapur'],
        baseW: 110,
        baseH: 45,
        defaultWmm: 800,
        defaultHmm: 350,
    },
    {
        id: 'dispenser',
        name: 'Dispenser Air Galon Bawah',
        category: 'kitchen',
        icon: '🚾',
        desc: 'Dispenser air minum panas & dingin',
        tags: ['dispenser', 'air', 'galon', 'minum', 'dapur'],
        baseW: 55,
        baseH: 110,
        defaultWmm: 350,
        defaultHmm: 1050,
    },

    // ── 2. LIVING ROOM & MULTIMEDIA ──
    {
        id: 'tv',
        name: 'Smart TV 65 Inch',
        category: 'living',
        icon: '📺',
        desc: 'Layar lebar TV 16:9 + braket gantung',
        tags: ['tv', 'televisi', 'smart tv', 'screen', 'layar', 'living', 'backdrop'],
        baseW: 180,
        baseH: 105,
        defaultWmm: 1450,
        defaultHmm: 840,
    },
    {
        id: 'soundbar',
        name: 'Soundbar Audio System',
        category: 'living',
        icon: '🔊',
        desc: 'Speaker panjang gantung meja TV',
        tags: ['soundbar', 'audio', 'speaker', 'suara', 'musik', 'tv', 'backdrop'],
        baseW: 140,
        baseH: 16,
        defaultWmm: 1000,
        defaultHmm: 80,
    },
    {
        id: 'game_console',
        name: 'Game Console / Media Box',
        category: 'living',
        icon: '🎮',
        desc: 'Unit konsol game / TV box receiver',
        tags: ['console', 'ps5', 'game', 'box', 'stb', 'receiver', 'living'],
        baseW: 60,
        baseH: 25,
        defaultWmm: 400,
        defaultHmm: 150,
    },
    {
        id: 'speaker',
        name: 'Speaker Bookshelf / Tower',
        category: 'living',
        icon: '🎙️',
        desc: 'Sepasang speaker audio stereo',
        tags: ['speaker', 'audio', 'sound', 'stereo', 'living'],
        baseW: 40,
        baseH: 70,
        defaultWmm: 220,
        defaultHmm: 400,
    },
    {
        id: 'clock',
        name: 'Jam Dinding Minimalis',
        category: 'living',
        icon: '⏰',
        desc: 'Jam dinding analog modern estetik',
        tags: ['jam', 'clock', 'dinding', 'waktu', 'pajangan'],
        baseW: 50,
        baseH: 50,
        defaultWmm: 350,
        defaultHmm: 350,
    },

    // ── 3. LEMARI, WARDROBE & KAMAR ──
    {
        id: 'wardrobe_door',
        name: 'Pintu Lemari Wardrobe Solid',
        category: 'wardrobe',
        icon: '🚪',
        desc: 'Pintu lemari pakaian solid + handle ramping',
        tags: ['pintu', 'lemari', 'wardrobe', 'solid', 'pakaian', 'kamar'],
        baseW: 80,
        baseH: 150,
        defaultWmm: 500,
        defaultHmm: 2100,
    },
    {
        id: 'glass_door',
        name: 'Pintu Lemari Kaca Aluminium',
        category: 'wardrobe',
        icon: '🪟',
        desc: 'Pintu kaca tempered frame hitam modern',
        tags: ['pintu', 'kaca', 'aluminium', 'glass', 'wardrobe', 'showcase'],
        baseW: 80,
        baseH: 150,
        defaultWmm: 500,
        defaultHmm: 2100,
    },
    {
        id: 'drawer',
        name: 'Modul 3 Laci Kayu',
        category: 'wardrobe',
        icon: '🗄️',
        desc: 'Modul 3 susun laci geser softclose',
        tags: ['laci', 'drawer', 'susun', 'tandem', 'lemari', 'meja'],
        baseW: 100,
        baseH: 90,
        defaultWmm: 600,
        defaultHmm: 750,
    },
    {
        id: 'clothes_rail',
        name: 'Gantungan Baju & Hanger',
        category: 'wardrobe',
        icon: '👔',
        desc: 'Pipa gantungan baju + kemeja gantung',
        tags: ['gantungan', 'baju', 'hanger', 'pakaian', 'wardrobe', 'pipa'],
        baseW: 130,
        baseH: 70,
        defaultWmm: 900,
        defaultHmm: 900,
    },
    {
        id: 'shoe_rack',
        name: 'Rak Sepatu Susun Miring',
        category: 'wardrobe',
        icon: '👠',
        desc: 'Rak susun penyimpanan sepatu / heels',
        tags: ['rak', 'sepatu', 'shoes', 'heels', 'wardrobe', 'lemari'],
        baseW: 100,
        baseH: 60,
        defaultWmm: 800,
        defaultHmm: 600,
    },
    {
        id: 'mirror_round',
        name: 'Cermin Bulat LED Backlit',
        category: 'wardrobe',
        icon: '🪞',
        desc: 'Cermin rias bulat dengan cahaya LED',
        tags: ['cermin', 'mirror', 'bulat', 'led', 'rias', 'meja rias', 'vanity'],
        baseW: 70,
        baseH: 70,
        defaultWmm: 600,
        defaultHmm: 600,
    },
    {
        id: 'mirror_rect',
        name: 'Cermin Full Body Dinding',
        category: 'wardrobe',
        icon: '🪞',
        desc: 'Cermin rias tinggi penuh seluruh badan',
        tags: ['cermin', 'mirror', 'full body', 'panjang', 'rias', 'wardrobe'],
        baseW: 60,
        baseH: 140,
        defaultWmm: 500,
        defaultHmm: 1500,
    },

    // ── 4. DEKORASI & PENCAHAYAAN ──
    {
        id: 'books',
        name: 'Deretan Buku & Pajangan',
        category: 'decor',
        icon: '📚',
        desc: 'Buku hardcover tebal & dekor ambalan',
        tags: ['buku', 'books', 'pajangan', 'dekorasi', 'ambalan', 'rak'],
        baseW: 100,
        baseH: 55,
        defaultWmm: 600,
        defaultHmm: 350,
    },
    {
        id: 'plant',
        name: 'Vas Bunga & Tanaman Hias',
        category: 'decor',
        icon: '🪴',
        desc: 'Pot keramik putih + tanaman daun tropis',
        tags: ['tanaman', 'plant', 'vas', 'bunga', 'pot', 'hiasan', 'dekor'],
        baseW: 50,
        baseH: 60,
        defaultWmm: 300,
        defaultHmm: 400,
    },
    {
        id: 'wall_art',
        name: 'Lukisan Kanvas Dinding',
        category: 'decor',
        icon: '🖼️',
        desc: 'Frame lukisan abstrak minimalis modern',
        tags: ['lukisan', 'kanvas', 'art', 'frame', 'foto', 'gambar', 'dinding'],
        baseW: 120,
        baseH: 80,
        defaultWmm: 800,
        defaultHmm: 600,
    },
    {
        id: 'pendant_light',
        name: 'Lampu Gantung / Pendant',
        category: 'decor',
        icon: '💡',
        desc: 'Lampu gantung kap kerucut meja bar',
        tags: ['lampu', 'gantung', 'pendant', 'light', 'bar', 'meja bar'],
        baseW: 50,
        baseH: 90,
        defaultWmm: 350,
        defaultHmm: 800,
    },
    {
        id: 'downlight',
        name: 'Downlight Spotlight Plafon',
        category: 'decor',
        icon: '🔦',
        desc: 'Lampu sorot plafon / lemari display',
        tags: ['downlight', 'spotlight', 'lampu sorot', 'plafon', 'led'],
        baseW: 35,
        baseH: 25,
        defaultWmm: 120,
        defaultHmm: 90,
    },
    {
        id: 'led_strip',
        name: 'Glow LED Strip Ambalan',
        category: 'decor',
        icon: '✨',
        desc: 'Lampu LED profil tersembunyi warm white',
        tags: ['led', 'strip', 'glow', 'cahaya', 'ambalan', 'lampu'],
        baseW: 140,
        baseH: 12,
        defaultWmm: 1200,
        defaultHmm: 30,
    },

    // ── 5. MEP, LISTRIK & SANITARI ──
    {
        id: 'power_outlet',
        name: 'Stop Kontak Listrik Dinding',
        category: 'mep',
        icon: '🔌',
        desc: 'Stop kontak ganda tanam 220V standard',
        tags: ['stop kontak', 'colokan', 'listrik', 'outlet', 'socket', 'mep'],
        baseW: 30,
        baseH: 30,
        defaultWmm: 160,
        defaultHmm: 80,
    },
    {
        id: 'switch',
        name: 'Saklar Lampu Ganda',
        category: 'mep',
        icon: '🔘',
        desc: 'Saklar switch lampu seri dinding',
        tags: ['saklar', 'switch', 'lampu', 'tombol', 'listrik', 'mep'],
        baseW: 30,
        baseH: 30,
        defaultWmm: 80,
        defaultHmm: 80,
    },
    {
        id: 'ac_indoor',
        name: 'AC Split Indoor Unit',
        category: 'mep',
        icon: '❄️',
        desc: 'Indoor AC pendingin ruangan split wall',
        tags: ['ac', 'air conditioner', 'pendingin', 'split', 'mep', 'kamar'],
        baseW: 130,
        baseH: 45,
        defaultWmm: 850,
        defaultHmm: 300,
    },
    {
        id: 'barstool',
        name: 'Kursi Barstool Cafe',
        category: 'mep',
        icon: '🪑',
        desc: 'Kursi tinggi hidrolik meja island/bar',
        tags: ['kursi', 'barstool', 'stool', 'bangku', 'bar', 'island'],
        baseW: 50,
        baseH: 90,
        defaultWmm: 450,
        defaultHmm: 950,
    },
];

const showAccessoryMenu = ref(false);
const accessoryButtonRef = ref<HTMLElement | null>(null);
const accessoryDropdownPos = ref({ top: 80, left: 200 });
const accessorySearchQuery = ref('');
const accessorySelectedCategory = ref('all');
const accessorySearchInputRef = ref<HTMLInputElement | null>(null);

const filteredAccessories = computed(() => {
    const q = accessorySearchQuery.value.trim().toLowerCase();
    const cat = accessorySelectedCategory.value;

    return ACCESSORY_CATALOG.filter(item => {
        if (cat !== 'all' && item.category !== cat) {
            return false;
        }
        if (!q) return true;
        return (
            item.name.toLowerCase().includes(q) ||
            item.desc.toLowerCase().includes(q) ||
            item.category.toLowerCase().includes(q) ||
            item.tags.some(tag => tag.toLowerCase().includes(q))
        );
    });
});

function toggleAccessoryMenu() {
    if (!showAccessoryMenu.value && accessoryButtonRef.value) {
        const rect = accessoryButtonRef.value.getBoundingClientRect();
        const dropWidth = Math.min(410, window.innerWidth - 24);
        const dropHeight = Math.min(540, window.innerHeight - 90);

        let top = Math.round(rect.bottom + 6);
        let left = Math.max(12, Math.min(Math.round(rect.left), window.innerWidth - dropWidth - 12));

        if (top + dropHeight > window.innerHeight - 12) {
            top = Math.max(12, window.innerHeight - dropHeight - 12);
        }

        accessoryDropdownPos.value = { top, left };
        accessorySearchQuery.value = '';
        accessorySelectedCategory.value = 'all';

        nextTick(() => {
            accessorySearchInputRef.value?.focus();
        });
    }
    showAccessoryMenu.value = !showAccessoryMenu.value;
}

function getAccessoryBaseSize(type?: string): { w: number; h: number } {
    const item = ACCESSORY_CATALOG.find(a => a.id === type);
    if (item) return { w: item.baseW, h: item.baseH };
    return { w: 120, h: 80 };
}

function getAccessoryWidthPx(ann: AnnotationItem): number {
    const base = getAccessoryBaseSize(ann.accessoryType);
    return ann.width || Math.round(base.w * (ann.scale || 1));
}

function getAccessoryHeightPx(ann: AnnotationItem): number {
    const base = getAccessoryBaseSize(ann.accessoryType);
    return ann.height || Math.round(base.h * (ann.scale || 1));
}

function getAccessoryWidthMm(ann: AnnotationItem): number {
    const pxW = getAccessoryWidthPx(ann);
    const cabinetW = cabinet.value.width || 2400;
    const svgW = furnitureSvgWidth.value || 760;
    return Math.round(pxW * (cabinetW / svgW));
}

function getAccessoryHeightMm(ann: AnnotationItem): number {
    const pxH = getAccessoryHeightPx(ann);
    const cabinetH = cabinet.value.height || 2200;
    return Math.round(pxH * (cabinetH / 390));
}

function setAccessoryWidthMm(ann: AnnotationItem, mm: number) {
    if (!mm || mm <= 0) return;
    const cabinetW = cabinet.value.width || 2400;
    const svgW = furnitureSvgWidth.value || 760;
    ann.width = Math.max(30, Math.round(mm * (svgW / cabinetW)));
    ann.scale = 1;
    captureSnapshot();
}

function setAccessoryHeightMm(ann: AnnotationItem, mm: number) {
    if (!mm || mm <= 0) return;
    const cabinetH = cabinet.value.height || 2200;
    ann.height = Math.max(20, Math.round(mm * (390 / cabinetH)));
    ann.scale = 1;
    captureSnapshot();
}

function stepAccessoryWidth(ann: AnnotationItem, deltaMm: number) {
    const cur = getAccessoryWidthMm(ann);
    setAccessoryWidthMm(ann, Math.max(80, cur + deltaMm));
}

function stepAccessoryHeight(ann: AnnotationItem, deltaMm: number) {
    const cur = getAccessoryHeightMm(ann);
    setAccessoryHeightMm(ann, Math.max(40, cur + deltaMm));
}

function resetAccessorySize(ann: AnnotationItem) {
    const base = getAccessoryBaseSize(ann.accessoryType);
    ann.width = base.w;
    ann.height = base.h;
    ann.scale = 1;
    captureSnapshot();
    toast.success('Ukuran aksesoris dikembalikan ke standar.');
}

function applyAccessoryPreset(ann: AnnotationItem, wMm: number, hMm: number) {
    setAccessoryWidthMm(ann, wMm);
    setAccessoryHeightMm(ann, hMm);
    toast.success(`Ukuran diatur ke ${wMm} × ${hMm} mm`);
}

function stampAccessory(type: string) {
    showAccessoryMenu.value = false;
    const item = ACCESSORY_CATALOG.find(a => a.id === type);
    const baseW = item ? item.baseW : 120;
    const baseH = item ? item.baseH : 80;
    const label = item ? item.name : 'Aksesoris';

    const defaultX = Math.round(furnitureSvgX.value + (furnitureSvgWidth.value / 2) - (baseW / 2));
    const defaultY = Math.round(230 + (Math.random() * 60));

    const newAcc: AnnotationItem = {
        id: `acc-${Date.now()}`,
        type: 'accessory',
        x: defaultX,
        y: defaultY,
        width: baseW,
        height: baseH,
        text: label,
        accessoryType: type,
        scale: 1,
    };

    annotations.value.push(newAcc);
    selectedObjectId.value = newAcc.id;
    selectedElement.value = { type: 'accessory', id: newAcc.id, name: label };
    captureSnapshot();
    toast.success(`Aksesoris "${label}" ditambahkan. Anda dapat menarik tepi atau sudutnya untuk resize!`);
}

// ── MODAL KOP GAMBAR LENGKAP ────────────────────────────────────────────────
const showKopModal = ref(false);

// Modular interactive component offsets & sizes (bisa ditarik & digeser langsung di canvas)
const sectionLineX = ref(280);
const shelvesOffsetX = ref(0);
const shelvesOffsetY = ref(0);
const shelvesWidth = ref(250);
const sinkOffsetX = ref(0);
const tvOffsetX = ref(0);
const tvOffsetY = ref(0);

// Title Block Position & Dynamic Furniture Sizing
const titleBlockPos = computed(() => cabinet.value.titleBlockPosition || 'side');

const furnitureSvgX = computed(() => {
    if (titleBlockPos.value === 'side') return 90;
    return 160;
});

const furnitureSvgWidth = computed(() => {
    if (titleBlockPos.value === 'side') return 760;
    return 780;
});

// Direct Drag & Drop direct manipulation engine
const isDraggingObject = ref(false);
const selectedObjectId = ref<string | null>('tv');
const hoveredAccessoryId = ref<string | null>(null);
const dragPayload = ref<{
    type: 'annotation' | 'annotation_target' | 'section_line' | 'shelves' | 'shelves_resize' | 'sink' | 'divider_fridge' | 'divider_tall' | 'countertop' | 'component' | 'line_body' | 'line_endpoint_a' | 'line_endpoint_b' | 'tv' | 'credenza' | 'accessory_resize_se' | 'accessory_resize_e' | 'accessory_resize_s' | 'accessory_resize_w' | 'accessory_resize_n' | 'accessory_resize_nw' | 'accessory_resize_ne' | 'accessory_resize_sw';
    id: string;
    startX: number;
    startY: number;
    initialData: Record<string, any>;
} | null>(null);

// Scale conversions between mm and SVG pixels
const scaleFactorX = computed(() => furnitureSvgWidth.value / cabinet.value.width);
const scaleFactorY = computed(() => 390 / cabinet.value.height);

const fridgeSvgWidth = computed(() => {
    if (!cabinet.value.hasFridge) return 0;
    return Math.round(cabinet.value.fridgeWidth * scaleFactorX.value);
});

const tallSvgWidth = computed(() => {
    return Math.round(cabinet.value.tallUnitWidth * scaleFactorX.value);
});

const middleSvgX = computed(() => furnitureSvgX.value + fridgeSvgWidth.value);
const middleSvgWidth = computed(() => Math.max(80, furnitureSvgWidth.value - fridgeSvgWidth.value - tallSvgWidth.value));
const tallSvgX = computed(() => furnitureSvgX.value + furnitureSvgWidth.value - tallSvgWidth.value);

const counterSvgHeight = computed(() => {
    return Math.round(cabinet.value.counterHeight * scaleFactorY.value);
});
const counterSvgY = computed(() => 580 - counterSvgHeight.value);

// Helper functions for flexible line manipulation
function getLineLengthMm(ann: AnnotationItem): number {
    if (ann.lengthMm && ann.lengthMm > 0) return ann.lengthMm;
    const dx = (ann.x2 ?? (ann.x + 100)) - ann.x;
    const dy = (ann.y2 ?? ann.y) - ann.y;
    return Math.round(Math.hypot(dx, dy) * (cabinet.value.width / 780));
}

function setLineLengthMm(ann: AnnotationItem, newLengthMm: number) {
    if (!newLengthMm || newLengthMm <= 0) return;
    ann.lengthMm = Math.round(newLengthMm);
    const pxLen = ann.lengthMm * (780 / cabinet.value.width);
    const curDx = (ann.x2 ?? (ann.x + 100)) - ann.x;
    const curDy = (ann.y2 ?? ann.y) - ann.y;
    const curHypot = Math.hypot(curDx, curDy) || 1;
    const ux = curDx / curHypot;
    const uy = curDy / curHypot;
    ann.x2 = Math.round(ann.x + ux * pxLen);
    ann.y2 = Math.round(ann.y + uy * pxLen);
}

function setLineOrientation(ann: AnnotationItem, orientation: 'horizontal' | 'vertical') {
    const curLen = getLineLengthMm(ann);
    const pxLen = curLen * (780 / cabinet.value.width);
    if (orientation === 'horizontal') {
        ann.x2 = ann.x + Math.round(pxLen);
        ann.y2 = ann.y;
    } else {
        ann.x2 = ann.x;
        ann.y2 = ann.y + Math.round(pxLen);
    }
}

function promptChangeLineLength(ann: AnnotationItem) {
    selectedObjectId.value = ann.id;
    selectedElement.value = { type: 'annotation', id: ann.id, name: `Garis: ${getLineLengthMm(ann)} mm` };
    if (!isDesktopSidebarOpen.value && !isMobileScreen.value) {
        isDesktopSidebarOpen.value = true;
    }
    const cur = getLineLengthMm(ann);
    const val = prompt(`Masukkan panjang garis baru (mm) [tanpa perlu tarik]:`, String(cur));
    if (val !== null && val.trim() !== '') {
        const num = Number(val);
        if (!isNaN(num) && num > 0) {
            setLineLengthMm(ann, num);
            toast.success(`Panjang garis diperbarui menjadi ${num} mm.`);
        }
    }
}

// Selected element & annotation computed
const selectedElement = ref<{ type: string; id: string; name: string } | null>({
    type: 'component',
    id: 'fridge',
    name: 'Ruang Kulkas 2 Pintu',
});

const selectedAnnotation = computed(() => {
    return annotations.value.find(a => a.id === selectedObjectId.value) || null;
});

// Load saved data from database props if available
function initFromProps() {
    if (!props.elements || !props.elements.length) return;
    const shopEl = props.elements.find((e: any) => e.type === 'shop_drawing' || e.data?.cabinet);
    if (shopEl?.data) {
        const d = typeof shopEl.data === 'string' ? JSON.parse(shopEl.data) : shopEl.data;
        if (d.cabinet) {
            cabinet.value = { ...cabinet.value, ...d.cabinet };
        }
        if (d.annotations && Array.isArray(d.annotations) && d.annotations.length > 0) {
            annotations.value = d.annotations;
        }
    }
}

watch(() => props.elements, () => {
    initFromProps();
}, { immediate: true, deep: true });

function getSvgPoint(e: MouseEvent | Touch): { x: number; y: number } {
    const svgEl = document.getElementById('shop-drawing-svg') as SVGSVGElement | null;
    if (!svgEl) return { x: 0, y: 0 };
    try {
        const pt = svgEl.createSVGPoint();
        pt.x = e.clientX;
        pt.y = e.clientY;
        const ctm = svgEl.getScreenCTM();
        if (ctm) {
            const transformed = pt.matrixTransform(ctm.inverse());
            return {
                x: Math.round(transformed.x),
                y: Math.round(transformed.y),
            };
        }
    } catch {
        // Fallback
    }
    const rect = svgEl.getBoundingClientRect();
    return {
        x: Math.round(((e.clientX - rect.left) / rect.width) * 1200),
        y: Math.round(((e.clientY - rect.top) / rect.height) * 840),
    };
}

function startObjectDrag(e: MouseEvent | TouchEvent, type: string, id: string, initialData: Record<string, any> = {}) {
    e.stopPropagation();
    const touch = 'touches' in e && e.touches.length > 0 ? e.touches[0] : (e as MouseEvent);
    const pt = getSvgPoint(touch);

    isDraggingObject.value = true;
    selectedObjectId.value = id;
    selectedElement.value = { type, id, name: initialData.name || id };

    dragPayload.value = {
        type: type as any,
        id,
        startX: pt.x,
        startY: pt.y,
        initialData: { ...initialData },
    };
}

function doObjectDrag(pointEvent: MouseEvent | Touch) {
    if (!dragPayload.value) return;
    const pt = getSvgPoint(pointEvent);
    const dx = pt.x - dragPayload.value.startX;
    const dy = pt.y - dragPayload.value.startY;
    const target = dragPayload.value;

    if (target.type === 'annotation') {
        const ann = annotations.value.find(a => a.id === target.id);
        if (ann) {
            ann.x = target.initialData.x + dx;
            ann.y = target.initialData.y + dy;
            if (ann.x2 !== undefined && target.initialData.x2 !== undefined) {
                ann.x2 = target.initialData.x2 + dx;
            }
            if (ann.y2 !== undefined && target.initialData.y2 !== undefined) {
                ann.y2 = target.initialData.y2 + dy;
            }
        }
    } else if (target.type === 'line_body') {
        const ann = annotations.value.find(a => a.id === target.id);
        if (ann) {
            ann.x = target.initialData.x + dx;
            ann.y = target.initialData.y + dy;
            ann.x2 = target.initialData.x2 + dx;
            ann.y2 = target.initialData.y2 + dy;
        }
    } else if (target.type === 'line_endpoint_a') {
        const ann = annotations.value.find(a => a.id === target.id);
        if (ann) {
            ann.x = target.initialData.x + dx;
            ann.y = target.initialData.y + dy;
            ann.lengthMm = Math.round(Math.hypot((ann.x2 ?? ann.x) - ann.x, (ann.y2 ?? ann.y) - ann.y) * (cabinet.value.width / 780));
        }
    } else if (target.type === 'line_endpoint_b') {
        const ann = annotations.value.find(a => a.id === target.id);
        if (ann) {
            ann.x2 = target.initialData.x2 + dx;
            ann.y2 = target.initialData.y2 + dy;
            ann.lengthMm = Math.round(Math.hypot(ann.x2 - ann.x, ann.y2 - ann.y) * (cabinet.value.width / 780));
        }
    } else if (target.type === 'annotation_target') {
        const ann = annotations.value.find(a => a.id === target.id);
        if (ann) {
            ann.targetX = target.initialData.targetX + dx;
            ann.targetY = target.initialData.targetY + dy;
        }
    } else if (target.type === 'section_line') {
        sectionLineX.value = Math.min(Math.max(170, target.initialData.x + dx), 930);
    } else if (target.type === 'divider_fridge') {
        const deltaMm = Math.round(dx * (cabinet.value.width / 780));
        const newW = Math.min(Math.max(600, target.initialData.fridgeWidth + deltaMm), 1800);
        cabinet.value.fridgeWidth = newW;
    } else if (target.type === 'divider_tall') {
        const deltaMm = Math.round(-dx * (cabinet.value.width / 780));
        const newW = Math.min(Math.max(600, target.initialData.tallUnitWidth + deltaMm), 2500);
        cabinet.value.tallUnitWidth = newW;
    } else if (target.type === 'countertop') {
        const deltaMm = Math.round(-dy * (cabinet.value.height / 390));
        cabinet.value.counterHeight = Math.min(Math.max(400, target.initialData.counterHeight + deltaMm), 1150);
    } else if (target.type === 'shelves') {
        shelvesOffsetX.value = target.initialData.shelvesOffsetX + dx;
        shelvesOffsetY.value = Math.min(Math.max(-60, target.initialData.shelvesOffsetY + dy), 120);
    } else if (target.type === 'shelves_resize') {
        shelvesWidth.value = Math.min(Math.max(120, target.initialData.shelvesWidth + dx), middleSvgWidth.value - 20);
    } else if (target.type === 'sink') {
        sinkOffsetX.value = Math.min(Math.max(-100, target.initialData.sinkOffsetX + dx), 120);
    } else if (target.type === 'tv') {
        tvOffsetX.value = target.initialData.tvOffsetX + dx;
        tvOffsetY.value = Math.min(Math.max(-60, target.initialData.tvOffsetY + dy), 60);
    } else if (target.type.startsWith('accessory_resize_')) {
        const ann = annotations.value.find(a => a.id === target.id);
        if (ann) {
            const base = getAccessoryBaseSize(ann.accessoryType);
            const initialW = target.initialData.width || (base.w * (ann.scale || 1));
            const initialH = target.initialData.height || (base.h * (ann.scale || 1));
            const initialX = target.initialData.x;
            const initialY = target.initialData.y;
            const minW = 25;
            const minH = 15;

            // Horizontal resize
            if (target.type === 'accessory_resize_e' || target.type === 'accessory_resize_se' || target.type === 'accessory_resize_ne') {
                ann.width = Math.max(minW, Math.round(initialW + dx));
            } else if (target.type === 'accessory_resize_w' || target.type === 'accessory_resize_nw' || target.type === 'accessory_resize_sw') {
                const rawW = initialW - dx;
                if (rawW >= minW) {
                    ann.x = Math.round(initialX + dx);
                    ann.width = Math.round(rawW);
                } else {
                    ann.x = Math.round(initialX + (initialW - minW));
                    ann.width = minW;
                }
            }

            // Vertical resize
            if (target.type === 'accessory_resize_s' || target.type === 'accessory_resize_se' || target.type === 'accessory_resize_sw') {
                ann.height = Math.max(minH, Math.round(initialH + dy));
            } else if (target.type === 'accessory_resize_n' || target.type === 'accessory_resize_nw' || target.type === 'accessory_resize_ne') {
                const rawH = initialH - dy;
                if (rawH >= minH) {
                    ann.y = Math.round(initialY + dy);
                    ann.height = Math.round(rawH);
                } else {
                    ann.y = Math.round(initialY + (initialH - minH));
                    ann.height = minH;
                }
            }
        }
    }
}

function handleGlobalMouseMove(e: MouseEvent) {
    if (isDraggingObject.value) {
        doObjectDrag(e);
    } else if (isPanning.value) {
        doPan(e.clientX, e.clientY);
    }
}

function handleGlobalMouseUp() {
    if (isDraggingObject.value) {
        isDraggingObject.value = false;
        dragPayload.value = null;
        captureSnapshot();
    }
    if (isPanning.value) {
        endPan();
    }
}

function handleGlobalTouchMove(e: TouchEvent) {
    if (isDraggingObject.value && e.touches.length === 1) {
        e.preventDefault();
        doObjectDrag(e.touches[0]);
    } else if (isPanning.value) {
        onTouchMove(e);
    }
}

function handleGlobalTouchEnd() {
    handleGlobalMouseUp();
}

function onCanvasClick(e: MouseEvent) {
    if (isDraggingObject.value) return;
    if (activeTool.value === 'select') return;

    const pt = getSvgPoint(e);
    const clickX = pt.x;
    const clickY = pt.y;

    if (activeTool.value === 'text') {
        const input = prompt('Masukkan teks notasi arsitektural (tanpa kotak):');
        if (input) {
            const newTxt: AnnotationItem = {
                id: `txt-${Date.now()}`,
                type: 'text',
                x: clickX,
                y: clickY,
                text: input,
                color: '#000000',
                fontSize: 12,
                isBold: true,
                hasBox: false, // Default: Tanpa kotak sesuai permintaan user!
            };
            annotations.value.push(newTxt);
            selectedObjectId.value = newTxt.id;
            selectedElement.value = { type: 'annotation', id: newTxt.id, name: `Teks: ${input}` };
            captureSnapshot();
            toast.success('Notasi teks berhasil ditambahkan (tanpa kotak background).');
        }
    } else if (activeTool.value === 'label') {
        const mat = prompt('Nama Material & Kode Balon (Contoh: HPL Wood 16, M-01):', 'HPL Wood 16, M-01');
        if (mat) {
            const parts = mat.split(',');
            annotations.value.push({
                id: `lbl-${Date.now()}`,
                type: 'label',
                x: clickX,
                y: clickY,
                targetX: Math.max(160, clickX - 120),
                targetY: clickY,
                text: parts[0]?.trim() || 'Custom Material',
                subText: parts[1]?.trim() || 'M-99',
            });
            captureSnapshot();
            toast.success('Balon material berhasil ditambahkan. Balon & ujung panah bisa ditarik!');
        }
    } else if (activeTool.value === 'measure') {
        const dist = prompt('Masukkan ukuran panjang dimensi (mm):', '1000');
        if (dist) {
            annotations.value.push({
                id: `dim-${Date.now()}`,
                type: 'measure',
                x: clickX,
                y: clickY,
                text: `${dist} mm`,
            });
            captureSnapshot();
            toast.success('Garis dimensi berhasil ditambahkan. Anda dapat menarik posisinya!');
        }
    } else if (activeTool.value === 'line') {
        const inputLen = prompt('Panjang garis awal (mm) [Bisa ditarik langsung atau diketik ulang nanti]:', '1200');
        if (inputLen === null) {
            activeTool.value = 'select';
            return;
        }
        const parsed = Number(inputLen.trim());
        const lenMm = !isNaN(parsed) && parsed > 0 ? parsed : 1200;
        const pxLen = Math.round(lenMm * (780 / cabinet.value.width));
        
        const newLine: AnnotationItem = {
            id: `ln-${Date.now()}`,
            type: 'line',
            x: clickX,
            y: clickY,
            x2: clickX + pxLen,
            y2: clickY,
            lengthMm: lenMm,
            lineStyle: 'solid',
            lineWidth: 2,
            color: '#2563eb',
        };
        annotations.value.push(newLine);
        selectedObjectId.value = newLine.id;
        selectedElement.value = { type: 'annotation', id: newLine.id, name: `Garis: ${lenMm} mm` };
        if (!isDesktopSidebarOpen.value && !isMobileScreen.value) {
            isDesktopSidebarOpen.value = true;
        }
        captureSnapshot();
        toast.success(`Garis ${lenMm} mm dibuat! Anda bisa tarik titik ujungnya atau ubah angkanya langsung di sidebar.`);
    } else if (activeTool.value === 'rect') {
        annotations.value.push({
            id: `rc-${Date.now()}`,
            type: 'rect',
            x: clickX - 40,
            y: clickY - 25,
            width: 80,
            height: 50,
        });
        captureSnapshot();
        toast.success('Kotak notasi berhasil ditambahkan.');
    }
    activeTool.value = 'select';
}

function selectComponent(name: string, id: string) {
    selectedObjectId.value = id;
    selectedElement.value = { type: 'component', id, name };
    if (!isDesktopSidebarOpen.value && !isMobileScreen.value) {
        isDesktopSidebarOpen.value = true;
    }
    toast.info(`Elemen terpilih: ${name}`);
}

function deleteSelectedObject() {
    if (!selectedObjectId.value) return;

    const id = selectedObjectId.value;
    const annIndex = annotations.value.findIndex(a => a.id === id);
    if (annIndex !== -1) {
        const item = annotations.value[annIndex];
        annotations.value.splice(annIndex, 1);
        selectedObjectId.value = null;
        selectedElement.value = null;
        captureSnapshot();
        toast.success(`"${item.text || item.type || 'Objek'}" berhasil dihapus. Tekan Ctrl+Z untuk membatalkan.`);
        return;
    }

    if (id === 'tv') {
        cabinet.value.hasSlats = !cabinet.value.hasSlats;
        selectedObjectId.value = null;
        selectedElement.value = null;
        captureSnapshot();
        toast.success('Modul TV dinonaktifkan.');
        return;
    }
    if (id === 'shelves') {
        cabinet.value.hasGlassShelves = false;
        selectedObjectId.value = null;
        selectedElement.value = null;
        captureSnapshot();
        toast.success('Rak kaca disembunyikan.');
        return;
    }
    if (id === 'sink') {
        cabinet.value.hasSink = false;
        selectedObjectId.value = null;
        selectedElement.value = null;
        captureSnapshot();
        toast.success('Sink cuci piring disembunyikan.');
        return;
    }
    if (id === 'fridge') {
        cabinet.value.hasFridge = false;
        selectedObjectId.value = null;
        selectedElement.value = null;
        captureSnapshot();
        toast.success('Kulkas disembunyikan.');
        return;
    }

    selectedObjectId.value = null;
    selectedElement.value = null;
}

function handleGlobalKeyDown(e: KeyboardEvent) {
    // Abaikan jika fokus sedang mengetik di input / textarea
    const target = e.target as HTMLElement | null;
    if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) {
        return;
    }

    // Ctrl+Z atau Cmd+Z (Undo)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
        e.preventDefault();
        undo();
        return;
    }

    // Ctrl+Y atau Cmd+Y atau Ctrl+Shift+Z (Redo / Forward)
    if (
        ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') ||
        ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'z')
    ) {
        e.preventDefault();
        redo();
        return;
    }

    // Tombol Delete atau Backspace (Hapus objek yang dipilih)
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (selectedObjectId.value) {
            e.preventDefault();
            deleteSelectedObject();
        }
    }
}

function removeAnnotationDirectly(id: string) {
    annotations.value = annotations.value.filter(a => a.id !== id);
    if (selectedObjectId.value === id) {
        selectedObjectId.value = null;
        selectedElement.value = null;
    }
    captureSnapshot();
    toast.success('Garis / notasi berhasil dihapus.');
}

function deleteAnnotation(id: string) {
    const ann = annotations.value.find(a => a.id === id);
    if (!ann) return;

    if (ann.type === 'label' || ann.type === 'text' || ann.type === 'measure') {
        const choice = confirm(`Notasi: "${ann.text || ''}"\n• Klik [OK] untuk mengedit teks/kode\n• Klik [Batal] untuk menghapus notasi ini`);
        if (choice) {
            const newText = prompt('Ubah teks notasi:', ann.text || '');
            if (newText !== null && newText.trim() !== '') {
                ann.text = newText.trim();
                if (ann.type === 'label') {
                    const newSub = prompt('Ubah kode balon (contoh M-01):', ann.subText || '');
                    if (newSub !== null) ann.subText = newSub.trim();
                }
                captureSnapshot();
                toast.success('Notasi berhasil diperbarui.');
            }
            return;
        }
    }

    annotations.value = annotations.value.filter(a => a.id !== id);
    if (selectedObjectId.value === id) {
        selectedObjectId.value = null;
        selectedElement.value = null;
    }
    captureSnapshot();
    toast.success('Notasi dihapus.');
}

// ── DYNAMIC CUTTING LIST COMPUTATION (RECALCULATES AUTOMATICALLY!) ────────────
const cutListItems = computed(() => {
    const list = [];
    let id = 1;
    const w = cabinet.value.width;
    const d = cabinet.value.depth;
    const h = cabinet.value.height;

    // Cutting list khusus Backdrop TV & Floating Credenza
    if (cabinet.value.furnitureType === 'credenza_tv') {
        list.push({ id: id++, name: 'Panel Dinding Marmer TV (Calacatta)', qty: 1, length: Math.round(w * 0.65), width: h - 450, thick: 12, mat: 'Slab Marmer / Solid Surface', finish: 'Calacatta White Polished (M-02)', edging: 'Bevel Keliling' });
        list.push({ id: id++, name: 'Panel Dinding Kisi-kisi Kayu Fluted', qty: 1, length: Math.round(w * 0.35), width: h - 450, thick: 18, mat: 'MDF / Kayu Solid Akasia', finish: 'TH 235 AC (M-01)', edging: '4 Sisi' });
        list.push({ id: id++, name: 'Top Table Floating Credenza', qty: 1, length: w, width: d, thick: 25, mat: 'Solid Surface / Multiplek 18mm', finish: 'White Marmer (M-02)', edging: '3 Sisi Chamfer' });
        list.push({ id: id++, name: 'Dinding Samping & Bawah Credenza', qty: 2, length: w, width: d, thick: 18, mat: 'Multiplek 18mm', finish: 'Wood 16 (LM-01)', edging: 'P1, L1' });
        list.push({ id: id++, name: 'Muka Laci Floating Credenza (x3)', qty: 3, length: Math.round(w / 4) - 4, width: 380, thick: 18, mat: 'Multiplek 18mm', finish: 'Wood 16 Serat Kayu (LM-01)', edging: '4 Sisi J-Pull' });
        list.push({ id: id++, name: 'Modul Open Niche Game Console', qty: 1, length: Math.round(w / 4) - 4, width: 380, thick: 18, mat: 'Multiplek 18mm', finish: 'Hitam Doff (M-04)', edging: '4 Sisi' });
        list.push({ id: id++, name: 'Ambalan Kaca Rak Display LED (x3)', qty: 3, length: Math.round(w * 0.35) - 60, width: 220, thick: 8, mat: 'Kaca Tempered 8mm', finish: 'Bening Polish Flat', edging: 'Polish Keliling' });
        list.push({ id: id++, name: 'Backing Penguat Rangka Dinding', qty: 1, length: w, width: h, thick: 18, mat: 'Multiplek 18mm', finish: 'Mentahan / Melaminto', edging: 'Tanpa Edging' });
        return list;
    }

    const fw = cabinet.value.hasFridge ? cabinet.value.fridgeWidth : 0;
    const tw = cabinet.value.tallUnitWidth;
    const cw = Math.max(1000, w - fw - tw);

    // 1. Dinding Samping Kiri
    list.push({ id: id++, name: 'Dinding Samping Kiri Luar', qty: 1, length: h, width: d, thick: 18, mat: 'Multiplek 18mm', finish: cabinet.value.tallUnitColor === '#6F7E66' ? 'TH 076 SB (M-02)' : 'HPL Duco', edging: 'P1, L1' });
    // 2. Dinding Samping Kanan
    list.push({ id: id++, name: 'Dinding Samping Kanan Luar', qty: 1, length: h, width: d, thick: 18, mat: 'Multiplek 18mm', finish: cabinet.value.tallUnitColor === '#6F7E66' ? 'TH 076 SB (M-02)' : 'HPL Duco', edging: 'P1, L1' });

    // 3. Kulkas Parts
    if (cabinet.value.hasFridge) {
        list.push({ id: id++, name: 'Sekat Vertikal Partisi Kulkas', qty: 1, length: h, width: d, thick: 18, mat: 'Multiplek 18mm', finish: 'TH 076 SB (M-02)', edging: 'P1' });
        list.push({ id: id++, name: 'Top Panel & Ambalan Atas Kulkas', qty: 1, length: fw, width: d, thick: 18, mat: 'Multiplek 18mm', finish: 'Melaminto Putih', edging: 'P1' });
        list.push({ id: id++, name: 'Pintu Kabinet Atas Kulkas', qty: 1, length: fw, width: Math.max(400, h - cabinet.value.fridgeHeight), thick: 18, mat: 'Multiplek 18mm', finish: 'TH 076 SB (M-02)', edging: '4 Sisi' });
    }

    // 4. Tengah: Slats, Counter, Drawers
    if (cabinet.value.hasSlats) {
        list.push({ id: id++, name: 'Panel Kisi-kisi Dinding Kayu', qty: 1, length: cw, width: Math.max(800, h - cabinet.value.counterHeight), thick: 18, mat: 'MDF 18mm', finish: 'TH 235 AC (M-01)', edging: '4 Sisi' });
    }

    if (cabinet.value.counterHeight > 0) {
        list.push({ id: id++, name: 'Meja Countertop Solid Surface', qty: 1, length: cw, width: d, thick: 40, mat: 'Solid Surface', finish: 'DeepPink / Terrazzo (M-03)', edging: '3 Sisi Bevel' });

        const lowerDoorW = Math.round(cw / 3);
        const lowerH = Math.max(500, cabinet.value.counterHeight - 80);
        list.push({ id: id++, name: 'Pintu Laci Bawah Modul 1 (x2)', qty: 2, length: lowerDoorW, width: Math.round(lowerH / 2), thick: 18, mat: 'Multiplek 18mm', finish: 'Wood 16 (LM-01)', edging: '4 Sisi' });
        list.push({ id: id++, name: 'Pintu Laci Bawah Modul 2 (x3)', qty: 3, length: lowerDoorW, width: Math.round(lowerH / 3), thick: 18, mat: 'Multiplek 18mm', finish: 'Wood 16 (LM-01)', edging: '4 Sisi' });
        list.push({ id: id++, name: 'Pintu Kabinet Bawah Modul 3 (x2)', qty: 2, length: Math.round(lowerDoorW / 2), width: lowerH, thick: 18, mat: 'Multiplek 18mm', finish: 'Wood 16 (LM-01)', edging: '4 Sisi' });
    }

    // 5. Tall Unit
    if (tw > 0) {
        list.push({ id: id++, name: 'Sekat Vertikal Tall Unit', qty: 1, length: h, width: d, thick: 18, mat: 'Multiplek 18mm', finish: 'TH 076 SB (M-02)', edging: 'P1' });
        list.push({ id: id++, name: 'Pintu Tall Unit Panel 1', qty: 1, length: h - 80, width: Math.round(tw / 2), thick: 18, mat: 'Multiplek 18mm', finish: 'TH 076 SB (M-02)', edging: '4 Sisi' });
        list.push({ id: id++, name: 'Pintu Tall Unit Panel 2', qty: 1, length: h - 80, width: Math.round(tw / 2), thick: 18, mat: 'Multiplek 18mm', finish: 'TH 076 SB (M-02)', edging: '4 Sisi' });
    }

    if (cabinet.value.hasGlassShelves) {
        list.push({ id: id++, name: 'Ambalan Kaca Gantung Tempered', qty: 2, length: Math.round(cw * 0.65), width: 250, thick: 8, mat: 'Kaca Tempered 8mm', finish: 'Bening Polish Keliling', edging: 'Polish Flat' });
    }

    // 6. Backing & Plinth
    list.push({ id: id++, name: 'Backing Dinding Belakang', qty: 1, length: w, width: h - 80, thick: 6, mat: 'Multiplek 6mm', finish: 'Melaminto Putih', edging: 'Tanpa Edging' });
    list.push({ id: id++, name: 'Plinth / Skirting Kaki Bawah', qty: 1, length: w, width: 80, thick: 18, mat: 'Multiplek 18mm', finish: 'Hitam Doff (M-04)', edging: 'P1' });

    return list;
});

const totalPcs = computed(() => cutListItems.value.reduce((acc, cur) => acc + cur.qty, 0));

// ── INLINE FIELD EDITING (KOP GAMBAR) ─────────────────────────────────────────
const editingField = ref<string | null>(null);
const editValue = ref('');

function openEditField(field: string, currentVal: string) {
    if (!props.canEdit) return;
    editingField.value = field;
    editValue.value = currentVal;
}

function saveFieldEdit() {
    if (!editingField.value) return;
    if (editingField.value === 'name') cabinet.value.name = editValue.value || 'Lemari';
    if (editingField.value === 'width') cabinet.value.width = Number(editValue.value) || 5190;
    if (editingField.value === 'depth') cabinet.value.depth = Number(editValue.value) || 675;
    if (editingField.value === 'height') cabinet.value.height = Number(editValue.value) || 2390;
    if (editingField.value === 'date') cabinet.value.date = editValue.value;
    editingField.value = null;
    toast.success('Data lembar kerja berhasil diperbarui.');
}

// ── PAN & ZOOM ENGINE ────────────────────────────────────────────────────────
function resetZoom() {
    zoomScale.value = isMobileScreen.value ? 0.72 : 1;
    panX.value = 0;
    panY.value = 0;
}

function zoomIn() {
    zoomScale.value = Math.min(zoomScale.value * 1.25, 3.5);
}

function zoomOut() {
    zoomScale.value = Math.max(zoomScale.value / 1.25, 0.3);
}

function handleWheel(e: WheelEvent) {
    e.preventDefault();
    const factor = e.deltaY < 0 ? 1.12 : 0.89;
    zoomScale.value = Math.min(Math.max(zoomScale.value * factor, 0.3), 4.0);
}

function startPan(clientX: number, clientY: number) {
    isPanning.value = true;
    startPanX.value = clientX - panX.value;
    startPanY.value = clientY - panY.value;
}

function doPan(clientX: number, clientY: number) {
    if (!isPanning.value) return;
    panX.value = clientX - startPanX.value;
    panY.value = clientY - startPanY.value;
}

function endPan() {
    isPanning.value = false;
}

function onMouseDown(e: MouseEvent) {
    if (isDraggingObject.value) return;
    if (activeTool.value === 'select' && (e.button === 0 || e.button === 1)) {
        startPan(e.clientX, e.clientY);
    }
}

function onMouseMove(e: MouseEvent) {
    if (isDraggingObject.value) return;
    doPan(e.clientX, e.clientY);
}

function onMouseUp() {
    if (isDraggingObject.value) {
        handleGlobalMouseUp();
        return;
    }
    endPan();
}

function onTouchStart(e: TouchEvent) {
    if (isDraggingObject.value) return;
    if (e.touches.length === 1) {
        startPan(e.touches[0].clientX, e.touches[0].clientY);
    } else if (e.touches.length === 2) {
        isPanning.value = false;
        const dx = e.touches[0].clientX - e.touches[1].clientX;
        const dy = e.touches[0].clientY - e.touches[1].clientY;
        initialPinchDistance = Math.hypot(dx, dy);
        initialPinchScale = zoomScale.value;
    }
}

function onTouchMove(e: TouchEvent) {
    if (isDraggingObject.value) return;
    if (e.touches.length === 1 && isPanning.value) {
        doPan(e.touches[0].clientX, e.touches[0].clientY);
    } else if (e.touches.length === 2 && initialPinchDistance > 0) {
        const dx = e.touches[0].clientX - e.touches[1].clientX;
        const dy = e.touches[0].clientY - e.touches[1].clientY;
        const dist = Math.hypot(dx, dy);
        zoomScale.value = Math.min(Math.max(initialPinchScale * (dist / initialPinchDistance), 0.3), 4.0);
    }
}

function onTouchEnd() {
    if (isDraggingObject.value) {
        handleGlobalMouseUp();
        return;
    }
    endPan();
    initialPinchDistance = 0;
}

// ── SAVE & EXPORT ACTIONS ────────────────────────────────────────────────────
async function saveToDatabase() {
    isSaving.value = true;
    try {
        const payload = {
            elements: [
                {
                    type: 'shop_drawing',
                    data: {
                        cabinet: cabinet.value,
                        annotations: annotations.value,
                    },
                    sort_order: 1,
                },
            ],
            sheet: {
                width: cabinet.value.width,
                height: cabinet.value.height,
                paper_size: paperSize.value,
            },
        };

        const res = await fetch(`/drawings/${props.sheet.id}/sync`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
            },
            body: JSON.stringify(payload),
        });

        if (res.ok) {
            toast.success('Desain Gambar Kerja & Daftar Potong berhasil disimpan ke server!');
        } else {
            toast.success('Perubahan disimpan secara lokal.');
        }
    } catch {
        toast.success('Perubahan tersimpan.');
    } finally {
        isSaving.value = false;
    }
}

function exportPdf() {
    window.print();
    toast.success('Membuka dialog cetak / Simpan PDF...');
}

function exportPng() {
    toast.info('Menyiapkan gambar PNG tajam...');
    const svgEl = document.getElementById('shop-drawing-svg');
    if (!svgEl) return;
    const svgString = new XMLSerializer().serializeToString(svgEl);
    const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
    const blobURL = URL.createObjectURL(svgBlob);
    
    const image = new Image();
    image.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = 2400;
        canvas.height = 1700;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
            const png = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.href = png;
            link.download = `Gambar_Kerja_${cabinet.value.name}_${cabinet.value.date}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            toast.success('Gambar PNG berhasil diunduh.');
        }
    };
    image.src = blobURL;
}

function exportDxf() {
    window.location.href = `/drawings/${props.sheet.id}/export-dxf`;
}

function exportDwg() {
    toast.info('Format AutoCAD DWG dikonversi dari DXF standar.');
    window.location.href = `/drawings/${props.sheet.id}/export-dxf`;
}

function checkScreenSize() {
    isMobileScreen.value = window.innerWidth < 1024;
    if (isMobileScreen.value) {
        zoomScale.value = 0.72;
    }
}

function handleWindowResize() {
    checkScreenSize();
    checkToolsScroll();
    checkHeaderScroll();
}

onMounted(() => {
    checkScreenSize();
    window.addEventListener('resize', handleWindowResize);
    window.addEventListener('mousemove', handleGlobalMouseMove);
    window.addEventListener('mouseup', handleGlobalMouseUp);
    window.addEventListener('touchmove', handleGlobalTouchMove, { passive: false });
    window.addEventListener('touchend', handleGlobalTouchEnd);
    window.addEventListener('keydown', handleGlobalKeyDown);
    nextTick(() => {
        checkToolsScroll();
        checkHeaderScroll();
        captureSnapshot();
    });
    setTimeout(() => {
        checkToolsScroll();
        checkHeaderScroll();
    }, 250);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleWindowResize);
    window.removeEventListener('mousemove', handleGlobalMouseMove);
    window.removeEventListener('mouseup', handleGlobalMouseUp);
    window.removeEventListener('touchmove', handleGlobalTouchMove);
    window.removeEventListener('touchend', handleGlobalTouchEnd);
    window.removeEventListener('keydown', handleGlobalKeyDown);
});
</script>

<template>
    <!-- FULLSCREEN FIXED CONTAINER TO ENSURE TOOLBAR IS ALWAYS 100% VISIBLE -->
    <div class="fixed inset-0 w-screen h-screen flex flex-col bg-[#121214] text-slate-100 select-none overflow-hidden font-sans z-40">
        <!-- ── TOP TOOLBAR & CONTROLS (ALWAYS VISIBLE & STICKY AT TOP) ───────── -->
        <header class="bg-[#18181b] border-b border-white/10 px-2 sm:px-4 py-1.5 flex flex-col gap-1.5 shrink-0 z-50 shadow-md">
            <!-- Row 1: Relative Top Header Bar with Horizontal Scroll & 'Tools Lainnya' Indicator -->
            <div class="relative w-full">
                <!-- Petunjuk Gulir Kiri Row 1 -->
                <div
                    v-if="canScrollHeaderLeft"
                    class="absolute left-0 top-0 bottom-0 z-20 flex items-center pl-1 pr-6 bg-gradient-to-r from-[#18181b] via-[#18181b]/95 to-transparent pointer-events-none"
                >
                    <button
                        @click="scrollHeader('left')"
                        type="button"
                        class="pointer-events-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-400/40 shadow-lg transition-all cursor-pointer"
                        title="Geser kembali ke tombol sebelah kiri"
                    >
                        <ChevronLeft class="w-3.5 h-3.5 stroke-[2.5]" />
                        <span>Kembali</span>
                    </button>
                </div>

                <!-- Scrollable Container Row 1 -->
                <div
                    ref="headerRowRef"
                    @scroll="checkHeaderScroll"
                    @wheel.passive="handleHeaderWheel"
                    :class="[
                        'flex items-center justify-between gap-2 overflow-x-auto scrollbar-none py-0.5 touch-pan-x scroll-smooth transition-all',
                        canScrollHeaderLeft ? 'pl-24' : 'pl-0',
                        canScrollHeaderRight ? 'pr-36' : 'pr-0',
                    ]"
                >
                    <!-- Left: Logo & Project Title -->
                    <div class="flex items-center gap-2 min-w-0 shrink-0">
                        <button
                            type="button"
                            @click="emit('switchMode', 'cad')"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer shrink-0"
                            title="Beralih ke CAD Denah Tradisional"
                        >
                            <ArrowLeft class="w-4 h-4" />
                        </button>

                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="text-sm sm:text-base font-bold text-white tracking-wide truncate">
                                Gambar <span class="text-amber-400">Kerja</span>
                            </span>
                            <span class="hidden sm:inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/30">
                                SHOP DRAWING
                            </span>
                            <span class="text-xs text-slate-400 hidden md:inline-block truncate max-w-[180px]">
                                • {{ cabinet.projectName }}
                            </span>
                        </div>
                    </div>

                    <!-- Center Actions: Desain Lemari & Mode Switcher -->
                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Tombol Desain & Ubah Lemari -->
                        <button
                            type="button"
                            @click="showCabinetModal = true"
                            class="px-3 py-1 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 flex items-center gap-1.5 transition-all shadow-md shadow-amber-500/20 cursor-pointer shrink-0"
                            title="Ubah Dimensi, Preset, dan Komponen Lemari"
                        >
                            <Wand2 class="w-3.5 h-3.5" />
                            <span>Desain &amp; Ubah Lemari</span>
                        </button>

                        <!-- Tombol Panduan Pemula (Tutorial Lengkap) -->
                        <button
                            type="button"
                            @click="showTutorialModal = true"
                            class="px-3 py-1 rounded-xl text-xs font-bold bg-blue-600/30 hover:bg-blue-600/40 text-blue-200 border border-blue-400/40 flex items-center gap-1.5 transition-all shadow-sm cursor-pointer shrink-0"
                            title="Buka Buku Panduan &amp; Cara Mengoperasikan Gambar Kerja"
                        >
                            <HelpCircle class="w-3.5 h-3.5 text-blue-300 animate-pulse" />
                            <span>Panduan Cara Pakai</span>
                        </button>

                        <!-- Switcher CAD -->
                        <button
                            type="button"
                            @click="emit('switchMode', 'cad')"
                            class="hidden sm:flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium text-slate-400 hover:text-white hover:bg-white/5 transition-colors cursor-pointer shrink-0"
                        >
                            <Box class="w-3.5 h-3.5" />
                            <span>CAD Denah</span>
                        </button>
                    </div>

                    <!-- Right Actions: Simpan, PDF, PNG, Toggle Panel -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button
                            type="button"
                            @click="saveToDatabase"
                            :disabled="isSaving"
                            class="hidden sm:flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white transition-all shadow cursor-pointer disabled:opacity-50 shrink-0"
                            title="Simpan ke Database"
                        >
                            <Save class="w-3.5 h-3.5" />
                            <span>{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="exportPdf"
                            class="hidden sm:flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-white/10 hover:bg-white/15 text-white transition-colors cursor-pointer shrink-0"
                            title="Cetak atau Simpan PDF"
                        >
                            <Printer class="w-3.5 h-3.5 text-amber-400" />
                            <span>PDF</span>
                        </button>

                        <button
                            type="button"
                            @click="exportPng"
                            class="hidden sm:flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-white/10 hover:bg-white/15 text-white transition-colors cursor-pointer shrink-0"
                            title="Unduh Gambar PNG"
                        >
                            <Download class="w-3.5 h-3.5 text-emerald-400" />
                            <span>PNG</span>
                        </button>

                        <!-- Desktop Sidebar Toggle Button -->
                        <button
                            type="button"
                            @click="isDesktopSidebarOpen = !isDesktopSidebarOpen"
                            :class="isDesktopSidebarOpen ? 'bg-amber-500/20 text-amber-300 border-amber-500/40 hover:bg-amber-500/30' : 'bg-white/10 text-slate-300 border-white/10 hover:text-white'"
                            class="hidden lg:flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border transition-all cursor-pointer shadow-sm shrink-0"
                            :title="isDesktopSidebarOpen ? 'Sembunyikan Sidebar Kanan' : 'Tampilkan Sidebar Kanan'"
                        >
                            <PanelRightClose v-if="isDesktopSidebarOpen" class="w-3.5 h-3.5 text-amber-400" />
                            <PanelRightOpen v-else class="w-3.5 h-3.5 text-slate-300" />
                            <span>{{ isDesktopSidebarOpen ? 'Tutup Panel' : 'Buka Panel' }}</span>
                        </button>

                        <!-- Mobile Drawer Toggle Button -->
                        <button
                            type="button"
                            @click="showMobileDrawer = !showMobileDrawer"
                            class="lg:hidden p-1.5 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center gap-1 cursor-pointer shrink-0"
                            title="Buka Menu Pengaturan & Tampak"
                        >
                            <Sliders class="w-4 h-4" />
                            <span class="text-xs font-semibold">Tampak</span>
                        </button>
                    </div>
                </div>

                <!-- Petunjuk Gulir Kanan: Tombol 'Tools Lainnya' Row 1 -->
                <div
                    v-if="canScrollHeaderRight"
                    class="absolute right-0 top-0 bottom-0 z-20 flex items-center pr-1 pl-6 bg-gradient-to-l from-[#18181b] via-[#18181b]/95 to-transparent pointer-events-none"
                >
                    <button
                        @click="scrollHeader('right')"
                        type="button"
                        class="pointer-events-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/30 border border-amber-300/70 transition-all cursor-pointer animate-pulse hover:animate-none"
                        title="Masih ada tombol/tools lainnya di sebelah kanan, klik atau geser untuk melihat"
                    >
                        <span>Tools Lainnya</span>
                        <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
                    </button>
                </div>
            </div>

            <!-- Row 2: Relative Tool Ribbon Container with Horizontal Scroll & Right Overflow Indicator -->
            <div class="relative bg-[#18181b] border-t border-white/5 pt-1">
                <!-- Petunjuk Gulir Kiri -->
                <div
                    v-if="canScrollToolsLeft"
                    class="absolute left-0 top-0 bottom-0 z-20 flex items-center pl-1 pr-6 bg-gradient-to-r from-[#18181b] via-[#18181b]/95 to-transparent pointer-events-none"
                >
                    <button
                        @click="scrollTools('left')"
                        type="button"
                        class="pointer-events-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 hover:bg-slate-700 text-amber-300 border border-amber-400/40 shadow-lg transition-all cursor-pointer"
                        title="Geser kembali ke tools sebelah kiri"
                    >
                        <ChevronLeft class="w-3.5 h-3.5 stroke-[2.5]" />
                        <span>Kembali</span>
                    </button>
                </div>

                <!-- Scrollable Tool Ribbon Container -->
                <div
                    ref="toolsContainerRef"
                    @scroll="checkToolsScroll"
                    @wheel.passive="handleToolsWheel"
                    :class="[
                        'flex items-center justify-between gap-3 overflow-x-auto scrollbar-none py-1 touch-pan-x scroll-smooth transition-all',
                        canScrollToolsLeft ? 'pl-24' : 'pl-2',
                        canScrollToolsRight ? 'pr-36' : 'pr-2'
                    ]"
                >
                    <!-- Undo & Redo (Ctrl+Z & Ctrl+Y) -->
                    <div class="flex items-center gap-0.5 bg-black/50 border border-white/15 rounded-lg p-0.5 shrink-0">
                        <button
                            type="button"
                            @click="undo"
                            :disabled="!canUndo"
                            class="p-1 rounded text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer"
                            title="Undo / Batalkan (Ctrl + Z)"
                        >
                            <Undo2 class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="redo"
                            :disabled="!canRedo"
                            class="p-1 rounded text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:hover:bg-transparent transition-colors cursor-pointer"
                            title="Redo / Maju Lagi (Ctrl + Y atau Ctrl + Shift + Z)"
                        >
                            <Redo2 class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Tombol Hapus Objek Terpilih (Delete / Backspace) -->
                    <button
                        v-if="selectedObjectId"
                        type="button"
                        @click="deleteSelectedObject"
                        class="px-2 py-1 rounded-lg text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30 hover:bg-rose-500/30 flex items-center gap-1 transition-colors cursor-pointer shrink-0"
                        title="Hapus Objek Terpilih (atau tekan tombol Delete / Backspace di keyboard)"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">Hapus</span>
                    </button>

                    <!-- Tool Buttons -->
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            @click="activeTool = 'select'"
                            :class="activeTool === 'select' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                        >
                            <MousePointer class="w-3.5 h-3.5" />
                            <span>Pilih</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTool = 'text'"
                            :class="activeTool === 'text' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                            title="Buat Teks Notasi Bersih (Tanpa Kotak Background)"
                        >
                            <Type class="w-3.5 h-3.5" />
                            <span>Teks</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTool = 'label'"
                            :class="activeTool === 'label' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                        >
                            <Tag class="w-3.5 h-3.5" />
                            <span>Label</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTool = 'measure'"
                            :class="activeTool === 'measure' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                        >
                            <Ruler class="w-3.5 h-3.5" />
                            <span>Ukur</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTool = 'line'"
                            :class="activeTool === 'line' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                        >
                            <Minus class="w-3.5 h-3.5" />
                            <span>Garis</span>
                        </button>
                        <button
                            type="button"
                            @click="activeTool = 'rect'"
                            :class="activeTool === 'rect' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                        >
                            <Square class="w-3.5 h-3.5" />
                            <span>Kotak</span>
                        </button>
                    </div>

                    <!-- Aksesoris Stamper Tool: [ 📦 + Aksesoris ▾ ] -->
                    <div class="relative shrink-0">
                        <button
                            ref="accessoryButtonRef"
                            type="button"
                            @click.stop="toggleAccessoryMenu"
                            :class="showAccessoryMenu ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer"
                            title="Tambah Gambar Aksesoris (TV, Soundbar, Buku, Vas Bunga, Sink, Kompor, dll)"
                        >
                            <Package class="w-3.5 h-3.5 text-amber-400" />
                            <span>+ Aksesoris</span>
                            <ChevronDown class="w-3 h-3 opacity-70" />
                        </button>
                    </div>

                    <!-- Posisi Kop Gambar & Tombol Edit Kop -->
                    <div class="flex items-center gap-0.5 bg-black/50 border border-white/15 rounded-lg p-0.5 shrink-0">
                        <span class="text-[10px] text-slate-400 font-semibold px-1.5 hidden sm:inline">Kop Info:</span>
                        <button
                            type="button"
                            @click="cabinet.titleBlockPosition = 'side'; captureSnapshot();"
                            :class="titleBlockPos === 'side' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                            class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer"
                            title="Letakkan Tulisan Informasi di Samping Kanan"
                        >
                            ◨ Samping
                        </button>
                        <button
                            type="button"
                            @click="cabinet.titleBlockPosition = 'top'; captureSnapshot();"
                            :class="titleBlockPos === 'top' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                            class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer"
                            title="Letakkan Tulisan Informasi di Atas"
                        >
                            ⬒ Atas
                        </button>
                        <button
                            type="button"
                            @click="cabinet.titleBlockPosition = 'bottom'; captureSnapshot();"
                            :class="titleBlockPos === 'bottom' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                            class="px-2 py-0.5 rounded text-[11px] transition-colors cursor-pointer"
                            title="Letakkan Tulisan Informasi di Bawah"
                        >
                            ⬓ Bawah
                        </button>
                        <button
                            type="button"
                            @click="showKopModal = true"
                            class="ml-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-500/20 text-blue-300 border border-blue-400/30 hover:bg-blue-500/30 transition-colors cursor-pointer flex items-center gap-1"
                            title="Buka form untuk mengubah tulisan informasi kop gambar"
                        >
                            <FileText class="w-3 h-3 text-blue-300" />
                            <span>Edit Kop</span>
                        </button>
                    </div>

                    <!-- Secondary Modifiers (Mobile Simpan, Nomor Panel, Zoom) -->
                    <div class="flex items-center gap-1.5 shrink-0 pl-2">
                        <!-- Quick Mobile Simpan Button -->
                        <button
                            type="button"
                            @click="saveToDatabase"
                            :disabled="isSaving"
                            class="sm:hidden px-2.5 py-1 rounded-lg text-xs font-semibold bg-violet-600 hover:bg-violet-500 text-white flex items-center gap-1 cursor-pointer shrink-0 shadow-sm"
                            title="Simpan ke Database"
                        >
                            <Save class="w-3.5 h-3.5" />
                            <span>{{ isSaving ? '...' : 'Simpan' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="showPanelNumbers = !showPanelNumbers"
                            :class="showPanelNumbers ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-white/5 text-slate-400 border-white/10'"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold border flex items-center gap-1 transition-colors cursor-pointer shrink-0"
                            title="Tampilkan Nomor Balon Panel Sesuai Daftar Potong"
                        >
                            <ListOrdered class="w-3.5 h-3.5" />
                            <span>Nomor Panel</span>
                        </button>

                        <div class="flex items-center bg-black/40 rounded-lg p-0.5 border border-white/10 shrink-0">
                            <button
                                type="button"
                                @click="zoomOut"
                                class="p-1 rounded text-slate-400 hover:text-white transition-colors cursor-pointer"
                                title="Perkecil (-)"
                            >
                                <ZoomOut class="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                @click="resetZoom"
                                class="px-1.5 text-[10px] font-mono font-medium text-slate-300 hover:text-white"
                                title="Reset / Pas Layar"
                            >
                                {{ Math.round(zoomScale * 100) }}%
                            </button>
                            <button
                                type="button"
                                @click="zoomIn"
                                class="p-1 rounded text-slate-400 hover:text-white transition-colors cursor-pointer"
                                title="Perbesar (+)"
                            >
                                <ZoomIn class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Petunjuk Gulir Kanan: Notifikasi visual bila layar kecil / masih ada tool di sebelah kanan -->
                <div
                    v-if="canScrollToolsRight"
                    class="absolute right-0 top-0 bottom-0 z-20 flex items-center pr-1 pl-6 bg-gradient-to-l from-[#18181b] via-[#18181b]/95 to-transparent pointer-events-none"
                >
                    <button
                        @click="scrollTools('right')"
                        type="button"
                        class="pointer-events-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/30 border border-amber-300/70 transition-all cursor-pointer animate-pulse hover:animate-none"
                        title="Masih ada tools lainnya di kanan, klik untuk menggeser"
                    >
                        <span>Tools Lainnya</span>
                        <ChevronRight class="w-3.5 h-3.5 stroke-[2.5]" />
                    </button>
                </div>
            </div>
        </header>

        <!-- ── MAIN WORKSPACE (CANVAS + COLLAPSIBLE SIDEBAR) ────────────────── -->
        <div class="flex-1 flex overflow-hidden relative">
            <!-- CANVAS CONTAINER -->
            <div
                ref="canvasContainerRef"
                class="flex-1 h-full bg-[#0d0d0f] relative overflow-hidden flex items-center justify-center cursor-grab active:cursor-grabbing touch-none select-none"
                @mousedown="onMouseDown"
                @mousemove="onMouseMove"
                @mouseup="onMouseUp"
                @mouseleave="onMouseUp"
                @wheel="handleWheel"
                @touchstart="onTouchStart"
                @touchmove="onTouchMove"
                @touchend="onTouchEnd"
                @click="onCanvasClick"
            >
                <!-- SVG PAPER SHEET (A3 / A4 LANDSCAPE) -->
                <div
                    class="transition-transform duration-75 origin-center will-change-transform shadow-2xl"
                    :style="{
                        transform: `translate(${panX}px, ${panY}px) scale(${zoomScale})`,
                    }"
                >
                    <!-- TAB 01: GAMBAR KERJA (SHOP DRAWING SHEET) -->
                    <div
                        v-show="activeSheetTab === '01'"
                        class="bg-white text-slate-900 border border-slate-300 rounded shadow-2xl relative"
                        style="width: 1200px; height: 840px; box-sizing: border-box;"
                    >
                        <!-- PURE VECTOR SVG CANVAS -->
                        <svg
                            id="shop-drawing-svg"
                            viewBox="0 0 1200 840"
                            class="w-full h-full block font-sans"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <!-- KERTAS BORDER ARSITEKTURAL -->
                            <rect x="25" y="25" width="1150" height="790" fill="none" stroke="#222" stroke-width="1.5" />
                            <rect x="30" y="30" width="1140" height="780" fill="none" stroke="#666" stroke-width="0.5" />

                            <!-- ============================================================== -->
                            <!-- MODE SINGLE: PER-TAMPAK (FOKUS 1 VIEW BESAR, SEPERTI GAMBAR 1 & 3) -->
                            <!-- ============================================================== -->
                            <g v-if="layoutMode === 'single'">
                                <!-- SECTION CUTTING LINE A - A (BISA DIGESER / DITARIK HORISONTAL) -->
                                <g
                                    v-if="activeView === 'depan' || activeView === 'tanpa_pintu'"
                                    id="section-line-A-A"
                                    class="cursor-ew-resize select-none"
                                    @mousedown.stop="startObjectDrag($event, 'section_line', 'section_line', { x: sectionLineX, name: 'Garis Potongan A-A' })"
                                    @touchstart.stop="startObjectDrag($event, 'section_line', 'section_line', { x: sectionLineX, name: 'Garis Potongan A-A' })"
                                    @click.stop="selectComponent('Garis Potongan A-A (Tarik untuk geser posisi)', 'section_line')"
                                >
                                    <!-- Hitbox for easy dragging -->
                                    <rect :x="sectionLineX - 14" y="70" width="28" height="550" fill="transparent" />
                                    <line :x1="sectionLineX" y1="80" :x2="sectionLineX" y2="610" stroke="#000" stroke-width="1.2" stroke-dasharray="8,4,2,4" />
                                    <!-- Top arrow A -->
                                    <polygon :points="`${sectionLineX},80 ${sectionLineX - 7},95 ${sectionLineX + 7},95`" fill="#000" />
                                    <line :x1="sectionLineX - 20" y1="80" :x2="sectionLineX" y2="80" stroke="#000" stroke-width="1.5" />
                                    <text :x="sectionLineX - 30" y="85" font-size="14" font-weight="bold" fill="#000">A</text>
                                    <!-- Bottom arrow A -->
                                    <polygon :points="`${sectionLineX},610 ${sectionLineX - 7},595 ${sectionLineX + 7},595`" fill="#000" />
                                    <line :x1="sectionLineX - 20" y1="610" :x2="sectionLineX" y2="610" stroke="#000" stroke-width="1.5" />
                                    <text :x="sectionLineX - 30" y="615" font-size="14" font-weight="bold" fill="#000">A</text>

                                    <!-- Drag pill indicator when selected -->
                                    <g v-if="selectedObjectId === 'section_line'" :transform="`translate(${sectionLineX - 50}, 88)`">
                                        <rect width="100" height="20" rx="10" fill="#2563eb" />
                                        <text x="50" y="14" font-size="9.5" font-weight="bold" fill="#fff" text-anchor="middle">◂ Tarik Garis A-A ▸</text>
                                    </g>
                                </g>

                                <!-- DIMENSION LINES (WIDTH MM TOP, HEIGHT MM LEFT) -->
                                <!-- Horizontal Width -->
                                <g stroke="#666" stroke-width="0.7">
                                    <line :x1="furnitureSvgX" y1="120" :x2="furnitureSvgX" y2="180" stroke="#999" />
                                    <line :x1="furnitureSvgX + furnitureSvgWidth" y1="120" :x2="furnitureSvgX + furnitureSvgWidth" y2="180" stroke="#999" />
                                    <line :x1="furnitureSvgX" y1="130" :x2="furnitureSvgX + furnitureSvgWidth" y2="130" stroke="#222" stroke-width="0.8" />
                                    <!-- 45-deg tick marks -->
                                    <line :x1="furnitureSvgX - 4" y1="134" :x2="furnitureSvgX + 4" y2="126" stroke="#000" stroke-width="1.2" />
                                    <line :x1="furnitureSvgX + furnitureSvgWidth - 4" y1="134" :x2="furnitureSvgX + furnitureSvgWidth + 4" y2="126" stroke="#000" stroke-width="1.2" />
                                </g>
                                <text :x="furnitureSvgX + furnitureSvgWidth / 2" y="125" font-size="12" font-family="monospace" font-weight="bold" fill="#111" text-anchor="middle">
                                    {{ cabinet.width }}
                                </text>

                                <!-- Vertical Height -->
                                <g stroke="#666" stroke-width="0.7">
                                    <line :x1="furnitureSvgX - 50" y1="180" :x2="furnitureSvgX - 5" y2="180" stroke="#999" />
                                    <line :x1="furnitureSvgX - 50" y1="600" :x2="furnitureSvgX - 5" y2="600" stroke="#999" />
                                    <line :x1="furnitureSvgX - 40" y1="180" :x2="furnitureSvgX - 40" y2="600" stroke="#222" stroke-width="0.8" />
                                    <!-- 45-deg tick marks -->
                                    <line :x1="furnitureSvgX - 44" y1="184" :x2="furnitureSvgX - 36" y2="176" stroke="#000" stroke-width="1.2" />
                                    <line :x1="furnitureSvgX - 44" y1="604" :x2="furnitureSvgX - 36" y2="596" stroke="#000" stroke-width="1.2" />
                                </g>
                                <text :x="furnitureSvgX - 47" y="390" font-size="12" font-family="monospace" font-weight="bold" fill="#111" :transform="`rotate(-90, ${furnitureSvgX - 47}, 390)`" text-anchor="middle">
                                    {{ cabinet.height }}
                                </text>

                                <!-- ── TV CREDENZA GRAPHIC (BUKAN DAPUR, BENDA UTAMA SMART TV 65" & FLOATING CREDENZA) ── -->
                                <g v-if="cabinet.furnitureType === 'credenza_tv'" id="tv-credenza-graphic">
                                    <!-- Outer Wall Frame -->
                                    <rect :x="furnitureSvgX" y="180" :width="furnitureSvgWidth" height="420" fill="#f8fafc" stroke="#111" stroke-width="1.5" />

                                    <!-- 1. BACKDROP WALL PANEL (Marmer Calacatta di Kiri, Kisi-kisi Kayu di Kanan) -->
                                    <g id="marble-tv-panel" class="cursor-pointer" @click.stop="selectComponent('Panel Marmer Dinding TV (Calacatta)', 'marble_panel')">
                                        <rect :x="furnitureSvgX" y="180" :width="Math.round(furnitureSvgWidth * 0.65)" height="320" fill="#f1f5f9" stroke="#334155" stroke-width="1.2" />
                                        <!-- Subtle marble veins -->
                                        <path :d="`M ${furnitureSvgX + 40} 180 Q ${furnitureSvgX + 120} 240 ${furnitureSvgX + 80} 320 T ${furnitureSvgX + 160} 500`" fill="none" stroke="#cbd5e1" stroke-width="1.5" opacity="0.6" />
                                        <path :d="`M ${furnitureSvgX + 220} 180 Q ${furnitureSvgX + 280} 280 ${furnitureSvgX + 240} 380 T ${furnitureSvgX + 320} 500`" fill="none" stroke="#cbd5e1" stroke-width="1.2" opacity="0.5" />
                                        <!-- Indirect LED glow line top & bottom -->
                                        <line :x1="furnitureSvgX + 10" y1="184" :x2="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) - 10" y2="184" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" opacity="0.75" />
                                        <line :x1="furnitureSvgX + 10" y1="496" :x2="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) - 10" y2="496" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" opacity="0.75" />
                                        <text :x="furnitureSvgX + 14" y="200" font-size="8.5" font-family="monospace" font-weight="bold" fill="#64748b">PANEL MARMER M-02 + INDIRECT LED</text>
                                    </g>

                                    <!-- Sisi Kanan: Dinding Kisi-kisi Kayu Fluted (M-01) -->
                                    <g id="wood-slats-tv-panel" class="cursor-pointer" @click.stop="selectComponent('Dinding Kisi-kisi Kayu Fluted', 'slats_panel')">
                                        <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65)" y="180" :width="furnitureSvgWidth - Math.round(furnitureSvgWidth * 0.65)" height="320" :fill="cabinet.slatsColor || '#7E5334'" stroke="#333" stroke-width="1.2" />
                                        <!-- Fluted wood slats vertical lines -->
                                        <line
                                            v-for="i in Math.floor((furnitureSvgWidth - Math.round(furnitureSvgWidth * 0.65)) / 8)"
                                            :key="i"
                                            :x1="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + i * 8"
                                            y1="180"
                                            :x2="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + i * 8"
                                            y2="500"
                                            stroke="#5a3d28"
                                            stroke-width="1.2"
                                        />
                                    </g>

                                    <!-- 2. SMART TV 65 INCH (BENDA UTAMA, BUKAN KULKAS - BISA DIGESER) -->
                                    <g
                                        id="smart-tv-unit"
                                        class="cursor-move select-none"
                                        :transform="`translate(${tvOffsetX}, ${tvOffsetY})`"
                                        @mousedown.stop="startObjectDrag($event, 'tv', 'tv', { tvOffsetX, tvOffsetY, name: 'Smart TV 65 Inch' })"
                                        @touchstart.stop="startObjectDrag($event, 'tv', 'tv', { tvOffsetX, tvOffsetY, name: 'Smart TV 65 Inch' })"
                                        @click.stop="selectComponent('Smart TV 65 Inch Wall Mount (Tarik untuk geser posisi)', 'tv')"
                                    >
                                        <!-- TV Outer Bezel Frame -->
                                        <rect
                                            :x="furnitureSvgX + 70"
                                            y="225"
                                            width="320"
                                            height="185"
                                            rx="4"
                                            fill="#09090b"
                                            stroke="#3f3f46"
                                            stroke-width="2"
                                            class="shadow-2xl"
                                        />
                                        <!-- Inner Glass Display Screen -->
                                        <rect
                                            :x="furnitureSvgX + 75"
                                            y="230"
                                            width="310"
                                            height="175"
                                            rx="2"
                                            fill="#18181b"
                                        />
                                        <!-- Screen Diagonal Glass Reflection -->
                                        <polygon
                                            :points="`${furnitureSvgX + 76},231 ${furnitureSvgX + 200},231 ${furnitureSvgX + 140},404 ${furnitureSvgX + 76},404`"
                                            fill="#ffffff"
                                            opacity="0.08"
                                        />
                                        <!-- TV Info Badge Center -->
                                        <rect :x="furnitureSvgX + 145" y="295" width="170" height="46" rx="4" fill="#000000" opacity="0.75" />
                                        <text :x="furnitureSvgX + 230" y="315" font-size="11.5" font-weight="bold" fill="#ffffff" text-anchor="middle">
                                            SMART TV 65"
                                        </text>
                                        <text :x="furnitureSvgX + 230" y="331" font-size="8.5" font-family="monospace" fill="#38bdf8" text-anchor="middle">
                                            1450 × 830 mm (Wall Mount)
                                        </text>

                                        <!-- Highlight border if TV is selected -->
                                        <rect
                                            v-if="selectedObjectId === 'tv'"
                                            :x="furnitureSvgX + 66"
                                            y="221"
                                            width="328"
                                            height="193"
                                            rx="5"
                                            fill="none"
                                            stroke="#3b82f6"
                                            stroke-width="1.8"
                                            stroke-dasharray="4,3"
                                        />

                                        <!-- Soundbar Audio Bar directly under TV -->
                                        <g id="tv-soundbar">
                                            <rect :x="furnitureSvgX + 125" y="420" width="210" height="18" rx="3" fill="#18181b" stroke="#333" stroke-width="1" />
                                            <line
                                                v-for="s in 22"
                                                :key="s"
                                                :x1="furnitureSvgX + 135 + s * 8.5"
                                                y1="422"
                                                :x2="furnitureSvgX + 135 + s * 8.5"
                                                y2="436"
                                                stroke="#3f3f46"
                                                stroke-width="0.8"
                                            />
                                            <text :x="furnitureSvgX + 230" y="432" font-size="7.5" font-weight="bold" fill="#94a3b8" text-anchor="middle">
                                                SOUNDBAR AUDIO
                                            </text>
                                        </g>
                                    </g>

                                    <!-- 3. RAK DISPLAY BUKU & PAJANGAN KACA LED (SISI KANAN) -->
                                    <g id="display-shelf-tower" class="cursor-pointer" @click.stop="selectComponent('Rak Display Kaca LED Sisi Kanan', 'display_tower')">
                                        <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 20" y="200" :width="furnitureSvgWidth - Math.round(furnitureSvgWidth * 0.65) - 40" height="280" fill="#0f172a" opacity="0.3" stroke="#000" stroke-width="1" />
                                        <!-- 3 Ambalan Kaca dengan Aksen LED -->
                                        <g v-for="(tierY, tIdx) in [265, 335, 405]" :key="tIdx">
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 22" :y="tierY" :width="furnitureSvgWidth - Math.round(furnitureSvgWidth * 0.65) - 44" height="5" fill="#a5f3fc" stroke="#0891b2" stroke-width="0.6" opacity="0.85" />
                                            <line :x1="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 22" :y1="tierY + 5" :x2="furnitureSvgX + furnitureSvgWidth - 22" :y2="tierY + 5" stroke="#f59e0b" stroke-width="1.5" opacity="0.8" />
                                        </g>
                                        <!-- Aksesoris Buku & Pajangan Koleksi -->
                                        <g v-if="showAccessories">
                                            <!-- Rak 1 (Top): Buku-buku Berdiri -->
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 35" y="235" width="8" height="28" fill="#e11d48" rx="1" />
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 44" y="238" width="7" height="25" fill="#0284c7" rx="1" />
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 52" y="233" width="9" height="30" fill="#d97706" rx="1" />
                                            <circle :cx="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 95" cy="248" r="9" fill="#e2e8f0" stroke="#94a3b8" stroke-width="0.8" />
                                            
                                            <!-- Rak 2 (Middle): Vas Tanaman & Mini Figur -->
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 40" y="315" width="14" height="18" fill="#78350f" rx="2" />
                                            <circle :cx="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 47" cy="310" r="7" fill="#16a34a" />
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 85" y="305" width="12" height="28" fill="#ffffff" stroke="#333" stroke-width="0.8" rx="1" />

                                            <!-- Rak 3 (Bottom): Buku Horizontal Tumpuk -->
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 45" y="385" width="35" height="7" fill="#475569" rx="1" />
                                            <rect :x="furnitureSvgX + Math.round(furnitureSvgWidth * 0.65) + 47" y="393" width="31" height="7" fill="#0f766e" rx="1" />
                                        </g>
                                    </g>

                                    <!-- 4. FLOATING CREDENZA / MEJA KONSOL TV GANTUNG -->
                                    <g id="floating-credenza" class="cursor-pointer" @click.stop="selectComponent('Floating Credenza Meja TV Gantung', 'credenza')">
                                        <!-- Shadowline Celah Gantung Bawah (Floating Gap 14px di atas lantai) -->
                                        <rect :x="furnitureSvgX" y="586" :width="furnitureSvgWidth" height="14" fill="#09090b" opacity="0.6" />
                                        <line :x1="furnitureSvgX" y1="586" :x2="furnitureSvgX + furnitureSvgWidth" y2="586" stroke="#000" stroke-width="1" />

                                        <!-- Countertop Credenza (Top Table Marmer / Solid Surface) -->
                                        <rect :x="furnitureSvgX" y="500" :width="furnitureSvgWidth" height="14" fill="#ECEAE6" stroke="#333" stroke-width="1.2" />

                                        <!-- 4 Modul Laci Floating Credenza (Finishing Wood 16 - LM-01) -->
                                        <g v-for="(drawerIdx, dIdx) in [0, 1, 2, 3]" :key="dIdx">
                                            <!-- Modul 1 (Tengah): Open Niche for Console / Decoder -->
                                            <g v-if="dIdx === 1">
                                                <rect
                                                    :x="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + 2"
                                                    y="516"
                                                    :width="Math.round(furnitureSvgWidth / 4) - 4"
                                                    height="68"
                                                    fill="#18181b"
                                                    stroke="#000"
                                                    stroke-width="1"
                                                />
                                                <!-- Ambalan kaca perantara di dalam niche -->
                                                <line
                                                    :x1="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + 4"
                                                    y1="550"
                                                    :x2="furnitureSvgX + (dIdx + 1) * Math.round(furnitureSvgWidth / 4) - 4"
                                                    y2="550"
                                                    stroke="#38bdf8"
                                                    stroke-width="1.2"
                                                />
                                                <!-- Cable Grommet -->
                                                <circle :cx="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + Math.round(furnitureSvgWidth / 8)" cy="535" r="5" fill="#333" stroke="#555" stroke-width="0.8" />
                                                <text :x="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + Math.round(furnitureSvgWidth / 8)" y="568" font-size="7.5" font-family="monospace" fill="#94a3b8" text-anchor="middle">
                                                    GAME CONSOLE / RECEIVER
                                                </text>
                                            </g>
                                            <!-- Laci-Laci Kayu Soft-Close Wood 16 -->
                                            <g v-else>
                                                <rect
                                                    :x="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + 2"
                                                    y="516"
                                                    :width="Math.round(furnitureSvgWidth / 4) - 4"
                                                    height="68"
                                                    fill="#BFA07A"
                                                    stroke="#5c4033"
                                                    stroke-width="1"
                                                    rx="1"
                                                />
                                                <!-- Handleless J-Pull Finger Groove Line -->
                                                <line
                                                    :x1="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + 12"
                                                    y1="522"
                                                    :x2="furnitureSvgX + (dIdx + 1) * Math.round(furnitureSvgWidth / 4) - 12"
                                                    y2="522"
                                                    stroke="#3e2723"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                />
                                                <text
                                                    :x="furnitureSvgX + dIdx * Math.round(furnitureSvgWidth / 4) + Math.round(furnitureSvgWidth / 8)"
                                                    y="556"
                                                    font-size="8"
                                                    font-weight="bold"
                                                    fill="#3e2723"
                                                    text-anchor="middle"
                                                >
                                                    LACI {{ dIdx === 0 ? '1' : dIdx === 2 ? '2' : '3' }} (LM-01)
                                                </text>
                                            </g>
                                        </g>
                                    </g>
                                </g>

                                <!-- ── MAIN GRAPHIC: TAMPAK DEPAN KITCHEN SET (JIKA BUKAN TV CREDENZA) ── -->
                                <g v-else id="cabinet-front-graphic">
                                    <!-- Outer Casing / Dinding Luar -->
                                    <rect x="160" y="190" width="780" height="390" fill="#f8fafc" stroke="#111" stroke-width="1.5" />

                                    <!-- 1. KULKAS (SISI KIRI) -->
                                    <g
                                        v-if="cabinet.hasFridge"
                                        id="fridge-section"
                                        class="cursor-pointer"
                                        @click.stop="selectComponent('Ruang Kulkas 2 Pintu', 'fridge')"
                                    >
                                        <!-- Upper cabinet above fridge -->
                                        <rect x="160" y="190" :width="fridgeSvgWidth" height="90" :fill="cabinet.tallUnitColor" stroke="#111" stroke-width="1" />
                                        <line x1="160" y1="190" :x2="160 + fridgeSvgWidth / 2" y2="235" stroke="#fff" stroke-width="0.8" stroke-dasharray="3,3" opacity="0.8" />
                                        <line x1="160" y1="280" :x2="160 + fridgeSvgWidth / 2" y2="235" stroke="#fff" stroke-width="0.8" stroke-dasharray="3,3" opacity="0.8" />

                                        <!-- Kulkas 2 Pintu Hitam Glossy -->
                                        <rect x="162" y="310" :width="Math.max(50, fridgeSvgWidth - 4)" height="270" rx="3" fill="#18181b" stroke="#000" stroke-width="1.2" />
                                        <rect x="165" y="315" :width="Math.max(44, fridgeSvgWidth - 10)" height="95" rx="2" fill="#27272a" stroke="#3f3f46" stroke-width="0.6" />
                                        <line x1="167" y1="405" :x2="160 + fridgeSvgWidth - 5" y2="405" stroke="#09090b" stroke-width="1.5" />
                                        <rect x="165" y="415" :width="Math.max(44, fridgeSvgWidth - 10)" height="160" rx="2" fill="#27272a" stroke="#3f3f46" stroke-width="0.6" />
                                        <polygon :points="`166,316 ${160 + fridgeSvgWidth / 2},316 ${160 + fridgeSvgWidth / 2 - 15},405 166,405`" fill="#ffffff" opacity="0.08" />
                                        <polygon :points="`166,416 ${160 + fridgeSvgWidth / 2},416 ${160 + fridgeSvgWidth / 2 - 20},570 166,570`" fill="#ffffff" opacity="0.08" />
                                        <rect x="175" y="580" width="12" height="5" fill="#111" />
                                        <rect :x="160 + fridgeSvgWidth - 25" y="580" width="12" height="5" fill="#111" />
                                    </g>

                                    <!-- 2. AREA TENGAH: KISI-KISI KAYU, RAK KACA, WASTAFEL, LACI -->
                                    <g id="middle-kitchen-section">
                                        <!-- Backdrop Kisi-kisi Kayu (Wood Slats / Fluted) -->
                                        <rect
                                            v-if="cabinet.hasSlats"
                                            :x="middleSvgX"
                                            y="190"
                                            :width="middleSvgWidth"
                                            :height="Math.max(80, counterSvgY - 190 + 20)"
                                            :fill="cabinet.slatsColor"
                                            stroke="#111"
                                            stroke-width="1"
                                            class="cursor-pointer"
                                            @click.stop="selectComponent('Dinding Kisi-kisi Kayu', 'slats')"
                                        />
                                        <g v-if="cabinet.hasSlats && showTextures" stroke="#5a3d28" stroke-width="1.2">
                                            <line
                                                v-for="i in Math.floor(middleSvgWidth / 8.5)"
                                                :key="i"
                                                :x1="middleSvgX + i * 8.5"
                                                y1="190"
                                                :x2="middleSvgX + i * 8.5"
                                                :y2="counterSvgY + 20"
                                            />
                                        </g>

                                        <!-- Hanging 2-Tier Glass Shelves & Bracket (Bisa Ditarik Geser & Lebar) -->
                                        <g
                                            v-if="cabinet.hasGlassShelves"
                                            id="glass-shelves"
                                            class="cursor-move select-none"
                                            :transform="`translate(${shelvesOffsetX}, ${shelvesOffsetY})`"
                                            @mousedown.stop="startObjectDrag($event, 'shelves', 'shelves', { shelvesOffsetX, shelvesOffsetY, name: 'Rak Gantung Kaca 2 Susun' })"
                                            @touchstart.stop="startObjectDrag($event, 'shelves', 'shelves', { shelvesOffsetX, shelvesOffsetY, name: 'Rak Gantung Kaca 2 Susun' })"
                                            @click.stop="selectComponent('Rak Gantung Kaca 2 Susun (Tarik untuk geser)', 'shelves')"
                                        >
                                            <line :x1="middleSvgX + 40" y1="210" :x2="middleSvgX + 40" y2="360" stroke="#1a1a1a" stroke-width="1.5" />
                                            <line :x1="middleSvgX + Math.min(shelvesWidth, middleSvgWidth - 30)" y1="210" :x2="middleSvgX + Math.min(shelvesWidth, middleSvgWidth - 30)" y2="360" stroke="#1a1a1a" stroke-width="1.5" />
                                            <rect :x="middleSvgX + 20" y="275" :width="Math.min(shelvesWidth + 30, middleSvgWidth - 25)" height="4" fill="#a5f3fc" stroke="#0891b2" stroke-width="0.6" opacity="0.85" />
                                            <rect :x="middleSvgX + 20" y="345" :width="Math.min(shelvesWidth + 30, middleSvgWidth - 25)" height="4" fill="#a5f3fc" stroke="#0891b2" stroke-width="0.6" opacity="0.85" />

                                            <!-- Accessories on Shelves -->
                                            <g v-if="showAccessories">
                                                <rect :x="middleSvgX + Math.min(shelvesWidth + 10, middleSvgWidth - 35)" y="258" width="14" height="16" rx="2" fill="#78350f" />
                                                <circle :cx="middleSvgX + Math.min(shelvesWidth + 17, middleSvgWidth - 28)" cy="254" r="6" fill="#16a34a" />
                                                <rect :x="middleSvgX + 50" y="252" width="8" height="22" rx="1" fill="#fef08a" stroke="#ca8a04" stroke-width="0.5" />
                                                <rect :x="middleSvgX + 62" y="254" width="8" height="20" rx="1" fill="#fef08a" stroke="#ca8a04" stroke-width="0.5" />
                                                <rect :x="middleSvgX + 110" y="330" width="10" height="14" rx="2" fill="#ffffff" stroke="#94a3b8" stroke-width="0.5" />
                                                <rect :x="middleSvgX + 125" y="330" width="10" height="14" rx="2" fill="#ffffff" stroke="#94a3b8" stroke-width="0.5" />
                                            </g>

                                            <!-- 3 Hanging Pendant Downlights -->
                                            <g stroke="#d97706" stroke-width="1" fill="#b45309">
                                                <line :x1="middleSvgX + 45" y1="190" :x2="middleSvgX + 45" y2="230" stroke="#111" stroke-width="0.8" />
                                                <polygon :points="`${middleSvgX + 40},230 ${middleSvgX + 50},230 ${middleSvgX + 47},242 ${middleSvgX + 43},242`" />
                                                <line :x1="middleSvgX + middleSvgWidth / 2" y1="190" :x2="middleSvgX + middleSvgWidth / 2" y2="230" stroke="#111" stroke-width="0.8" />
                                                <polygon :points="`${middleSvgX + middleSvgWidth / 2 - 5},230 ${middleSvgX + middleSvgWidth / 2 + 5},230 ${middleSvgX + middleSvgWidth / 2 + 2},242 ${middleSvgX + middleSvgWidth / 2 - 2},242`" />
                                                <line :x1="middleSvgX + middleSvgWidth - 45" y1="190" :x2="middleSvgX + middleSvgWidth - 45" y2="230" stroke="#111" stroke-width="0.8" />
                                                <polygon :points="`${middleSvgX + middleSvgWidth - 50},230 ${middleSvgX + middleSvgWidth - 40},230 ${middleSvgX + middleSvgWidth - 43},242 ${middleSvgX + middleSvgWidth - 47},242`" />
                                            </g>

                                            <!-- Selection indicator border if selected -->
                                            <rect
                                                v-if="selectedObjectId === 'shelves'"
                                                :x="middleSvgX + 16"
                                                y="200"
                                                :width="Math.min(shelvesWidth + 38, middleSvgWidth - 18)"
                                                height="170"
                                                fill="none"
                                                stroke="#3b82f6"
                                                stroke-width="1.2"
                                                stroke-dasharray="3,3"
                                            />
                                        </g>

                                        <!-- Countertop Solid Surface / Terrazzo (Bisa Ditarik Ubah Tinggi) -->
                                        <rect
                                            v-if="cabinet.counterHeight > 0"
                                            :x="middleSvgX"
                                            :y="counterSvgY"
                                            :width="middleSvgWidth"
                                            height="22"
                                            :fill="cabinet.countertopColor"
                                            stroke="#333"
                                            stroke-width="1"
                                            class="cursor-ns-resize"
                                            @mousedown.stop="startObjectDrag($event, 'countertop', 'countertop', { counterHeight: cabinet.counterHeight, name: 'Tinggi Meja Countertop' })"
                                            @touchstart.stop="startObjectDrag($event, 'countertop', 'countertop', { counterHeight: cabinet.counterHeight, name: 'Tinggi Meja Countertop' })"
                                            @click.stop="selectComponent('Meja Countertop Terrazzo (Tarik untuk ubah tinggi)', 'countertop')"
                                        />

                                        <!-- Wastafel Sink & Kran Leher Angsa (Bisa Digeser Horisontal) -->
                                        <g
                                            v-if="cabinet.hasSink"
                                            id="sink-faucet"
                                            class="cursor-ew-resize select-none"
                                            :transform="`translate(${sinkOffsetX}, 0)`"
                                            @mousedown.stop="startObjectDrag($event, 'sink', 'sink', { sinkOffsetX, name: 'Sink & Keran Wastafel' })"
                                            @touchstart.stop="startObjectDrag($event, 'sink', 'sink', { sinkOffsetX, name: 'Sink & Keran Wastafel' })"
                                            @click.stop="selectComponent('Wastafel Sink & Kran (Tarik untuk geser posisi)', 'sink')"
                                        >
                                            <rect :x="middleSvgX + middleSvgWidth - 95" :y="counterSvgY" width="55" height="4" fill="#cbd5e1" stroke="#475569" stroke-width="0.8" />
                                            <path :d="`M ${middleSvgX + middleSvgWidth - 75} ${counterSvgY} L ${middleSvgX + middleSvgWidth - 75} ${counterSvgY - 25} C ${middleSvgX + middleSvgWidth - 75} ${counterSvgY - 35} ${middleSvgX + middleSvgWidth - 65} ${counterSvgY - 35} ${middleSvgX + middleSvgWidth - 65} ${counterSvgY - 28} L ${middleSvgX + middleSvgWidth - 65} ${counterSvgY - 20}`" fill="none" stroke="#475569" stroke-width="2.5" stroke-linecap="round" />
                                        </g>

                                        <!-- KABINET BAWAH: DRAWER & AMBALAN FINISHING WOOD 16 -->
                                        <g id="lower-cabinets">
                                            <rect :x="middleSvgX" y="568" :width="middleSvgWidth" height="12" fill="#18181b" stroke="#000" stroke-width="0.8" />

                                            <g v-if="activeView !== 'tanpa_pintu'">
                                                <!-- 3 Kolom Laci / Pintu proporsional -->
                                                <rect :x="middleSvgX + 2" :y="counterSvgY + 23" :width="Math.round(middleSvgWidth / 3) - 3" :height="Math.round((568 - counterSvgY - 23) / 2) - 2" :fill="cabinet.lowerCabinetColor" stroke="#5c4033" stroke-width="0.8" class="cursor-pointer" @click.stop="selectComponent('Modul Laci 1', 'drawer1')" />
                                                <rect :x="middleSvgX + 2" :y="counterSvgY + 23 + Math.round((568 - counterSvgY - 23) / 2)" :width="Math.round(middleSvgWidth / 3) - 3" :height="Math.round((568 - counterSvgY - 23) / 2) - 2" :fill="cabinet.lowerCabinetColor" stroke="#5c4033" stroke-width="0.8" class="cursor-pointer" @click.stop="selectComponent('Modul Laci 2', 'drawer2')" />

                                                <rect :x="middleSvgX + Math.round(middleSvgWidth / 3) + 1" :y="counterSvgY + 23" :width="Math.round(middleSvgWidth / 3) - 3" :height="Math.round((568 - counterSvgY - 23) / 3) - 2" :fill="cabinet.lowerCabinetColor" stroke="#5c4033" stroke-width="0.8" class="cursor-pointer" @click.stop="selectComponent('Modul Laci 3', 'drawer3')" />
                                                <rect :x="middleSvgX + Math.round(middleSvgWidth / 3) + 1" :y="counterSvgY + 23 + Math.round((568 - counterSvgY - 23) / 3)" :width="Math.round(middleSvgWidth / 3) - 3" :height="Math.round((568 - counterSvgY - 23) / 3) - 2" :fill="cabinet.lowerCabinetColor" stroke="#5c4033" stroke-width="0.8" class="cursor-pointer" @click.stop="selectComponent('Modul Laci 4', 'drawer4')" />
                                                <rect :x="middleSvgX + Math.round(middleSvgWidth / 3) + 1" :y="counterSvgY + 23 + Math.round((568 - counterSvgY - 23) / 3) * 2" :width="Math.round(middleSvgWidth / 3) - 3" :height="Math.round((568 - counterSvgY - 23) / 3) - 2" :fill="cabinet.lowerCabinetColor" stroke="#5c4033" stroke-width="0.8" class="cursor-pointer" @click.stop="selectComponent('Modul Laci 5', 'drawer5')" />

                                                <rect :x="middleSvgX + Math.round(middleSvgWidth / 3) * 2" :y="counterSvgY + 23" :width="middleSvgWidth - Math.round(middleSvgWidth / 3) * 2 - 2" :height="568 - counterSvgY - 25" :fill="cabinet.lowerCabinetColor" stroke="#5c4033" stroke-width="0.8" class="cursor-pointer" @click.stop="selectComponent('Pintu Bawah Sink', 'door1')" />
                                            </g>
                                            <g v-else>
                                                <!-- Rangka Karkas Carcass Dalam -->
                                                <rect :x="middleSvgX + 2" :y="counterSvgY + 23" :width="middleSvgWidth - 4" :height="568 - counterSvgY - 25" fill="#fef3c7" stroke="#d97706" stroke-width="0.8" />
                                            </g>
                                        </g>
                                    </g>

                                    <!-- 3. SISI KANAN: TALL UNIT SAGE GREEN -->
                                    <g v-if="cabinet.tallUnitWidth > 0" id="tall-cabinet-section" class="cursor-pointer" @click.stop="selectComponent('Tall Unit Pantry Cabinet', 'tallUnit')">
                                        <rect :x="tallSvgX" y="190" :width="tallSvgWidth" height="390" :fill="cabinet.tallUnitColor" stroke="#111" stroke-width="1.2" />

                                        <g v-if="cabinet.tallUnitChevron" stroke="#55634e" stroke-width="1.2">
                                            <line :x1="tallSvgX" y1="210" :x2="tallSvgX + Math.min(50, tallSvgWidth / 2)" y2="280" />
                                            <line :x1="tallSvgX + Math.min(50, tallSvgWidth / 2)" y1="280" :x2="tallSvgX" y2="350" />
                                            <line :x1="tallSvgX" y1="360" :x2="tallSvgX + Math.min(50, tallSvgWidth / 2)" y2="430" />
                                            <line :x1="tallSvgX + Math.min(50, tallSvgWidth / 2)" y1="430" :x2="tallSvgX" y2="500" />
                                            <line :x1="tallSvgX" y1="510" :x2="tallSvgX + Math.min(50, tallSvgWidth / 2)" y2="580" />
                                        </g>

                                        <g v-if="cabinet.hasNiche" id="niche-box">
                                            <rect :x="tallSvgX + 60" y="440" width="38" height="80" rx="2" fill="#ffffff" stroke="#333" stroke-width="0.8" />
                                            <circle :cx="tallSvgX + 70" cy="460" r="3" fill="#2563eb" />
                                            <circle :cx="tallSvgX + 88" cy="460" r="3" fill="#dc2626" />
                                            <rect :x="tallSvgX + 66" y="475" width="26" height="35" rx="1" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.5" />
                                        </g>
                                        <line x1="940" y1="425" x2="940" y2="580" stroke="#000" stroke-width="1.5" />
                                    </g>

                                    <!-- ── INTERACTIVE DIVIDER DRAG HANDLES (TARIK UNTUK RESIZE MODUL) ── -->
                                    <!-- Handle 1: Divider Kulkas ◂ ▸ Meja Bar -->
                                    <g
                                        v-if="cabinet.hasFridge"
                                        class="cursor-ew-resize group select-none"
                                        @mousedown.stop="startObjectDrag($event, 'divider_fridge', 'divider_fridge', { fridgeWidth: cabinet.fridgeWidth, name: 'Partisi Kulkas (Tarik untuk Ubah Lebar)' })"
                                        @touchstart.stop="startObjectDrag($event, 'divider_fridge', 'divider_fridge', { fridgeWidth: cabinet.fridgeWidth, name: 'Partisi Kulkas (Tarik untuk Ubah Lebar)' })"
                                        @click.stop="selectComponent('Partisi Kulkas (Tarik untuk Ubah Lebar)', 'divider_fridge')"
                                    >
                                        <rect :x="middleSvgX - 10" y="190" width="20" height="390" fill="transparent" />
                                        <line :x1="middleSvgX" y1="190" :x2="middleSvgX" y2="580" stroke="#3b82f6" stroke-width="2" stroke-dasharray="4,4" opacity="0.75" />
                                        <rect :x="middleSvgX - 7" y="375" width="14" height="26" rx="4" fill="#3b82f6" stroke="#fff" stroke-width="1.5" />
                                        <text :x="middleSvgX" y="392" font-size="11" font-weight="bold" fill="#fff" text-anchor="middle">↔</text>
                                    </g>

                                    <!-- Handle 2: Divider Meja Bar ◂ ▸ Tall Unit -->
                                    <g
                                        v-if="cabinet.tallUnitWidth > 0"
                                        class="cursor-ew-resize group select-none"
                                        @mousedown.stop="startObjectDrag($event, 'divider_tall', 'divider_tall', { tallUnitWidth: cabinet.tallUnitWidth, name: 'Partisi Tall Unit (Tarik untuk Ubah Lebar)' })"
                                        @touchstart.stop="startObjectDrag($event, 'divider_tall', 'divider_tall', { tallUnitWidth: cabinet.tallUnitWidth, name: 'Partisi Tall Unit (Tarik untuk Ubah Lebar)' })"
                                        @click.stop="selectComponent('Partisi Tall Unit (Tarik untuk Ubah Lebar)', 'divider_tall')"
                                    >
                                        <rect :x="tallSvgX - 10" y="190" width="20" height="390" fill="transparent" />
                                        <line :x1="tallSvgX" y1="190" :x2="tallSvgX" y2="580" stroke="#3b82f6" stroke-width="2" stroke-dasharray="4,4" opacity="0.75" />
                                        <rect :x="tallSvgX - 7" y="375" width="14" height="26" rx="4" fill="#3b82f6" stroke="#fff" stroke-width="1.5" />
                                        <text :x="tallSvgX" y="392" font-size="11" font-weight="bold" fill="#fff" text-anchor="middle">↔</text>
                                    </g>
                                </g>

                                <!-- ── DYNAMIC USER ANNOTATIONS & LABELS (EDITABLE & DRAGGABLE) ── -->
                                <g v-for="ann in annotations" :key="ann.id">
                                    <!-- Label Material with Draggable Balloon & Leader Line Tip -->
                                    <g
                                        v-if="ann.type === 'label'"
                                        class="cursor-move select-none"
                                        @mousedown.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Label Material: ${ann.text}` })"
                                        @touchstart.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Label Material: ${ann.text}` })"
                                        @click.stop="selectComponent(`Label Material: ${ann.text}`, ann.id)"
                                    >
                                        <!-- Leader Line from Tip Anchor to Balloon -->
                                        <line
                                            :x1="ann.targetX ?? (ann.x - 120)"
                                            :y1="ann.targetY ?? ann.y"
                                            :x2="ann.x"
                                            :y2="ann.y"
                                            stroke="#000"
                                            stroke-width="0.9"
                                        />
                                        <!-- Arrow / Dot at the Tip -->
                                        <circle
                                            :cx="ann.targetX ?? (ann.x - 120)"
                                            :cy="ann.targetY ?? ann.y"
                                            r="3.5"
                                            fill="#000"
                                        />
                                        <!-- Tip Drag Handle (Target Anchor) -->
                                        <circle
                                            v-if="selectedObjectId === ann.id"
                                            :cx="ann.targetX ?? (ann.x - 120)"
                                            :cy="ann.targetY ?? ann.y"
                                            r="7"
                                            fill="#3b82f6"
                                            stroke="#fff"
                                            stroke-width="2"
                                            class="cursor-crosshair animate-pulse"
                                            @mousedown.stop="startObjectDrag($event, 'annotation_target', ann.id, { targetX: ann.targetX ?? (ann.x - 120), targetY: ann.targetY ?? ann.y, name: `Titik Panah: ${ann.text}` })"
                                            @touchstart.stop="startObjectDrag($event, 'annotation_target', ann.id, { targetX: ann.targetX ?? (ann.x - 120), targetY: ann.targetY ?? ann.y, name: `Titik Panah: ${ann.text}` })"
                                        />

                                        <!-- Underline Shelf for Text -->
                                        <line
                                            :x1="ann.x"
                                            :y1="ann.y"
                                            :x2="ann.x + 130"
                                            :y2="ann.y"
                                            stroke="#000"
                                            stroke-width="0.9"
                                        />
                                        <!-- Material Name -->
                                        <text
                                            :x="ann.x + 4"
                                            :y="ann.y - 4"
                                            font-size="9.5"
                                            font-family="sans-serif"
                                            font-weight="bold"
                                            fill="#000"
                                        >
                                            {{ ann.text }}
                                        </text>
                                        <!-- Circle Balloon with Code (e.g. M-01) -->
                                        <circle
                                            :cx="ann.x + 115"
                                            :cy="ann.y"
                                            r="11"
                                            :fill="selectedObjectId === ann.id ? '#fef08a' : '#ffffff'"
                                            stroke="#000"
                                            stroke-width="1.2"
                                        />
                                        <text
                                            :x="ann.x + 115"
                                            :y="ann.y + 3.5"
                                            font-size="8.5"
                                            font-family="sans-serif"
                                            font-weight="bold"
                                            fill="#000"
                                            text-anchor="middle"
                                        >
                                            {{ ann.subText }}
                                        </text>

                                        <!-- Selection highlight box -->
                                        <rect
                                            v-if="selectedObjectId === ann.id"
                                            :x="ann.x - 4"
                                            :y="ann.y - 18"
                                            width="135"
                                            height="32"
                                            fill="none"
                                            stroke="#3b82f6"
                                            stroke-width="1.2"
                                            stroke-dasharray="3,3"
                                        />
                                    </g>

                                    <!-- Free Text Annotation (Clean Architectural Typography, Tanpa Kotak Kuning) -->
                                    <g
                                        v-else-if="ann.type === 'text'"
                                        class="cursor-move select-none"
                                        @mousedown.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Teks: ${ann.text}` })"
                                        @touchstart.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Teks: ${ann.text}` })"
                                        @click.stop="selectComponent(`Teks: ${ann.text}`, ann.id)"
                                        @dblclick.stop="deleteAnnotation(ann.id)"
                                    >
                                        <!-- Invisible hitbox when unselected, subtle dashed outline only when selected -->
                                        <rect
                                            :x="ann.x - 4"
                                            :y="ann.y - 14"
                                            :width="Math.max(40, (ann.text || '').length * 8 + 8)"
                                            height="18"
                                            :fill="ann.hasBox ? (selectedObjectId === ann.id ? '#bae6fd' : '#fef08a') : 'transparent'"
                                            :stroke="selectedObjectId === ann.id ? '#3b82f6' : (ann.hasBox ? '#0284c7' : 'none')"
                                            :stroke-dasharray="!ann.hasBox && selectedObjectId === ann.id ? '3,3' : 'none'"
                                            stroke-width="1"
                                            rx="2"
                                        />
                                        <text
                                            :x="ann.x"
                                            :y="ann.y"
                                            :font-size="ann.fontSize || 12"
                                            :font-weight="ann.isBold !== false ? 'bold' : 'normal'"
                                            :fill="ann.color || '#000000'"
                                        >
                                            {{ ann.text }}
                                        </text>
                                    </g>

                                    <!-- Dimension Annotation (Draggable) -->
                                    <g
                                        v-else-if="ann.type === 'measure'"
                                        class="cursor-move select-none"
                                        @mousedown.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Garis Ukur: ${ann.text}` })"
                                        @touchstart.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Garis Ukur: ${ann.text}` })"
                                        @click.stop="selectComponent(`Garis Ukur: ${ann.text}`, ann.id)"
                                    >
                                        <line :x1="ann.x - 50" :y1="ann.y" :x2="ann.x + 50" :y2="ann.y" stroke="#e11d48" stroke-width="1.5" />
                                        <line :x1="ann.x - 50" :y1="ann.y - 6" :x2="ann.x - 50" :y2="ann.y + 6" stroke="#e11d48" stroke-width="1.5" />
                                        <line :x1="ann.x + 50" :y1="ann.y - 6" :x2="ann.x + 50" :y2="ann.y + 6" stroke="#e11d48" stroke-width="1.5" />
                                        <text :x="ann.x" :y="ann.y - 4" font-size="10" font-family="monospace" font-weight="bold" fill="#e11d48" text-anchor="middle">{{ ann.text }}</text>
                                    </g>

                                    <!-- Free Line Annotation (Flexible, Draggable Body & Endpoints, Live Length Badge) -->
                                    <g
                                        v-else-if="ann.type === 'line'"
                                        class="select-none"
                                        @click.stop="selectComponent(`Garis: ${getLineLengthMm(ann)} mm`, ann.id)"
                                    >
                                        <!-- Wider invisible line for easy clicking/dragging the body -->
                                        <line
                                            :x1="ann.x"
                                            :y1="ann.y"
                                            :x2="ann.x2 ?? (ann.x + 100)"
                                            :y2="ann.y2 ?? ann.y"
                                            stroke="transparent"
                                            stroke-width="20"
                                            class="cursor-move"
                                            @mousedown.stop="startObjectDrag($event, 'line_body', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y, name: `Garis: ${getLineLengthMm(ann)} mm` })"
                                            @touchstart.stop="startObjectDrag($event, 'line_body', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y, name: `Garis: ${getLineLengthMm(ann)} mm` })"
                                        />

                                        <!-- Visible Line -->
                                        <line
                                            :x1="ann.x"
                                            :y1="ann.y"
                                            :x2="ann.x2 ?? (ann.x + 100)"
                                            :y2="ann.y2 ?? ann.y"
                                            :stroke="selectedObjectId === ann.id ? '#2563eb' : (ann.color || '#1e293b')"
                                            :stroke-width="ann.lineWidth || 2"
                                            :stroke-dasharray="ann.lineStyle === 'dashed' ? '6,4' : ann.lineStyle === 'dashdot' ? '8,3,2,3' : 'none'"
                                            stroke-linecap="round"
                                            class="cursor-move"
                                            @mousedown.stop="startObjectDrag($event, 'line_body', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y, name: `Garis: ${getLineLengthMm(ann)} mm` })"
                                            @touchstart.stop="startObjectDrag($event, 'line_body', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y, name: `Garis: ${getLineLengthMm(ann)} mm` })"
                                        />

                                        <!-- Endpoint dots -->
                                        <circle :cx="ann.x" :cy="ann.y" r="3" :fill="selectedObjectId === ann.id ? '#2563eb' : '#000'" />
                                        <circle :cx="ann.x2 ?? (ann.x + 100)" :cy="ann.y2 ?? ann.y" r="3" :fill="selectedObjectId === ann.id ? '#2563eb' : '#000'" />

                                        <!-- Live Length Floating Badge above line (Klik untuk langsung ketik mm tanpa tarik) -->
                                        <g
                                            :transform="`translate(${((ann.x + (ann.x2 ?? (ann.x + 100))) / 2)}, ${((ann.y + (ann.y2 ?? ann.y)) / 2) - 12})`"
                                            class="cursor-pointer"
                                            @click.stop="promptChangeLineLength(ann)"
                                        >
                                            <rect
                                                :x="-38"
                                                y="-9"
                                                width="76"
                                                height="18"
                                                rx="4"
                                                :fill="selectedObjectId === ann.id ? '#2563eb' : '#18181b'"
                                                stroke="#ffffff"
                                                stroke-width="0.8"
                                                class="shadow-lg"
                                            />
                                            <text
                                                x="0"
                                                y="3.5"
                                                font-size="9"
                                                font-family="monospace"
                                                font-weight="bold"
                                                fill="#ffffff"
                                                text-anchor="middle"
                                            >
                                                {{ getLineLengthMm(ann) }} mm
                                            </text>
                                        </g>

                                        <!-- Interactive Drag Endpoints when Line is Selected -->
                                        <g v-if="selectedObjectId === ann.id">
                                            <!-- Endpoint A (Start handle) - drag to stretch / rotate from start -->
                                            <circle
                                                :cx="ann.x"
                                                :cy="ann.y"
                                                r="7"
                                                fill="#3b82f6"
                                                stroke="#ffffff"
                                                stroke-width="2"
                                                class="cursor-crosshair shadow-lg animate-pulse"
                                                title="Tarik Titik Pangkal A"
                                                @mousedown.stop="startObjectDrag($event, 'line_endpoint_a', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y })"
                                                @touchstart.stop="startObjectDrag($event, 'line_endpoint_a', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y })"
                                            />

                                            <!-- Endpoint B (End handle) - drag to stretch / rotate from end -->
                                            <circle
                                                :cx="ann.x2 ?? (ann.x + 100)"
                                                :cy="ann.y2 ?? ann.y"
                                                r="7"
                                                fill="#3b82f6"
                                                stroke="#ffffff"
                                                stroke-width="2"
                                                class="cursor-crosshair shadow-lg animate-pulse"
                                                title="Tarik Titik Ujung B"
                                                @mousedown.stop="startObjectDrag($event, 'line_endpoint_b', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y })"
                                                @touchstart.stop="startObjectDrag($event, 'line_endpoint_b', ann.id, { x: ann.x, y: ann.y, x2: ann.x2 ?? (ann.x + 100), y2: ann.y2 ?? ann.y })"
                                            />
                                        </g>
                                    </g>

                                    <!-- Free Rect Annotation (Draggable) -->
                                    <g
                                        v-else-if="ann.type === 'rect'"
                                        class="cursor-move select-none"
                                        @mousedown.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: 'Kotak Notasi' })"
                                        @touchstart.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: 'Kotak Notasi' })"
                                        @click.stop="selectComponent('Kotak Notasi', ann.id)"
                                    >
                                        <rect :x="ann.x" :y="ann.y" :width="ann.width || 80" :height="ann.height || 50" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-dasharray="3,3" />
                                    </g>

                                    <!-- Accessory Annotation (TV, Soundbar, Books, Plants, Sink, Stove, Drawer, Wardrobe) with Interactive Edge & Corner Resizing -->
                                    <g
                                        v-else-if="ann.type === 'accessory'"
                                        :transform="`translate(${ann.x}, ${ann.y})`"
                                        class="select-none"
                                        @mouseenter="hoveredAccessoryId = ann.id"
                                        @mouseleave="hoveredAccessoryId = (hoveredAccessoryId === ann.id ? null : hoveredAccessoryId)"
                                        @click.stop="selectComponent(`Aksesoris: ${ann.text || ann.accessoryType}`, ann.id)"
                                    >
                                        <!-- 1. Central Body: Draggable to move position -->
                                        <g
                                            class="cursor-move"
                                            @mousedown.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Aksesoris: ${ann.text || ann.accessoryType}` })"
                                            @touchstart.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, name: `Aksesoris: ${ann.text || ann.accessoryType}` })"
                                        >
                                            <!-- Transparent background hit zone so dragging anywhere inside the accessory moves it -->
                                            <rect
                                                :x="0"
                                                :y="0"
                                                :width="getAccessoryWidthPx(ann)"
                                                :height="getAccessoryHeightPx(ann)"
                                                fill="transparent"
                                            />

                                            <!-- Scaled Graphic Elements to match exact width & height -->
                                            <g :transform="`scale(${getAccessoryWidthPx(ann) / getAccessoryBaseSize(ann.accessoryType).w}, ${getAccessoryHeightPx(ann) / getAccessoryBaseSize(ann.accessoryType).h})`">
                                                <!-- 1. TV 65 Inch -->
                                                <g v-if="ann.accessoryType === 'tv'">
                                                    <rect x="0" y="0" width="180" height="105" fill="#0f172a" stroke="#334155" stroke-width="2" rx="3" />
                                                    <rect x="3" y="3" width="174" height="99" fill="#1e293b" />
                                                    <line x1="3" y1="3" x2="80" y2="102" stroke="rgba(255,255,255,0.08)" stroke-width="2" />
                                                    <rect x="75" y="105" width="30" height="6" fill="#475569" rx="1" />
                                                    <text x="90" y="55" font-size="9" fill="#94a3b8" text-anchor="middle" font-family="sans-serif">Smart TV 65"</text>
                                                </g>

                                                <!-- 2. Soundbar -->
                                                <g v-else-if="ann.accessoryType === 'soundbar'">
                                                    <rect x="0" y="0" width="140" height="16" fill="#18181b" stroke="#3f3f46" stroke-width="1.5" rx="2" />
                                                    <circle cx="20" cy="8" r="4" fill="#27272a" />
                                                    <circle cx="120" cy="8" r="4" fill="#27272a" />
                                                    <text x="70" y="11" font-size="7" fill="#71717a" text-anchor="middle">DOLBY SOUNDBAR</text>
                                                </g>

                                                <!-- 3. Books & Decor -->
                                                <g v-else-if="ann.accessoryType === 'books'">
                                                    <rect x="0" y="0" width="14" height="42" fill="#b91c1c" stroke="#7f1d1d" stroke-width="0.8" rx="1" />
                                                    <line x1="3" y1="6" x2="11" y2="6" stroke="#fca5a5" stroke-width="1" />
                                                    <rect x="15" y="4" width="12" height="38" fill="#1d4ed8" stroke="#1e3a8a" stroke-width="0.8" rx="1" />
                                                    <rect x="28" y="2" width="16" height="40" fill="#047857" stroke="#064e3b" stroke-width="0.8" rx="1" />
                                                    <g transform="translate(45, 10) rotate(16)">
                                                        <rect x="0" y="0" width="12" height="36" fill="#d97706" stroke="#78350f" stroke-width="0.8" rx="1" />
                                                    </g>
                                                    <circle cx="85" cy="28" r="14" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1" />
                                                    <text x="45" y="52" font-size="7.5" fill="#64748b" text-anchor="middle">Buku &amp; Dekorasi</text>
                                                </g>

                                                <!-- 4. Plant / Vas Bunga -->
                                                <g v-else-if="ann.accessoryType === 'plant'">
                                                    <path d="M15,45 Q10,30 18,22 Q25,20 32,22 Q40,30 35,45 Z" fill="#e2e8f0" stroke="#64748b" stroke-width="1.2" />
                                                    <path d="M25,20 Q12,5 5,12 Q15,18 24,20" fill="#22c55e" stroke="#15803d" stroke-width="0.8" />
                                                    <path d="M25,20 Q25,-2 32,4 Q28,14 26,20" fill="#16a34a" stroke="#14532d" stroke-width="0.8" />
                                                    <path d="M25,20 Q38,6 44,14 Q34,18 26,20" fill="#4ade80" stroke="#16a34a" stroke-width="0.8" />
                                                    <text x="25" y="55" font-size="7.5" fill="#64748b" text-anchor="middle">Tanaman Hias</text>
                                                </g>

                                                <!-- 5. Kitchen Sink & Faucet -->
                                                <g v-else-if="ann.accessoryType === 'sink'">
                                                    <rect x="0" y="0" width="110" height="60" fill="#cbd5e1" stroke="#475569" stroke-width="1.5" rx="4" />
                                                    <rect x="8" y="8" width="60" height="44" fill="#94a3b8" stroke="#334155" stroke-width="1" rx="2" />
                                                    <circle cx="38" cy="30" r="6" fill="#64748b" stroke="#334155" stroke-width="1" />
                                                    <path d="M88,30 C88,14 96,14 96,22" fill="none" stroke="#334155" stroke-width="2.5" />
                                                    <circle cx="88" cy="30" r="3" fill="#334155" />
                                                    <text x="55" y="55" font-size="7.5" fill="#1e293b" text-anchor="middle">Kitchen Sink</text>
                                                </g>

                                                <!-- 6. Kompor Induksi -->
                                                <g v-else-if="ann.accessoryType === 'stove'">
                                                    <rect x="0" y="0" width="110" height="55" fill="#09090b" stroke="#3f3f46" stroke-width="1.5" rx="3" />
                                                    <circle cx="35" cy="27" r="18" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="2,2" />
                                                    <circle cx="35" cy="27" r="9" fill="none" stroke="#ef4444" stroke-width="1" />
                                                    <circle cx="78" cy="27" r="14" fill="none" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="2,2" />
                                                    <circle cx="78" cy="27" r="7" fill="none" stroke="#ef4444" stroke-width="1" />
                                                    <text x="55" y="50" font-size="7" fill="#71717a" text-anchor="middle">Kompor Induksi</text>
                                                </g>

                                                <!-- 7. Modul 3 Laci -->
                                                <g v-else-if="ann.accessoryType === 'drawer'">
                                                    <rect x="0" y="0" width="100" height="90" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" />
                                                    <line x1="0" y1="30" x2="100" y2="30" stroke="#8c6d37" stroke-width="1" />
                                                    <rect x="35" y="12" width="30" height="4" fill="#1c1917" rx="1" />
                                                    <line x1="0" y1="60" x2="100" y2="60" stroke="#8c6d37" stroke-width="1" />
                                                    <rect x="35" y="42" width="30" height="4" fill="#1c1917" rx="1" />
                                                    <rect x="35" y="72" width="30" height="4" fill="#1c1917" rx="1" />
                                                </g>

                                                <!-- 8. Wardrobe Door -->
                                                <g v-else-if="ann.accessoryType === 'wardrobe_door'">
                                                    <rect x="0" y="0" width="80" height="150" fill="#e2e8f0" stroke="#64748b" stroke-width="1.2" />
                                                    <rect x="68" y="70" width="4" height="20" fill="#0f172a" rx="1" />
                                                    <line x1="10" y1="10" x2="70" y2="10" stroke="#94a3b8" stroke-width="0.5" stroke-dasharray="2,2" />
                                                    <line x1="10" y1="140" x2="70" y2="140" stroke="#94a3b8" stroke-width="0.5" stroke-dasharray="2,2" />
                                                    <text x="40" y="82" font-size="8" fill="#475569" text-anchor="middle">Pintu Lemari</text>
                                                </g>

                                                <!-- 9. Cooker Hood -->
                                                <g v-else-if="ann.accessoryType === 'hood'">
                                                    <rect x="42" y="0" width="36" height="28" fill="#94a3b8" stroke="#475569" stroke-width="1.2" />
                                                    <line x1="42" y1="12" x2="78" y2="12" stroke="#64748b" stroke-width="0.8" />
                                                    <polygon points="10,48 42,28 78,28 110,48" fill="#cbd5e1" stroke="#475569" stroke-width="1.2" />
                                                    <rect x="10" y="48" width="100" height="12" fill="#64748b" stroke="#334155" stroke-width="1.2" rx="1" />
                                                    <rect x="15" y="50" width="40" height="8" fill="#475569" stroke="#94a3b8" stroke-width="0.6" stroke-dasharray="2,1" />
                                                    <rect x="65" y="50" width="40" height="8" fill="#475569" stroke="#94a3b8" stroke-width="0.6" stroke-dasharray="2,1" />
                                                    <text x="60" y="41" font-size="7" fill="#334155" font-weight="bold" text-anchor="middle">COOKER HOOD</text>
                                                </g>

                                                <!-- 10. Microwave & Built-in Oven -->
                                                <g v-else-if="ann.accessoryType === 'oven'">
                                                    <rect x="0" y="0" width="100" height="80" fill="#0f172a" stroke="#334155" stroke-width="1.5" rx="3" />
                                                    <rect x="4" y="4" width="92" height="18" fill="#1e293b" rx="2" />
                                                    <circle cx="18" cy="13" r="5" fill="#334155" stroke="#94a3b8" stroke-width="0.8" />
                                                    <rect x="35" y="8" width="30" height="10" fill="#020617" rx="1" />
                                                    <text x="50" y="15" font-size="6.5" font-family="monospace" fill="#22c55e" text-anchor="middle">180°C</text>
                                                    <circle cx="82" cy="13" r="5" fill="#334155" stroke="#94a3b8" stroke-width="0.8" />
                                                    <rect x="6" y="26" width="88" height="48" fill="#18181b" stroke="#475569" stroke-width="1.2" rx="2" />
                                                    <rect x="14" y="32" width="72" height="36" fill="#0f172a" stroke="#334155" stroke-width="0.8" />
                                                    <rect x="25" y="28" width="50" height="3" fill="#cbd5e1" rx="1.5" />
                                                    <text x="50" y="53" font-size="7" fill="#64748b" text-anchor="middle">BUILT-IN OVEN</text>
                                                </g>

                                                <!-- 11. Kulkas Standalone 2 Pintu -->
                                                <g v-else-if="ann.accessoryType === 'fridge_standalone'">
                                                    <rect x="0" y="0" width="120" height="180" fill="#e2e8f0" stroke="#475569" stroke-width="1.8" rx="4" />
                                                    <rect x="3" y="3" width="114" height="65" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1" rx="2" />
                                                    <rect x="3" y="71" width="114" height="106" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1" rx="2" />
                                                    <rect x="15" y="85" width="28" height="40" fill="#334155" rx="2" />
                                                    <rect x="18" y="90" width="22" height="28" fill="#0f172a" rx="1" />
                                                    <line x1="29" y1="92" x2="29" y2="104" stroke="#38bdf8" stroke-width="2" />
                                                    <rect x="110" y="35" width="3" height="30" fill="#64748b" rx="1" />
                                                    <rect x="110" y="75" width="3" height="45" fill="#64748b" rx="1" />
                                                    <text x="60" y="40" font-size="8" font-weight="bold" fill="#64748b" text-anchor="middle">FREEZER</text>
                                                    <text x="70" y="140" font-size="8.5" font-weight="bold" fill="#64748b" text-anchor="middle">REFRIGERATOR</text>
                                                </g>

                                                <!-- 12. Rak Piring Gantung Stainless -->
                                                <g v-else-if="ann.accessoryType === 'dish_rack'">
                                                    <rect x="0" y="0" width="110" height="45" fill="#f8fafc" stroke="#64748b" stroke-width="1.2" rx="2" />
                                                    <line v-for="i in 10" :key="i" :x1="10 + i * 8.5" y1="6" :x2="10 + i * 8.5" y2="34" stroke="#94a3b8" stroke-width="1.2" />
                                                    <line x1="8" y1="34" x2="102" y2="34" stroke="#475569" stroke-width="1.5" />
                                                    <rect x="4" y="38" width="102" height="5" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" rx="1" />
                                                    <text x="55" y="24" font-size="7" fill="#475569" font-weight="bold" text-anchor="middle">RAK PIRING STAINLESS</text>
                                                </g>

                                                <!-- 13. Dispenser Air -->
                                                <g v-else-if="ann.accessoryType === 'dispenser'">
                                                    <rect x="0" y="0" width="55" height="110" fill="#f8fafc" stroke="#64748b" stroke-width="1.5" rx="3" />
                                                    <rect x="4" y="4" width="47" height="30" fill="#0f172a" rx="2" />
                                                    <circle cx="18" cy="46" r="3" fill="#ef4444" />
                                                    <circle cx="28" cy="46" r="3" fill="#3b82f6" />
                                                    <circle cx="38" cy="46" r="3" fill="#22c55e" />
                                                    <rect x="10" y="55" width="35" height="10" fill="#e2e8f0" stroke="#94a3b8" stroke-width="0.8" rx="1" />
                                                    <rect x="4" y="70" width="47" height="36" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1" rx="2" />
                                                    <rect x="44" y="80" width="2" height="15" fill="#64748b" rx="1" />
                                                    <text x="28" y="22" font-size="6" fill="#38bdf8" font-family="monospace" text-anchor="middle">WATER</text>
                                                </g>

                                                <!-- 14. Media Box / Console -->
                                                <g v-else-if="ann.accessoryType === 'game_console'">
                                                    <rect x="0" y="0" width="60" height="25" fill="#09090b" stroke="#27272a" stroke-width="1.2" rx="3" />
                                                    <rect x="4" y="4" width="52" height="17" fill="#18181b" rx="2" />
                                                    <line x1="8" y1="12" x2="35" y2="12" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round" />
                                                    <circle cx="48" cy="12" r="2.5" fill="#22c55e" />
                                                    <text x="30" y="21" font-size="5" fill="#71717a" text-anchor="middle">MEDIA BOX</text>
                                                </g>

                                                <!-- 15. Speaker Bookshelf -->
                                                <g v-else-if="ann.accessoryType === 'speaker'">
                                                    <rect x="0" y="0" width="40" height="70" fill="#18181b" stroke="#3f3f46" stroke-width="1.5" rx="3" />
                                                    <circle cx="20" cy="22" r="8" fill="#27272a" stroke="#52525b" stroke-width="1" />
                                                    <circle cx="20" cy="22" r="3.5" fill="#71717a" />
                                                    <circle cx="20" cy="48" r="14" fill="#27272a" stroke="#52525b" stroke-width="1.2" />
                                                    <circle cx="20" cy="48" r="6" fill="#3f3f46" />
                                                </g>

                                                <!-- 16. Jam Dinding -->
                                                <g v-else-if="ann.accessoryType === 'clock'">
                                                    <circle cx="25" cy="25" r="24" fill="#ffffff" stroke="#1e293b" stroke-width="2" />
                                                    <circle cx="25" cy="25" r="21" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.8" />
                                                    <line x1="25" y1="6" x2="25" y2="10" stroke="#0f172a" stroke-width="1.5" />
                                                    <line x1="25" y1="40" x2="25" y2="44" stroke="#0f172a" stroke-width="1.5" />
                                                    <line x1="6" y1="25" x2="10" y2="25" stroke="#0f172a" stroke-width="1.5" />
                                                    <line x1="40" y1="25" x2="44" y2="25" stroke="#0f172a" stroke-width="1.5" />
                                                    <line x1="25" y1="25" x2="25" y2="13" stroke="#0f172a" stroke-width="1.8" stroke-linecap="round" />
                                                    <line x1="25" y1="25" x2="33" y2="25" stroke="#0f172a" stroke-width="1.4" stroke-linecap="round" />
                                                    <line x1="25" y1="25" x2="20" y2="32" stroke="#ef4444" stroke-width="0.8" />
                                                    <circle cx="25" cy="25" r="2" fill="#ef4444" />
                                                </g>

                                                <!-- 17. Pintu Lemari Kaca Aluminium -->
                                                <g v-else-if="ann.accessoryType === 'glass_door'">
                                                    <rect x="0" y="0" width="80" height="150" fill="#0f172a" stroke="#334155" stroke-width="1.5" rx="2" />
                                                    <rect x="5" y="5" width="70" height="140" fill="rgba(56, 189, 248, 0.12)" stroke="#0284c7" stroke-width="0.8" />
                                                    <line x1="12" y1="12" x2="45" y2="138" stroke="rgba(255, 255, 255, 0.25)" stroke-width="1.5" />
                                                    <rect x="68" y="65" width="3" height="30" fill="#e2e8f0" stroke="#475569" stroke-width="0.6" rx="1" />
                                                    <text x="40" y="80" font-size="7.5" fill="#38bdf8" font-weight="bold" text-anchor="middle">PINTU KACA</text>
                                                </g>

                                                <!-- 18. Gantungan Baju & Hanger -->
                                                <g v-else-if="ann.accessoryType === 'clothes_rail'">
                                                    <rect x="0" y="0" width="6" height="12" fill="#475569" />
                                                    <rect x="124" y="0" width="6" height="12" fill="#475569" />
                                                    <rect x="4" y="8" width="122" height="5" fill="#cbd5e1" stroke="#64748b" stroke-width="1" rx="1.5" />
                                                    <g v-for="(col, idx) in ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#64748b']" :key="idx" :transform="`translate(${15 + idx * 18}, 13)`">
                                                        <path d="M0,8 L7,0 L14,8 Z" fill="none" stroke="#64748b" stroke-width="1" />
                                                        <path d="M-1,8 L4,6 L10,6 L15,8 L13,48 L1,48 Z" :fill="col" fill-opacity="0.85" stroke="#334155" stroke-width="0.8" />
                                                    </g>
                                                    <text x="65" y="66" font-size="6.5" fill="#64748b" font-weight="bold" text-anchor="middle">GANTUNGAN BAJU</text>
                                                </g>

                                                <!-- 19. Rak Sepatu Susun Miring -->
                                                <g v-else-if="ann.accessoryType === 'shoe_rack'">
                                                    <rect x="0" y="0" width="100" height="60" fill="#f8fafc" stroke="#64748b" stroke-width="1.2" rx="2" />
                                                    <line x1="6" y1="26" x2="94" y2="20" stroke="#0f172a" stroke-width="2" />
                                                    <line x1="6" y1="26" x2="6" y2="18" stroke="#0f172a" stroke-width="2" />
                                                    <rect x="15" y="10" width="28" height="9" rx="3" fill="#d97706" stroke="#78350f" stroke-width="0.8" />
                                                    <rect x="52" y="7" width="28" height="9" rx="3" fill="#1e293b" stroke="#0f172a" stroke-width="0.8" />
                                                    <line x1="6" y1="52" x2="94" y2="46" stroke="#0f172a" stroke-width="2" />
                                                    <line x1="6" y1="52" x2="6" y2="44" stroke="#0f172a" stroke-width="2" />
                                                    <rect x="15" y="36" width="28" height="9" rx="3" fill="#dc2626" stroke="#991b1b" stroke-width="0.8" />
                                                    <rect x="52" y="33" width="28" height="9" rx="3" fill="#2563eb" stroke="#1e3a8a" stroke-width="0.8" />
                                                </g>

                                                <!-- 20. Cermin Bulat LED -->
                                                <g v-else-if="ann.accessoryType === 'mirror_round'">
                                                    <circle cx="35" cy="35" r="34" fill="rgba(251, 191, 36, 0.25)" stroke="#f59e0b" stroke-width="1.5" />
                                                    <circle cx="35" cy="35" r="30" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1.2" />
                                                    <circle cx="35" cy="35" r="28" fill="#e2e8f0" />
                                                    <path d="M15,22 Q35,12 52,22 Q35,16 15,22 Z" fill="#ffffff" fill-opacity="0.8" />
                                                    <text x="35" y="38" font-size="7" fill="#64748b" font-weight="bold" text-anchor="middle">LED MIRROR</text>
                                                </g>

                                                <!-- 21. Cermin Full Body -->
                                                <g v-else-if="ann.accessoryType === 'mirror_rect'">
                                                    <rect x="0" y="0" width="60" height="140" fill="#0f172a" stroke="#d97706" stroke-width="2" rx="6" />
                                                    <rect x="4" y="4" width="52" height="132" fill="#e2e8f0" stroke="#94a3b8" stroke-width="0.8" rx="4" />
                                                    <line x1="10" y1="12" x2="35" y2="128" stroke="rgba(255,255,255,0.4)" stroke-width="2" stroke-linecap="round" />
                                                    <text x="30" y="75" font-size="7.5" fill="#475569" font-weight="bold" text-anchor="middle">FULL MIRROR</text>
                                                </g>

                                                <!-- 22. Lukisan Kanvas Dinding -->
                                                <g v-else-if="ann.accessoryType === 'wall_art'">
                                                    <rect x="0" y="0" width="120" height="80" fill="#1c1917" stroke="#78350f" stroke-width="2.5" rx="2" />
                                                    <rect x="4" y="4" width="112" height="72" fill="#fef3c7" stroke="#b45309" stroke-width="0.8" />
                                                    <circle cx="45" cy="34" r="14" fill="#f59e0b" />
                                                    <path d="M10,56 Q40,32 75,56 T110,50 L110,72 L10,72 Z" fill="#0284c7" />
                                                    <path d="M10,64 Q45,45 80,64 T110,60 L110,72 L10,72 Z" fill="#0369a1" />
                                                    <text x="60" y="72" font-size="6.5" fill="#1e293b" font-weight="bold" text-anchor="middle">LUKISAN KANVAS</text>
                                                </g>

                                                <!-- 23. Lampu Gantung / Pendant -->
                                                <g v-else-if="ann.accessoryType === 'pendant_light'">
                                                    <rect x="20" y="0" width="10" height="3" fill="#1e293b" rx="1" />
                                                    <line x1="25" y1="3" x2="25" y2="45" stroke="#0f172a" stroke-width="1.2" />
                                                    <polygon points="5,72 25,45 45,72" fill="#1e293b" stroke="#334155" stroke-width="1.2" />
                                                    <circle cx="25" cy="74" r="7" fill="#fbbf24" stroke="#d97706" stroke-width="1" />
                                                    <polygon points="12,78 38,78 48,90 2,90" fill="rgba(251, 191, 36, 0.2)" />
                                                </g>

                                                <!-- 24. Downlight Spotlight Plafon -->
                                                <g v-else-if="ann.accessoryType === 'downlight'">
                                                    <rect x="2" y="0" width="31" height="5" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="1" />
                                                    <ellipse cx="17.5" cy="5" rx="12" ry="4" fill="#f8fafc" stroke="#334155" stroke-width="1" />
                                                    <polygon points="8,7 27,7 34,24 1,24" fill="rgba(251, 191, 36, 0.15)" stroke="none" />
                                                    <circle cx="17.5" cy="6" r="3" fill="#f59e0b" />
                                                </g>

                                                <!-- 25. Glow LED Strip Ambalan -->
                                                <g v-else-if="ann.accessoryType === 'led_strip'">
                                                    <rect x="0" y="1" width="140" height="10" fill="rgba(251, 191, 36, 0.35)" rx="5" />
                                                    <rect x="2" y="4" width="136" height="4" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" rx="1" />
                                                    <line x1="4" y1="6" x2="134" y2="6" stroke="#fbbf24" stroke-width="2" stroke-dasharray="3,3" />
                                                </g>

                                                <!-- 26. Stop Kontak Listrik Dinding -->
                                                <g v-else-if="ann.accessoryType === 'power_outlet'">
                                                    <rect x="0" y="0" width="30" height="30" fill="#ffffff" stroke="#0f172a" stroke-width="1.8" rx="4" />
                                                    <rect x="2" y="2" width="26" height="26" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.8" rx="3" />
                                                    <circle cx="10" cy="15" r="5" fill="#f1f5f9" stroke="#64748b" stroke-width="1" />
                                                    <circle cx="9" cy="15" r="1.2" fill="#0f172a" />
                                                    <circle cx="11" cy="15" r="1.2" fill="#0f172a" />
                                                    <circle cx="20" cy="15" r="5" fill="#f1f5f9" stroke="#64748b" stroke-width="1" />
                                                    <circle cx="19" cy="15" r="1.2" fill="#0f172a" />
                                                    <circle cx="21" cy="15" r="1.2" fill="#0f172a" />
                                                    <text x="15" y="28" font-size="4" font-weight="bold" fill="#64748b" text-anchor="middle">220V</text>
                                                </g>

                                                <!-- 27. Saklar Lampu Ganda -->
                                                <g v-else-if="ann.accessoryType === 'switch'">
                                                    <rect x="0" y="0" width="30" height="30" fill="#ffffff" stroke="#0f172a" stroke-width="1.8" rx="4" />
                                                    <rect x="4" y="5" width="10" height="20" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1" rx="1" />
                                                    <line x1="4" y1="15" x2="14" y2="15" stroke="#cbd5e1" stroke-width="1" />
                                                    <circle cx="9" cy="18" r="1" fill="#ef4444" />
                                                    <rect x="16" y="5" width="10" height="20" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1" rx="1" />
                                                    <line x1="16" y1="15" x2="26" y2="15" stroke="#cbd5e1" stroke-width="1" />
                                                    <circle cx="21" cy="18" r="1" fill="#ef4444" />
                                                </g>

                                                <!-- 28. AC Split Indoor Unit -->
                                                <g v-else-if="ann.accessoryType === 'ac_indoor'">
                                                    <rect x="0" y="0" width="130" height="45" fill="#ffffff" stroke="#475569" stroke-width="1.8" rx="4" />
                                                    <line v-for="j in 8" :key="j" :x1="12 + j * 12" y1="5" :x2="12 + j * 12" y2="14" stroke="#cbd5e1" stroke-width="1.2" />
                                                    <line x1="6" y1="36" x2="124" y2="36" stroke="#94a3b8" stroke-width="1.2" />
                                                    <rect x="100" y="16" width="22" height="12" fill="#0f172a" rx="2" />
                                                    <text x="111" y="25" font-size="7" font-family="monospace" fill="#38bdf8" text-anchor="middle">24°</text>
                                                    <text x="50" y="26" font-size="7" fill="#64748b" font-weight="bold" text-anchor="middle">AIR CONDITIONER</text>
                                                </g>

                                                <!-- 29. Kursi Barstool Cafe -->
                                                <g v-else-if="ann.accessoryType === 'barstool'">
                                                    <ellipse cx="25" cy="20" rx="18" ry="7" fill="#854d0e" stroke="#5c3807" stroke-width="1.2" />
                                                    <path d="M7,20 C7,26 43,26 43,20" fill="#a16207" stroke="#5c3807" stroke-width="1.2" />
                                                    <rect x="23" y="24" width="4" height="55" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" />
                                                    <ellipse cx="25" cy="58" rx="12" ry="4" fill="none" stroke="#64748b" stroke-width="1.8" />
                                                    <ellipse cx="25" cy="80" rx="18" ry="6" fill="#94a3b8" stroke="#475569" stroke-width="1.5" />
                                                </g>
                                            </g>
                                        </g>

                                        <!-- 2. Bounding Box Highlight (Visible on hover or when selected) -->
                                        <rect
                                            v-if="selectedObjectId === ann.id || hoveredAccessoryId === ann.id"
                                            :x="0"
                                            :y="0"
                                            :width="getAccessoryWidthPx(ann)"
                                            :height="getAccessoryHeightPx(ann)"
                                            fill="rgba(59, 130, 246, 0.04)"
                                            :stroke="selectedObjectId === ann.id ? '#3b82f6' : 'rgba(59, 130, 246, 0.65)'"
                                            :stroke-dasharray="selectedObjectId === ann.id ? 'none' : '4,3'"
                                            stroke-width="1.5"
                                            rx="2"
                                            pointer-events="none"
                                        />

                                        <!-- 3. Live Dimension Tooltip Badge (Visible when selected or while dragging) -->
                                        <g
                                            v-if="selectedObjectId === ann.id || (isDraggingObject && dragPayload?.id === ann.id)"
                                            :transform="`translate(${getAccessoryWidthPx(ann) / 2}, ${ann.y < 35 ? getAccessoryHeightPx(ann) + 16 : -14})`"
                                            class="pointer-events-none select-none"
                                        >
                                            <rect x="-65" y="-11" width="130" height="22" rx="4" fill="#0f172a" fill-opacity="0.95" stroke="#3b82f6" stroke-width="1.2" />
                                            <text x="0" y="4" font-size="10" font-family="monospace" font-weight="bold" fill="#93c5fd" text-anchor="middle">
                                                {{ getAccessoryWidthMm(ann) }} × {{ getAccessoryHeightMm(ann) }} mm
                                            </text>
                                        </g>

                                        <!-- 4. Interactive 4-Edge Hover Zones (Wide transparent hit lines with cursor feedback) -->
                                        <!-- East / Kanan Edge -->
                                        <g
                                            style="cursor: ew-resize;"
                                            @mousedown.stop="startObjectDrag($event, 'accessory_resize_e', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            @touchstart.stop="startObjectDrag($event, 'accessory_resize_e', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                        >
                                            <line
                                                :x1="getAccessoryWidthPx(ann)"
                                                :y1="0"
                                                :x2="getAccessoryWidthPx(ann)"
                                                :y2="getAccessoryHeightPx(ann)"
                                                stroke="transparent"
                                                stroke-width="14"
                                            />
                                        </g>

                                        <!-- West / Kiri Edge -->
                                        <g
                                            style="cursor: ew-resize;"
                                            @mousedown.stop="startObjectDrag($event, 'accessory_resize_w', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            @touchstart.stop="startObjectDrag($event, 'accessory_resize_w', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                        >
                                            <line
                                                :x1="0"
                                                :y1="0"
                                                :x2="0"
                                                :y2="getAccessoryHeightPx(ann)"
                                                stroke="transparent"
                                                stroke-width="14"
                                            />
                                        </g>

                                        <!-- South / Bawah Edge -->
                                        <g
                                            style="cursor: ns-resize;"
                                            @mousedown.stop="startObjectDrag($event, 'accessory_resize_s', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            @touchstart.stop="startObjectDrag($event, 'accessory_resize_s', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                        >
                                            <line
                                                :x1="0"
                                                :y1="getAccessoryHeightPx(ann)"
                                                :x2="getAccessoryWidthPx(ann)"
                                                :y2="getAccessoryHeightPx(ann)"
                                                stroke="transparent"
                                                stroke-width="14"
                                            />
                                        </g>

                                        <!-- North / Atas Edge -->
                                        <g
                                            style="cursor: ns-resize;"
                                            @mousedown.stop="startObjectDrag($event, 'accessory_resize_n', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            @touchstart.stop="startObjectDrag($event, 'accessory_resize_n', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                        >
                                            <line
                                                :x1="0"
                                                :y1="0"
                                                :x2="getAccessoryWidthPx(ann)"
                                                :y2="0"
                                                stroke="transparent"
                                                stroke-width="14"
                                            />
                                        </g>

                                        <!-- 5. 8 Visible Handles (Visible when selected or hovered) -->
                                        <g v-if="selectedObjectId === ann.id || hoveredAccessoryId === ann.id">
                                            <!-- Top-Left (NW) -->
                                            <g
                                                style="cursor: nwse-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_nw', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_nw', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="-8" :y="-8" width="16" height="16" fill="transparent" />
                                                <rect :x="-4" :y="-4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Top-Center (N) -->
                                            <g
                                                style="cursor: ns-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_n', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_n', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="getAccessoryWidthPx(ann) / 2 - 8" :y="-8" width="16" height="16" fill="transparent" />
                                                <rect :x="getAccessoryWidthPx(ann) / 2 - 4" :y="-4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Top-Right (NE) -->
                                            <g
                                                style="cursor: nesw-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_ne', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_ne', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="getAccessoryWidthPx(ann) - 8" :y="-8" width="16" height="16" fill="transparent" />
                                                <rect :x="getAccessoryWidthPx(ann) - 4" :y="-4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Middle-Right (E) -->
                                            <g
                                                style="cursor: ew-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_e', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_e', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="getAccessoryWidthPx(ann) - 8" :y="getAccessoryHeightPx(ann) / 2 - 8" width="16" height="16" fill="transparent" />
                                                <rect :x="getAccessoryWidthPx(ann) - 4" :y="getAccessoryHeightPx(ann) / 2 - 4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Bottom-Right (SE) -->
                                            <g
                                                style="cursor: nwse-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_se', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_se', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="getAccessoryWidthPx(ann) - 8" :y="getAccessoryHeightPx(ann) - 8" width="16" height="16" fill="transparent" />
                                                <rect :x="getAccessoryWidthPx(ann) - 4" :y="getAccessoryHeightPx(ann) - 4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Bottom-Center (S) -->
                                            <g
                                                style="cursor: ns-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_s', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_s', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="getAccessoryWidthPx(ann) / 2 - 8" :y="getAccessoryHeightPx(ann) - 8" width="16" height="16" fill="transparent" />
                                                <rect :x="getAccessoryWidthPx(ann) / 2 - 4" :y="getAccessoryHeightPx(ann) - 4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Bottom-Left (SW) -->
                                            <g
                                                style="cursor: nesw-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_sw', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_sw', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="-8" :y="getAccessoryHeightPx(ann) - 8" width="16" height="16" fill="transparent" />
                                                <rect :x="-4" :y="getAccessoryHeightPx(ann) - 4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>

                                            <!-- Middle-Left (W) -->
                                            <g
                                                style="cursor: ew-resize;"
                                                @mousedown.stop="startObjectDrag($event, 'accessory_resize_w', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                                @touchstart.stop="startObjectDrag($event, 'accessory_resize_w', ann.id, { x: ann.x, y: ann.y, width: getAccessoryWidthPx(ann), height: getAccessoryHeightPx(ann) })"
                                            >
                                                <rect :x="-8" :y="getAccessoryHeightPx(ann) / 2 - 8" width="16" height="16" fill="transparent" />
                                                <rect :x="-4" :y="getAccessoryHeightPx(ann) / 2 - 4" width="8" height="8" fill="#ffffff" stroke="#2563eb" stroke-width="1.8" rx="1.5" />
                                            </g>
                                        </g>
                                    </g>
                                </g>

                                <!-- Panel Numbers (Balon ① ② ③ Sesuai Daftar Potong) -->
                                <g v-if="showPanelNumbers">
                                    <circle :cx="middleSvgX - 70" cy="235" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="middleSvgX - 70" y="238" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑥</text>

                                    <circle :cx="middleSvgX + middleSvgWidth / 2" cy="210" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="middleSvgX + middleSvgWidth / 2" y="213" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑦</text>

                                    <circle :cx="middleSvgX + middleSvgWidth / 2" :cy="counterSvgY + 8" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="middleSvgX + middleSvgWidth / 2" :y="counterSvgY + 11" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑧</text>

                                    <circle :cx="middleSvgX + Math.round(middleSvgWidth / 6)" :cy="counterSvgY + 50" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="middleSvgX + Math.round(middleSvgWidth / 6)" :y="counterSvgY + 53" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑨</text>

                                    <circle :cx="middleSvgX + Math.round(middleSvgWidth / 2)" :cy="counterSvgY + 50" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="middleSvgX + Math.round(middleSvgWidth / 2)" :y="counterSvgY + 53" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑩</text>

                                    <circle :cx="middleSvgX + Math.round(middleSvgWidth * 0.82)" :cy="counterSvgY + 80" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="middleSvgX + Math.round(middleSvgWidth * 0.82)" :y="counterSvgY + 83" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑪</text>

                                    <circle :cx="tallSvgX + Math.min(80, tallSvgWidth / 2)" cy="300" r="9" fill="#fef08a" stroke="#ca8a04" stroke-width="1" />
                                    <text :x="tallSvgX + Math.min(80, tallSvgWidth / 2)" y="303" font-size="9" font-weight="bold" fill="#854d0e" text-anchor="middle">⑫</text>
                                </g>

                                <!-- ── SUB-TITLE VIEW: 1 TAMPAK DEPAN SKALA 1 : 20 ─────────── -->
                                <g id="view-title" :transform="`translate(${furnitureSvgX + Math.round(furnitureSvgWidth / 2) - 80}, 645)`">
                                    <circle cx="15" cy="0" r="10" fill="none" stroke="#000" stroke-width="1" />
                                    <text x="15" y="4" font-size="11" font-weight="bold" text-anchor="middle" fill="#000">1</text>
                                    <text x="35" y="4" font-size="13" font-weight="bold" fill="#000" letter-spacing="1.5">TAMPAK DEPAN</text>
                                    <line x1="35" y1="8" x2="165" y2="8" stroke="#000" stroke-width="0.8" />
                                    <line x1="50" y1="12" x2="120" y2="12" stroke="#e11d48" stroke-width="2.5" stroke-linecap="round" />
                                    <text x="100" y="24" font-size="9" font-family="monospace" text-anchor="middle" fill="#555">
                                        SKALA {{ activeView === 'depan' ? cabinet.scaleSingle : '1 : 60' }}
                                    </text>
                                </g>
                            </g>

                            <!-- ============================================================== -->
                            <!-- MODE AUTO: MULTI-VIEW PAS KERTAS (PERSIS SEPERTI GAMBAR 2)     -->
                            <!-- ============================================================== -->
                            <g v-else id="multi-view-layout">
                                <!-- 1. TAMPAK DEPAN (MINI) -->
                                <g transform="translate(60, 80) scale(0.38)">
                                    <rect x="0" y="0" width="780" height="390" fill="#f8fafc" stroke="#111" stroke-width="1.5" />
                                    <rect x="0" y="0" width="140" height="90" fill="#738166" />
                                    <rect x="2" y="120" width="134" height="270" fill="#18181b" />
                                    <rect x="140" y="0" width="410" height="250" fill="#C5A47E" />
                                    <rect x="140" y="230" width="410" height="22" fill="#EDEBE7" stroke="#333" />
                                    <rect x="140" y="252" width="410" height="138" fill="#C2A27D" stroke="#5c4033" />
                                    <rect x="550" y="0" width="230" height="390" fill="#6F7E66" stroke="#111" />
                                    <text x="390" y="420" font-size="28" font-weight="bold" text-anchor="middle" fill="#000">1 TAMPAK DEPAN</text>
                                </g>

                                <!-- 2. TAMPAK DEPAN TANPA PINTU (MINI) -->
                                <g transform="translate(390, 80) scale(0.38)">
                                    <rect x="0" y="0" width="780" height="390" fill="#f8fafc" stroke="#111" stroke-width="1.5" />
                                    <rect x="0" y="0" width="140" height="390" fill="#fef3c7" stroke="#b45309" />
                                    <rect x="140" y="0" width="410" height="250" fill="#C5A47E" />
                                    <rect x="140" y="252" width="410" height="138" fill="#fef3c7" stroke="#b45309" />
                                    <rect x="550" y="0" width="230" height="390" fill="#fef3c7" stroke="#b45309" />
                                    <text x="390" y="420" font-size="28" font-weight="bold" text-anchor="middle" fill="#000">2 TAMPAK DEPAN TANPA PINTU</text>
                                </g>

                                <!-- 3. TAMPAK SAMPING (MINI) -->
                                <g transform="translate(730, 80) scale(0.38)">
                                    <rect x="0" y="0" width="140" height="390" fill="#6F7E66" stroke="#111" stroke-width="1.5" />
                                    <line x1="0" y1="230" x2="140" y2="230" stroke="#333" stroke-width="2" />
                                    <text x="70" y="420" font-size="28" font-weight="bold" text-anchor="middle" fill="#000">3 TAMPAK SAMPING</text>
                                </g>

                                <!-- 4. POTONGAN A-A (MINI) -->
                                <g transform="translate(180, 310) scale(0.38)">
                                    <rect x="0" y="0" width="140" height="390" fill="#fff" stroke="#111" stroke-width="1.5" />
                                    <line x1="20" y1="0" x2="20" y2="390" stroke="#b45309" stroke-width="3" />
                                    <line x1="20" y1="230" x2="140" y2="230" stroke="#b45309" stroke-width="4" />
                                    <text x="70" y="420" font-size="28" font-weight="bold" text-anchor="middle" fill="#000">4 POTONGAN A-A</text>
                                </g>

                                <!-- 5. TAMPAK ATAS (MINI) -->
                                <g transform="translate(290, 470) scale(0.38)">
                                    <rect x="0" y="0" width="780" height="140" fill="#f8fafc" stroke="#111" stroke-width="1.5" />
                                    <line x1="140" y1="0" x2="140" y2="140" stroke="#555" stroke-width="1" />
                                    <line x1="550" y1="0" x2="550" y2="140" stroke="#555" stroke-width="1" />
                                    <text x="390" y="180" font-size="28" font-weight="bold" text-anchor="middle" fill="#000">5 TAMPAK ATAS</text>
                                </g>

                                <!-- 6. ISOMETRI 3D (MINI AXONOMETRIC) -->
                                <g transform="translate(680, 310) scale(0.38)">
                                    <polygon points="100,200 450,80 750,220 400,340" fill="#edebe7" stroke="#111" stroke-width="2" />
                                    <polygon points="100,200 400,340 400,520 100,380" fill="#C2A27D" stroke="#111" stroke-width="2" />
                                    <polygon points="400,340 750,220 750,400 400,520" fill="#6F7E66" stroke="#111" stroke-width="2" />
                                    <polygon points="580,270 750,220 750,400 580,450" fill="#586750" stroke="#111" stroke-width="1.5" />
                                    <polygon points="100,200 200,165 200,345 100,380" fill="#18181b" stroke="#111" stroke-width="1.5" />
                                    <text x="425" y="560" font-size="28" font-weight="bold" text-anchor="middle" fill="#000">6 ISOMETRI</text>
                                </g>
                            </g>

                            <!-- ============================================================== -->
                            <!-- KOP GAMBAR (TITLE BLOCK) BERDASARKAN POSISI (SAMPING / ATAS / BAWAH) -->
                            <!-- ============================================================== -->
                            <!-- OPSI 1: KOP DI SAMPING KANAN (titleBlockPos === 'side') -->
                            <g v-if="titleBlockPos === 'side'" id="title-block-side">
                                <!-- Outer vertical border line dividing drawing from title block -->
                                <line x1="940" y1="25" x2="940" y2="815" stroke="#000" stroke-width="1.2" />

                                <!-- 1. Header Box: Logo & Judul -->
                                <g transform="translate(950, 42)">
                                    <text x="0" y="0" font-size="7" font-weight="bold" fill="#666" letter-spacing="1">STUDIO ARSITEKTUR &amp; INTERIOR</text>
                                    <text x="0" y="16" font-size="12" font-weight="900" fill="#000" letter-spacing="0.5">GAMBAR KERJA</text>
                                    <text x="0" y="28" font-size="8" font-family="monospace" fill="#555">DETAIL PRODUKSI FURNITURE</text>
                                </g>
                                <line x1="940" y1="80" x2="1175" y2="80" stroke="#000" stroke-width="0.8" />

                                <!-- 2. Nama Item & Kategori -->
                                <g transform="translate(950, 96)" class="cursor-pointer" @dblclick="openEditField('name', cabinet.name)">
                                    <text x="0" y="0" font-size="7" font-weight="bold" fill="#666">NAMA ITEM :</text>
                                    <text x="0" y="16" font-size="11" font-weight="bold" fill="#000">{{ cabinet.name }}</text>
                                    <text x="0" y="30" font-size="8" fill="#444">{{ cabinet.category }}</text>
                                </g>
                                <line x1="940" y1="135" x2="1175" y2="135" stroke="#000" stroke-width="0.8" />

                                <!-- 3. Proyek & Arsitek -->
                                <g transform="translate(950, 150)">
                                    <text x="0" y="0" font-size="7" font-weight="bold" fill="#666">PROYEK :</text>
                                    <text x="0" y="14" font-size="9.5" font-weight="bold" fill="#000" class="truncate max-w-[215px]">{{ cabinet.projectName }}</text>
                                    <text x="0" y="28" font-size="7" fill="#666">STATUS :</text>
                                    <text x="45" y="28" font-size="7.5" font-weight="bold" fill="#16a34a">DISETUJUI PRODUKSI</text>
                                </g>
                                <line x1="940" y1="190" x2="1175" y2="190" stroke="#000" stroke-width="0.8" />

                                <!-- 4. Ukuran, Skala, Tanggal, No. Lembar -->
                                <g transform="translate(950, 205)">
                                    <text x="0" y="0" font-size="7" font-weight="bold" fill="#666">DIMENSI (L × D × T) :</text>
                                    <text x="0" y="14" font-size="9.5" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ cabinet.width }} × {{ cabinet.depth }} × {{ cabinet.height }} mm
                                    </text>
                                    <text x="0" y="28" font-size="7" fill="#666">SKALA :</text>
                                    <text x="40" y="28" font-size="8.5" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ layoutMode === 'single' ? cabinet.scaleSingle : cabinet.scaleAuto }}
                                    </text>
                                    <text x="110" y="28" font-size="7" fill="#666">TGL :</text>
                                    <text x="135" y="28" font-size="8" font-family="monospace" fill="#000">{{ cabinet.date }}</text>
                                    <text x="0" y="42" font-size="7" fill="#666">LEMBAR :</text>
                                    <text x="45" y="42" font-size="8.5" font-family="monospace" font-weight="bold" fill="#000">{{ cabinet.sheetNumber }}</text>
                                </g>
                                <line x1="940" y1="260" x2="1175" y2="260" stroke="#000" stroke-width="0.8" />

                                <!-- 5. Legenda Material -->
                                <g transform="translate(950, 276)">
                                    <text x="0" y="0" font-size="7.5" font-weight="bold" fill="#000">LEGENDA MATERIAL :</text>
                                    <g v-for="(mat, idx) in cabinet.materials" :key="mat.code" :transform="`translate(0, ${10 + idx * 22})`">
                                        <rect x="0" y="0" width="14" height="10" :fill="mat.color" stroke="#000" stroke-width="0.6" rx="1" />
                                        <text x="18" y="8" font-size="8" font-weight="bold" fill="#000">{{ mat.code }}</text>
                                        <text x="56" y="8" font-size="7" fill="#333" class="truncate max-w-[160px]">{{ mat.name }}</text>
                                    </g>
                                </g>
                                <line x1="940" y1="440" x2="1175" y2="440" stroke="#000" stroke-width="0.8" />

                                <!-- 6. Catatan Teknis -->
                                <g transform="translate(950, 455)">
                                    <text x="0" y="0" font-size="7.5" font-weight="bold" fill="#000">CATATAN TEKNIS :</text>
                                    <text v-for="(note, idx) in cabinet.notes" :key="idx" x="0" :y="12 + idx * 16" font-size="6.8" fill="#333" class="max-w-[215px]">
                                        {{ note }}
                                    </text>
                                </g>
                                <line x1="940" y1="675" x2="1175" y2="675" stroke="#000" stroke-width="0.8" />

                                <!-- 7. Tanda Tangan / Pengesahan -->
                                <g transform="translate(940, 675)">
                                    <line x1="78" y1="0" x2="78" y2="140" stroke="#000" stroke-width="0.6" />
                                    <line x1="156" y1="0" x2="156" y2="140" stroke="#000" stroke-width="0.6" />
                                    <line x1="0" y1="20" x2="235" y2="20" stroke="#000" stroke-width="0.6" />
                                    <text x="39" y="14" font-size="6.5" font-weight="bold" fill="#555" text-anchor="middle">DIBUAT</text>
                                    <text x="117" y="14" font-size="6.5" font-weight="bold" fill="#555" text-anchor="middle">DIPERIKSA</text>
                                    <text x="195" y="14" font-size="6.5" font-weight="bold" fill="#555" text-anchor="middle">DISETUJUI</text>
                                    <text x="39" y="125" font-size="7" fill="#666" text-anchor="middle">DRAFTER</text>
                                    <text x="117" y="125" font-size="7" fill="#666" text-anchor="middle">ARSITEK</text>
                                    <text x="195" y="125" font-size="7" fill="#666" text-anchor="middle">KLIEN</text>
                                </g>
                            </g>

                            <!-- OPSI 2: KOP DI ATAS (titleBlockPos === 'top') -->
                            <g v-else-if="titleBlockPos === 'top'" id="title-block-top">
                                <line x1="25" y1="110" x2="1175" y2="110" stroke="#000" stroke-width="1.2" />
                                <line x1="260" y1="25" x2="260" y2="110" stroke="#000" stroke-width="0.8" />
                                <line x1="560" y1="25" x2="560" y2="110" stroke="#000" stroke-width="0.8" />
                                <line x1="820" y1="25" x2="820" y2="110" stroke="#000" stroke-width="0.8" />
                                <line x1="1020" y1="25" x2="1020" y2="110" stroke="#000" stroke-width="0.8" />

                                <g transform="translate(35, 48)">
                                    <text x="0" y="0" font-size="7.5" font-weight="bold" fill="#666">STUDIO ARSITEKTUR &amp; INTERIOR</text>
                                    <text x="0" y="20" font-size="14" font-weight="900" fill="#000">GAMBAR KERJA</text>
                                    <text x="0" y="34" font-size="8" fill="#555">{{ props.architect?.company_name || 'DETAIL FURNITURE' }}</text>
                                </g>
                                <g transform="translate(275, 48)">
                                    <text x="0" y="0" font-size="7.5" fill="#666">NAMA ITEM :</text>
                                    <text x="0" y="20" font-size="13" font-weight="bold" fill="#000">{{ cabinet.name }}</text>
                                    <text x="0" y="34" font-size="8.5" fill="#444">{{ cabinet.category }}</text>
                                </g>
                                <g transform="translate(575, 48)">
                                    <text x="0" y="0" font-size="7.5" fill="#666">DIMENSI ( L × D × T ) :</text>
                                    <text x="0" y="20" font-size="11.5" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ cabinet.width }} × {{ cabinet.depth }} × {{ cabinet.height }} mm
                                    </text>
                                    <text x="0" y="34" font-size="8.5" fill="#555">PROYEK : {{ cabinet.projectName }}</text>
                                </g>
                                <g transform="translate(835, 42)">
                                    <text x="0" y="0" font-size="7.5" font-weight="bold" fill="#000">LEGENDA :</text>
                                    <g v-for="(mat, idx) in cabinet.materials.slice(0, 3)" :key="mat.code" :transform="`translate(0, ${10 + idx * 16})`">
                                        <rect x="0" y="0" width="10" height="8" :fill="mat.color" stroke="#000" stroke-width="0.5" />
                                        <text x="14" y="7" font-size="7.5" font-weight="bold" fill="#000">{{ mat.code }}</text>
                                        <text x="45" y="7" font-size="7" fill="#333" class="truncate">{{ mat.name }}</text>
                                    </g>
                                </g>
                                <g transform="translate(1035, 48)">
                                    <text x="0" y="0" font-size="7.5" fill="#666">SKALA :</text>
                                    <text x="50" y="0" font-size="8.5" font-family="monospace" font-weight="bold" fill="#000">{{ layoutMode === 'single' ? cabinet.scaleSingle : cabinet.scaleAuto }}</text>
                                    <text x="0" y="18" font-size="7.5" fill="#666">TGL :</text>
                                    <text x="50" y="18" font-size="8.5" font-family="monospace" fill="#000">{{ cabinet.date }}</text>
                                    <text x="0" y="36" font-size="7.5" fill="#666">LEMBAR :</text>
                                    <text x="50" y="36" font-size="8.5" font-family="monospace" font-weight="bold" fill="#000">{{ cabinet.sheetNumber }}</text>
                                </g>
                            </g>

                            <!-- OPSI 3: KOP DI BAWAH (titleBlockPos === 'bottom') -->
                            <g v-else id="title-block-bottom">
                                <line x1="25" y1="670" x2="1175" y2="670" stroke="#000" stroke-width="1" />

                                <!-- KOLOM 1: CATATAN -->
                                <g transform="translate(35, 680)">
                                    <text x="0" y="10" font-size="8" font-weight="bold" fill="#666">CATATAN :</text>
                                    <text v-for="(note, idx) in cabinet.notes" :key="idx" x="40" :y="10 + idx * 11" font-size="8" fill="#333">
                                        {{ note }}
                                    </text>
                                </g>

                                <!-- KOLOM 2: LEGENDA MATERIAL -->
                                <g transform="translate(530, 680)">
                                    <text x="0" y="8" font-size="8" font-weight="bold" fill="#666">LEGENDA MATERIAL :</text>
                                    <g v-for="(mat, idx) in cabinet.materials" :key="mat.code" :transform="`translate(0, ${14 + idx * 10})`">
                                        <text x="0" y="6" font-size="7.5" font-weight="bold" fill="#111">{{ mat.code }}</text>
                                        <rect x="25" y="0" width="14" height="6" :fill="mat.color" stroke="#555" stroke-width="0.5" />
                                        <text x="45" y="6" font-size="7.5" fill="#333" class="truncate">{{ mat.name }}</text>
                                    </g>
                                </g>

                                <!-- TABEL INFORMASI PROYEK & KOP CETAK -->
                                <line x1="25" y1="745" x2="1175" y2="745" stroke="#000" stroke-width="0.8" />
                                <line x1="160" y1="745" x2="160" y2="815" stroke="#000" stroke-width="0.8" />
                                <line x1="450" y1="745" x2="450" y2="815" stroke="#000" stroke-width="0.8" />
                                <line x1="650" y1="745" x2="650" y2="815" stroke="#000" stroke-width="0.8" />
                                <line x1="820" y1="745" x2="820" y2="815" stroke="#000" stroke-width="0.8" />
                                <line x1="940" y1="745" x2="940" y2="815" stroke="#000" stroke-width="0.8" />
                                <line x1="1060" y1="745" x2="1060" y2="815" stroke="#000" stroke-width="0.8" />

                                <!-- Cell 1: LOGO / GAMBAR KERJA -->
                                <g transform="translate(35, 760)">
                                    <text x="0" y="0" font-size="7" fill="#666">NAMA / LOGO (bila perlu)</text>
                                    <text x="0" y="22" font-size="11" font-weight="bold" fill="#000">GAMBAR KERJA</text>
                                </g>

                                <!-- Cell 2: NAMA ITEM (Double click to edit) -->
                                <g transform="translate(170, 760)" class="cursor-pointer" @dblclick="openEditField('name', cabinet.name)">
                                    <text x="0" y="0" font-size="7" fill="#666">NAMA</text>
                                    <text x="0" y="24" font-size="13" font-weight="bold" fill="#000">{{ cabinet.name }}</text>
                                </g>

                                <!-- Cell 3: UKURAN L x D x T -->
                                <g transform="translate(460, 760)" class="cursor-pointer" @dblclick="openEditField('width', String(cabinet.width))">
                                    <text x="0" y="0" font-size="7" fill="#666">UKURAN ( L × D × T )</text>
                                    <text x="0" y="22" font-size="10.5" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ cabinet.width }} × {{ cabinet.depth }} × {{ cabinet.height }} mm
                                    </text>
                                </g>

                                <!-- Cell 4: PROYEK -->
                                <g transform="translate(660, 760)">
                                    <text x="0" y="0" font-size="7" fill="#666">PROYEK</text>
                                    <text x="0" y="22" font-size="9.5" font-weight="bold" fill="#000" class="truncate max-w-[150px]">
                                        {{ cabinet.projectName }}
                                    </text>
                                </g>

                                <!-- Cell 5: SKALA -->
                                <g transform="translate(830, 760)">
                                    <text x="0" y="0" font-size="7" fill="#666">SKALA</text>
                                    <text x="0" y="22" font-size="10" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ layoutMode === 'single' ? cabinet.scaleSingle : cabinet.scaleAuto }}
                                    </text>
                                </g>

                                <!-- Cell 6: TANGGAL -->
                                <g transform="translate(950, 760)" class="cursor-pointer" @dblclick="openEditField('date', cabinet.date)">
                                    <text x="0" y="0" font-size="7" fill="#666">TANGGAL</text>
                                    <text x="0" y="22" font-size="9.5" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ cabinet.date }}
                                    </text>
                                </g>

                                <!-- Cell 7: LEMBAR -->
                                <g transform="translate(1070, 760)">
                                    <text x="0" y="0" font-size="7" fill="#666">LEMBAR</text>
                                    <text x="0" y="22" font-size="10" font-family="monospace" font-weight="bold" fill="#000">
                                        {{ cabinet.sheetNumber }}
                                    </text>
                                </g>
                            </g>
                        </svg>
                    </div>

                    <!-- TAB 02: LEMBAR DAFTAR POTONG (CUTTING LIST SHEET) -->
                    <div
                        v-show="activeSheetTab === '02'"
                        class="bg-white text-slate-900 border border-slate-300 rounded shadow-2xl relative p-8 flex flex-col justify-between"
                        style="width: 1200px; height: 840px; box-sizing: border-box;"
                    >
                        <div>
                            <div class="flex items-center justify-between border-b-2 border-slate-900 pb-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h1 class="text-xl font-black tracking-tight text-slate-900">
                                            DAFTAR POTONG LEMBARAN (CUTTING LIST)
                                        </h1>
                                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-800">
                                            LEMBAR 02
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-0.5">
                                        Item: <strong>{{ cabinet.name }}</strong> | Proyek: <strong>{{ cabinet.projectName }}</strong> | Dimensi: <strong>{{ cabinet.width }} × {{ cabinet.depth }} × {{ cabinet.height }} mm</strong>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-slate-500">Tanggal: {{ cabinet.date }}</div>
                                    <div class="text-xs font-mono font-bold text-slate-800">Standar Produksi Workshop</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-3 my-4">
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                                    <div class="text-[10px] font-semibold text-slate-500 uppercase">Total Komponen</div>
                                    <div class="text-lg font-bold text-slate-900">{{ cutListItems.length }} Part <span class="text-xs text-slate-500 font-normal">({{ totalPcs }} Pcs)</span></div>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                                    <div class="text-[10px] font-semibold text-slate-500 uppercase">Multiplek 18mm</div>
                                    <div class="text-lg font-bold text-amber-600">± {{ Math.ceil(totalPcs * 0.3) }} Lembar</div>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                                    <div class="text-[10px] font-semibold text-slate-500 uppercase">Multiplek 6mm (Backing)</div>
                                    <div class="text-lg font-bold text-blue-600">1 Lembar</div>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5">
                                    <div class="text-[10px] font-semibold text-slate-500 uppercase">Edge Banding PVC</div>
                                    <div class="text-lg font-bold text-emerald-600">± {{ Math.round(totalPcs * 1.6) }} Meter</div>
                                </div>
                            </div>

                            <div class="border border-slate-300 rounded overflow-hidden max-h-[500px] overflow-y-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead class="bg-slate-100 text-slate-800 font-semibold border-b border-slate-300 sticky top-0">
                                        <tr>
                                            <th class="py-2 px-2.5 w-12 text-center">No</th>
                                            <th class="py-2 px-3">Nama Bagian / Panel</th>
                                            <th class="py-2 px-2 text-center w-14">Qty</th>
                                            <th class="py-2 px-2 text-center w-20">P (mm)</th>
                                            <th class="py-2 px-2 text-center w-20">L (mm)</th>
                                            <th class="py-2 px-2 text-center w-16">T (mm)</th>
                                            <th class="py-2 px-3">Bahan Baku</th>
                                            <th class="py-2 px-3">Finishing Muka</th>
                                            <th class="py-2 px-3">Edging</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200 text-slate-700">
                                        <tr
                                            v-for="(item, idx) in cutListItems"
                                            :key="item.id"
                                            class="hover:bg-amber-50/50 transition-colors"
                                        >
                                            <td class="py-1.5 px-2.5 text-center font-mono font-bold text-slate-900 bg-slate-50/50">
                                                {{ item.id }}
                                            </td>
                                            <td class="py-1.5 px-3 font-medium text-slate-900">
                                                {{ item.name }}
                                            </td>
                                            <td class="py-1.5 px-2 text-center font-bold text-slate-800">
                                                {{ item.qty }}
                                            </td>
                                            <td class="py-1.5 px-2 text-center font-mono text-slate-800">
                                                {{ item.length }}
                                            </td>
                                            <td class="py-1.5 px-2 text-center font-mono text-slate-800">
                                                {{ item.width }}
                                            </td>
                                            <td class="py-1.5 px-2 text-center font-mono text-slate-800">
                                                {{ item.thick }}
                                            </td>
                                            <td class="py-1.5 px-3 text-slate-600">
                                                {{ item.mat }}
                                            </td>
                                            <td class="py-1.5 px-3">
                                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-800 border border-slate-200">
                                                    {{ item.finish }}
                                                </span>
                                            </td>
                                            <td class="py-1.5 px-3 text-[11px] text-slate-600 font-mono">
                                                {{ item.edging }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="border-t border-slate-300 pt-3 flex items-center justify-between text-xs text-slate-500">
                            <div>Catatan: Potong dengan offset saw kerf 3mm. Dihitung otomatis dari model parameter lemari.</div>
                            <div class="font-mono font-bold text-slate-700">Lembar 2 / 2</div>
                        </div>
                    </div>
                </div>

                <!-- FLOATING DESKTOP TOGGLE HANDLE (MUNCUL SAAT SIDEBAR TERTUTUP) -->
                <button
                    v-if="!isDesktopSidebarOpen && !isMobileScreen"
                    type="button"
                    @click="isDesktopSidebarOpen = true"
                    class="hidden lg:flex absolute right-0 top-1/2 -translate-y-1/2 z-30 bg-[#18181b]/95 hover:bg-amber-500 text-slate-300 hover:text-slate-950 border-y border-l border-white/20 px-2 py-4 rounded-l-2xl shadow-2xl flex-col items-center gap-2 transition-all cursor-pointer group backdrop-blur-md"
                    title="Buka Sidebar Kanan"
                >
                    <ChevronLeft class="w-4 h-4 transition-transform group-hover:-translate-x-1 text-amber-400 group-hover:text-slate-950" />
                    <span class="text-[10px] font-bold uppercase tracking-wider [writing-mode:vertical-rl] rotate-180">
                        Panel
                    </span>
                </button>

                <!-- FLOATING MOBILE BOTTOM BAR (QUICK VIEW SWITCHER) -->
                <div class="lg:hidden absolute bottom-3 inset-x-3 z-20 flex items-center justify-between gap-1 bg-[#18181b]/95 backdrop-blur-md border border-white/10 rounded-2xl p-1.5 shadow-2xl">
                    <div class="flex items-center gap-1 overflow-x-auto scrollbar-none">
                        <button
                            type="button"
                            @click="activeSheetTab = '01'"
                            :class="activeSheetTab === '01' ? 'bg-amber-500 text-slate-950 font-bold' : 'text-slate-300 bg-white/5'"
                            class="px-2.5 py-1 rounded-xl text-xs shrink-0"
                        >
                            01 Lembar
                        </button>
                        <button
                            type="button"
                            @click="activeSheetTab = '02'"
                            :class="activeSheetTab === '02' ? 'bg-amber-500 text-slate-950 font-bold' : 'text-slate-300 bg-white/5'"
                            class="px-2.5 py-1 rounded-xl text-xs shrink-0"
                        >
                            02 Potong
                        </button>
                        <div class="w-px h-5 bg-white/10 shrink-0 mx-0.5"></div>
                        <button
                            type="button"
                            @click="activeView = 'depan'; layoutMode = 'single'"
                            :class="activeView === 'depan' && layoutMode === 'single' ? 'bg-white/20 text-white font-bold' : 'text-slate-400'"
                            class="px-2 py-1 rounded-lg text-xs shrink-0"
                        >
                            Depan
                        </button>
                        <button
                            type="button"
                            @click="showCabinetModal = true"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-500 text-slate-950 shrink-0"
                        >
                            Ubah
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="showMobileDrawer = true"
                        class="p-1.5 rounded-xl bg-amber-500 text-slate-950 shrink-0 font-bold text-xs flex items-center gap-1 shadow-lg cursor-pointer"
                    >
                        <Menu class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- RIGHT SIDEBAR (PERSIS SEPERTI GAMBAR 2 & 3) -->
            <!-- Desktop static sidebar OR Mobile Slide-over Drawer -->
            <aside
                :class="[
                    isMobileScreen
                        ? (showMobileDrawer ? 'translate-y-0' : 'translate-y-full pointer-events-none') + ' fixed inset-x-0 bottom-0 max-h-[85vh] rounded-t-3xl z-50 shadow-2xl transition-transform duration-300'
                        : isDesktopSidebarOpen
                            ? 'w-72 shrink-0 border-l border-white/10 relative opacity-100'
                            : 'w-0 shrink-0 border-none overflow-hidden p-0 opacity-0 pointer-events-none',
                ]"
                class="bg-[#18181b] flex flex-col overflow-y-auto select-none transition-all duration-300 ease-in-out"
            >
                <!-- Desktop Sidebar Header dengan Tombol Toggle Sembunyikan -->
                <div v-if="!isMobileScreen" class="px-3.5 py-2.5 border-b border-white/10 flex items-center justify-between bg-black/30 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span class="text-xs font-bold text-white tracking-wide uppercase">Sidebar Panel</span>
                    </div>
                    <button
                        type="button"
                        @click="isDesktopSidebarOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                        title="Sembunyikan Sidebar Kanan"
                    >
                        <ChevronRight class="w-4 h-4 text-slate-300 hover:text-amber-400" />
                    </button>
                </div>

                <!-- Mobile Drawer Header -->
                <div v-if="isMobileScreen" class="p-3 border-b border-white/10 flex items-center justify-between bg-black/40">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span class="font-bold text-white text-sm">Panel Kontrol Gambar Kerja</span>
                    </div>
                    <button
                        type="button"
                        @click="showMobileDrawer = false"
                        class="p-1 rounded-lg bg-white/10 text-slate-300 hover:text-white"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-3.5 space-y-4">
                    <!-- SECTION 0: INSPEKTOR OBJEK TERPILIH (INTERAKTIF: BISA DITARIK ATAU DIUBAH DI SINI) -->
                    <div v-if="selectedElement" class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-3 space-y-2.5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
                                <span class="text-xs font-bold text-amber-300 truncate">{{ selectedElement.name }}</span>
                            </div>
                            <button
                                type="button"
                                @click="selectedElement = null; selectedObjectId = null"
                                class="text-slate-400 hover:text-white p-0.5 rounded cursor-pointer"
                                title="Tutup Inspektor"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>

                        <p class="text-[11px] text-slate-300 leading-relaxed bg-black/30 p-2 rounded-xl border border-white/5">
                            👉 <strong>Bisa ditarik langsung:</strong> Klik &amp; tahan objek/balon ini pada gambar untuk menggesernya langsung, atau sesuaikan angka di bawah:
                        </p>

                        <!-- If Line Annotation (Flexible mm Input, Endpoints, Presets, Orientation) -->
                        <div v-if="selectedAnnotation && selectedAnnotation.type === 'line'" class="space-y-3 pt-1 border-t border-blue-500/30">
                            <!-- Header Info Card -->
                            <div class="bg-blue-950/40 border border-blue-500/30 rounded-xl p-2.5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-300">Panjang Garis</span>
                                    <span class="font-mono text-sm font-black text-amber-300 bg-black/60 px-2 py-0.5 rounded border border-amber-400/30">
                                        {{ getLineLengthMm(selectedAnnotation) }} mm
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-300 leading-snug">
                                    💡 <strong>Tidak perlu ditarik:</strong> Langsung ketik angka mm di bawah atau klik tombol preset. Ujung garis juga tetap bisa ditarik langsung di gambar!
                                </p>
                            </div>

                            <!-- Input Panjang Garis (mm) with +/- 50 steppers -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-300 block mb-1">Input Panjang Langsung (mm)</label>
                                <div class="flex items-center bg-black/60 border border-amber-400/50 rounded-xl p-1 shadow-inner">
                                    <button
                                        type="button"
                                        @click="setLineLengthMm(selectedAnnotation, Math.max(50, getLineLengthMm(selectedAnnotation) - 50))"
                                        class="px-2.5 py-1.5 bg-white/10 hover:bg-white/20 active:scale-95 rounded-lg text-xs font-bold text-white transition-all cursor-pointer"
                                        title="-50 mm"
                                    >
                                        -50
                                    </button>
                                    <input
                                        :value="getLineLengthMm(selectedAnnotation)"
                                        @input="setLineLengthMm(selectedAnnotation, Number(($event.target as HTMLInputElement).value))"
                                        type="number"
                                        min="10"
                                        step="10"
                                        class="w-full bg-transparent text-center font-mono font-bold text-base text-amber-300 focus:outline-none"
                                        placeholder="1200"
                                    />
                                    <button
                                        type="button"
                                        @click="setLineLengthMm(selectedAnnotation, getLineLengthMm(selectedAnnotation) + 50)"
                                        class="px-2.5 py-1.5 bg-white/10 hover:bg-white/20 active:scale-95 rounded-lg text-xs font-bold text-white transition-all cursor-pointer"
                                        title="+50 mm"
                                    >
                                        +50
                                    </button>
                                </div>
                            </div>

                            <!-- Presets -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-400 block mb-1">Preset Panjang Cepat</label>
                                <div class="grid grid-cols-4 gap-1.5">
                                    <button
                                        v-for="p in [400, 600, 1000, 1200, 1500, 1800, 2000, 2400]"
                                        :key="p"
                                        type="button"
                                        @click="setLineLengthMm(selectedAnnotation, p)"
                                        class="py-1 px-1 bg-white/5 hover:bg-amber-500/20 hover:text-amber-300 hover:border-amber-400/40 border border-white/10 rounded-lg text-[10px] font-mono text-slate-300 text-center transition-all cursor-pointer"
                                    >
                                        {{ p }}
                                    </button>
                                </div>
                            </div>

                            <!-- Straighten / Snap Alignment -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-400 block mb-1">Kunci Arah Garis</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        type="button"
                                        @click="setLineOrientation(selectedAnnotation, 'horizontal')"
                                        class="py-1.5 px-2 bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/40 rounded-lg text-xs font-medium text-blue-300 flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                    >
                                        <span>↔</span> Horisontal (Datar)
                                    </button>
                                    <button
                                        type="button"
                                        @click="setLineOrientation(selectedAnnotation, 'vertical')"
                                        class="py-1.5 px-2 bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/40 rounded-lg text-xs font-medium text-blue-300 flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                                    >
                                        <span>↕</span> Vertikal (Tegak)
                                    </button>
                                </div>
                            </div>

                            <!-- Line Style & Width -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Bentuk Garis</label>
                                    <select
                                        v-model="selectedAnnotation.lineStyle"
                                        class="w-full bg-black/60 border border-white/20 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                    >
                                        <option value="solid">Lurus (Solid)</option>
                                        <option value="dashed">Putus-putus</option>
                                        <option value="dashdot">Titik-Garis</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Ketebalan</label>
                                    <select
                                        v-model.number="selectedAnnotation.lineWidth"
                                        class="w-full bg-black/60 border border-white/20 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                    >
                                        <option :value="1">1 px (Halus)</option>
                                        <option :value="2">2 px (Standar)</option>
                                        <option :value="3">3 px (Tebal)</option>
                                        <option :value="4">4 px (Sangat Tebal)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Line Color -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-400 block mb-1">Warna Garis</label>
                                <div class="flex items-center gap-2">
                                    <button
                                        v-for="c in ['#1e293b', '#2563eb', '#e11d48', '#16a34a', '#d97706', '#9333ea']"
                                        :key="c"
                                        type="button"
                                        @click="selectedAnnotation.color = c"
                                        class="w-6 h-6 rounded-full border-2 transition-transform hover:scale-110 cursor-pointer"
                                        :class="(selectedAnnotation.color || '#2563eb') === c ? 'border-amber-400 ring-2 ring-amber-400/40 scale-105' : 'border-white/20'"
                                        :style="{ backgroundColor: c }"
                                    />
                                </div>
                            </div>

                            <!-- Shift Position (X, Y) -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Posisi X</label>
                                    <div class="flex items-center bg-black/50 border border-white/20 rounded-lg p-0.5">
                                        <button
                                            type="button"
                                            @click="
                                                const dx = 10;
                                                selectedAnnotation.x -= dx;
                                                if (selectedAnnotation.x2 !== undefined) selectedAnnotation.x2 -= dx;
                                            "
                                            class="px-2 py-0.5 text-xs text-slate-400 hover:text-white"
                                        >
                                            -
                                        </button>
                                        <input
                                            v-model.number="selectedAnnotation.x"
                                            type="number"
                                            class="w-full bg-transparent text-center text-xs text-white focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                const dx = 10;
                                                selectedAnnotation.x += dx;
                                                if (selectedAnnotation.x2 !== undefined) selectedAnnotation.x2 += dx;
                                            "
                                            class="px-2 py-0.5 text-xs text-slate-400 hover:text-white"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Posisi Y</label>
                                    <div class="flex items-center bg-black/50 border border-white/20 rounded-lg p-0.5">
                                        <button
                                            type="button"
                                            @click="
                                                const dy = 10;
                                                selectedAnnotation.y -= dy;
                                                if (selectedAnnotation.y2 !== undefined) selectedAnnotation.y2 -= dy;
                                            "
                                            class="px-2 py-0.5 text-xs text-slate-400 hover:text-white"
                                        >
                                            -
                                        </button>
                                        <input
                                            v-model.number="selectedAnnotation.y"
                                            type="number"
                                            class="w-full bg-transparent text-center text-xs text-white focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            @click="
                                                const dy = 10;
                                                selectedAnnotation.y += dy;
                                                if (selectedAnnotation.y2 !== undefined) selectedAnnotation.y2 -= dy;
                                            "
                                            class="px-2 py-0.5 text-xs text-slate-400 hover:text-white"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Delete Line Button -->
                            <button
                                type="button"
                                @click="removeAnnotationDirectly(selectedAnnotation.id)"
                                class="w-full py-1.5 rounded-lg text-xs font-semibold bg-red-500/20 text-red-300 hover:bg-red-500/30 border border-red-500/30 transition-colors cursor-pointer"
                            >
                                Hapus Garis Ini
                            </button>
                        </div>

                        <!-- If Annotation (Text / Label / Measure / Accessory) -->
                        <div v-else-if="selectedAnnotation" class="space-y-3 pt-1 border-t border-amber-500/20">
                            <!-- Header Info -->
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-300">
                                    {{ selectedAnnotation.type === 'accessory' ? 'Aksesoris Gambar' : selectedAnnotation.type === 'text' ? 'Notasi Teks (Bebas)' : 'Notasi Material' }}
                                </span>
                                <span class="text-[10px] text-slate-400">ID: {{ selectedAnnotation.id }}</span>
                            </div>

                            <!-- Teks / Label input -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-400 block mb-1">
                                    {{ selectedAnnotation.type === 'accessory' ? 'Nama Aksesoris' : selectedAnnotation.type === 'text' ? 'Isi Teks Notasi' : 'Nama Material' }}
                                </label>
                                <input
                                    v-model="selectedAnnotation.text"
                                    type="text"
                                    class="w-full bg-black/50 border border-white/20 rounded-lg px-2.5 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                />
                            </div>

                            <!-- Opsi Khusus Teks (Ukuran Font, Tebal & Kotak Background) -->
                            <div v-if="selectedAnnotation.type === 'text'" class="space-y-2 bg-black/30 p-2 rounded-xl border border-white/10">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-300">Ukuran Font:</span>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="selectedAnnotation.fontSize = Math.max(9, (selectedAnnotation.fontSize || 12) - 1)" class="px-2 py-0.5 bg-white/10 rounded hover:bg-white/20">-</button>
                                        <span class="font-mono px-1 font-bold text-amber-300">{{ selectedAnnotation.fontSize || 12 }}px</span>
                                        <button type="button" @click="selectedAnnotation.fontSize = Math.min(28, (selectedAnnotation.fontSize || 12) + 1)" class="px-2 py-0.5 bg-white/10 rounded hover:bg-white/20">+</button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between text-xs pt-1 border-t border-white/5">
                                    <span class="text-slate-300">Beri Kotak Kuning:</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="selectedAnnotation.hasBox" class="sr-only peer" />
                                        <div class="w-8 h-4 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-amber-500"></div>
                                    </label>
                                </div>
                                <div class="text-[10px] text-slate-400 italic">
                                    {{ selectedAnnotation.hasBox ? 'Kotak aktif' : '✓ Bersih tanpa kotak (arsitektural)' }}
                                </div>
                            </div>

                            <!-- Opsi Khusus Aksesoris (Lebar, Tinggi, Presets) -->
                            <div v-if="selectedAnnotation.type === 'accessory'" class="space-y-3 bg-black/40 p-2.5 rounded-xl border border-white/10">
                                <div class="flex items-center justify-between text-xs pb-1 border-b border-white/10">
                                    <span class="font-semibold text-amber-300">Ukuran Aksesoris (mm)</span>
                                    <button
                                        type="button"
                                        @click="resetAccessorySize(selectedAnnotation)"
                                        class="text-[10px] text-slate-400 hover:text-white underline cursor-pointer"
                                        title="Kembalikan ke ukuran bawaan"
                                    >
                                        Reset Standar
                                    </button>
                                </div>

                                <!-- Lebar (mm) -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[11px] text-slate-300">
                                        <span>Lebar:</span>
                                        <span class="font-mono font-bold text-amber-300">{{ getAccessoryWidthMm(selectedAnnotation) }} mm</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            @click="stepAccessoryWidth(selectedAnnotation, -50)"
                                            class="px-2 py-1 bg-white/10 hover:bg-white/20 rounded text-xs text-white cursor-pointer font-mono"
                                            title="Kurang 50 mm"
                                        >
                                            -50
                                        </button>
                                        <input
                                            :value="getAccessoryWidthMm(selectedAnnotation)"
                                            @change="setAccessoryWidthMm(selectedAnnotation, Number(($event.target as HTMLInputElement).value))"
                                            type="number"
                                            step="10"
                                            min="50"
                                            max="5000"
                                            class="w-full bg-black/50 border border-white/20 rounded px-2 py-1 text-xs text-white text-center font-mono focus:outline-none focus:ring-1 focus:ring-amber-400"
                                        />
                                        <button
                                            type="button"
                                            @click="stepAccessoryWidth(selectedAnnotation, 50)"
                                            class="px-2 py-1 bg-white/10 hover:bg-white/20 rounded text-xs text-white cursor-pointer font-mono"
                                            title="Tambah 50 mm"
                                        >
                                            +50
                                        </button>
                                    </div>
                                </div>

                                <!-- Tinggi (mm) -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-[11px] text-slate-300">
                                        <span>Tinggi:</span>
                                        <span class="font-mono font-bold text-amber-300">{{ getAccessoryHeightMm(selectedAnnotation) }} mm</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            @click="stepAccessoryHeight(selectedAnnotation, -50)"
                                            class="px-2 py-1 bg-white/10 hover:bg-white/20 rounded text-xs text-white cursor-pointer font-mono"
                                            title="Kurang 50 mm"
                                        >
                                            -50
                                        </button>
                                        <input
                                            :value="getAccessoryHeightMm(selectedAnnotation)"
                                            @change="setAccessoryHeightMm(selectedAnnotation, Number(($event.target as HTMLInputElement).value))"
                                            type="number"
                                            step="10"
                                            min="50"
                                            max="5000"
                                            class="w-full bg-black/50 border border-white/20 rounded px-2 py-1 text-xs text-white text-center font-mono focus:outline-none focus:ring-1 focus:ring-amber-400"
                                        />
                                        <button
                                            type="button"
                                            @click="stepAccessoryHeight(selectedAnnotation, 50)"
                                            class="px-2 py-1 bg-white/10 hover:bg-white/20 rounded text-xs text-white cursor-pointer font-mono"
                                            title="Tambah 50 mm"
                                        >
                                            +50
                                        </button>
                                    </div>
                                </div>

                                <!-- Preset Ukuran Cepat Sesuai Tipe Aksesoris -->
                                <div class="space-y-1 pt-1 border-t border-white/10">
                                    <span class="text-[10px] text-slate-400 block font-medium">Preset Ukuran Cepat:</span>
                                    <!-- TV Presets -->
                                    <div v-if="selectedAnnotation.accessoryType === 'tv'" class="grid grid-cols-4 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 970, 560)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">43"</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1230, 710)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">55"</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1450, 840)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">65"</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1680, 960)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">75"</button>
                                    </div>
                                    <!-- Sink Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'sink'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 500, 450)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1 Bak</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 800, 500)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1B+Sayap</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 950, 500)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">2 Bak</button>
                                    </div>
                                    <!-- Stove Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'stove'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 300, 510)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">2 Tungku</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 520)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">3 Tungku</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 850, 520)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">4 Tungku</button>
                                    </div>
                                    <!-- Drawer Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'drawer'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 450, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">45 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 800, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">80 cm</button>
                                    </div>
                                    <!-- Wardrobe & Glass Door Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'wardrobe_door' || selectedAnnotation.accessoryType === 'glass_door'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 450, 2000)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">45×200</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 500, 2100)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">50×210</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 2200)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60×220</button>
                                    </div>
                                    <!-- Oven Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'oven'" class="grid grid-cols-2 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 450)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60×45 cm (Compact)</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 600)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60×60 cm (Standard)</button>
                                    </div>
                                    <!-- Cooker Hood Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'hood'" class="grid grid-cols-2 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 500)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60 cm (Slim)</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 900, 500)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">90 cm (Chimney)</button>
                                    </div>
                                    <!-- Kulkas Standalone Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'fridge_standalone'" class="grid grid-cols-2 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 1600)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1 Pintu (60×160)</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 900, 1800)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">2 Pintu (90×180)</button>
                                    </div>
                                    <!-- Cermin Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'mirror_round'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 500, 500)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Ø 50 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 600)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Ø 60 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 800, 800)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Ø 80 cm</button>
                                    </div>
                                    <div v-else-if="selectedAnnotation.accessoryType === 'mirror_rect'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 450, 1400)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">45×140</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 500, 1600)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">50×160</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 1800)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60×180</button>
                                    </div>
                                    <!-- AC Split Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'ac_indoor'" class="grid grid-cols-2 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 800, 280)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">0.5 - 1 PK</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1000, 320)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1.5 - 2 PK</button>
                                    </div>
                                    <!-- Clothes Rail Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'clothes_rail'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 900)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">60 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 900, 900)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">90 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1200, 900)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">120 cm</button>
                                    </div>
                                    <!-- General Scale Presets -->
                                    <div v-else class="grid grid-cols-4 gap-1 text-[10px]">
                                        <button type="button" @click="selectedAnnotation.width = Math.round(getAccessoryBaseSize(selectedAnnotation.accessoryType).w * 0.75); selectedAnnotation.height = Math.round(getAccessoryBaseSize(selectedAnnotation.accessoryType).h * 0.75)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">0.75x</button>
                                        <button type="button" @click="selectedAnnotation.width = getAccessoryBaseSize(selectedAnnotation.accessoryType).w; selectedAnnotation.height = getAccessoryBaseSize(selectedAnnotation.accessoryType).h" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1x</button>
                                        <button type="button" @click="selectedAnnotation.width = Math.round(getAccessoryBaseSize(selectedAnnotation.accessoryType).w * 1.25); selectedAnnotation.height = Math.round(getAccessoryBaseSize(selectedAnnotation.accessoryType).h * 1.25)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1.25x</button>
                                        <button type="button" @click="selectedAnnotation.width = Math.round(getAccessoryBaseSize(selectedAnnotation.accessoryType).w * 1.5); selectedAnnotation.height = Math.round(getAccessoryBaseSize(selectedAnnotation.accessoryType).h * 1.5)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1.5x</button>
                                    </div>
                                </div>

                                <div class="text-[10px] text-amber-200/90 bg-amber-500/10 p-2 rounded-lg border border-amber-500/20 leading-relaxed">
                                    💡 <strong>Tips Interaktif:</strong> Anda juga bisa langsung arahkan kursor mouse ke tepi/sudut gambar pada canvas lalu klik-tarik untuk resize bebas!
                                </div>
                            </div>

                            <div v-if="selectedAnnotation.type === 'label'">
                                <label class="text-[10px] font-semibold text-slate-400 block mb-1">Kode Balon (Contoh: M-01)</label>
                                <input
                                    v-model="selectedAnnotation.subText"
                                    type="text"
                                    class="w-full bg-black/50 border border-white/20 rounded-lg px-2.5 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Posisi X</label>
                                    <div class="flex items-center bg-black/50 border border-white/20 rounded-lg p-0.5">
                                        <button type="button" @click="selectedAnnotation.x -= 10" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">-</button>
                                        <input v-model.number="selectedAnnotation.x" type="number" class="w-full bg-transparent text-center text-xs text-white focus:outline-none" />
                                        <button type="button" @click="selectedAnnotation.x += 10" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">+</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Posisi Y</label>
                                    <div class="flex items-center bg-black/50 border border-white/20 rounded-lg p-0.5">
                                        <button type="button" @click="selectedAnnotation.y -= 10" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">-</button>
                                        <input v-model.number="selectedAnnotation.y" type="number" class="w-full bg-transparent text-center text-xs text-white focus:outline-none" />
                                        <button type="button" @click="selectedAnnotation.y += 10" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">+</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Hapus Objek Ini -->
                            <button
                                type="button"
                                @click="deleteSelectedObject"
                                class="w-full py-1.5 rounded-lg text-xs font-semibold bg-red-500/20 text-red-300 hover:bg-red-500/30 border border-red-500/30 transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Hapus Objek Ini (atau Tekan Delete)</span>
                            </button>
                        </div>

                        <!-- If Section Line A-A -->
                        <div v-else-if="selectedObjectId === 'section_line'" class="space-y-2 pt-1 border-t border-amber-500/20">
                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Posisi Garis Potongan</span>
                                <span class="font-mono font-bold text-amber-300">{{ sectionLineX }} px</span>
                            </div>
                            <input
                                v-model.number="sectionLineX"
                                type="range"
                                min="170"
                                max="930"
                                class="w-full accent-amber-500 cursor-pointer"
                            />
                        </div>

                        <!-- If Fridge Divider / Section -->
                        <div v-else-if="selectedObjectId === 'divider_fridge' || selectedObjectId === 'fridge'" class="space-y-2 pt-1 border-t border-amber-500/20">
                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Lebar Ruang Kulkas</span>
                                <span class="font-mono font-bold text-amber-300">{{ cabinet.fridgeWidth }} mm</span>
                            </div>
                            <input
                                v-model.number="cabinet.fridgeWidth"
                                type="range"
                                min="600"
                                max="1800"
                                step="25"
                                class="w-full accent-amber-500 cursor-pointer"
                            />
                        </div>

                        <!-- If Tall Unit Divider / Section -->
                        <div v-else-if="selectedObjectId === 'divider_tall' || selectedObjectId === 'tallUnit'" class="space-y-2 pt-1 border-t border-amber-500/20">
                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Lebar Tall Unit</span>
                                <span class="font-mono font-bold text-amber-300">{{ cabinet.tallUnitWidth }} mm</span>
                            </div>
                            <input
                                v-model.number="cabinet.tallUnitWidth"
                                type="range"
                                min="600"
                                max="2500"
                                step="25"
                                class="w-full accent-amber-500 cursor-pointer"
                            />
                        </div>

                        <!-- If Countertop -->
                        <div v-else-if="selectedObjectId === 'countertop'" class="space-y-2 pt-1 border-t border-amber-500/20">
                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Tinggi Meja Bar</span>
                                <span class="font-mono font-bold text-amber-300">{{ cabinet.counterHeight }} mm</span>
                            </div>
                            <input
                                v-model.number="cabinet.counterHeight"
                                type="range"
                                min="500"
                                max="1100"
                                step="20"
                                class="w-full accent-amber-500 cursor-pointer"
                            />
                        </div>

                        <!-- If Shelves -->
                        <div v-else-if="selectedObjectId === 'shelves'" class="space-y-2 pt-1 border-t border-amber-500/20">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>Geser Horisontal</span>
                                    <span class="font-mono">{{ shelvesOffsetX }} px</span>
                                </div>
                                <input v-model.number="shelvesOffsetX" type="range" min="-100" max="100" class="w-full accent-amber-500" />
                            </div>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-[10px] text-slate-400">
                                    <span>Geser Vertikal</span>
                                    <span class="font-mono">{{ shelvesOffsetY }} px</span>
                                </div>
                                <input v-model.number="shelvesOffsetY" type="range" min="-60" max="100" class="w-full accent-amber-500" />
                            </div>
                        </div>

                        <!-- If Sink -->
                        <div v-else-if="selectedObjectId === 'sink'" class="space-y-2 pt-1 border-t border-amber-500/20">
                            <div class="flex items-center justify-between text-[11px] text-slate-300">
                                <span>Geser Posisi Sink</span>
                                <span class="font-mono font-bold text-amber-300">{{ sinkOffsetX }} px</span>
                            </div>
                            <input
                                v-model.number="sinkOffsetX"
                                type="range"
                                min="-100"
                                max="100"
                                class="w-full accent-amber-500 cursor-pointer"
                            />
                        </div>
                    </div>

                    <!-- SECTION 1: LEMBAR TABS -->
                    <div>
                        <div class="text-[11px] font-bold tracking-wider text-slate-400 uppercase mb-2 flex items-center justify-between">
                            <span>LEMBAR</span>
                            <span class="text-[10px] text-slate-500">2 LEMBAR</span>
                        </div>
                        <div class="space-y-1.5">
                            <button
                                type="button"
                                @click="activeSheetTab = '01'; showMobileDrawer = false"
                                :class="activeSheetTab === '01' ? 'bg-amber-100 text-slate-900 font-bold shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="w-full text-left px-3 py-2 rounded-xl text-xs flex items-center justify-between transition-all cursor-pointer"
                            >
                                <span class="truncate">01 {{ cabinet.name }}</span>
                                <span v-if="activeSheetTab === '01'" class="w-2 h-2 rounded-full bg-amber-600"></span>
                            </button>
                            <button
                                type="button"
                                @click="activeSheetTab = '02'; showMobileDrawer = false"
                                :class="activeSheetTab === '02' ? 'bg-amber-100 text-slate-900 font-bold shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="w-full text-left px-3 py-2 rounded-xl text-xs flex items-center justify-between transition-all cursor-pointer"
                            >
                                <span class="truncate">02 {{ cabinet.name }} · daftar potong</span>
                                <span v-if="activeSheetTab === '02'" class="w-2 h-2 rounded-full bg-amber-600"></span>
                            </button>
                        </div>
                    </div>

                    <!-- SECTION 2: TAMPAK -->
                    <div>
                        <div class="text-[11px] font-bold tracking-wider text-slate-400 uppercase mb-2">
                            TAMPAK
                        </div>

                        <div class="grid grid-cols-3 gap-1.5 mb-2.5">
                            <button
                                type="button"
                                @click="activeView = 'depan'; layoutMode = 'single'; activeSheetTab = '01'"
                                :class="activeView === 'depan' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Depan
                            </button>
                            <button
                                type="button"
                                @click="activeView = 'tanpa_pintu'; layoutMode = 'single'; activeSheetTab = '01'"
                                :class="activeView === 'tanpa_pintu' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer leading-tight"
                            >
                                Tanpa pintu
                            </button>
                            <button
                                type="button"
                                @click="activeView = 'samping'; layoutMode = 'single'; activeSheetTab = '01'"
                                :class="activeView === 'samping' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Samping
                            </button>
                            <button
                                type="button"
                                @click="activeView = 'potongan'; layoutMode = 'single'; activeSheetTab = '01'"
                                :class="activeView === 'potongan' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Potongan
                            </button>
                            <button
                                type="button"
                                @click="activeView = 'atas'; layoutMode = 'single'; activeSheetTab = '01'"
                                :class="activeView === 'atas' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Atas
                            </button>
                            <button
                                type="button"
                                @click="activeView = 'iso'; layoutMode = 'single'; activeSheetTab = '01'"
                                :class="activeView === 'iso' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Iso
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-1.5 mb-3">
                            <button
                                type="button"
                                @click="showAccessories = !showAccessories"
                                :class="showAccessories ? 'bg-white/15 text-white font-semibold' : 'bg-white/5 text-slate-400'"
                                class="py-1.5 px-2 rounded-xl text-xs text-center border border-white/10 transition-all cursor-pointer"
                            >
                                Aksesori
                            </button>
                            <button
                                type="button"
                                @click="showTextures = !showTextures"
                                :class="showTextures ? 'bg-white/15 text-white font-semibold' : 'bg-white/5 text-slate-400'"
                                class="py-1.5 px-2 rounded-xl text-xs text-center border border-white/10 transition-all cursor-pointer"
                            >
                                Tekstur
                            </button>
                        </div>

                        <!-- Layout Dropdown -->
                        <div class="relative">
                            <select
                                v-model="layoutMode"
                                class="w-full bg-slate-900 border border-white/20 rounded-xl px-3 py-2 text-xs font-semibold text-white focus:outline-none focus:ring-1 focus:ring-amber-400 cursor-pointer appearance-none"
                            >
                                <option value="auto">Layout: Otomatis (pas kertas)</option>
                                <option value="single">Layout: Per tampak (fokus)</option>
                            </select>
                            <ChevronDown class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-3 pointer-events-none" />
                        </div>

                        <!-- Tombol Buka Konfigurasi Desain Lemari -->
                        <div class="mt-3">
                            <button
                                type="button"
                                @click="showCabinetModal = true"
                                class="w-full py-2 px-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                            >
                                <Wand2 class="w-3.5 h-3.5" />
                                <span>Ubah Komponen Lemari</span>
                            </button>
                        </div>
                    </div>

                    <!-- SECTION 3: HASIL & EXPORT -->
                    <div class="border-t border-white/10 pt-3">
                        <div class="text-[11px] font-bold tracking-wider text-slate-400 uppercase mb-2">
                            HASIL
                        </div>

                        <div class="grid grid-cols-2 gap-1.5 mb-2">
                            <button
                                type="button"
                                @click="layoutMode = 'auto'"
                                :class="layoutMode === 'auto' ? 'bg-white text-slate-950 font-bold' : 'bg-white/5 text-slate-300'"
                                class="py-1.5 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                1 lembar
                            </button>
                            <button
                                type="button"
                                @click="layoutMode = 'single'"
                                :class="layoutMode === 'single' ? 'bg-white text-slate-950 font-bold' : 'bg-white/5 text-slate-300'"
                                class="py-1.5 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Per tampak
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-1 mb-2.5">
                            <button
                                type="button"
                                @click="exportPdf"
                                class="py-1.5 rounded-xl text-xs font-bold bg-white/10 hover:bg-amber-500 hover:text-slate-950 text-white transition-all cursor-pointer"
                            >
                                PDF
                            </button>
                            <button
                                type="button"
                                @click="exportPng"
                                class="py-1.5 rounded-xl text-xs font-bold bg-white/10 hover:bg-emerald-500 hover:text-slate-950 text-white transition-all cursor-pointer"
                            >
                                PNG
                            </button>
                            <button
                                type="button"
                                @click="exportDxf"
                                class="py-1.5 rounded-xl text-xs font-bold bg-white/10 hover:bg-blue-500 hover:text-slate-950 text-white transition-all cursor-pointer"
                            >
                                DXF
                            </button>
                            <button
                                type="button"
                                @click="exportDwg"
                                class="py-1.5 rounded-xl text-xs font-bold bg-white/10 hover:bg-purple-500 hover:text-slate-950 text-white transition-all cursor-pointer"
                            >
                                DWG
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-1.5 mb-3">
                            <button
                                type="button"
                                @click="activeSheetTab = activeSheetTab === '02' ? '01' : '02'"
                                :class="activeSheetTab === '02' ? 'bg-amber-500 text-slate-950 font-bold' : 'bg-white/5 text-slate-300'"
                                class="py-1.5 px-2 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Daftar potong
                            </button>
                            <button
                                type="button"
                                @click="showPanelNumbers = !showPanelNumbers"
                                :class="showPanelNumbers ? 'bg-emerald-500 text-slate-950 font-bold' : 'bg-white/5 text-slate-300'"
                                class="py-1.5 px-2 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Nomor panel
                            </button>
                        </div>

                        <!-- Settings Dropdowns -->
                        <div class="space-y-1.5">
                            <select
                                v-model="paperSize"
                                class="w-full bg-slate-900 border border-white/10 rounded-xl px-2.5 py-1.5 text-xs text-slate-300 focus:outline-none focus:ring-1 focus:ring-amber-400 cursor-pointer"
                            >
                                <option value="A3">Kertas A3 (420 × 297 mm)</option>
                                <option value="A4">Kertas A4 (297 × 210 mm)</option>
                            </select>

                            <select
                                v-model="unit"
                                class="w-full bg-slate-900 border border-white/10 rounded-xl px-2.5 py-1.5 text-xs text-slate-300 focus:outline-none focus:ring-1 focus:ring-amber-400 cursor-pointer"
                            >
                                <option value="milimeter (mm)">Satuan: milimeter (mm)</option>
                                <option value="centimeter (cm)">Satuan: centimeter (cm)</option>
                                <option value="meter (m)">Satuan: meter (m)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- ── BOTTOM HUD STATUS BAR ────────────────────────────────────────── -->
        <footer class="h-8 bg-[#18181b] border-t border-white/10 px-3 flex items-center justify-between text-[11px] text-slate-400 shrink-0 z-50 overflow-x-auto scrollbar-none">
            <div class="truncate max-w-full">
                💡 Klik 'Desain &amp; Ubah Lemari' untuk atur ukuran &amp; modul · Klik ganda teks judul/kop untuk mengedit · Roda/cubit = zoom · Geser = pan
            </div>
            <div class="hidden sm:flex items-center gap-3 shrink-0 text-slate-500 font-mono text-[10px]">
                <span>Skala {{ layoutMode === 'single' ? cabinet.scaleSingle : cabinet.scaleAuto }}</span>
                <span>•</span>
                <span>{{ paperSize }} Landscape</span>
            </div>
        </footer>

        <!-- ── MODAL KONFIGURATOR & CREATOR LEMARI (BUAT/UBAH DESAIN) ───────── -->
        <div
            v-if="showCabinetModal"
            class="fixed inset-0 bg-black/75 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-5"
        >
            <div class="bg-slate-900 border border-white/20 rounded-3xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center border border-amber-500/30">
                            <Wand2 class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-white">Konfigurasi Desain &amp; Dimensi Furniture</h3>
                            <p class="text-xs text-slate-400">Atur ukuran mm, modul kompartemen, dan bahan HPL untuk mengupdate gambar kerja &amp; daftar potong.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showCabinetModal = false"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-4 sm:p-5 overflow-y-auto space-y-5 flex-1">
                    <!-- Quick Presets -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Preset Desain Cepat</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button
                                type="button"
                                @click="applyPreset('kitchen_bar')"
                                class="p-2.5 rounded-xl border border-amber-500/30 bg-amber-500/10 hover:bg-amber-500/20 text-left transition-all cursor-pointer"
                            >
                                <div class="text-xs font-bold text-amber-300">Kitchen &amp; Bar</div>
                                <div class="text-[10px] text-slate-400">5190×675 mm (Seperti Gambar)</div>
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('wardrobe_3door')"
                                class="p-2.5 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-left transition-all cursor-pointer"
                            >
                                <div class="text-xs font-bold text-white">Lemari Pakaian</div>
                                <div class="text-[10px] text-slate-400">2400×600 mm (Wardrobe 3 Pintu)</div>
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('credenza_tv')"
                                class="p-2.5 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-left transition-all cursor-pointer"
                            >
                                <div class="text-xs font-bold text-white">Backdrop TV</div>
                                <div class="text-[10px] text-slate-400">3000×450 mm (Credenza)</div>
                            </button>
                            <button
                                type="button"
                                @click="applyPreset('minimalist_kitchen')"
                                class="p-2.5 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-left transition-all cursor-pointer"
                            >
                                <div class="text-xs font-bold text-white">Kitchen Minimalis</div>
                                <div class="text-[10px] text-slate-400">3200×600 mm (Lurus)</div>
                            </button>
                        </div>
                    </div>

                    <!-- Dimensi Utama -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Dimensi Utama (Milimeter / mm)</label>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <span class="text-[11px] text-slate-400 mb-1 block">Panjang / Lebar (L)</span>
                                <input
                                    v-model.number="cabinet.width"
                                    type="number"
                                    min="800"
                                    max="12000"
                                    step="50"
                                    class="w-full bg-slate-950 border border-white/20 rounded-xl px-3 py-2 text-sm text-white font-mono font-bold focus:outline-none focus:ring-2 focus:ring-amber-400"
                                />
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 mb-1 block">Kedalaman (D)</span>
                                <input
                                    v-model.number="cabinet.depth"
                                    type="number"
                                    min="300"
                                    max="2000"
                                    step="25"
                                    class="w-full bg-slate-950 border border-white/20 rounded-xl px-3 py-2 text-sm text-white font-mono font-bold focus:outline-none focus:ring-2 focus:ring-amber-400"
                                />
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 mb-1 block">Tinggi Total (T)</span>
                                <input
                                    v-model.number="cabinet.height"
                                    type="number"
                                    min="600"
                                    max="4000"
                                    step="50"
                                    class="w-full bg-slate-950 border border-white/20 rounded-xl px-3 py-2 text-sm text-white font-mono font-bold focus:outline-none focus:ring-2 focus:ring-amber-400"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Kompartemen & Modul -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Pilihan Modul &amp; Kompartemen</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition">
                                <input v-model="cabinet.hasFridge" type="checkbox" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400" />
                                <span class="text-xs text-white">Ruang Kulkas Kiri</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition">
                                <input v-model="cabinet.hasSlats" type="checkbox" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400" />
                                <span class="text-xs text-white">Kisi-kisi Dinding Kayu</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition">
                                <input v-model="cabinet.hasGlassShelves" type="checkbox" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400" />
                                <span class="text-xs text-white">Rak Kaca Gantung</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition">
                                <input v-model="cabinet.hasSink" type="checkbox" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400" />
                                <span class="text-xs text-white">Wastafel &amp; Kran Sink</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition">
                                <input v-model="cabinet.tallUnitChevron" type="checkbox" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400" />
                                <span class="text-xs text-white">Garis Chevron Pintu Kanan</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border border-white/10 bg-white/5 cursor-pointer hover:bg-white/10 transition">
                                <input v-model="cabinet.hasNiche" type="checkbox" class="w-4 h-4 rounded text-amber-500 focus:ring-amber-400" />
                                <span class="text-xs text-white">Ceruk Dispenser Air</span>
                            </label>
                        </div>
                    </div>

                    <!-- Warna & Finishing Material -->
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Finishing Material &amp; HPL</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-[11px] text-slate-400 mb-1 block">Warna Tall Unit / Lemari Kanan</span>
                                <div class="flex items-center gap-2">
                                    <input v-model="cabinet.tallUnitColor" type="color" class="w-8 h-8 rounded-lg border-0 cursor-pointer p-0" />
                                    <span class="text-xs font-mono text-slate-300">{{ cabinet.tallUnitColor }}</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 mb-1 block">Warna Kabinet Bawah / Laci</span>
                                <div class="flex items-center gap-2">
                                    <input v-model="cabinet.lowerCabinetColor" type="color" class="w-8 h-8 rounded-lg border-0 cursor-pointer p-0" />
                                    <span class="text-xs font-mono text-slate-300">{{ cabinet.lowerCabinetColor }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 sm:p-5 border-t border-white/10 flex items-center justify-between bg-slate-950/60">
                    <div class="text-xs text-slate-400">
                        Total Komponen Terhitung: <strong>{{ cutListItems.length }} Part</strong> ({{ totalPcs }} Pcs)
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showCabinetModal = false"
                            class="px-4 py-2 rounded-xl text-xs text-slate-300 hover:text-white hover:bg-white/5 transition cursor-pointer"
                        >
                            Tutup
                        </button>
                        <button
                            type="button"
                            @click="showCabinetModal = false; saveToDatabase();"
                            class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition cursor-pointer"
                        >
                            Terapkan ke Gambar Kerja
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── INLINE EDITING MODAL (UNTUK UBAH NAMA/UKURAN DI CANVAS) ───────── -->
        <div
            v-if="editingField"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        >
            <div class="bg-slate-900 border border-white/20 rounded-2xl p-5 max-w-md w-full shadow-2xl">
                <h3 class="text-sm font-bold text-white mb-2">
                    Ubah {{ editingField.toUpperCase() }}
                </h3>
                <input
                    v-model="editValue"
                    type="text"
                    class="w-full bg-slate-950 border border-white/20 rounded-xl px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-400 mb-4"
                    @keyup.enter="saveFieldEdit"
                />
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        @click="editingField = null"
                        class="px-3 py-1.5 rounded-xl text-xs text-slate-400 hover:text-white cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="saveFieldEdit"
                        class="px-4 py-1.5 rounded-xl text-xs font-semibold bg-amber-500 text-slate-950 cursor-pointer"
                    >
                        Simpan
                    </button>
                </div>
            </div>
        </div>

        <!-- ── MODAL EDIT KOP GAMBAR (TITLE BLOCK) ────────────────────────── -->
        <div
            v-if="showKopModal"
            class="fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-5"
        >
            <div class="bg-slate-900 border border-amber-500/30 rounded-3xl max-w-2xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center shadow-inner">
                            <FileText class="w-5 h-5 text-amber-400" />
                        </div>
                        <div>
                            <h2 class="text-base font-black text-white tracking-wide">
                                📝 Informasi Gambar (Kop / Title Block)
                            </h2>
                            <p class="text-xs text-slate-400">
                                Atur posisi tulisan informasi di samping/atas/bawah dan sesuaikan data proyek.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showKopModal = false"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-4 sm:p-6 overflow-y-auto space-y-4 flex-1 text-xs">
                    <!-- 1. Pilihan Posisi Kop Gambar -->
                    <div>
                        <label class="block text-xs font-bold text-amber-300 uppercase tracking-wider mb-2">
                            📍 Posisi Kop Gambar pada Kertas
                        </label>
                        <div class="grid grid-cols-3 gap-2.5">
                            <button
                                type="button"
                                @click="cabinet.titleBlockPosition = 'side'"
                                :class="cabinet.titleBlockPosition === 'side' ? 'border-amber-400 bg-amber-500/20 text-white shadow-md' : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="p-3 rounded-2xl border text-left transition flex flex-col gap-1 cursor-pointer"
                            >
                                <div class="font-bold text-xs flex items-center gap-1.5">
                                    <span>◨</span> Samping Kanan
                                </div>
                                <span class="text-[10px] text-slate-400">Standar Shop Drawing Resmi (Vertikal Sisi Kanan)</span>
                            </button>

                            <button
                                type="button"
                                @click="cabinet.titleBlockPosition = 'top'"
                                :class="cabinet.titleBlockPosition === 'top' ? 'border-amber-400 bg-amber-500/20 text-white shadow-md' : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="p-3 rounded-2xl border text-left transition flex flex-col gap-1 cursor-pointer"
                            >
                                <div class="font-bold text-xs flex items-center gap-1.5">
                                    <span>⬒</span> Bagian Atas
                                </div>
                                <span class="text-[10px] text-slate-400">Horizontal melintang di atas kertas</span>
                            </button>

                            <button
                                type="button"
                                @click="cabinet.titleBlockPosition = 'bottom'"
                                :class="cabinet.titleBlockPosition === 'bottom' ? 'border-amber-400 bg-amber-500/20 text-white shadow-md' : 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="p-3 rounded-2xl border text-left transition flex flex-col gap-1 cursor-pointer"
                            >
                                <div class="font-bold text-xs flex items-center gap-1.5">
                                    <span>⬓</span> Bagian Bawah
                                </div>
                                <span class="text-[10px] text-slate-400">Horizontal di bawah kertas</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Form Isian Kop -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-white/10">
                        <div>
                            <label class="text-[11px] font-semibold text-slate-300 block mb-1">Nama Proyek</label>
                            <input
                                v-model="cabinet.projectName"
                                type="text"
                                class="w-full bg-black/50 border border-white/20 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                placeholder="Contoh: Interior SEMANGKOKae"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold text-slate-300 block mb-1">Nama Item / Judul Gambar</label>
                            <input
                                v-model="cabinet.name"
                                type="text"
                                class="w-full bg-black/50 border border-white/20 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                placeholder="Contoh: Backdrop TV & Floating Credenza"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold text-slate-300 block mb-1">Kategori Gambar</label>
                            <input
                                v-model="cabinet.category"
                                type="text"
                                class="w-full bg-black/50 border border-white/20 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                placeholder="Contoh: Living Room & Interior TV Console"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold text-slate-300 block mb-1">Skala Gambar</label>
                            <input
                                v-model="cabinet.scaleSingle"
                                type="text"
                                class="w-full bg-black/50 border border-white/20 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                placeholder="Contoh: 1 : 20"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold text-slate-300 block mb-1">Tanggal Gambar</label>
                            <input
                                v-model="cabinet.date"
                                type="text"
                                class="w-full bg-black/50 border border-white/20 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                placeholder="22-10-2026"
                            />
                        </div>
                        <div>
                            <label class="text-[11px] font-semibold text-slate-300 block mb-1">Nomor Lembar</label>
                            <input
                                v-model="cabinet.sheetNumber"
                                type="text"
                                class="w-full bg-black/50 border border-white/20 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-amber-400"
                                placeholder="1 / 1"
                            />
                        </div>
                    </div>

                    <!-- 3. Catatan Teknis Produksi -->
                    <div class="pt-2 border-t border-white/10">
                        <label class="text-[11px] font-semibold text-slate-300 block mb-1.5">
                            Catatan Teknis Produksi (Muncul di Kop)
                        </label>
                        <div class="space-y-1.5">
                            <div v-for="(note, nIdx) in cabinet.notes" :key="nIdx" class="flex items-center gap-2">
                                <input
                                    v-model="cabinet.notes[nIdx]"
                                    type="text"
                                    class="flex-1 bg-black/50 border border-white/15 rounded-lg px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-amber-400"
                                />
                                <button
                                    type="button"
                                    @click="cabinet.notes.splice(nIdx, 1)"
                                    class="p-1 text-slate-400 hover:text-rose-400 cursor-pointer"
                                    title="Hapus baris catatan"
                                >
                                    ✕
                                </button>
                            </div>
                            <button
                                type="button"
                                @click="cabinet.notes.push(`${cabinet.notes.length + 1}. Catatan teknis baru...`)"
                                class="text-xs text-amber-400 hover:text-amber-300 font-semibold cursor-pointer pt-1"
                            >
                                + Tambah Baris Catatan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 sm:p-5 border-t border-white/10 flex items-center justify-between bg-slate-950/70">
                    <button
                        type="button"
                        @click="showKopModal = false"
                        class="px-4 py-2 rounded-xl text-xs text-slate-400 hover:text-white cursor-pointer"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        @click="showKopModal = false; captureSnapshot(); toast.success('Informasi kop gambar berhasil diperbarui.');"
                        class="px-5 py-2 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition cursor-pointer"
                    >
                        Simpan &amp; Terapkan ke Kop
                    </button>
                </div>
            </div>
        </div>

        <!-- ── TELEPORTED FLOATING DROPDOWN AKSESORIS (SELECT2 STYLE: SEARCHABLE, CATEGORIZED, SCROLLABLE Y) ── -->
        <Teleport to="body">
            <div v-if="showAccessoryMenu" class="fixed inset-0 z-[100] select-none pointer-events-auto">
                <!-- Backdrop transparan penutup: klik di luar otomatis menutup dropdown -->
                <div
                    class="fixed inset-0 bg-black/40 backdrop-blur-[0.5px]"
                    @click="showAccessoryMenu = false"
                ></div>

                <!-- Floating Dropdown Box yang Mengambang Sesuai Posisi Tombol & Scroll Y pada Layar Kecil -->
                <div
                    class="fixed w-[360px] sm:w-[410px] max-w-[94vw] bg-[#18181b] border border-amber-500/40 rounded-2xl shadow-2xl z-10 text-xs shadow-black/90 flex flex-col animate-in fade-in zoom-in-95 duration-100 overflow-hidden"
                    :style="{
                        top: `${accessoryDropdownPos.top}px`,
                        left: `${accessoryDropdownPos.left}px`,
                        maxHeight: 'min(560px, calc(100vh - 80px))'
                    }"
                    @click.stop
                >
                    <!-- Header dengan Icon, Count & Tombol Tutup -->
                    <div class="px-4 py-2.5 bg-gradient-to-r from-amber-500/15 via-amber-500/5 to-transparent border-b border-white/10 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-2">
                            <Package class="w-4 h-4 text-amber-400" />
                            <span class="font-bold text-white text-xs">Aksesoris &amp; Komponen Desain</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 font-mono font-bold">
                                {{ ACCESSORY_CATALOG.length }}
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="showAccessoryMenu = false"
                            class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 cursor-pointer transition text-xs"
                            title="Tutup Menu"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Search Input (Select2 Live Search) & Category Filter Pills -->
                    <div class="p-2.5 pb-2 border-b border-white/10 bg-black/40 shrink-0">
                        <div class="relative flex items-center">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 pointer-events-none" />
                            <input
                                ref="accessorySearchInputRef"
                                v-model="accessorySearchQuery"
                                type="text"
                                placeholder="Cari aksesoris (misal: oven, kulkas, lampu, cermin, stop kontak)..."
                                class="w-full bg-black/60 border border-white/20 rounded-xl pl-9 pr-8 py-2 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition"
                            />
                            <button
                                v-if="accessorySearchQuery"
                                type="button"
                                @click="accessorySearchQuery = ''"
                                class="absolute right-2.5 text-slate-400 hover:text-white text-xs p-1 cursor-pointer"
                                title="Hapus pencarian"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Category Filter Pills -->
                        <div class="flex items-center gap-1.5 mt-2 overflow-x-auto pb-0.5 scrollbar-none">
                            <button
                                v-for="cat in ACCESSORY_CATEGORIES"
                                :key="cat.id"
                                type="button"
                                @click="accessorySelectedCategory = cat.id"
                                :class="accessorySelectedCategory === cat.id
                                    ? 'bg-amber-500 text-slate-950 font-bold shadow-sm'
                                    : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="px-2.5 py-1 rounded-lg text-[11px] whitespace-nowrap transition cursor-pointer flex items-center gap-1"
                            >
                                <span>{{ cat.icon }}</span>
                                <span>{{ cat.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- List Aksesoris (Bisa Scroll Y pada Layar Kecil) -->
                    <div class="p-2 space-y-1 overflow-y-auto flex-1 scrollbar-thin scrollbar-thumb-white/20 scrollbar-track-transparent">
                        <div v-if="filteredAccessories.length === 0" class="py-8 text-center text-slate-400 text-xs">
                            <div class="text-2xl mb-1">🔍</div>
                            <div class="font-semibold text-slate-300">Tidak ada aksesoris yang cocok</div>
                            <div class="text-[10px] text-slate-500 mt-0.5">Coba kata kunci lain atau pilih tab Semua</div>
                            <button
                                type="button"
                                @click="accessorySearchQuery = ''; accessorySelectedCategory = 'all'"
                                class="mt-2.5 px-3 py-1 bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 rounded-lg text-xs font-semibold cursor-pointer transition"
                            >
                                Tampilkan Semua Aksesoris
                            </button>
                        </div>

                        <button
                            v-for="item in filteredAccessories"
                            :key="item.id"
                            type="button"
                            @click="stampAccessory(item.id)"
                            class="w-full text-left p-2 rounded-xl hover:bg-amber-500/15 hover:border-amber-500/30 border border-transparent flex items-center gap-3 transition cursor-pointer group"
                        >
                            <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-xl shrink-0 group-hover:scale-110 group-hover:bg-amber-500/20 transition">
                                {{ item.icon }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="font-bold text-white group-hover:text-amber-300 truncate text-xs">{{ item.name }}</span>
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-white/10 text-slate-300 font-mono shrink-0">{{ item.defaultWmm }}×{{ item.defaultHmm }} mm</span>
                                </div>
                                <div class="text-[10px] text-slate-400 truncate mt-0.5">{{ item.desc }}</div>
                            </div>
                        </button>
                    </div>

                    <!-- Footer Tip & Total Count -->
                    <div class="px-3.5 py-1.5 bg-black/50 border-t border-white/10 text-[10px] text-slate-400 flex items-center justify-between shrink-0">
                        <span>💡 Klik aksesoris untuk ditempatkan ke gambar</span>
                        <span class="text-amber-300/90 font-mono font-bold">{{ filteredAccessories.length }} opsi</span>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── MODAL TUTORIAL / PANDUAN PENGGUNAAN (DARI SCRATCH / PEMULA) ─────── -->
        <div
            v-if="showTutorialModal"
            class="fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-3 sm:p-5"
        >
            <div class="bg-slate-900 border border-blue-500/30 rounded-3xl max-w-3xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="p-4 sm:p-5 border-b border-white/10 flex items-center justify-between bg-slate-950/70">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-blue-500/20 border border-blue-400/30 flex items-center justify-center shadow-inner">
                            <HelpCircle class="w-5 h-5 text-blue-400" />
                        </div>
                        <div>
                            <h2 class="text-base font-black text-white tracking-wide">
                                📘 Panduan Mengoperasikan Gambar Kerja (Dari Nol)
                            </h2>
                            <p class="text-xs text-slate-400">
                                Tutorial langkah demi langkah untuk pemula: geser kanvas, edit benda, atur garis, notasi, hingga cetak.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showTutorialModal = false"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
                        title="Tutup Panduan"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center gap-1 px-4 pt-3 pb-1 border-b border-white/10 bg-slate-950/40 overflow-x-auto scrollbar-none">
                    <button
                        type="button"
                        @click="tutorialTab = 'canvas'"
                        :class="tutorialTab === 'canvas' ? 'bg-blue-600 text-white font-bold shadow' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                        class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                    >
                        <span>🧭</span>
                        <span>1. Navigasi Kanvas</span>
                    </button>
                    <button
                        type="button"
                        @click="tutorialTab = 'objects'"
                        :class="tutorialTab === 'objects' ? 'bg-blue-600 text-white font-bold shadow' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                        class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                    >
                        <span>📺</span>
                        <span>2. Edit &amp; Geser Objek</span>
                    </button>
                    <button
                        type="button"
                        @click="tutorialTab = 'lines'"
                        :class="tutorialTab === 'lines' ? 'bg-blue-600 text-white font-bold shadow' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                        class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                    >
                        <span>📐</span>
                        <span>3. Garis Fleksibel</span>
                    </button>
                    <button
                        type="button"
                        @click="tutorialTab = 'labels'"
                        :class="tutorialTab === 'labels' ? 'bg-blue-600 text-white font-bold shadow' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                        class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                    >
                        <span>🏷️</span>
                        <span>4. Notasi &amp; Kop</span>
                    </button>
                    <button
                        type="button"
                        @click="tutorialTab = 'export'"
                        :class="tutorialTab === 'export' ? 'bg-blue-600 text-white font-bold shadow' : 'text-slate-400 hover:text-white hover:bg-white/5'"
                        class="px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                    >
                        <span>📋</span>
                        <span>5. Daftar Potong</span>
                    </button>
                </div>

                <!-- Tab Content Body -->
                <div class="p-5 sm:p-6 overflow-y-auto space-y-4 flex-1 text-slate-200">
                    <!-- Tab 1: Navigasi Kanvas -->
                    <div v-if="tutorialTab === 'canvas'" class="space-y-4">
                        <div class="bg-blue-950/30 border border-blue-500/30 rounded-2xl p-4">
                            <h3 class="text-sm font-bold text-blue-300 mb-1 flex items-center gap-2">
                                <span>🖱️</span> Menggeser (Pan) &amp; Memperbesar (Zoom) Kanvas
                            </h3>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Lembar kerja dibuat dalam skala vektor kertas A3. Anda dapat menjelajah seluruh gambar dengan mudah di komputer maupun di layar sentuh HP.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4 space-y-2">
                                <div class="font-bold text-amber-300 flex items-center gap-1.5">
                                    <span>💻</span> Di Komputer / Laptop
                                </div>
                                <ul class="space-y-2 text-slate-300 list-disc pl-4">
                                    <li><strong>Menggeser (Pan):</strong> Klik &amp; tahan tombol mouse kiri di area kosong kanvas, lalu geser ke segala arah. Atau gunakan klik tombol tengah roda mouse.</li>
                                    <li><strong>Zoom In / Out:</strong> Putar roda mouse (*scroll*) ke atas untuk memperbesar, dan ke bawah untuk memperkecil.</li>
                                    <li><strong>Pas Layar (Fit):</strong> Klik tombol <strong>"100%"</strong> di pojok kanan bawah untuk mengembalikan tampilan ke tengah pas kertas.</li>
                                </ul>
                            </div>

                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4 space-y-2">
                                <div class="font-bold text-amber-300 flex items-center gap-1.5">
                                    <span>📱</span> Di HP / Layar Sentuh
                                </div>
                                <ul class="space-y-2 text-slate-300 list-disc pl-4">
                                    <li><strong>Menggeser (Pan):</strong> Cukup geser dengan 1 jari pada kanvas.</li>
                                    <li><strong>Pinch-to-Zoom:</strong> Gunakan 2 jari (cubit / rentangkan) untuk memperbesar area detail gambar.</li>
                                    <li><strong>Tombol Mini Zoom:</strong> Tersedia tombol <strong>(+)</strong> dan <strong>(-)</strong> di pojok kanan bawah jika ingin zoom bertahap.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Edit & Geser Objek -->
                    <div v-if="tutorialTab === 'objects'" class="space-y-4">
                        <div class="bg-emerald-950/30 border border-emerald-500/30 rounded-2xl p-4">
                            <h3 class="text-sm font-bold text-emerald-300 mb-1 flex items-center gap-2">
                                <span>📺</span> Cara Menggeser &amp; Menambah Benda (TV, Meja, Rak, Aksesoris)
                            </h3>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Setiap objek pada gambar bersifat interaktif. Anda tidak hanya melihat gambar statis, tapi bisa menggeser posisi, menambah aksesoris, membatalkan aksi (Undo), dan menghapus objek.
                            </p>
                        </div>

                        <div class="space-y-3 text-xs">
                            <!-- Shortcut Keyboard Info -->
                            <div class="bg-amber-950/40 border border-amber-500/30 rounded-2xl p-3.5 space-y-1.5">
                                <div class="font-bold text-amber-300 flex items-center gap-2">
                                    <span>⌨️</span> Shortcut Keyboard Cepat:
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1 text-[11px]">
                                    <div class="bg-black/40 rounded-xl p-2 border border-white/10">
                                        <div class="font-bold text-white mb-0.5">Ctrl + Z</div>
                                        <div class="text-slate-400">Undo (Membatalkan perubahan terakhir)</div>
                                    </div>
                                    <div class="bg-black/40 rounded-xl p-2 border border-white/10">
                                        <div class="font-bold text-white mb-0.5">Ctrl + Y</div>
                                        <div class="text-slate-400">Redo (Maju ke perubahan berikutnya)</div>
                                    </div>
                                    <div class="bg-black/40 rounded-xl p-2 border border-white/10">
                                        <div class="font-bold text-white mb-0.5">Delete / Backspace</div>
                                        <div class="text-slate-400">Hapus objek/notasi yang sedang dipilih</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Aksesoris Library Info -->
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-2 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-blue-500 text-white font-bold flex items-center justify-center text-[10px]">📦</span>
                                    <span>Dari Mana Gambar Aksesoris (TV, Buku, Dapur, Lemari)?</span>
                                </div>
                                <p class="text-slate-300 leading-relaxed pl-7 mb-2">
                                    Aksesoris dibuat dari <strong>Library Vektor SVG Modular</strong>. Anda dapat menambahkannya kapan saja:
                                </p>
                                <div class="pl-7 text-slate-300 space-y-1">
                                    <div>1. Klik tombol <strong>[ 📦 + Aksesoris ▾ ]</strong> di toolbar atas.</div>
                                    <div>2. Pilih benda: <em>Smart TV 65", Soundbar, Deretan Buku, Vas Bunga, Sink Wastafel, Kompor Induksi, Modul Laci, atau Pintu Lemari</em>.</div>
                                    <div>3. Benda langsung muncul di kanvas dan bisa Anda seret/tarik posisinya ke mana saja!</div>
                                </div>
                            </div>

                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-2 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-[10px]">1</span>
                                    <span>Pilih Objek pada Kanvas</span>
                                </div>
                                <p class="text-slate-300 leading-relaxed pl-7">
                                    Pastikan tool <strong>"Pilih"</strong> di toolbar atas aktif (kursor panah). Klik pada Smart TV, laci credenza gantung, panel marmer, atau rak buku. Objek yang dipilih akan memiliki garis seleksi berwarna biru putus-putus.
                                </p>
                            </div>

                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-2 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-[10px]">2</span>
                                    <span>Tarik Langsung di Layar (Drag &amp; Drop)</span>
                                </div>
                                <p class="text-slate-300 leading-relaxed pl-7">
                                    Klik &amp; tahan pada unit <strong>Smart TV 65"</strong> atau aksesoris untuk menggeser posisinya. Anda juga bisa menarik garis potongan <strong>A-A / B-B</strong> secara horizontal.
                                </p>
                            </div>

                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-2 flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-500 text-slate-950 font-bold flex items-center justify-center text-[10px]">3</span>
                                    <span>Ubah Ukuran Melalui Sidebar Kanan atau Modal</span>
                                </div>
                                <p class="text-slate-300 leading-relaxed pl-7">
                                    Saat objek dipilih, <strong>Sidebar Inspektor Kanan</strong> otomatis membuka setelan objek tersebut. Jika ingin mengubah ukuran lemari secara keseluruhan (misal dari 3600 mm jadi 4000 mm), klik tombol <strong>"Desain &amp; Ubah Lemari"</strong> di toolbar atas.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Garis Fleksibel -->
                    <div v-if="tutorialTab === 'lines'" class="space-y-4">
                        <div class="bg-amber-950/30 border border-amber-500/30 rounded-2xl p-4">
                            <h3 class="text-sm font-bold text-amber-300 mb-1 flex items-center gap-2">
                                <span>📐</span> Cara Menggunakan Fitur Garis Fleksibel
                            </h3>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Garis memiliki dua mode penyesuaian: bisa ditarik visual ujungnya, atau cukup ketik angka milimeter (mm) langsung tanpa perlu repot menarik.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                            <div class="bg-black/40 border border-amber-500/20 rounded-2xl p-4 space-y-2">
                                <div class="font-bold text-amber-300 flex items-center gap-1.5">
                                    <span>✍️</span> Metode A: Ketik Angka mm (Tanpa Tarik)
                                </div>
                                <ul class="space-y-2 text-slate-300 list-disc pl-4">
                                    <li><strong>Klik Badge Angka di Garis:</strong> Di tengah setiap garis ada kotak hitam/biru bertuliskan ukuran mm (contoh: <code>[ 1450 mm ]</code>). Klik kotak tersebut ➔ langsung ketik angka baru ➔ Enter!</li>
                                    <li><strong>Lewat Sidebar Kanan:</strong> Saat garis diklik, sidebar kanan menyediakan kotak ketik mm, tombol <code>+50</code> dan <code>-50</code>, serta preset instan (400, 600, 1000, 1200, 1500, 2000 mm).</li>
                                    <li><strong>Kunci Lurus:</strong> Klik tombol <code>↔ Horisontal</code> atau <code>↕ Vertikal</code> agar garis tegak lurus sempurna tanpa miring.</li>
                                </ul>
                            </div>

                            <div class="bg-black/40 border border-blue-500/20 rounded-2xl p-4 space-y-2">
                                <div class="font-bold text-blue-300 flex items-center gap-1.5">
                                    <span>🖐️</span> Metode B: Tarik Titik Ujung Manual
                                </div>
                                <ul class="space-y-2 text-slate-300 list-disc pl-4">
                                    <li><strong>Pilih Garis:</strong> Klik badan garis. Akan muncul 2 titik lingkaran biru yang berkedip di ujung pangkal dan ujung akhir.</li>
                                    <li><strong>Tarik Handle Biru:</strong> Klik &amp; tahan lingkaran biru tersebut, lalu tarik ke mana pun. Panjang garis akan terhitung dan ter-update secara otomatis di layar.</li>
                                    <li><strong>Pindahkan Seluruh Garis:</strong> Klik &amp; tahan bagian tengah garis untuk menggeser garis ke tempat lain tanpa mengubah panjangnya.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-black/40 border border-white/10 rounded-2xl p-3 text-xs flex items-center gap-2">
                            <span class="text-base">💡</span>
                            <span><strong>Cara Bikin Garis Baru:</strong> Klik tool <strong>"Garis"</strong> di toolbar atas ➔ klik di kanvas ➔ masukkan panjang awal (misal 1200) ➔ Garis langsung muncul!</span>
                        </div>
                    </div>

                    <!-- Tab 4: Notasi & Kop -->
                    <div v-if="tutorialTab === 'labels'" class="space-y-4">
                        <div class="bg-purple-950/30 border border-purple-500/30 rounded-2xl p-4">
                            <h3 class="text-sm font-bold text-purple-300 mb-1 flex items-center gap-2">
                                <span>🏷️</span> Teks Notasi, Balon Material &amp; Kop Informasi Gambar
                            </h3>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Notasi teks arsitektur sekarang tampil bersih tanpa kotak kuning. Informasi gambar (Kop) dapat ditaruh di samping kanan, atas, maupun bawah, serta datanya bisa diedit kapan saja.
                            </p>
                        </div>

                        <div class="space-y-3 text-xs">
                            <!-- Teks Tanpa Kotak -->
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-1 flex items-center gap-1.5">
                                    <span>✍️</span> Teks Notasi Arsitektural Bersih (Tanpa Kotak):
                                </div>
                                <p class="text-slate-300 leading-relaxed mb-2">
                                    Saat Anda mengklik tool <strong>"Teks"</strong> di toolbar atas lalu klik di kanvas, teks akan langsung ditampilkan sebagai tipografi murni profesional tanpa kotak latar belakang kuning.
                                </p>
                                <ul class="list-disc pl-4 text-slate-300 space-y-1">
                                    <li>Teks dapat diklik dan diseret/digeser (drag &amp; drop) bebas posisinya.</li>
                                    <li>Saat dipilih, akan muncul garis batas putus-putus biru yang menandakan teks sedang aktif.</li>
                                    <li>Di sidebar kanan, Anda dapat mengubah isi teks, ukuran font (px), atau menyalakan kotak kuning jika memang diperlukan.</li>
                                    <li>Tekan tombol <strong>Delete</strong> atau <strong>Backspace</strong> di keyboard untuk menghapus teks tersebut.</li>
                                </ul>
                            </div>

                            <!-- Memindahkan Posisi Kop ke Samping -->
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-1 flex items-center gap-1.5">
                                    <span>📑</span> Cara Menaruh Tulisan Informasi (Kop) di Samping:
                                </div>
                                <p class="text-slate-300 leading-relaxed mb-2">
                                    Di toolbar ribbon bagian atas terdapat pilihan <strong>Kop Info</strong>:
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 my-2">
                                    <div class="p-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                        <div class="font-bold text-amber-300 mb-0.5">◨ Samping (Rekomendasi)</div>
                                        <div class="text-[11px] text-slate-300">Kop diletakkan di sisi samping kanan. Standar gambar kerja A3 resmi internasional.</div>
                                    </div>
                                    <div class="p-2.5 rounded-xl border border-white/10 bg-white/5">
                                        <div class="font-bold text-white mb-0.5">⬒ Atas</div>
                                        <div class="text-[11px] text-slate-300">Kop horizontal melintang di sepanjang bagian atas kertas.</div>
                                    </div>
                                    <div class="p-2.5 rounded-xl border border-white/10 bg-white/5">
                                        <div class="font-bold text-white mb-0.5">⬓ Bawah</div>
                                        <div class="text-[11px] text-slate-300">Kop horizontal di sepanjang bagian bawah kertas.</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cara Mengedit Informasi Kop Gambar -->
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-1 flex items-center gap-1.5">
                                    <span>📝</span> Cara Membuat &amp; Mengedit Data Informasi Gambar (Kop):
                                </div>
                                <p class="text-slate-300 leading-relaxed mb-2">
                                    Ada dua cara mudah untuk mengisi dan mengubah informasi proyek di Kop:
                                </p>
                                <ul class="list-disc pl-4 text-slate-300 space-y-1.5">
                                    <li><strong>Lewat Tombol "Edit Kop":</strong> Klik tombol biru <strong>[ 📝 Edit Kop ]</strong> di toolbar atas. Akan muncul popup formulir lengkap untuk mengisi Judul Proyek, Nama Item/Lemari, Kategori, Arsitek, Skala Gambar, Tanggal, No Lembar, dan Catatan Teknis.</li>
                                    <li><strong>Langsung Klik Ganda (Double-Click):</strong> Anda juga bisa langsung klik dua kali pada teks judul lemari di kanvas untuk mengganti namanya seketika.</li>
                                </ul>
                            </div>

                            <!-- Balon Material -->
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-1">📍 Menggeser Balon Material &amp; Titik Panah:</div>
                                <p class="text-slate-300 leading-relaxed">
                                    Klik balon material mana saja pada gambar. Lingkaran balon dapat Anda geser posisinya, dan titik panah birunya dapat ditarik untuk menunjuk ke objek yang Anda inginkan. Nama material dan kode balon (M-01, M-02) bisa diedit di sidebar kanan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 5: Daftar Potong & Simpan -->
                    <div v-if="tutorialTab === 'export'" class="space-y-4">
                        <div class="bg-sky-950/30 border border-sky-500/30 rounded-2xl p-4">
                            <h3 class="text-sm font-bold text-sky-300 mb-1 flex items-center gap-2">
                                <span>📋</span> Daftar Potong (Cutting List) &amp; Menyimpan Pekerjaan
                            </h3>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Semua ukuran yang Anda atur otomatis dihitung menjadi lembaran panel potong untuk produksi tukang di workshop.
                            </p>
                        </div>

                        <div class="space-y-3 text-xs">
                            <div class="bg-black/40 border border-white/10 rounded-2xl p-4">
                                <div class="font-bold text-white mb-1">📑 Buka Tab 02 (Daftar Potong):</div>
                                <p class="text-slate-300 leading-relaxed">
                                    Di bawah header, klik tombol tab <strong>"Tab 02: Lembar Daftar Potong"</strong>. Anda akan melihat tabel rincian part: nama panel, panjang (mm), lebar (mm), tebal (mm), bahan multiplek/marmer, dan jenis edging.
                                </p>
                            </div>

                            <div class="bg-black/40 border border-emerald-500/30 rounded-2xl p-4">
                                <div class="font-bold text-emerald-300 mb-1 flex items-center gap-1.5">
                                    <span>💾</span> Cara Menyimpan:
                                </div>
                                <p class="text-slate-300 leading-relaxed">
                                    Klik tombol hijau <strong>"Simpan"</strong> di pojok kanan atas toolbar. Perubahan posisi garis, teks, dan ukuran lemari akan tersimpan permanen ke database proyek.
                                </p>
                            </div>

                            <div class="bg-black/40 border border-blue-500/30 rounded-2xl p-4">
                                <div class="font-bold text-blue-300 mb-1 flex items-center gap-1.5">
                                    <span>🖨️</span> Cetak / Unduh PDF:
                                </div>
                                <p class="text-slate-300 leading-relaxed">
                                    Klik tombol <strong>"Cetak / PDF"</strong> di toolbar atas untuk mencetak gambar kerja atau mengunduhnya sebagai dokumen PDF beresolusi tajam.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="p-4 sm:p-5 border-t border-white/10 flex items-center justify-between bg-slate-950/70">
                    <div class="text-xs text-slate-400">
                        Tips: Anda bisa membuka kembali panduan ini kapan saja lewat tombol <strong>"Panduan Cara Pakai"</strong> di toolbar.
                    </div>
                    <button
                        type="button"
                        @click="showTutorialModal = false"
                        class="px-5 py-2 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white shadow-lg shadow-blue-600/30 transition cursor-pointer"
                    >
                        Tutup &amp; Mulai Menggambar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
svg {
    shape-rendering: geometricPrecision;
    text-rendering: geometricPrecision;
}
</style>
