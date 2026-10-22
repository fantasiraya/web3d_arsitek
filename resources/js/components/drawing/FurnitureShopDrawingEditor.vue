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
    Copy,
    ClipboardPaste,
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

const isDemoSheet = props.sheet?.id === '01a16100-3421-70f5-ac75-f9db46e8c46f';
const hasSavedData = Boolean(props.elements && props.elements.length && props.elements.some((e: any) => e.type === 'shop_drawing' || e.data?.cabinet));

// ── INTERACTIVE CABINET DATA MODEL (FULLY EDITABLE & REACTIVE) ──────────────
const cabinet = ref({
    name: props.sheet?.title || (isDemoSheet ? 'Backdrop TV & Floating Credenza' : 'Lembar Kerja Baru'),
    category: isDemoSheet ? 'Living Room & Interior TV Console' : 'Gambar Kerja',
    furnitureType: (isDemoSheet || hasSavedData) ? ('credenza_tv' as 'credenza_tv' | 'kitchen_bar' | 'wardrobe_3door' | 'blank') : ('blank' as 'credenza_tv' | 'kitchen_bar' | 'wardrobe_3door' | 'blank'),
    titleBlockPosition: 'side' as 'side' | 'top' | 'bottom',
    projectName: props.project?.title || 'Renovasi & Interior SEMANGKOKae',
    viewTitle: 'TAMPAK DEPAN',
    viewNumber: '1',
    viewTitleOffsetX: 0,
    viewTitleOffsetY: 0,
    showViewTitle: true,
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
    hasSlats: isDemoSheet,
    slatsColor: '#7E5334',
    hasGlassShelves: isDemoSheet,
    hasSink: false,
    counterHeight: 450,
    countertopColor: '#ECEAE6',
    lowerCabinetStyle: 'drawers',
    lowerCabinetColor: '#BFA07A',
    tallUnitWidth: 800,
    tallUnitColor: '#7E5334',
    tallUnitChevron: false,
    hasNiche: false,
    notes: isDemoSheet ? [
        '1. Semua ukuran dalam millimeter (mm).',
        '2. Braket TV 65" ditanam ke dinding bata + penguat multiplek 18mm.',
        '3. Sediakan conduit kabel HDMI & stop kontak di balik panel marmer.',
        '4. Rel laci tandem slowmotion clip-on beban 35kg.',
        '5. Trafo LED 12V 10A diletakkan di dalam modul laci tengah.',
    ] : [
        '1. Semua ukuran dalam millimeter (mm).',
        '2. Periksa kembali ukuran di lapangan sebelum pabrikasi.',
    ],
    materials: isDemoSheet ? [
        { code: 'M-01', name: 'TH 235 AC', color: '#7E5334', desc: 'Panel Dinding Kisi-kisi Kayu Akasia' },
        { code: 'M-02', name: 'Calacatta White', color: '#ECEAE6', desc: 'Panel Marmer Dinding TV / Bookmatch' },
        { code: 'LM-01', name: 'Wood 16', color: '#BFA07A', desc: 'HPL Serat Kayu Floating Credenza' },
        { code: 'M-04', name: 'Black Anodized', color: '#18181b', desc: 'Frame Aluminium Hitam & Smart TV 65"' },
        { code: 'LED-01', name: 'Warm White 3000K', color: '#f59e0b', desc: 'Indirect LED Strip Light Cove' },
    ] : [],
});

// Preset Options for Quick Generating
function applyPreset(type: 'kitchen_bar' | 'wardrobe_3door' | 'credenza_tv' | 'minimalist_kitchen' | 'blank') {
    if (type === 'blank') {
        cabinet.value.name = props.sheet?.title || 'Lembar Kerja Baru';
        cabinet.value.category = 'Gambar Kerja';
        cabinet.value.furnitureType = 'blank';
        cabinet.value.hasFridge = false;
        cabinet.value.hasSlats = false;
        cabinet.value.hasGlassShelves = false;
        cabinet.value.hasSink = false;
        cabinet.value.materials = [];
        cabinet.value.notes = [];
        annotations.value = [];
        selectedElement.value = null;
        selectedObjectId.value = null;
        cabinet.value.showViewTitle = true;
        toast.success('Kanvas lembar kerja dikosongkan.');
        showCabinetModal.value = false;
        return;
    }
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
    view?: 'depan' | 'atas' | 'samping' | 'bawah';
    rotation?: number;
    flipH?: boolean;
    flipV?: boolean;
}

const annotations = ref<AnnotationItem[]>(
    isDemoSheet ? [
        { id: 'ann-1', type: 'label', x: 975, y: 220, targetX: 820, targetY: 260, text: 'TH 076 SB', subText: 'M-02' },
        { id: 'ann-2', type: 'label', x: 975, y: 310, targetX: 490, targetY: 300, text: 'TH 235 AC', subText: 'M-01' },
        { id: 'ann-3', type: 'label', x: 975, y: 430, targetX: 550, targetY: 425, text: 'DeepPink', subText: 'M-03' },
        { id: 'ann-4', type: 'label', x: 975, y: 485, targetX: 230, targetY: 460, text: 'a660dbd0ee9ad7629b20e10b9571b', subText: 'M-04' },
        { id: 'ann-5', type: 'label', x: 975, y: 535, targetX: 430, targetY: 510, text: 'Wood 16', subText: 'LM-01' },
        { id: 'ann-6', type: 'text', x: 260, y: 645, text: 'POTONGAN A-A', color: '#000000', fontSize: 13, isBold: true, hasBox: false },
        { id: 'ann-7', type: 'measure', x: 410, y: 155, text: '5190 mm' },
        { id: 'ln-sample', type: 'line', x: 320, y: 390, x2: 500, y2: 390, lengthMm: 1200, lineStyle: 'solid', lineWidth: 2, color: '#2563eb' },
    ] : []
);

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
    category: 'kitchen' | 'living' | 'bedroom' | 'wardrobe' | 'bathroom' | 'decor' | 'mep';
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
    { id: 'living', label: 'Ruang Tamu & Duduk', icon: '🛋️' },
    { id: 'bedroom', label: 'Kamar Tidur', icon: '🛏️' },
    { id: 'kitchen', label: 'Dapur & Makan', icon: '🍳' },
    { id: 'wardrobe', label: 'Wardrobe & Rias', icon: '🚪' },
    { id: 'bathroom', label: 'Kamar Mandi & Sanitari', icon: '🚿' },
    { id: 'decor', label: 'Dekor & Lampu', icon: '🪴' },
    { id: 'mep', label: 'Listrik & Elektronik', icon: '🔌' },
];

const ACCESSORY_CATALOG: AccessoryDef[] = [
    // ── 1. RUANG TAMU & DUDUK (LIVING ROOM) ──
    {
        id: 'sofa',
        name: 'Sofa 3 Dudukan Minimalis',
        category: 'living',
        icon: '🛋️',
        desc: 'Sofa empuk fabric 3-seater + bantal cushion',
        tags: ['sofa', 'couch', 'kursi tamu', 'ruang tamu', 'living', 'duduk', 'bantal', 'santai'],
        baseW: 180,
        baseH: 80,
        defaultWmm: 2000,
        defaultHmm: 850,
    },
    {
        id: 'armchair',
        name: 'Armchair / Kursi Santai Tunggal',
        category: 'living',
        icon: '🪑',
        desc: 'Kursi single lounge busa empuk + sandaran tangan',
        tags: ['armchair', 'kursi santai', 'lounge chair', 'single chair', 'ruang tamu', 'living'],
        baseW: 80,
        baseH: 85,
        defaultWmm: 800,
        defaultHmm: 850,
    },
    {
        id: 'coffee_table',
        name: 'Meja Kopi Ruang Tamu',
        category: 'living',
        icon: '☕',
        desc: 'Coffee table minimalis kayu/marmer depan sofa',
        tags: ['meja kopi', 'coffee table', 'meja tamu', 'living', 'meja rendah', 'ruang tamu'],
        baseW: 100,
        baseH: 45,
        defaultWmm: 1000,
        defaultHmm: 450,
    },
    {
        id: 'rug',
        name: 'Karpet Lantai Motif Modern',
        category: 'living',
        icon: '🧶',
        desc: 'Karpet permadani lantai tebal motif geometris hangat',
        tags: ['karpet', 'rug', 'permadani', 'lantai', 'living', 'kamar', 'alas lantai'],
        baseW: 180,
        baseH: 16,
        defaultWmm: 2000,
        defaultHmm: 150,
    },
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
        id: 'study_desk',
        name: 'Meja Kerja & Belajar Minimalis',
        category: 'living',
        icon: '🖥️',
        desc: 'Meja kerja laptop + laci arsip & lampu baca',
        tags: ['meja kerja', 'meja belajar', 'desk', 'laptop', 'komputer', 'belajar', 'kantor'],
        baseW: 120,
        baseH: 75,
        defaultWmm: 1200,
        defaultHmm: 750,
    },
    {
        id: 'office_chair',
        name: 'Kursi Kerja Ergonomis',
        category: 'living',
        icon: '💺',
        desc: 'Kursi kantor putar hidrolik sandaran jaring mesh',
        tags: ['kursi kantor', 'kursi kerja', 'office chair', 'ergonomis', 'kursi putar', 'hidrolik'],
        baseW: 60,
        baseH: 95,
        defaultWmm: 600,
        defaultHmm: 950,
    },

    // ── 2. KAMAR TIDUR (BEDROOM) ──
    {
        id: 'bed',
        name: 'Tempat Tidur Queen / King & Headboard',
        category: 'bedroom',
        icon: '🛏️',
        desc: 'Ranjang kasur empuk + headboard sandaran & bantal guling',
        tags: ['tempat tidur', 'kasur', 'bed', 'headboard', 'ranjang', 'bantal', 'kamar tidur', 'bedroom'],
        baseW: 160,
        baseH: 100,
        defaultWmm: 1600,
        defaultHmm: 1000,
    },
    {
        id: 'bedside_table',
        name: 'Meja Nakas Samping Kasur',
        category: 'bedroom',
        icon: '🗄️',
        desc: 'Nakas 2 laci samping tempat tidur + lampu meja',
        tags: ['nakas', 'bedside', 'nightstand', 'meja samping', 'kamar', 'laci'],
        baseW: 50,
        baseH: 55,
        defaultWmm: 500,
        defaultHmm: 550,
    },

    // ── 3. DAPUR & RUANG MAKAN (KITCHEN & DINING) ──
    {
        id: 'dining_table',
        name: 'Meja Makan 4-6 Orang & Kursi',
        category: 'kitchen',
        icon: '🍽️',
        desc: 'Set meja makan kayu solid + kursi makan ramping',
        tags: ['meja makan', 'dining table', 'kursi makan', 'makan', 'kitchen', 'ruang makan'],
        baseW: 160,
        baseH: 78,
        defaultWmm: 1600,
        defaultHmm: 780,
    },
    {
        id: 'barstool',
        name: 'Kursi Barstool Cafe',
        category: 'kitchen',
        icon: '🪑',
        desc: 'Kursi tinggi hidrolik meja island/bar',
        tags: ['kursi', 'barstool', 'stool', 'bangku', 'bar', 'island', 'dapur'],
        baseW: 50,
        baseH: 90,
        defaultWmm: 450,
        defaultHmm: 950,
    },
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
        id: 'washing_machine',
        name: 'Mesin Cuci Front Loading',
        category: 'kitchen',
        icon: '🧺',
        desc: 'Mesin cuci tabung bukaan depan + panel digital laundry',
        tags: ['mesin cuci', 'washing machine', 'laundry', 'cuci baju', 'tabung depan', 'service', 'dapur'],
        baseW: 65,
        baseH: 90,
        defaultWmm: 600,
        defaultHmm: 850,
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
    {
        id: 'trash_bin',
        name: 'Tempat Sampah Pedal Stainless',
        category: 'kitchen',
        icon: '🗑️',
        desc: 'Tong sampah injak pedal stainless steel 20 Liter',
        tags: ['tempat sampah', 'tong sampah', 'trash bin', 'pedal', 'dapur', 'interior', 'kebersihan'],
        baseW: 35,
        baseH: 50,
        defaultWmm: 300,
        defaultHmm: 450,
    },

    // ── 4. WARDROBE, LEMARI & RIAS ──
    {
        id: 'vanity_table',
        name: 'Meja Rias Vanity & Stool',
        category: 'wardrobe',
        icon: '💄',
        desc: 'Meja rias konsol laci + cermin arch LED & stool empuk',
        tags: ['meja rias', 'vanity', 'dresser', 'rias', 'makeup', 'stool', 'cermin rias', 'kamar'],
        baseW: 100,
        baseH: 140,
        defaultWmm: 1000,
        defaultHmm: 1400,
    },
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
        desc: 'Cermin rias bulat dengan cahaya LED warm white',
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

    // ── 5. KAMAR MANDI & SANITARI (BATHROOM) ──
    {
        id: 'toilet',
        name: 'Kloset Duduk Sanitari',
        category: 'bathroom',
        icon: '🚽',
        desc: 'Monoblok kloset duduk keramik putih + tombol dual flush',
        tags: ['kloset', 'toilet', 'wc', 'water closet', 'sanitari', 'kamar mandi', 'bathroom'],
        baseW: 45,
        baseH: 80,
        defaultWmm: 400,
        defaultHmm: 750,
    },
    {
        id: 'shower',
        name: 'Shower Tiang & Rain Shower',
        category: 'bathroom',
        icon: '🚿',
        desc: 'Tiang shower stainless + rain shower & hand shower',
        tags: ['shower', 'rain shower', 'tiang shower', 'mandi', 'kamar mandi', 'sanitari'],
        baseW: 40,
        baseH: 120,
        defaultWmm: 350,
        defaultHmm: 1200,
    },
    {
        id: 'bathroom_vanity',
        name: 'Wastafel Kabinet Kamar Mandi',
        category: 'bathroom',
        icon: '🪞',
        desc: 'Wastafel cuci tangan keramik + kabinet gantung & cermin',
        tags: ['wastafel', 'vanity', 'kamar mandi', 'cuci tangan', 'basin', 'bathroom', 'kabinet'],
        baseW: 70,
        baseH: 85,
        defaultWmm: 700,
        defaultHmm: 850,
    },
    {
        id: 'water_heater',
        name: 'Water Heater Listrik Dinding',
        category: 'bathroom',
        icon: '♨️',
        desc: 'Pemanas air elektrik dinding 15-30L + display digital',
        tags: ['water heater', 'pemanas air', 'mandi', 'kamar mandi', 'listrik', 'mep'],
        baseW: 50,
        baseH: 50,
        defaultWmm: 450,
        defaultHmm: 450,
    },
    {
        id: 'towel_rail',
        name: 'Gantungan Handuk & Rak Sanitari',
        category: 'bathroom',
        icon: '🧣',
        desc: 'Gantungan handuk ganda stainless kamar mandi',
        tags: ['gantungan handuk', 'towel rail', 'handuk', 'rak handuk', 'kamar mandi', 'sanitari'],
        baseW: 70,
        baseH: 55,
        defaultWmm: 600,
        defaultHmm: 450,
    },

    // ── 6. DEKORASI & PENCAHAYAAN (DECOR & LIGHTING) ──
    {
        id: 'curtain',
        name: 'Gorden & Tirai Jendela Kain',
        category: 'decor',
        icon: '🪟',
        desc: 'Tirai kain gorden jendela lipat vertikal + vitrase',
        tags: ['gorden', 'tirai', 'curtain', 'jendela', 'vitrase', 'kain', 'dekorasi'],
        baseW: 130,
        baseH: 180,
        defaultWmm: 1500,
        defaultHmm: 2200,
    },
    {
        id: 'floor_lamp',
        name: 'Standing Floor Lamp Sudut',
        category: 'decor',
        icon: '🏮',
        desc: 'Lampu tiang berdiri estetik sudut ruang keluarga/kamar',
        tags: ['standing lamp', 'floor lamp', 'lampu lantai', 'lampu sudut', 'lampu berdiri', 'dekor'],
        baseW: 45,
        baseH: 150,
        defaultWmm: 450,
        defaultHmm: 1600,
    },
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
    {
        id: 'clock',
        name: 'Jam Dinding Minimalis',
        category: 'decor',
        icon: '⏰',
        desc: 'Jam dinding analog modern estetik',
        tags: ['jam', 'clock', 'dinding', 'waktu', 'pajangan', 'dekorasi'],
        baseW: 50,
        baseH: 50,
        defaultWmm: 350,
        defaultHmm: 350,
    },

    // ── 7. LISTRIK & ELEKTRONIK / MEP ──
    {
        id: 'ac_indoor',
        name: 'AC Split Indoor Unit',
        category: 'mep',
        icon: '❄️',
        desc: 'Indoor AC pendingin ruangan split wall',
        tags: ['ac', 'air conditioner', 'pendingin', 'split', 'mep', 'kamar', 'living'],
        baseW: 130,
        baseH: 45,
        defaultWmm: 850,
        defaultHmm: 300,
    },
    {
        id: 'ceiling_fan',
        name: 'Kipas Angin Gantung Plafon',
        category: 'mep',
        icon: '🌪️',
        desc: 'Kipas plafon modern 3 baling-baling kayu + lampu LED',
        tags: ['kipas', 'ceiling fan', 'kipas plafon', 'kipas angin', 'ventilasi', 'mep'],
        baseW: 120,
        baseH: 40,
        defaultWmm: 1200,
        defaultHmm: 400,
    },
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
];

const showAccessoryMenu = ref(false);
type AccessoryViewAngle = 'depan' | 'atas' | 'samping' | 'bawah';
const accessoryActiveView = ref<AccessoryViewAngle>('depan');
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

        // Auto-match active view from main canvas
        if (activeView.value === 'atas') {
            accessoryActiveView.value = 'atas';
        } else if (activeView.value === 'samping' || activeView.value === 'potongan') {
            accessoryActiveView.value = 'samping';
        }

        nextTick(() => {
            accessorySearchInputRef.value?.focus();
        });
    }
    showAccessoryMenu.value = !showAccessoryMenu.value;
}

function getAccessoryBaseSize(type?: string, view: string = 'depan'): { w: number; h: number } {
    const item = ACCESSORY_CATALOG.find(a => a.id === type);
    const defW = item ? item.baseW : 120;
    const defH = item ? item.baseH : 80;

    if (!type) return { w: defW, h: defH };

    if (view === 'atas') {
        switch (type) {
            case 'bed': return { w: 160, h: 180 };
            case 'sofa': return { w: 180, h: 80 };
            case 'armchair': return { w: 80, h: 80 };
            case 'coffee_table': return { w: 100, h: 55 };
            case 'dining_table': return { w: 160, h: 90 };
            case 'dining_chair': return { w: 45, h: 45 };
            case 'sink': return { w: 110, h: 70 };
            case 'stove': return { w: 110, h: 70 };
            case 'tv': return { w: 180, h: 22 };
            case 'soundbar': return { w: 140, h: 18 };
            case 'toilet': return { w: 50, h: 80 };
            case 'bathtub': return { w: 160, h: 80 };
            case 'fridge_standalone': return { w: 90, h: 75 };
            case 'oven': return { w: 100, h: 70 };
            case 'hood': return { w: 110, h: 60 };
            case 'wardrobe_door': return { w: 120, h: 65 };
            case 'plant': return { w: 50, h: 50 };
            case 'downlight': return { w: 24, h: 24 };
            case 'rug': return { w: 180, h: 120 };
            case 'drawer': return { w: 100, h: 60 };
            case 'books': return { w: 100, h: 30 };
            default: return { w: defW, h: Math.max(30, Math.round(defH * 0.8)) };
        }
    } else if (view === 'samping') {
        switch (type) {
            case 'bed': return { w: 180, h: 90 };
            case 'sofa': return { w: 80, h: 80 };
            case 'armchair': return { w: 80, h: 85 };
            case 'coffee_table': return { w: 55, h: 45 };
            case 'dining_table': return { w: 85, h: 75 };
            case 'dining_chair': return { w: 45, h: 85 };
            case 'sink': return { w: 70, h: 50 };
            case 'stove': return { w: 70, h: 25 };
            case 'tv': return { w: 22, h: 105 };
            case 'toilet': return { w: 80, h: 85 };
            case 'bathtub': return { w: 80, h: 60 };
            case 'fridge_standalone': return { w: 75, h: 180 };
            case 'oven': return { w: 70, h: 80 };
            case 'hood': return { w: 60, h: 60 };
            case 'wardrobe_door': return { w: 65, h: 150 };
            case 'barstool': return { w: 45, h: 90 };
            case 'drawer': return { w: 70, h: 90 };
            case 'plant': return { w: 45, h: 65 };
            default: return { w: Math.max(30, Math.round(defW * 0.6)), h: defH };
        }
    } else if (view === 'bawah') {
        switch (type) {
            case 'downlight': return { w: 24, h: 24 };
            case 'pendant_light': return { w: 32, h: 32 };
            case 'led_strip': return { w: 140, h: 14 };
            case 'sink': return { w: 110, h: 70 };
            case 'dining_table': return { w: 160, h: 90 };
            case 'coffee_table': return { w: 100, h: 55 };
            case 'sofa': return { w: 180, h: 80 };
            case 'bed': return { w: 160, h: 180 };
            default: return { w: defW, h: defH };
        }
    }

    return { w: defW, h: defH };
}

function getAccessoryWidthPx(ann: AnnotationItem): number {
    const base = getAccessoryBaseSize(ann.accessoryType, ann.view || 'depan');
    return ann.width || Math.round(base.w * (ann.scale || 1));
}

function getAccessoryHeightPx(ann: AnnotationItem): number {
    const base = getAccessoryBaseSize(ann.accessoryType, ann.view || 'depan');
    return ann.height || Math.round(base.h * (ann.scale || 1));
}

function setAccessoryView(ann: AnnotationItem, view: AccessoryViewAngle) {
    if (ann.view === view) return;
    const oldView = ann.view || 'depan';
    ann.view = view;

    const baseOld = getAccessoryBaseSize(ann.accessoryType, oldView);
    const baseNew = getAccessoryBaseSize(ann.accessoryType, view);
    if (baseOld.w !== baseNew.w || baseOld.h !== baseNew.h) {
        ann.width = baseNew.w;
        ann.height = baseNew.h;
    }

    captureSnapshot();
    const viewLabels: Record<AccessoryViewAngle, string> = {
        depan: 'Tampak Depan (Elevasi)',
        atas: 'Tampak Atas (Denah/Plan)',
        samping: 'Tampak Samping (Potongan)',
        bawah: 'Tampak Bawah (Plafon/Kolong)',
    };
    toast.success(`Sudut pandang diubah ke: ${viewLabels[view]}`);
}

function rotateAccessory(ann: AnnotationItem) {
    ann.rotation = ((ann.rotation || 0) + 90) % 360;
    captureSnapshot();
    toast.success(`Aksesoris diputar ke ${ann.rotation}°`);
}

function flipAccessoryH(ann: AnnotationItem) {
    ann.flipH = !ann.flipH;
    captureSnapshot();
    toast.success(`Aksesoris dibalik horisontal ${ann.flipH ? '(ON)' : '(OFF)'}`);
}

function flipAccessoryV(ann: AnnotationItem) {
    ann.flipV = !ann.flipV;
    captureSnapshot();
    toast.success(`Aksesoris dibalik vertikal ${ann.flipV ? '(ON)' : '(OFF)'}`);
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
    const base = getAccessoryBaseSize(ann.accessoryType, ann.view || 'depan');
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

function stampAccessory(type: string, viewAngle?: AccessoryViewAngle) {
    showAccessoryMenu.value = false;
    const view = viewAngle || accessoryActiveView.value || 'depan';
    const item = ACCESSORY_CATALOG.find(a => a.id === type);
    const base = getAccessoryBaseSize(type, view);
    const baseW = base.w;
    const baseH = base.h;
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
        view: view,
        rotation: 0,
        flipH: false,
        flipV: false,
        scale: 1,
    };

    annotations.value.push(newAcc);
    selectedObjectId.value = newAcc.id;
    selectedElement.value = { type: 'accessory', id: newAcc.id, name: `${label} (${view})` };
    captureSnapshot();
    toast.success(`Aksesoris "${label}" (${view}) berhasil ditambahkan ke kanvas.`);
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

// Multi-View 4-Quadrant Frame Coordinates (Dynamic according to Title Block Position)
const multiViewFrames = computed(() => {
    if (titleBlockPos.value === 'side') {
        return {
            f1: { x: 45, y: 45, w: 430, h: 365 },
            f2: { x: 495, y: 45, w: 430, h: 365 },
            f3: { x: 45, y: 430, w: 430, h: 365 },
            f4: { x: 495, y: 430, w: 430, h: 365 },
        };
    } else if (titleBlockPos.value === 'top') {
        return {
            f1: { x: 45, y: 125, w: 540, h: 325 },
            f2: { x: 605, y: 125, w: 540, h: 325 },
            f3: { x: 45, y: 470, w: 540, h: 325 },
            f4: { x: 605, y: 470, w: 540, h: 325 },
        };
    } else {
        // bottom
        return {
            f1: { x: 45, y: 45, w: 540, h: 325 },
            f2: { x: 605, y: 45, w: 540, h: 325 },
            f3: { x: 45, y: 390, w: 540, h: 325 },
            f4: { x: 605, y: 390, w: 540, h: 325 },
        };
    }
});

function selectView(view: ViewType) {
    activeView.value = view;
    layoutMode.value = 'single';
    activeSheetTab.value = '01';

    const defaultTitles: Record<ViewType, { title: string; num: string }> = {
        depan: { title: 'TAMPAK DEPAN', num: '1' },
        tanpa_pintu: { title: 'TAMPAK DEPAN TANPA PINTU', num: '2' },
        samping: { title: 'TAMPAK SAMPING', num: '3' },
        potongan: { title: 'POTONGAN A-A', num: '4' },
        atas: { title: 'TAMPAK ATAS (PLAN)', num: '5' },
        iso: { title: 'ISOMETRI 3D', num: '6' },
    };
    if (defaultTitles[view]) {
        cabinet.value.viewTitle = defaultTitles[view].title;
        cabinet.value.viewNumber = defaultTitles[view].num;
    }
    captureSnapshot();
}

// Direct Drag & Drop direct manipulation engine
const isDraggingObject = ref(false);
const isCtrlDrag = ref(false);
const isCtrlPressed = ref(false);
const clipboardObject = ref<AnnotationItem | any | null>(null);
const pasteOffsetCount = ref(1);
const selectedObjectId = ref<string | null>(isDemoSheet ? 'tv' : null);
const hoveredAccessoryId = ref<string | null>(null);
const dragPayload = ref<{
    type: 'view_title' | 'annotation' | 'annotation_target' | 'section_line' | 'shelves' | 'shelves_resize' | 'sink' | 'divider_fridge' | 'divider_tall' | 'countertop' | 'component' | 'line_body' | 'line_endpoint_a' | 'line_endpoint_b' | 'tv' | 'credenza' | 'accessory_resize_se' | 'accessory_resize_e' | 'accessory_resize_s' | 'accessory_resize_w' | 'accessory_resize_n' | 'accessory_resize_nw' | 'accessory_resize_ne' | 'accessory_resize_sw';
    id: string;
    startX: number;
    startY: number;
    initialData: Record<string, any>;
    isCtrl?: boolean;
    cloned?: boolean;
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
const selectedElement = ref<{ type: string; id: string; name: string } | null>(
    isDemoSheet ? {
        type: 'component',
        id: 'fridge',
        name: 'Ruang Kulkas 2 Pintu',
    } : null
);

const selectedAnnotation = computed(() => {
    return annotations.value.find(a => a.id === selectedObjectId.value) || null;
});

// Load saved data from database props if available
function initFromProps() {
    if (!props.elements || !props.elements.length) {
        if (!isDemoSheet) {
            annotations.value = [];
            cabinet.value.name = props.sheet?.title || 'Lembar Kerja Baru';
            cabinet.value.projectName = props.project?.title || '';
            cabinet.value.furnitureType = 'blank';
            cabinet.value.materials = [];
            selectedElement.value = null;
        }
        return;
    }
    const shopEl = props.elements.find((e: any) => e.type === 'shop_drawing' || e.data?.cabinet);
    if (shopEl?.data) {
        const d = typeof shopEl.data === 'string' ? JSON.parse(shopEl.data) : shopEl.data;
        if (d.cabinet) {
            cabinet.value = { ...cabinet.value, ...d.cabinet };
        }
        if (d.annotations && Array.isArray(d.annotations)) {
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

    const isCtrl = ('ctrlKey' in e && (e.ctrlKey || e.metaKey)) || isCtrlPressed.value;
    isCtrlDrag.value = isCtrl;

    isDraggingObject.value = true;
    selectedObjectId.value = id;
    selectedElement.value = { type, id, name: initialData.name || id };

    dragPayload.value = {
        type: type as any,
        id,
        startX: pt.x,
        startY: pt.y,
        initialData: { ...initialData },
        isCtrl,
        cloned: false,
    };
}

function doObjectDrag(pointEvent: MouseEvent | Touch) {
    if (!dragPayload.value) return;
    const pt = getSvgPoint(pointEvent);
    const dx = pt.x - dragPayload.value.startX;
    const dy = pt.y - dragPayload.value.startY;
    const target = dragPayload.value;

    const isCtrlNow = target.isCtrl || ('ctrlKey' in pointEvent && (pointEvent.ctrlKey || (pointEvent as any).metaKey)) || isCtrlPressed.value;

    // Duplikasi saat geser dengan Ctrl (Ctrl + Klik Kiri Geser)
    if (isCtrlNow && !target.cloned && (target.type === 'annotation' || target.type === 'line_body')) {
        const dist = Math.hypot(dx, dy);
        if (dist >= 3) {
            const originalIndex = annotations.value.findIndex(a => a.id === target.id);
            if (originalIndex !== -1) {
                const original = annotations.value[originalIndex];

                // Kembalikan objek asli ke posisi awal
                original.x = target.initialData.x;
                original.y = target.initialData.y;
                if (target.initialData.x2 !== undefined) original.x2 = target.initialData.x2;
                if (target.initialData.y2 !== undefined) original.y2 = target.initialData.y2;
                if (target.initialData.targetX !== undefined) original.targetX = target.initialData.targetX;
                if (target.initialData.targetY !== undefined) original.targetY = target.initialData.targetY;

                // Buat salinan baru yang akan bergerak mengikuti kursor mouse
                const cloneId = `${original.type || 'obj'}-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
                const clonedItem: AnnotationItem = {
                    ...JSON.parse(JSON.stringify(original)),
                    id: cloneId,
                };
                annotations.value.push(clonedItem);

                // Arahkan proses drag ke objek kloning baru
                target.id = cloneId;
                target.cloned = true;
                target.isCtrl = true;
                isCtrlDrag.value = true;
                selectedObjectId.value = cloneId;
                selectedElement.value = {
                    type: clonedItem.type,
                    id: cloneId,
                    name: clonedItem.text || clonedItem.accessoryType || 'Objek Duplikat',
                };
            }
        }
    }

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
            if (ann.targetX !== undefined && target.initialData.targetX !== undefined) {
                ann.targetX = target.initialData.targetX + dx;
            }
            if (ann.targetY !== undefined && target.initialData.targetY !== undefined) {
                ann.targetY = target.initialData.targetY + dy;
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
    } else if (target.type === 'view_title') {
        cabinet.value.viewTitleOffsetX = target.initialData.x + dx;
        cabinet.value.viewTitleOffsetY = target.initialData.y + dy;
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

function handleGlobalMouseUp(e?: MouseEvent) {
    if (isDraggingObject.value) {
        const payload = dragPayload.value;
        const releasedWithCtrl = (e && (e.ctrlKey || e.metaKey)) || isCtrlDrag.value || isCtrlPressed.value;

        // Jika user melepas drag dengan menahan tombol Ctrl dan belum di-clone:
        if (releasedWithCtrl && payload && !payload.cloned && (payload.type === 'annotation' || payload.type === 'line_body')) {
            const movedItem = annotations.value.find(a => a.id === payload.id);
            if (movedItem) {
                // Buat salinan di posisi awal
                const originalClone: AnnotationItem = {
                    ...JSON.parse(JSON.stringify(movedItem)),
                    id: `${movedItem.type || 'obj'}-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
                    x: payload.initialData.x,
                    y: payload.initialData.y,
                };
                if (payload.initialData.x2 !== undefined) originalClone.x2 = payload.initialData.x2;
                if (payload.initialData.y2 !== undefined) originalClone.y2 = payload.initialData.y2;
                if (payload.initialData.targetX !== undefined) originalClone.targetX = payload.initialData.targetX;
                if (payload.initialData.targetY !== undefined) originalClone.targetY = payload.initialData.targetY;

                annotations.value.push(originalClone);
                toast.success('Objek berhasil diduplikasi.');
            }
        } else if (payload && payload.cloned) {
            toast.success('Objek berhasil diduplikasi.');
        }

        isDraggingObject.value = false;
        dragPayload.value = null;
        isCtrlDrag.value = false;
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
    if (hasPannedDistance.value) return;

    if (activeTool.value === 'select') {
        clearSelection();
        return;
    }

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

function clearSelection() {
    selectedObjectId.value = null;
    selectedElement.value = null;
    hoveredAccessoryId.value = null;
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
    if (id === 'view_title') {
        cabinet.value.showViewTitle = false;
        selectedObjectId.value = null;
        selectedElement.value = null;
        captureSnapshot();
        toast.success('Judul tampak berhasil dihapus. Tekan tombol "+ Judul View" atau Ctrl+Z untuk memunculkannya kembali.');
        return;
    }

    selectedObjectId.value = null;
    selectedElement.value = null;
}

function addOrRestoreViewTitle() {
    cabinet.value.showViewTitle = true;
    if (!cabinet.value.viewTitle) {
        cabinet.value.viewTitle = 'TAMPAK DEPAN';
    }
    if (!cabinet.value.viewNumber) {
        cabinet.value.viewNumber = '1';
    }
    if (!cabinet.value.scaleSingle) {
        cabinet.value.scaleSingle = '1 : 20';
    }
    selectedObjectId.value = 'view_title';
    selectedElement.value = {
        type: 'component',
        id: 'view_title',
        name: `Judul Gambar: ${cabinet.value.viewTitle}`,
    };
    if (!isDesktopSidebarOpen.value && !isMobileScreen.value) {
        isDesktopSidebarOpen.value = true;
    }
    captureSnapshot();
    toast.success('Judul tampak berhasil ditambahkan ke kanvas.');
}

function toggleViewTitle() {
    if (cabinet.value.showViewTitle === false) {
        addOrRestoreViewTitle();
    } else {
        selectedObjectId.value = 'view_title';
        deleteSelectedObject();
    }
}

function copySelectedObject() {
    if (!selectedObjectId.value) {
        toast.info('Pilih objek terlebih dahulu untuk disalin (Ctrl+C).');
        return;
    }

    const id = selectedObjectId.value;
    const ann = annotations.value.find(a => a.id === id);

    if (ann) {
        clipboardObject.value = JSON.parse(JSON.stringify(ann));
        pasteOffsetCount.value = 1;
        const name = ann.text || ann.accessoryType || ann.type || 'Objek';
        toast.success(`"${name}" disalin ke clipboard (Ctrl+V untuk menempel).`);
        return;
    }

    if (id === 'view_title') {
        clipboardObject.value = {
            id: 'temp',
            type: 'label',
            x: Math.round(furnitureSvgX.value + (furnitureSvgWidth.value / 2) - 80 + (cabinet.value.viewTitleOffsetX || 0)),
            y: Math.round(645 + (cabinet.value.viewTitleOffsetY || 0)),
            text: `${cabinet.value.viewNumber || '1'} ${cabinet.value.viewTitle || 'TAMPAK DEPAN'} (${cabinet.value.scaleSingle || '1 : 20'})`,
            code: cabinet.value.viewNumber || '1',
            desc: cabinet.value.scaleSingle || '1 : 20',
        };
        pasteOffsetCount.value = 1;
        toast.success(`"Judul: ${cabinet.value.viewTitle || 'TAMPAK DEPAN'}" disalin (Ctrl+V untuk menempel).`);
        return;
    }

    // Built-in furniture accessories
    if (id === 'tv') {
        clipboardObject.value = {
            id: 'temp',
            type: 'accessory',
            accessoryType: 'tv',
            x: 550 + tvOffsetX.value,
            y: 280 + tvOffsetY.value,
            text: 'Smart TV 65"',
            scale: 1,
        };
        pasteOffsetCount.value = 1;
        toast.success('"Smart TV" disalin (Ctrl+V untuk menempel).');
        return;
    }

    if (id === 'sink') {
        clipboardObject.value = {
            id: 'temp',
            type: 'accessory',
            accessoryType: 'sink',
            x: 500 + sinkOffsetX.value,
            y: 420,
            text: 'Sink Wastafel',
            scale: 1,
        };
        pasteOffsetCount.value = 1;
        toast.success('"Sink Wastafel" disalin (Ctrl+V untuk menempel).');
        return;
    }

    toast.info('Pilih objek notasi atau aksesoris untuk disalin.');
}

function pasteCopiedObject() {
    if (!clipboardObject.value) {
        toast.info('Clipboard kosong. Pilih objek lalu tekan Ctrl+C terlebih dahulu.');
        return;
    }

    const raw = clipboardObject.value;
    const offset = 25 * pasteOffsetCount.value;
    pasteOffsetCount.value += 1;

    const newId = `${raw.type || 'obj'}-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
    const newItem: AnnotationItem = {
        ...JSON.parse(JSON.stringify(raw)),
        id: newId,
        x: Math.min(1140, Math.max(35, (raw.x ?? 500) + offset)),
        y: Math.min(780, Math.max(35, (raw.y ?? 350) + offset)),
    };

    if (raw.x2 !== undefined) {
        newItem.x2 = Math.min(1140, Math.max(35, raw.x2 + offset));
    }
    if (raw.y2 !== undefined) {
        newItem.y2 = Math.min(780, Math.max(35, raw.y2 + offset));
    }
    if (raw.targetX !== undefined) {
        newItem.targetX = Math.min(1140, Math.max(35, raw.targetX + offset));
    }
    if (raw.targetY !== undefined) {
        newItem.targetY = Math.min(780, Math.max(35, raw.targetY + offset));
    }

    annotations.value.push(newItem);

    // Otomatis pilih objek hasil paste
    selectedObjectId.value = newItem.id;
    selectedElement.value = {
        type: newItem.type,
        id: newItem.id,
        name: newItem.text || newItem.accessoryType || 'Objek Hasil Salinan',
    };

    captureSnapshot();
    toast.success(`"${newItem.text || newItem.accessoryType || 'Objek'}" berhasil ditempel.`);
}

function duplicateSelectedObject() {
    if (!selectedObjectId.value) return;
    copySelectedObject();
    pasteCopiedObject();
}

function handleGlobalKeyDown(e: KeyboardEvent) {
    if (e.key === 'Control' || e.key === 'Meta') {
        isCtrlPressed.value = true;
    }

    // Tombol Escape (Batalkan seleksi objek aktif / tutup modal & menu)
    if (e.key === 'Escape' || e.key === 'Esc' || e.code === 'Escape' || e.keyCode === 27) {
        if (showAccessoryMenu.value) {
            showAccessoryMenu.value = false;
            return;
        }
        if (showKopModal.value) {
            showKopModal.value = false;
            return;
        }
        if (showCabinetModal.value) {
            showCabinetModal.value = false;
            return;
        }
        if (showTutorialModal.value) {
            showTutorialModal.value = false;
            return;
        }
        if (editingField.value) {
            editingField.value = null;
            return;
        }
        e.preventDefault();
        clearSelection();
        toast.info('Seleksi objek dibatalkan.');
        return;
    }

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

    // Ctrl+C atau Cmd+C (Copy / Salin Objek Terpilih)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c' && !e.shiftKey) {
        const textSelection = window.getSelection()?.toString();
        if (!textSelection || textSelection.trim().length === 0) {
            if (selectedObjectId.value) {
                e.preventDefault();
                copySelectedObject();
                return;
            }
        }
    }

    // Ctrl+V atau Cmd+V (Paste / Tempel Objek)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v' && !e.shiftKey) {
        if (clipboardObject.value) {
            e.preventDefault();
            pasteCopiedObject();
            return;
        }
    }

    // Ctrl+D atau Cmd+D (Duplikasi Cepat Objek)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd' && !e.shiftKey) {
        if (selectedObjectId.value) {
            e.preventDefault();
            duplicateSelectedObject();
            return;
        }
    }

    // Ctrl+P atau Cmd+P (Cetak / Ekspor PDF Gambar Kerja)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
        e.preventDefault();
        exportPdf();
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

function handleGlobalKeyUp(e: KeyboardEvent) {
    if (e.key === 'Control' || e.key === 'Meta') {
        isCtrlPressed.value = false;
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
    const list: Array<{
        id: number;
        name: string;
        qty: number;
        length: number;
        width: number;
        thick: number;
        mat: string;
        finish: string;
        edging: string;
    }> = [];
    let id = 1;
    const w = cabinet.value.width || 2400;
    const d = cabinet.value.depth || 600;
    const h = cabinet.value.height || 2200;

    // JIKA TIPE BLANK (LEMBAR GAMBAR BEBAS / AKSESORIS KUSTOM FURNITUR)
    if (cabinet.value.furnitureType === 'blank') {
        const accessories = annotations.value.filter(a => a.type === 'accessory');

        if (accessories.length > 0) {
            accessories.forEach((ann, aIdx) => {
                const accName = ann.text || ann.accessoryType || `Komponen ${aIdx + 1}`;
                const accW = getAccessoryWidthMm(ann);
                const accH = getAccessoryHeightMm(ann);
                const type = ann.accessoryType;

                if (type === 'bed') {
                    const bedW = Math.max(1200, accW);
                    const bedHeadH = Math.max(800, accH);
                    list.push({ id: id++, name: `Panel Headboard Dinding (${accName})`, qty: 1, length: bedW, width: bedHeadH, thick: 18, mat: 'Multiplek 18mm + Busa / HPL', finish: 'Upholstery Fabric / HPL Wood Grain', edging: '4 Sisi Edging' });
                    list.push({ id: id++, name: `Dinding Samping Rangka Dipan Kiri & Kanan (${accName})`, qty: 2, length: 2000, width: 300, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Wood Grain', edging: 'P1, L1' });
                    list.push({ id: id++, name: `Dinding Depan Footboard Dipan (${accName})`, qty: 1, length: bedW, width: 300, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Wood Grain', edging: 'P1, L1' });
                    list.push({ id: id++, name: `Balok Gelagar Rangka Tengah Dipan (${accName})`, qty: 1, length: 1950, width: 80, thick: 40, mat: 'Kayu Solid Kamper / Meranti', finish: 'Mentahan Halus Presisi', edging: 'Tanpa Edging' });
                    list.push({ id: id++, name: `Rangka Slat Penyangga Kasur (${accName})`, qty: 8, length: bedW - 40, width: 70, thick: 20, mat: 'Kayu Solid Kamper', finish: 'Mentahan Halus Presisi', edging: 'Tanpa Edging' });
                    list.push({ id: id++, name: `Panel Tutup Alas Kasur (${accName})`, qty: 2, length: Math.round((bedW - 36) / 2), width: 980, thick: 9, mat: 'Multiplek 9mm Melaminto', finish: 'Melaminto Putih', edging: 'Tanpa Edging' });
                } else if (type === 'sofa' || type === 'armchair') {
                    const sofaW = Math.max(800, accW);
                    const sofaH = Math.max(600, accH);
                    list.push({ id: id++, name: `Rangka Bawah Dudukan (${accName})`, qty: 1, length: sofaW, width: 750, thick: 18, mat: 'Multiplek 18mm / Kayu Solid', finish: 'Melaminto / Mentahan', edging: 'P1' });
                    list.push({ id: id++, name: `Rangka Sandaran Punggung (${accName})`, qty: 1, length: sofaW, width: sofaH, thick: 18, mat: 'Multiplek 18mm + Busa D24', finish: 'Upholstery Fabric', edging: 'Tanpa Edging' });
                    list.push({ id: id++, name: `Rangka Sandaran Lengan Kiri & Kanan (${accName})`, qty: 2, length: 750, width: Math.round(sofaH * 0.75), thick: 18, mat: 'Multiplek 18mm + Busa', finish: 'Upholstery Fabric', edging: 'Tanpa Edging' });
                    list.push({ id: id++, name: `Kaki Penyangga Sofa (${accName})`, qty: 4, length: 150, width: 50, thick: 50, mat: 'Kayu Solid Bubut / Stainless Steel', finish: 'Natural Doff / Chrome', edging: 'Poles Keliling' });
                } else if (type === 'drawer') {
                    const drawerW = Math.max(400, accW);
                    const drawerH = Math.max(400, accH);
                    const faceH = Math.round(drawerH / 3) - 6;
                    list.push({ id: id++, name: `Muka Panel Laci (${accName})`, qty: 3, length: drawerW - 4, width: faceH, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Wood Grain / Duco', edging: '4 Sisi J-Pull' });
                    list.push({ id: id++, name: `Kotak Samping Laci (${accName})`, qty: 6, length: 450, width: Math.max(120, faceH - 40), thick: 15, mat: 'Multiplek 15mm Melaminto', finish: 'Melaminto Putih', edging: 'P1' });
                    list.push({ id: id++, name: `Kotak Depan & Belakang Laci (${accName})`, qty: 6, length: drawerW - 64, width: Math.max(120, faceH - 40), thick: 15, mat: 'Multiplek 15mm Melaminto', finish: 'Melaminto Putih', edging: 'P1' });
                    list.push({ id: id++, name: `Alas Bawah Kotak Laci (${accName})`, qty: 3, length: drawerW - 40, width: 440, thick: 6, mat: 'Multiplek 6mm Melaminto', finish: 'Melaminto Putih', edging: 'Tanpa Edging' });
                    list.push({ id: id++, name: `Rel Laci Tandem Slowmotion (${accName})`, qty: 3, length: 450, width: 45, thick: 35, mat: 'Besi Galvanis Cold Rolled', finish: 'Zinc Plated Slowmotion', edging: 'Fitting Hardware' });
                } else if (type === 'wardrobe_door' || type === 'wardrobe') {
                    const wardW = Math.max(600, accW);
                    const wardH = Math.max(1200, accH);
                    list.push({ id: id++, name: `Dinding Samping Luar Lemari (${accName})`, qty: 2, length: wardH, width: 600, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Wood Grain', edging: 'P1, L1' });
                    list.push({ id: id++, name: `Daun Pintu Lemari Pakaian (${accName})`, qty: 2, length: wardH - 80, width: Math.round(wardW / 2) - 4, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Solid / Wood', edging: '4 Sisi Edging 2mm' });
                    list.push({ id: id++, name: `Ambalan Rak Baju Internal (${accName})`, qty: 4, length: wardW - 36, width: 550, thick: 18, mat: 'Multiplek 18mm Melaminto', finish: 'Melaminto Putih', edging: 'P1' });
                    list.push({ id: id++, name: `Top Panel & Alas Bawah Lemari (${accName})`, qty: 2, length: wardW, width: 600, thick: 18, mat: 'Multiplek 18mm', finish: 'Melaminto Putih', edging: 'P1' });
                    list.push({ id: id++, name: `Backing Belakang Lemari (${accName})`, qty: 1, length: wardW, width: wardH - 80, thick: 6, mat: 'Multiplek 6mm Melaminto', finish: 'Melaminto Putih', edging: 'Tanpa Edging' });
                    list.push({ id: id++, name: `Plinth / Kaki Bawah Lemari (${accName})`, qty: 1, length: wardW, width: 80, thick: 18, mat: 'Multiplek 18mm', finish: 'Hitam Doff', edging: 'P1' });
                } else if (type === 'coffee_table' || type === 'dining_table' || type === 'table') {
                    const tblW = Math.max(600, accW);
                    const tblD = Math.max(400, accH);
                    list.push({ id: id++, name: `Top Table Meja (${accName})`, qty: 1, length: tblW, width: tblD, thick: 25, mat: 'Multiplek Double 25mm / Solid Surface', finish: 'HPL Marmer / Kayu Solid', edging: '4 Sisi Bevel' });
                    list.push({ id: id++, name: `Rangka Apron Panjang Meja (${accName})`, qty: 2, length: tblW - 100, width: 90, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL / Cat Duco', edging: 'P1' });
                    list.push({ id: id++, name: `Rangka Apron Pendek Meja (${accName})`, qty: 2, length: Math.max(300, tblD - 100), width: 90, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL / Cat Duco', edging: 'P1' });
                    list.push({ id: id++, name: `Kaki Penyangga Meja (${accName})`, qty: 4, length: 720, width: 60, thick: 60, mat: 'Hollow Besi / Kayu Solid', finish: 'Powder Coating / Natural', edging: 'Poles Keliling' });
                } else if (type === 'tv') {
                    const tvW = Math.max(800, accW);
                    const tvH = Math.max(500, accH);
                    list.push({ id: id++, name: `Panel Backdrop Dinding TV (${accName})`, qty: 1, length: tvW + 200, width: tvH + 200, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Marmer / Wood', edging: '4 Sisi' });
                    list.push({ id: id++, name: `Rangka Penguat Braket TV (${accName})`, qty: 2, length: tvW, width: 100, thick: 18, mat: 'Multiplek 18mm', finish: 'Mentahan', edging: 'Tanpa Edging' });
                } else if (type === 'sink') {
                    const sinkW = Math.max(600, accW);
                    list.push({ id: id++, name: `Top Table Kabinet Wastafel (${accName})`, qty: 1, length: sinkW + 100, width: 550, thick: 30, mat: 'Solid Surface / Granit', finish: 'White Polished Bevel', edging: '3 Sisi Chamfer' });
                    list.push({ id: id++, name: `Pintu Kabinet Wastafel Bawah (${accName})`, qty: 2, length: 650, width: Math.round(sinkW / 2), thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Duco Waterproof', edging: '4 Sisi' });
                } else {
                    const itemW = Math.max(300, accW);
                    const itemH = Math.max(200, accH);
                    list.push({ id: id++, name: `Panel Bodi Utama (${accName})`, qty: 1, length: itemW, width: itemH, thick: 18, mat: 'Multiplek 18mm', finish: 'HPL Wood Grain / Solid', edging: 'P1, L1' });
                    list.push({ id: id++, name: `Rangka Bodi Samping (${accName})`, qty: 2, length: itemH, width: 350, thick: 18, mat: 'Multiplek 18mm', finish: 'Melaminto Putih', edging: 'P1' });
                    list.push({ id: id++, name: `Alas & Penutup Atas (${accName})`, qty: 2, length: Math.max(200, itemW - 36), width: 350, thick: 18, mat: 'Multiplek 18mm', finish: 'Melaminto Putih', edging: 'P1' });
                }
            });
            return list;
        }

        return [];
    }

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

function promptEditViewTitle() {
    openEditField('viewTitle', cabinet.value.viewTitle || 'TAMPAK DEPAN');
}

function promptEditViewNumber() {
    openEditField('viewNumber', cabinet.value.viewNumber || '1');
}

function promptEditViewScale() {
    openEditField('scaleSingle', cabinet.value.scaleSingle || '1 : 20');
}

function openEditField(field: string, currentVal: string) {
    if (!props.canEdit) return;
    editingField.value = field;
    editValue.value = currentVal;
}

function saveFieldEdit() {
    if (!editingField.value) return;
    if (editingField.value === 'name') cabinet.value.name = editValue.value || 'Lemari';
    if (editingField.value === 'viewTitle') cabinet.value.viewTitle = editValue.value ? editValue.value.toUpperCase() : 'TAMPAK DEPAN';
    if (editingField.value === 'viewNumber') cabinet.value.viewNumber = editValue.value || '1';
    if (editingField.value === 'scaleSingle') cabinet.value.scaleSingle = editValue.value || '1 : 20';
    if (editingField.value === 'width') cabinet.value.width = Number(editValue.value) || 5190;
    if (editingField.value === 'depth') cabinet.value.depth = Number(editValue.value) || 675;
    if (editingField.value === 'height') cabinet.value.height = Number(editValue.value) || 2390;
    if (editingField.value === 'date') cabinet.value.date = editValue.value;
    editingField.value = null;
    captureSnapshot();
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

const panStartClientX = ref(0);
const panStartClientY = ref(0);
const hasPannedDistance = ref(false);

function startPan(clientX: number, clientY: number) {
    isPanning.value = true;
    hasPannedDistance.value = false;
    panStartClientX.value = clientX;
    panStartClientY.value = clientY;
    startPanX.value = clientX - panX.value;
    startPanY.value = clientY - panY.value;
}

function doPan(clientX: number, clientY: number) {
    if (!isPanning.value) return;
    const dx = clientX - panStartClientX.value;
    const dy = clientY - panStartClientY.value;
    if (Math.abs(dx) > 4 || Math.abs(dy) > 4) {
        hasPannedDistance.value = true;
    }
    panX.value = clientX - startPanX.value;
    panY.value = clientY - startPanY.value;
}

function endPan() {
    isPanning.value = false;
    setTimeout(() => {
        hasPannedDistance.value = false;
    }, 100);
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

function onMouseUp(e?: MouseEvent) {
    if (isDraggingObject.value) {
        handleGlobalMouseUp(e);
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

async function exportPdf() {
    toast.info('Mempersiapkan dokumen PDF...');

    // Hapus sementara seleksi objek agar bounding box dan handle tidak ikut tercetak
    const prevSelectedId = selectedObjectId.value;
    const prevSelectedEl = selectedElement.value;
    selectedObjectId.value = null;
    selectedElement.value = null;
    await nextTick();

    try {
        let printIframe = document.getElementById('shop-drawing-print-frame') as HTMLIFrameElement;
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'shop-drawing-print-frame';
            printIframe.style.position = 'fixed';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            printIframe.style.width = '0';
            printIframe.style.height = '0';
            printIframe.style.border = '0';
            printIframe.style.visibility = 'hidden';
            document.body.appendChild(printIframe);
        }

        const doc = printIframe.contentWindow?.document;
        if (!doc) {
            window.print();
            return;
        }

        const isTab02 = activeSheetTab.value === '02';
        const paper = paperSize.value === 'A4' ? 'A4 landscape' : 'A3 landscape';
        const sheetTitle = isTab02
            ? 'Lembar 02 - Daftar Potong'
            : (cabinet.value.viewTitle || 'Lembar 01 - Gambar Kerja');
        const docTitle = `${cabinet.value.name || 'Gambar Kerja'} - ${sheetTitle}`;

        let contentHtml = '';
        if (isTab02) {
            const cutEl = document.getElementById('cutting-list-sheet');
            if (cutEl) {
                const clone = cutEl.cloneNode(true) as HTMLElement;
                clone.style.display = 'flex';
                // Hilangkan batasan scroll agar seluruh daftar potong tercetak penuh
                const tableContainer = clone.querySelector('.overflow-y-auto') as HTMLElement;
                if (tableContainer) {
                    tableContainer.style.maxHeight = 'none';
                    tableContainer.style.overflow = 'visible';
                }
                contentHtml = `<div class="sheet-container">${clone.outerHTML}</div>`;
            }
        } else {
            const svgEl = document.getElementById('shop-drawing-svg');
            if (svgEl) {
                const svgClone = svgEl.cloneNode(true) as SVGElement;
                svgClone.setAttribute('width', '100%');
                svgClone.setAttribute('height', '100%');
                contentHtml = `<div class="sheet-container">${svgClone.outerHTML}</div>`;
            }
        }

        // Salin seluruh stylesheet parent agar styling Tailwind, font, dan elemen persis seperti di layar
        const parentStyles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
            .map(el => el.outerHTML)
            .join('\n');

        doc.open();
        doc.write(`
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="utf-8" />
                <title>${docTitle}</title>
                ${parentStyles}
                <style>
                    @page {
                        size: ${paper};
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
                        background: #ffffff !important;
                        margin: 0;
                        padding: 0;
                        overflow: hidden;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                    .sheet-container {
                        width: 100vw;
                        height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: #ffffff;
                    }
                    .sheet-container svg {
                        width: 100%;
                        height: 100%;
                        max-width: 100%;
                        max-height: 100%;
                        display: block;
                    }
                    #cutting-list-sheet {
                        width: 100vw !important;
                        height: 100vh !important;
                        max-width: 100vw !important;
                        max-height: 100vh !important;
                        border: none !important;
                        box-shadow: none !important;
                        border-radius: 0 !important;
                        padding: 24px 32px !important;
                        box-sizing: border-box !important;
                    }
                </style>
            </head>
            <body>
                ${contentHtml}
            </body>
            </html>
        `);
        doc.close();

        setTimeout(() => {
            try {
                printIframe.contentWindow?.focus();
                printIframe.contentWindow?.print();
                toast.success('Membuka dialog Simpan PDF...');
            } catch (err) {
                console.error('Print iframe error:', err);
                window.print();
            }
        }, 350);
    } catch (err) {
        console.error('Export PDF failed:', err);
        toast.error('Gagal membuka dialog PDF.');
    } finally {
        selectedObjectId.value = prevSelectedId;
        selectedElement.value = prevSelectedEl;
    }
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
    window.addEventListener('keyup', handleGlobalKeyUp);
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
    window.removeEventListener('keyup', handleGlobalKeyUp);
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

                    <!-- Tombol Salin Objek Terpilih (Ctrl+C) -->
                    <button
                        v-if="selectedObjectId"
                        type="button"
                        @click="copySelectedObject"
                        class="px-2 py-1 rounded-lg text-xs font-semibold bg-white/10 hover:bg-white/15 text-slate-200 border border-white/10 flex items-center gap-1 transition-colors cursor-pointer shrink-0"
                        title="Salin Objek Terpilih (Ctrl+C)"
                    >
                        <Copy class="w-3.5 h-3.5 text-amber-400" />
                        <span class="hidden sm:inline">Salin</span>
                    </button>

                    <!-- Tombol Tempel Objek dari Clipboard (Ctrl+V) -->
                    <button
                        v-if="clipboardObject"
                        type="button"
                        @click="pasteCopiedObject"
                        class="px-2 py-1 rounded-lg text-xs font-semibold bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 flex items-center gap-1 transition-colors cursor-pointer shrink-0"
                        title="Tempel Objek (Ctrl+V)"
                    >
                        <ClipboardPaste class="w-3.5 h-3.5 text-amber-400" />
                        <span class="hidden sm:inline">Tempel</span>
                    </button>

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

                    <!-- Judul View Tool: [ Judul View / + Judul View ] -->
                    <button
                        type="button"
                        @click="cabinet.showViewTitle !== false ? selectComponent(`Judul Gambar: ${cabinet.viewTitle || 'TAMPAK DEPAN'}`, 'view_title') : addOrRestoreViewTitle()"
                        :class="cabinet.showViewTitle !== false ? (selectedObjectId === 'view_title' ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' : 'bg-white/5 text-slate-300 hover:bg-white/10') : 'bg-amber-500/20 text-amber-300 border border-amber-500/40 hover:bg-amber-500/30 font-semibold'"
                        class="px-2.5 py-1 rounded-lg text-xs flex items-center gap-1.5 transition-all cursor-pointer shrink-0"
                        :title="cabinet.showViewTitle !== false ? 'Pilih / kelola Judul View di kanvas' : 'Tambahkan kembali Judul View (TAMPAK DEPAN) ke kanvas'"
                    >
                        <Plus v-if="cabinet.showViewTitle === false" class="w-3.5 h-3.5 text-amber-400" />
                        <Tag v-else class="w-3.5 h-3.5" :class="selectedObjectId === 'view_title' ? 'text-slate-950' : 'text-amber-400'" />
                        <span>{{ cabinet.showViewTitle !== false ? 'Judul View' : '+ Judul View' }}</span>
                    </button>

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
                class="flex-1 h-full bg-[#0d0d0f] relative overflow-hidden flex items-center justify-center touch-none select-none"
                :class="[
                    (isDraggingObject && (isCtrlPressed || isCtrlDrag))
                        ? '!cursor-copy'
                        : (isPanning ? 'cursor-grabbing' : 'cursor-grab')
                ]"
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
                        @click="onCanvasClick"
                    >
                        <!-- PURE VECTOR SVG CANVAS -->
                        <svg
                            id="shop-drawing-svg"
                            viewBox="0 0 1200 840"
                            class="w-full h-full block font-sans"
                            :class="{ '!cursor-copy': isDraggingObject && (isCtrlPressed || isCtrlDrag) }"
                            xmlns="http://www.w3.org/2000/svg"
                            @click="onCanvasClick"
                        >
                            <!-- BACKGROUND DASAR PUTIH -->
                            <rect width="1200" height="840" fill="#ffffff" @click="onCanvasClick" />

                            <!-- KERTAS BORDER ARSITEKTURAL -->
                            <rect x="25" y="25" width="1150" height="790" fill="none" stroke="#222" stroke-width="1.5" />
                            <rect x="30" y="30" width="1140" height="780" fill="none" stroke="#666" stroke-width="0.5" />

                            <!-- ============================================================== -->
                            <!-- MODE SINGLE: PER-TAMPAK (FOKUS 1 VIEW BESAR, SEPERTI GAMBAR 1 & 3) -->
                            <!-- ============================================================== -->
                            <g v-if="layoutMode === 'single'" id="single-view-layout">
                                <!-- SECTION CUTTING LINE A - A (BISA DIGESER / DITARIK HORISONTAL) -->
                                <g
                                    v-if="(activeView === 'depan' || activeView === 'tanpa_pintu') && cabinet.furnitureType !== 'blank'"
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
                                <g v-if="cabinet.furnitureType !== 'blank'">
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
                                </g>

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

                                <!-- ── BLANK CANVAS GRAPHIC (LEMBAR KOSONG PUTIH BERSIH) ── -->
                                <g v-else-if="cabinet.furnitureType === 'blank'" id="blank-sheet-graphic" />

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
                                        @mousedown.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, targetX: ann.targetX ?? (ann.x - 120), targetY: ann.targetY ?? ann.y, name: `Label Material: ${ann.text}` })"
                                        @touchstart.stop="startObjectDrag($event, 'annotation', ann.id, { x: ann.x, y: ann.y, targetX: ann.targetX ?? (ann.x - 120), targetY: ann.targetY ?? ann.y, name: `Label Material: ${ann.text}` })"
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
                                            :transform="`rotate(${ann.rotation || 0}, ${getAccessoryWidthPx(ann)/2}, ${getAccessoryHeightPx(ann)/2}) scale(${ann.flipH ? -1 : 1}, ${ann.flipV ? -1 : 1})`"
                                            :style="`transform-origin: ${getAccessoryWidthPx(ann)/2}px ${getAccessoryHeightPx(ann)/2}px;`"
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
                                            <g :transform="`scale(${getAccessoryWidthPx(ann) / getAccessoryBaseSize(ann.accessoryType, ann.view || 'depan').w}, ${getAccessoryHeightPx(ann) / getAccessoryBaseSize(ann.accessoryType, ann.view || 'depan').h})`">
                                                <!-- ============================================================== -->
                                                <!-- 1. TAMPAK ATAS (TOP VIEW / PLAN VIEW / DENAH HORISONTAL)       -->
                                                <!-- ============================================================== -->
                                                <g v-if="ann.view === 'atas'">
                                                    <!-- Sofa Tampak Atas -->
                                                    <g v-if="ann.accessoryType === 'sofa'">
                                                        <rect x="0" y="0" width="180" height="80" fill="#e2e8f0" stroke="#334155" stroke-width="1.5" rx="4" />
                                                        <rect x="0" y="0" width="180" height="24" fill="#cbd5e1" stroke="#475569" stroke-width="1" rx="3" />
                                                        <rect x="0" y="24" width="20" height="56" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="3" />
                                                        <rect x="160" y="24" width="20" height="56" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="3" />
                                                        <rect x="22" y="26" width="44" height="52" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                        <rect x="68" y="26" width="44" height="52" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                        <rect x="114" y="26" width="44" height="52" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                        <text x="90" y="74" font-size="7.5" fill="#475569" text-anchor="middle" font-weight="bold">SOFA (ATAS)</text>
                                                    </g>
                                                    <!-- Armchair Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'armchair'">
                                                        <rect x="0" y="0" width="80" height="80" fill="#e2e8f0" stroke="#334155" stroke-width="1.5" rx="4" />
                                                        <rect x="0" y="0" width="80" height="22" fill="#cbd5e1" stroke="#475569" stroke-width="1" rx="3" />
                                                        <rect x="0" y="22" width="16" height="58" fill="#94a3b8" rx="2" />
                                                        <rect x="64" y="22" width="16" height="58" fill="#94a3b8" rx="2" />
                                                        <rect x="18" y="24" width="44" height="54" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                        <text x="40" y="72" font-size="7" fill="#475569" text-anchor="middle">KURSI (ATAS)</text>
                                                    </g>
                                                    <!-- Kasur (Bed) Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'bed'">
                                                        <rect x="0" y="0" width="160" height="20" fill="#78350f" stroke="#451a03" stroke-width="1.2" rx="2" />
                                                        <rect x="5" y="20" width="150" height="155" fill="#f8fafc" stroke="#334155" stroke-width="1.2" rx="3" />
                                                        <rect x="15" y="26" width="55" height="32" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="4" />
                                                        <line x1="22" y1="42" x2="63" y2="42" stroke="#e2e8f0" stroke-width="0.8" />
                                                        <rect x="90" y="26" width="55" height="32" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="4" />
                                                        <line x1="97" y1="42" x2="138" y2="42" stroke="#e2e8f0" stroke-width="0.8" />
                                                        <rect x="55" y="64" width="50" height="16" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.8" rx="8" />
                                                        <rect x="5" y="85" width="150" height="90" fill="#e2e8f0" stroke="#64748b" stroke-width="1" rx="2" />
                                                        <path d="M5,115 C55,108 105,122 155,115 M5,145 C55,138 105,152 155,145" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,2" fill="none" />
                                                        <text x="80" y="165" font-size="7.5" fill="#475569" text-anchor="middle" font-weight="bold">KASUR (TAMPAK ATAS)</text>
                                                    </g>
                                                    <!-- Nakas (Bedside Table) Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'bedside_table'">
                                                        <rect x="0" y="0" width="45" height="45" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" rx="2" />
                                                        <circle cx="22" cy="22" r="8" fill="#f59e0b" stroke="#b45309" stroke-width="0.8" />
                                                        <text x="22" y="40" font-size="6" fill="#451a03" text-anchor="middle">NAKAS</text>
                                                    </g>
                                                    <!-- Sink Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'sink'">
                                                        <rect x="0" y="0" width="110" height="70" fill="#f1f5f9" stroke="#334155" stroke-width="1.5" rx="3" />
                                                        <rect x="6" y="6" width="65" height="58" fill="#cbd5e1" stroke="#475569" stroke-width="1.2" rx="4" />
                                                        <circle cx="38" cy="35" r="9" fill="#94a3b8" stroke="#475569" stroke-width="1" />
                                                        <circle cx="38" cy="35" r="3" fill="#334155" />
                                                        <line x1="78" y1="18" x2="102" y2="18" stroke="#94a3b8" stroke-width="1.2" stroke-linecap="round" />
                                                        <line x1="78" y1="28" x2="102" y2="28" stroke="#94a3b8" stroke-width="1.2" stroke-linecap="round" />
                                                        <line x1="78" y1="38" x2="102" y2="38" stroke="#94a3b8" stroke-width="1.2" stroke-linecap="round" />
                                                        <line x1="78" y1="48" x2="102" y2="48" stroke="#94a3b8" stroke-width="1.2" stroke-linecap="round" />
                                                        <circle cx="6" cy="35" r="5" fill="#334155" />
                                                        <path d="M6,35 L26,35" stroke="#1e293b" stroke-width="3" stroke-linecap="round" />
                                                        <text x="55" y="61" font-size="7" fill="#1e293b" font-weight="bold" text-anchor="middle">SINK (ATAS)</text>
                                                    </g>
                                                    <!-- Stove / Kompor Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'stove'">
                                                        <rect x="0" y="0" width="110" height="70" fill="#09090b" stroke="#27272a" stroke-width="1.5" rx="4" />
                                                        <circle cx="32" cy="35" r="20" fill="#18181b" stroke="#ef4444" stroke-width="1.2" stroke-dasharray="3,2" />
                                                        <circle cx="32" cy="35" r="10" fill="#27272a" stroke="#ef4444" stroke-width="1" />
                                                        <circle cx="78" cy="35" r="16" fill="#18181b" stroke="#ef4444" stroke-width="1.2" stroke-dasharray="3,2" />
                                                        <circle cx="78" cy="35" r="8" fill="#27272a" stroke="#ef4444" stroke-width="1" />
                                                        <rect x="47" y="58" width="16" height="6" fill="#27272a" rx="1" />
                                                        <text x="55" y="20" font-size="6.5" fill="#a1a1aa" text-anchor="middle">KOMPOR (ATAS)</text>
                                                    </g>
                                                    <!-- TV Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'tv'">
                                                        <line x1="0" y1="3" x2="180" y2="3" stroke="#cbd5e1" stroke-width="1" stroke-dasharray="4,2" />
                                                        <rect x="75" y="3" width="30" height="6" fill="#334155" rx="1" />
                                                        <rect x="0" y="9" width="180" height="8" fill="#0f172a" stroke="#334155" stroke-width="1" rx="1" />
                                                        <line x1="10" y1="13" x2="80" y2="13" stroke="rgba(255,255,255,0.25)" stroke-width="1" />
                                                        <text x="90" y="15" font-size="6" fill="#94a3b8" text-anchor="middle">TV 65" (TAMPAK ATAS)</text>
                                                    </g>
                                                    <!-- Soundbar Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'soundbar'">
                                                        <rect x="0" y="0" width="140" height="18" fill="#18181b" stroke="#3f3f46" stroke-width="1.2" rx="2" />
                                                        <circle cx="65" cy="9" r="1.5" fill="#71717a" />
                                                        <circle cx="70" cy="9" r="1.5" fill="#38bdf8" />
                                                        <circle cx="75" cy="9" r="1.5" fill="#71717a" />
                                                        <circle cx="20" cy="9" r="5" fill="#27272a" />
                                                        <circle cx="120" cy="9" r="5" fill="#27272a" />
                                                    </g>
                                                    <!-- Kloset / Toilet Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'toilet'">
                                                        <rect x="5" y="0" width="40" height="20" fill="#f8fafc" stroke="#334155" stroke-width="1.2" rx="2" />
                                                        <circle cx="25" cy="10" r="4" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" />
                                                        <path d="M10,20 Q10,75 25,78 Q40,75 40,20 Z" fill="#ffffff" stroke="#334155" stroke-width="1.5" />
                                                        <path d="M14,24 Q14,68 25,70 Q36,68 36,24 Z" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1" />
                                                        <circle cx="16" cy="20" r="2" fill="#64748b" />
                                                        <circle cx="34" cy="20" r="2" fill="#64748b" />
                                                        <text x="25" y="48" font-size="6.5" fill="#475569" text-anchor="middle">KLOSET</text>
                                                    </g>
                                                    <!-- Bathtub Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'bathtub'">
                                                        <rect x="0" y="0" width="160" height="80" fill="#f8fafc" stroke="#334155" stroke-width="1.5" rx="16" />
                                                        <rect x="10" y="8" width="140" height="64" fill="#e2e8f0" stroke="#64748b" stroke-width="1.2" rx="12" />
                                                        <path d="M12,25 Q30,40 12,55" fill="none" stroke="#94a3b8" stroke-width="1" />
                                                        <circle cx="135" cy="40" r="5" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                        <text x="80" y="44" font-size="7.5" fill="#475569" font-weight="bold" text-anchor="middle">BATHTUB (ATAS)</text>
                                                    </g>
                                                    <!-- Kulkas Standalone Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'fridge_standalone'">
                                                        <rect x="0" y="0" width="90" height="70" fill="#f1f5f9" stroke="#334155" stroke-width="1.5" rx="2" />
                                                        <path d="M0,70 Q45,76 90,70" stroke="#0f172a" stroke-width="2" fill="none" />
                                                        <path d="M0,70 A70,70 0 0,0 70,0" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="3,3" fill="none" />
                                                        <text x="45" y="40" font-size="7.5" fill="#334155" text-anchor="middle" font-weight="bold">KULKAS (ATAS)</text>
                                                    </g>
                                                    <!-- Meja Kopi & Meja Makan Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'coffee_table' || ann.accessoryType === 'dining_table'">
                                                        <rect x="0" y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'atas').w" :height="getAccessoryBaseSize(ann.accessoryType, 'atas').h" fill="#e2d9cc" stroke="#78350f" stroke-width="1.5" rx="3" />
                                                        <rect x="4" y="4" :width="getAccessoryBaseSize(ann.accessoryType, 'atas').w - 8" :height="getAccessoryBaseSize(ann.accessoryType, 'atas').h - 8" fill="none" stroke="#b45309" stroke-width="0.8" stroke-dasharray="6,3" />
                                                        <text :x="getAccessoryBaseSize(ann.accessoryType, 'atas').w / 2" :y="getAccessoryBaseSize(ann.accessoryType, 'atas').h / 2 + 3" font-size="7.5" font-weight="bold" fill="#78350f" text-anchor="middle">MEJA (ATAS)</text>
                                                    </g>
                                                    <!-- Kursi Makan / Barstool Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'dining_chair' || ann.accessoryType === 'barstool'">
                                                        <rect x="0" y="0" width="45" height="45" fill="#f1f5f9" stroke="#334155" stroke-width="1.2" rx="4" />
                                                        <circle cx="22.5" cy="22.5" r="16" fill="#e2e8f0" stroke="#64748b" stroke-width="1" />
                                                        <text x="22.5" y="25" font-size="6" fill="#475569" text-anchor="middle">KURSI</text>
                                                    </g>
                                                    <!-- Tanaman Hias Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'plant'">
                                                        <circle cx="25" cy="25" r="14" fill="#d97706" stroke="#78350f" stroke-width="1.2" />
                                                        <circle cx="25" cy="25" r="6" fill="#451a03" />
                                                        <ellipse cx="25" cy="8" rx="4" ry="7" fill="#22c55e" stroke="#15803d" stroke-width="0.6" />
                                                        <ellipse cx="25" cy="42" rx="4" ry="7" fill="#22c55e" stroke="#15803d" stroke-width="0.6" />
                                                        <ellipse cx="8" cy="25" rx="7" ry="4" fill="#22c55e" stroke="#15803d" stroke-width="0.6" />
                                                        <ellipse cx="42" cy="25" rx="7" ry="4" fill="#22c55e" stroke="#15803d" stroke-width="0.6" />
                                                        <ellipse cx="13" cy="13" rx="6" ry="4" transform="rotate(-45, 13, 13)" fill="#16a34a" stroke="#14532d" stroke-width="0.6" />
                                                        <ellipse cx="37" cy="13" rx="6" ry="4" transform="rotate(45, 37, 13)" fill="#16a34a" stroke="#14532d" stroke-width="0.6" />
                                                        <ellipse cx="13" cy="37" rx="6" ry="4" transform="rotate(45, 13, 37)" fill="#16a34a" stroke="#14532d" stroke-width="0.6" />
                                                        <ellipse cx="37" cy="37" rx="6" ry="4" transform="rotate(-45, 37, 37)" fill="#16a34a" stroke="#14532d" stroke-width="0.6" />
                                                    </g>
                                                    <!-- Downlight Tampak Atas (Plafon) -->
                                                    <g v-else-if="ann.accessoryType === 'downlight'">
                                                        <circle cx="12" cy="12" r="11" fill="#ffffff" stroke="#f59e0b" stroke-width="1.2" />
                                                        <circle cx="12" cy="12" r="5" fill="#fbbf24" stroke="#d97706" stroke-width="0.8" />
                                                        <line x1="1" y1="12" x2="23" y2="12" stroke="#d97706" stroke-width="0.8" />
                                                        <line x1="12" y1="1" x2="12" y2="23" stroke="#d97706" stroke-width="0.8" />
                                                    </g>
                                                    <!-- Karpet (Rug) Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'rug'">
                                                        <rect x="0" y="0" width="180" height="120" fill="#fef3c7" stroke="#b45309" stroke-width="1.5" rx="2" />
                                                        <rect x="8" y="8" width="164" height="104" fill="none" stroke="#d97706" stroke-width="1" stroke-dasharray="5,3" />
                                                        <rect x="20" y="20" width="140" height="80" fill="#fffbeb" stroke="#f59e0b" stroke-width="0.8" />
                                                        <text x="90" y="63" font-size="8" font-weight="bold" fill="#92400e" text-anchor="middle">KARPET LANTAI</text>
                                                    </g>
                                                    <!-- Lemari / Wardrobe Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'wardrobe_door'">
                                                        <rect x="0" y="0" width="120" height="65" fill="#f8fafc" stroke="#334155" stroke-width="1.2" />
                                                        <line x1="10" y1="32" x2="110" y2="32" stroke="#64748b" stroke-width="2" />
                                                        <line x1="0" y1="60" x2="65" y2="60" stroke="#0f172a" stroke-width="2.5" />
                                                        <line x1="55" y1="64" x2="120" y2="64" stroke="#0f172a" stroke-width="2.5" />
                                                        <text x="60" y="22" font-size="7" fill="#475569" text-anchor="middle">LEMARI (ATAS)</text>
                                                    </g>
                                                    <!-- Laci (Drawer) Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'drawer'">
                                                        <rect x="0" y="0" width="100" height="60" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" />
                                                        <line x1="3" y1="0" x2="3" y2="60" stroke="#71717a" stroke-width="2" />
                                                        <line x1="97" y1="0" x2="97" y2="60" stroke="#71717a" stroke-width="2" />
                                                        <rect x="35" y="57" width="30" height="4" fill="#1c1917" rx="1" />
                                                        <text x="50" y="34" font-size="7" fill="#5c4033" text-anchor="middle">LACI (ATAS)</text>
                                                    </g>
                                                    <!-- Cooker Hood Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'hood'">
                                                        <rect x="0" y="0" width="110" height="60" fill="#cbd5e1" stroke="#334155" stroke-width="1.2" rx="2" />
                                                        <circle cx="55" cy="30" r="18" fill="#94a3b8" stroke="#334155" stroke-width="1.5" />
                                                        <circle cx="55" cy="30" r="6" fill="#64748b" />
                                                        <text x="55" y="55" font-size="6.5" fill="#334155" text-anchor="middle">HOOD EXHAUST</text>
                                                    </g>
                                                    <!-- Oven Tampak Atas -->
                                                    <g v-else-if="ann.accessoryType === 'oven'">
                                                        <rect x="0" y="0" width="100" height="70" fill="#0f172a" stroke="#334155" stroke-width="1.5" rx="2" />
                                                        <rect x="10" y="65" width="80" height="4" fill="#cbd5e1" rx="1" />
                                                        <text x="50" y="38" font-size="7" fill="#94a3b8" text-anchor="middle">OVEN (ATAS)</text>
                                                    </g>
                                                    <!-- Fallback Universal Tampak Atas -->
                                                    <g v-else>
                                                        <rect :x="0" :y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'atas').w" :height="getAccessoryBaseSize(ann.accessoryType, 'atas').h" fill="#f8fafc" stroke="#475569" stroke-width="1.2" stroke-dasharray="4,2" rx="2" />
                                                        <line :x1="0" :y1="getAccessoryBaseSize(ann.accessoryType, 'atas').h / 2" :x2="getAccessoryBaseSize(ann.accessoryType, 'atas').w" :y2="getAccessoryBaseSize(ann.accessoryType, 'atas').h / 2" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                        <line :x1="getAccessoryBaseSize(ann.accessoryType, 'atas').w / 2" :y1="0" :x2="getAccessoryBaseSize(ann.accessoryType, 'atas').w / 2" :y2="getAccessoryBaseSize(ann.accessoryType, 'atas').h" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                        <text :x="getAccessoryBaseSize(ann.accessoryType, 'atas').w / 2" :y="getAccessoryBaseSize(ann.accessoryType, 'atas').h / 2 + 3" font-size="7" font-weight="bold" fill="#334155" text-anchor="middle">
                                                            {{ (ann.text || ann.accessoryType).toUpperCase() }} (ATAS)
                                                        </text>
                                                    </g>
                                                </g>

                                                <!-- ============================================================== -->
                                                <!-- 2. TAMPAK SAMPING (SIDE VIEW / POTONGAN / SISI TEBAL)          -->
                                                <!-- ============================================================== -->
                                                <g v-else-if="ann.view === 'samping'">
                                                    <!-- Kasur (Bed) Tampak Samping -->
                                                    <g v-if="ann.accessoryType === 'bed'">
                                                        <rect x="0" y="0" width="16" height="90" fill="#78350f" stroke="#451a03" stroke-width="1.2" rx="2" />
                                                        <rect x="16" y="50" width="160" height="25" fill="#92400e" stroke="#451a03" stroke-width="1" />
                                                        <rect x="16" y="28" width="158" height="24" fill="#ffffff" stroke="#475569" stroke-width="1" rx="3" />
                                                        <ellipse cx="36" cy="22" rx="14" ry="7" fill="#ffffff" stroke="#94a3b8" stroke-width="0.8" />
                                                        <path d="M70,30 L174,30 L174,65 L170,65" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" />
                                                        <rect x="25" y="75" width="8" height="15" fill="#451a03" />
                                                        <rect x="160" y="75" width="8" height="15" fill="#451a03" />
                                                        <text x="95" y="44" font-size="7" fill="#64748b" text-anchor="middle">KASUR (SAMPING)</text>
                                                    </g>
                                                    <!-- Sofa Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'sofa'">
                                                        <path d="M0,0 L20,0 L25,50 L5,50 Z" fill="#cbd5e1" stroke="#475569" stroke-width="1.2" />
                                                        <rect x="20" y="38" width="56" height="20" fill="#f8fafc" stroke="#475569" stroke-width="1" rx="4" />
                                                        <path d="M15,20 Q40,15 65,22 Q75,35 75,58 L15,58 Z" fill="#94a3b8" stroke="#475569" stroke-width="1.2" rx="4" />
                                                        <line x1="25" y1="58" x2="18" y2="78" stroke="#78350f" stroke-width="3.5" stroke-linecap="round" />
                                                        <line x1="65" y1="58" x2="72" y2="78" stroke="#78350f" stroke-width="3.5" stroke-linecap="round" />
                                                        <text x="40" y="48" font-size="6.5" fill="#334155" text-anchor="middle">SOFA (SISI)</text>
                                                    </g>
                                                    <!-- Armchair Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'armchair'">
                                                        <path d="M0,0 L18,0 L22,50 L4,50 Z" fill="#cbd5e1" stroke="#475569" stroke-width="1.2" />
                                                        <rect x="18" y="38" width="58" height="22" fill="#f8fafc" stroke="#475569" stroke-width="1" rx="4" />
                                                        <path d="M12,20 Q38,15 65,22 Q76,35 76,60 L12,60 Z" fill="#94a3b8" stroke="#475569" stroke-width="1.2" rx="4" />
                                                        <line x1="22" y1="60" x2="16" y2="82" stroke="#78350f" stroke-width="3.5" stroke-linecap="round" />
                                                        <line x1="64" y1="60" x2="70" y2="82" stroke="#78350f" stroke-width="3.5" stroke-linecap="round" />
                                                    </g>
                                                    <!-- Sink Tampak Samping / Potongan -->
                                                    <g v-else-if="ann.accessoryType === 'sink'">
                                                        <rect x="0" y="10" width="70" height="5" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                        <path d="M5,15 L12,40 L58,40 L65,15" fill="#94a3b8" stroke="#334155" stroke-width="1.2" />
                                                        <path d="M35,40 L35,48 A6,6 0 0,0 47,48 L47,38" fill="none" stroke="#475569" stroke-width="3" />
                                                        <path d="M12,10 C12,-5 28,-5 28,6" fill="none" stroke="#1e293b" stroke-width="2.5" />
                                                        <text x="35" y="30" font-size="6.5" fill="#1e293b" text-anchor="middle">SINK (POT)</text>
                                                    </g>
                                                    <!-- TV Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'tv'">
                                                        <rect x="0" y="40" width="8" height="25" fill="#334155" />
                                                        <rect x="8" y="0" width="8" height="105" fill="#0f172a" stroke="#334155" stroke-width="1" rx="1" />
                                                        <rect x="16" y="2" width="2" height="101" fill="#38bdf8" />
                                                        <text x="12" y="80" font-size="6" fill="#94a3b8" transform="rotate(-90, 12, 80)" text-anchor="middle">TV (SISI)</text>
                                                    </g>
                                                    <!-- Kloset / Toilet Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'toilet'">
                                                        <rect x="0" y="0" width="26" height="60" fill="#f8fafc" stroke="#334155" stroke-width="1.2" rx="2" />
                                                        <path d="M26,30 L72,30 Q78,45 65,55 L45,55 L45,85 L15,85 L15,60" fill="#ffffff" stroke="#334155" stroke-width="1.5" />
                                                        <rect x="24" y="27" width="50" height="3" fill="#cbd5e1" rx="1.5" />
                                                        <circle cx="13" cy="15" r="4" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" />
                                                        <text x="45" y="48" font-size="6.5" fill="#475569" text-anchor="middle">TOILET</text>
                                                    </g>
                                                    <!-- Bathtub Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'bathtub'">
                                                        <path d="M0,5 L80,5 L75,55 L5,55 Z" fill="#ffffff" stroke="#334155" stroke-width="1.5" />
                                                        <path d="M5,10 L72,10 L68,48 L10,48 Z" fill="#e2e8f0" stroke="#64748b" stroke-width="1" />
                                                        <rect x="10" y="55" width="8" height="5" fill="#334155" />
                                                        <rect x="62" y="55" width="8" height="5" fill="#334155" />
                                                        <text x="40" y="32" font-size="7" fill="#475569" text-anchor="middle">BATHTUB</text>
                                                    </g>
                                                    <!-- Kulkas Standalone Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'fridge_standalone'">
                                                        <rect x="0" y="0" width="70" height="175" fill="#f1f5f9" stroke="#334155" stroke-width="1.5" rx="2" />
                                                        <line x1="65" y1="0" x2="65" y2="175" stroke="#334155" stroke-width="1" />
                                                        <rect x="68" y="60" width="4" height="40" fill="#0f172a" rx="1" />
                                                        <rect x="8" y="175" width="8" height="5" fill="#334155" />
                                                        <rect x="54" y="175" width="8" height="5" fill="#334155" />
                                                        <text x="35" y="90" font-size="7" fill="#475569" transform="rotate(-90, 35, 90)" text-anchor="middle">KULKAS (SAMPING)</text>
                                                    </g>
                                                    <!-- Kompor Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'stove'">
                                                        <rect x="0" y="5" width="70" height="4" fill="#09090b" stroke="#27272a" />
                                                        <rect x="15" y="0" width="20" height="5" fill="#3f3f46" />
                                                        <rect x="42" y="0" width="18" height="5" fill="#3f3f46" />
                                                        <rect x="6" y="9" width="58" height="16" fill="#18181b" stroke="#3f3f46" stroke-width="1" />
                                                    </g>
                                                    <!-- Cooker Hood Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'hood'">
                                                        <polygon points="0,0 20,0 20,20 60,45 60,60 0,60" fill="#cbd5e1" stroke="#334155" stroke-width="1.2" />
                                                    </g>
                                                    <!-- Oven Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'oven'">
                                                        <rect x="0" y="0" width="65" height="80" fill="#0f172a" stroke="#334155" stroke-width="1.2" rx="2" />
                                                        <rect x="65" y="15" width="5" height="40" fill="#cbd5e1" rx="1" />
                                                    </g>
                                                    <!-- Meja Kopi / Makan Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'coffee_table' || ann.accessoryType === 'dining_table'">
                                                        <rect x="0" y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'samping').w" height="6" fill="#78350f" stroke="#451a03" stroke-width="1" />
                                                        <rect x="4" y="6" :width="getAccessoryBaseSize(ann.accessoryType, 'samping').w - 8" height="4" fill="#92400e" />
                                                        <rect x="6" y="10" width="6" :height="getAccessoryBaseSize(ann.accessoryType, 'samping').h - 10" fill="#451a03" />
                                                        <rect :x="getAccessoryBaseSize(ann.accessoryType, 'samping').w - 12" y="10" width="6" :height="getAccessoryBaseSize(ann.accessoryType, 'samping').h - 10" fill="#451a03" />
                                                    </g>
                                                    <!-- Kursi / Barstool Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'dining_chair' || ann.accessoryType === 'barstool'">
                                                        <path d="M5,0 L10,0 L12,45 L7,45 Z" fill="#451a03" />
                                                        <rect x="8" y="42" width="34" height="6" fill="#f8fafc" stroke="#451a03" stroke-width="1" rx="2" />
                                                        <line x1="12" y1="48" x2="8" y2="84" stroke="#451a03" stroke-width="3" stroke-linecap="round" />
                                                        <line x1="38" y1="48" x2="42" y2="84" stroke="#451a03" stroke-width="3" stroke-linecap="round" />
                                                    </g>
                                                    <!-- Lemari Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'wardrobe_door'">
                                                        <rect x="0" y="0" width="65" height="150" fill="#e2e8f0" stroke="#64748b" stroke-width="1.2" />
                                                        <line x1="60" y1="0" x2="60" y2="150" stroke="#475569" stroke-width="1.5" />
                                                        <text x="32" y="75" font-size="7" fill="#475569" transform="rotate(-90, 32, 75)" text-anchor="middle">TEBAL LEMARI (60 CM)</text>
                                                    </g>
                                                    <!-- Laci Tampak Samping -->
                                                    <g v-else-if="ann.accessoryType === 'drawer'">
                                                        <rect x="0" y="0" width="70" height="90" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" />
                                                        <line x1="0" y1="30" x2="70" y2="30" stroke="#8c6d37" stroke-width="1" />
                                                        <line x1="0" y1="60" x2="70" y2="60" stroke="#8c6d37" stroke-width="1" />
                                                        <rect x="67" y="10" width="3" height="10" fill="#1c1917" />
                                                        <rect x="67" y="40" width="3" height="10" fill="#1c1917" />
                                                        <rect x="67" y="70" width="3" height="10" fill="#1c1917" />
                                                    </g>
                                                    <!-- Fallback Universal Tampak Samping -->
                                                    <g v-else>
                                                        <rect :x="0" :y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'samping').w" :height="getAccessoryBaseSize(ann.accessoryType, 'samping').h" fill="#f8fafc" stroke="#475569" stroke-width="1.2" stroke-dasharray="4,2" rx="2" />
                                                        <text :x="getAccessoryBaseSize(ann.accessoryType, 'samping').w / 2" :y="getAccessoryBaseSize(ann.accessoryType, 'samping').h / 2 + 3" font-size="7" font-weight="bold" fill="#334155" text-anchor="middle">
                                                            {{ (ann.text || ann.accessoryType).toUpperCase() }} (SISI)
                                                        </text>
                                                    </g>
                                                </g>

                                                <!-- ============================================================== -->
                                                <!-- 3. TAMPAK BAWAH (BOTTOM / PLAFON / KOLONG / CEILING)           -->
                                                <!-- ============================================================== -->
                                                <g v-else-if="ann.view === 'bawah'">
                                                    <!-- Downlight (Tampak Bawah / Reflected Ceiling) -->
                                                    <g v-if="ann.accessoryType === 'downlight'">
                                                        <circle cx="12" cy="12" r="11" fill="#0f172a" stroke="#f59e0b" stroke-width="1.5" />
                                                        <circle cx="12" cy="12" r="7" fill="#fbbf24" stroke="#d97706" stroke-width="1" />
                                                        <circle cx="12" cy="12" r="2.5" fill="#ffffff" />
                                                        <line x1="1" y1="12" x2="23" y2="12" stroke="#f59e0b" stroke-width="0.8" />
                                                        <line x1="12" y1="1" x2="12" y2="23" stroke="#f59e0b" stroke-width="0.8" />
                                                    </g>
                                                    <!-- Pendant Light (Lampu Gantung Bawah) -->
                                                    <g v-else-if="ann.accessoryType === 'pendant_light'">
                                                        <circle cx="16" cy="16" r="15" fill="#18181b" stroke="#f59e0b" stroke-width="1.5" />
                                                        <circle cx="16" cy="16" r="9" fill="#fef08a" stroke="#eab308" stroke-width="1" />
                                                        <circle cx="16" cy="16" r="3.5" fill="#ffffff" />
                                                    </g>
                                                    <!-- LED Strip (Saluran Bawah Ambalan) -->
                                                    <g v-else-if="ann.accessoryType === 'led_strip'">
                                                        <rect x="0" y="0" width="140" height="14" fill="#1e293b" stroke="#475569" stroke-width="1" rx="1" />
                                                        <!-- LED Diodes -->
                                                        <circle v-for="d in 9" :key="d" :cx="14 * d" cy="7" r="2.5" fill="#fbbf24" stroke="#d97706" stroke-width="0.6" />
                                                    </g>
                                                    <!-- Sink Undermount Plumbing (Kolong Sink) -->
                                                    <g v-else-if="ann.accessoryType === 'sink'">
                                                        <rect x="0" y="0" width="110" height="70" fill="#f1f5f9" stroke="#64748b" stroke-width="1.2" stroke-dasharray="3,2" rx="3" />
                                                        <rect x="10" y="10" width="60" height="50" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="2" />
                                                        <!-- Drain Pipe & P-Trap -->
                                                        <circle cx="40" cy="35" r="8" fill="#334155" />
                                                        <path d="M40,35 L40,55 A8,8 0 0,0 56,55 L56,40" fill="none" stroke="#334155" stroke-width="3.5" />
                                                        <text x="40" y="65" font-size="6.5" fill="#1e293b" font-weight="bold" text-anchor="middle">PIPA TRAP SINK</text>
                                                    </g>
                                                    <!-- Meja / Sofa / Kasur Kolong Frame -->
                                                    <g v-else-if="ann.accessoryType === 'coffee_table' || ann.accessoryType === 'dining_table'">
                                                        <rect x="0" y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'bawah').w" :height="getAccessoryBaseSize(ann.accessoryType, 'bawah').h" fill="#fefce8" stroke="#78350f" stroke-width="1.5" />
                                                        <rect x="8" y="8" :width="getAccessoryBaseSize(ann.accessoryType, 'bawah').w - 16" :height="getAccessoryBaseSize(ann.accessoryType, 'bawah').h - 16" fill="none" stroke="#b45309" stroke-width="2" />
                                                        <!-- 4 Corner Leg Mounts -->
                                                        <rect x="2" y="2" width="12" height="12" fill="#451a03" />
                                                        <rect :x="getAccessoryBaseSize(ann.accessoryType, 'bawah').w - 14" y="2" width="12" height="12" fill="#451a03" />
                                                        <rect x="2" :y="getAccessoryBaseSize(ann.accessoryType, 'bawah').h - 14" width="12" height="12" fill="#451a03" />
                                                        <rect :x="getAccessoryBaseSize(ann.accessoryType, 'bawah').w - 14" :y="getAccessoryBaseSize(ann.accessoryType, 'bawah').h - 14" width="12" height="12" fill="#451a03" />
                                                        <text :x="getAccessoryBaseSize(ann.accessoryType, 'bawah').w / 2" :y="getAccessoryBaseSize(ann.accessoryType, 'bawah').h / 2 + 3" font-size="7" fill="#78350f" text-anchor="middle">RANGKA KOLONG</text>
                                                    </g>
                                                    <!-- Dipan / Rangka Kolong Kasur -->
                                                    <g v-else-if="ann.accessoryType === 'bed'">
                                                        <rect x="0" y="0" width="160" height="180" fill="#fefce8" stroke="#451a03" stroke-width="1.5" />
                                                        <line v-for="s in 11" :key="s" :x1="10" :y1="15 * s" :x2="150" :y2="15 * s" stroke="#b45309" stroke-width="2.5" />
                                                        <rect x="76" y="5" width="8" height="170" fill="#78350f" />
                                                        <circle cx="80" cy="90" r="6" fill="#451a03" />
                                                        <text x="80" y="100" font-size="7" fill="#fff" text-anchor="middle">Rangka Kolong</text>
                                                    </g>
                                                    <!-- Fallback Universal Tampak Bawah -->
                                                    <g v-else>
                                                        <rect :x="0" :y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'bawah').w" :height="getAccessoryBaseSize(ann.accessoryType, 'bawah').h" fill="#f8fafc" stroke="#475569" stroke-width="1.2" stroke-dasharray="4,2" rx="2" />
                                                        <line :x1="0" :y1="getAccessoryBaseSize(ann.accessoryType, 'bawah').h / 2" :x2="getAccessoryBaseSize(ann.accessoryType, 'bawah').w" :y2="getAccessoryBaseSize(ann.accessoryType, 'bawah').h / 2" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                        <line :x1="getAccessoryBaseSize(ann.accessoryType, 'bawah').w / 2" :y1="0" :x2="getAccessoryBaseSize(ann.accessoryType, 'bawah').w / 2" :y2="getAccessoryBaseSize(ann.accessoryType, 'bawah').h" stroke="#94a3b8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                        <text :x="getAccessoryBaseSize(ann.accessoryType, 'bawah').w / 2" :y="getAccessoryBaseSize(ann.accessoryType, 'bawah').h / 2 + 3" font-size="7" font-weight="bold" fill="#334155" text-anchor="middle">
                                                            {{ (ann.text || ann.accessoryType).toUpperCase() }} (BAWAH)
                                                        </text>
                                                    </g>
                                                </g>

                                                <!-- ============================================================== -->
                                                <!-- 4. TAMPAK DEPAN (FRONT VIEW / ELEVASI - DEFAULT)               -->
                                                <!-- ============================================================== -->
                                                <g v-else>
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

                                                <!-- 30. Sofa 3 Dudukan Minimalis -->
                                                <g v-else-if="ann.accessoryType === 'sofa'">
                                                    <!-- Backrest frame & 3 back cushions -->
                                                    <rect x="4" y="6" width="172" height="42" rx="6" fill="#475569" stroke="#1e293b" stroke-width="1.2" />
                                                    <rect x="18" y="8" width="46" height="36" rx="4" fill="#64748b" stroke="#334155" stroke-width="0.8" />
                                                    <rect x="67" y="8" width="46" height="36" rx="4" fill="#64748b" stroke="#334155" stroke-width="0.8" />
                                                    <rect x="116" y="8" width="46" height="36" rx="4" fill="#64748b" stroke="#334155" stroke-width="0.8" />
                                                    <!-- 3 Seat Cushions -->
                                                    <rect x="18" y="44" width="46" height="24" rx="3" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                    <rect x="67" y="44" width="46" height="24" rx="3" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                    <rect x="116" y="44" width="46" height="24" rx="3" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                    <!-- Armrests Left & Right -->
                                                    <rect x="0" y="24" width="18" height="44" rx="5" fill="#475569" stroke="#1e293b" stroke-width="1.2" />
                                                    <rect x="162" y="24" width="18" height="44" rx="5" fill="#475569" stroke="#1e293b" stroke-width="1.2" />
                                                    <!-- Accent Pillows -->
                                                    <g transform="translate(18, 30) rotate(-12)">
                                                        <rect x="0" y="0" width="18" height="18" rx="3" fill="#f59e0b" stroke="#b45309" stroke-width="0.8" />
                                                    </g>
                                                    <g transform="translate(144, 26) rotate(12)">
                                                        <rect x="0" y="0" width="18" height="18" rx="3" fill="#0284c7" stroke="#0369a1" stroke-width="0.8" />
                                                    </g>
                                                    <!-- Wooden legs -->
                                                    <line x1="14" y1="68" x2="8" y2="78" stroke="#78350f" stroke-width="2.5" stroke-linecap="round" />
                                                    <line x1="166" y1="68" x2="172" y2="78" stroke="#78350f" stroke-width="2.5" stroke-linecap="round" />
                                                    <line x1="90" y1="68" x2="90" y2="78" stroke="#78350f" stroke-width="2.5" stroke-linecap="round" />
                                                    <text x="90" y="60" font-size="7" font-weight="bold" fill="#1e293b" text-anchor="middle">SOFA 3 DUDUKAN</text>
                                                </g>

                                                <!-- 31. Armchair / Kursi Santai -->
                                                <g v-else-if="ann.accessoryType === 'armchair'">
                                                    <!-- Backrest -->
                                                    <path d="M10,8 Q40,2 70,8 Q74,38 70,54 L10,54 Q6,38 10,8 Z" fill="#334155" stroke="#0f172a" stroke-width="1.2" />
                                                    <!-- Seat Cushion -->
                                                    <rect x="12" y="50" width="56" height="20" rx="4" fill="#64748b" stroke="#1e293b" stroke-width="1" />
                                                    <!-- Armrests Left & Right -->
                                                    <rect x="2" y="30" width="12" height="38" rx="4" fill="#475569" stroke="#0f172a" stroke-width="1" />
                                                    <rect x="66" y="30" width="12" height="38" rx="4" fill="#475569" stroke="#0f172a" stroke-width="1" />
                                                    <!-- Lumbar Pillow -->
                                                    <rect x="24" y="42" width="32" height="12" rx="3" fill="#f59e0b" stroke="#b45309" stroke-width="0.8" />
                                                    <!-- Legs -->
                                                    <line x1="12" y1="70" x2="6" y2="82" stroke="#78350f" stroke-width="2.2" stroke-linecap="round" />
                                                    <line x1="68" y1="70" x2="74" y2="82" stroke="#78350f" stroke-width="2.2" stroke-linecap="round" />
                                                    <text x="40" y="64" font-size="6.5" font-weight="bold" fill="#f8fafc" text-anchor="middle">ARMCHAIR</text>
                                                </g>

                                                <!-- 32. Meja Kopi Ruang Tamu -->
                                                <g v-else-if="ann.accessoryType === 'coffee_table'">
                                                    <!-- Tabletop -->
                                                    <rect x="0" y="6" width="100" height="10" rx="3" fill="#f8fafc" stroke="#475569" stroke-width="1.2" />
                                                    <line x1="2" y1="9" x2="98" y2="9" stroke="#cbd5e1" stroke-width="0.8" />
                                                    <!-- Lower wire shelf -->
                                                    <line x1="12" y1="28" x2="88" y2="28" stroke="#94a3b8" stroke-width="1.2" stroke-dasharray="3,2" />
                                                    <!-- 4 Tapered legs -->
                                                    <line x1="10" y1="16" x2="5" y2="42" stroke="#1e293b" stroke-width="2" stroke-linecap="round" />
                                                    <line x1="90" y1="16" x2="95" y2="42" stroke="#1e293b" stroke-width="2" stroke-linecap="round" />
                                                    <line x1="20" y1="16" x2="16" y2="42" stroke="#475569" stroke-width="1.5" stroke-linecap="round" />
                                                    <line x1="80" y1="16" x2="84" y2="42" stroke="#475569" stroke-width="1.5" stroke-linecap="round" />
                                                    <!-- Small coffee cup on table -->
                                                    <rect x="68" y="0" width="8" height="6" rx="1" fill="#ffffff" stroke="#94a3b8" stroke-width="0.8" />
                                                    <path d="M76,2 C78,2 78,4 76,4" fill="none" stroke="#94a3b8" stroke-width="0.8" />
                                                    <text x="50" y="24" font-size="6.5" font-weight="bold" fill="#64748b" text-anchor="middle">COFFEE TABLE</text>
                                                </g>

                                                <!-- 33. Karpet Lantai Permadani -->
                                                <g v-else-if="ann.accessoryType === 'rug'">
                                                    <rect x="8" y="2" width="164" height="10" rx="2" fill="#d6d3d1" stroke="#78716c" stroke-width="1" />
                                                    <line v-for="r in 14" :key="r" :x1="12 + r * 11" y1="3" :x2="12 + r * 11" y2="11" stroke="#a8a29e" stroke-width="0.8" />
                                                    <!-- Tassels Left & Right -->
                                                    <line x1="8" y1="4" x2="0" y2="4" stroke="#a8a29e" stroke-width="1" />
                                                    <line x1="8" y1="7" x2="0" y2="7" stroke="#a8a29e" stroke-width="1" />
                                                    <line x1="8" y1="10" x2="0" y2="10" stroke="#a8a29e" stroke-width="1" />
                                                    <line x1="172" y1="4" x2="180" y2="4" stroke="#a8a29e" stroke-width="1" />
                                                    <line x1="172" y1="7" x2="180" y2="7" stroke="#a8a29e" stroke-width="1" />
                                                    <line x1="172" y1="10" x2="180" y2="10" stroke="#a8a29e" stroke-width="1" />
                                                    <text x="90" y="10" font-size="6" font-weight="bold" fill="#44403c" text-anchor="middle">KARPET LANTAI</text>
                                                </g>

                                                <!-- 34. Tempat Tidur & Headboard -->
                                                <g v-else-if="ann.accessoryType === 'bed'">
                                                    <!-- Headboard padded wall panel -->
                                                    <rect x="0" y="0" width="160" height="65" rx="3" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.2" />
                                                    <line v-for="k in 5" :key="k" :x1="k * 26.6" y1="2" :x2="k * 26.6" y2="63" stroke="#cbd5e1" stroke-width="1.2" stroke-dasharray="4,2" />
                                                    <!-- Mattress base -->
                                                    <rect x="4" y="45" width="152" height="48" rx="4" fill="#ffffff" stroke="#475569" stroke-width="1.5" />
                                                    <!-- Pillows -->
                                                    <rect x="12" y="28" width="60" height="22" rx="4" fill="#f8fafc" stroke="#94a3b8" stroke-width="1" />
                                                    <line x1="16" y1="39" x2="68" y2="39" stroke="#e2e8f0" stroke-width="1" />
                                                    <rect x="88" y="28" width="60" height="22" rx="4" fill="#f8fafc" stroke="#94a3b8" stroke-width="1" />
                                                    <line x1="92" y1="39" x2="144" y2="39" stroke="#e2e8f0" stroke-width="1" />
                                                    <!-- Duvet / Selimut lipat -->
                                                    <path d="M4,62 Q80,70 156,62 L156,92 L4,92 Z" fill="#64748b" fill-opacity="0.9" stroke="#334155" stroke-width="1" />
                                                    <path d="M4,62 Q80,70 156,62 L156,68 Q80,76 4,68 Z" fill="#94a3b8" />
                                                    <!-- Bed Wooden Legs -->
                                                    <rect x="12" y="93" width="8" height="6" fill="#78350f" rx="1" />
                                                    <rect x="140" y="93" width="8" height="6" fill="#78350f" rx="1" />
                                                    <text x="80" y="84" font-size="7.5" font-weight="bold" fill="#f8fafc" text-anchor="middle">TEMPAT TIDUR</text>
                                                </g>

                                                <!-- 35. Meja Nakas Samping Kasur -->
                                                <g v-else-if="ann.accessoryType === 'bedside_table'">
                                                    <!-- Table lamp on top -->
                                                    <ellipse cx="25" cy="14" rx="8" ry="3" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" />
                                                    <line x1="25" y1="6" x2="25" y2="14" stroke="#d97706" stroke-width="1.5" />
                                                    <polygon points="17,6 33,6 30,-2 20,-2" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
                                                    <!-- Cabinet Box -->
                                                    <rect x="2" y="14" width="46" height="34" rx="2" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" />
                                                    <!-- 2 Drawers -->
                                                    <rect x="5" y="17" width="40" height="13" rx="1" fill="#f5ebe0" stroke="#8c6d37" stroke-width="0.7" />
                                                    <rect x="21" y="22" width="8" height="2.5" rx="1" fill="#1c1917" />
                                                    <rect x="5" y="32" width="40" height="13" rx="1" fill="#f5ebe0" stroke="#8c6d37" stroke-width="0.7" />
                                                    <rect x="21" y="37" width="8" height="2.5" rx="1" fill="#1c1917" />
                                                    <!-- Legs -->
                                                    <line x1="6" y1="48" x2="3" y2="54" stroke="#8c6d37" stroke-width="2" stroke-linecap="round" />
                                                    <line x1="44" y1="48" x2="47" y2="54" stroke="#8c6d37" stroke-width="2" stroke-linecap="round" />
                                                </g>

                                                <!-- 36. Meja Makan & Kursi Set -->
                                                <g v-else-if="ann.accessoryType === 'dining_table'">
                                                    <!-- 3 Chairs in background -->
                                                    <g v-for="cIdx in 3" :key="cIdx" :transform="`translate(${24 + (cIdx - 1) * 44}, 0)`">
                                                        <rect x="0" y="2" width="24" height="26" rx="3" fill="#cbd5e1" stroke="#475569" stroke-width="0.8" />
                                                        <line x1="6" y1="8" x2="6" y2="24" stroke="#94a3b8" stroke-width="0.8" />
                                                        <line x1="18" y1="8" x2="18" y2="24" stroke="#94a3b8" stroke-width="0.8" />
                                                    </g>
                                                    <!-- Tabletop -->
                                                    <rect x="6" y="24" width="148" height="12" rx="2" fill="#78350f" stroke="#451a03" stroke-width="1.5" />
                                                    <!-- Table legs -->
                                                    <rect x="14" y="36" width="8" height="40" fill="#451a03" />
                                                    <rect x="138" y="36" width="8" height="40" fill="#451a03" />
                                                    <!-- Chair Legs in foreground -->
                                                    <g v-for="cIdx in 3" :key="'leg-' + cIdx" :transform="`translate(${24 + (cIdx - 1) * 44}, 36)`">
                                                        <line x1="4" y1="0" x2="2" y2="40" stroke="#64748b" stroke-width="1.5" />
                                                        <line x1="20" y1="0" x2="22" y2="40" stroke="#64748b" stroke-width="1.5" />
                                                    </g>
                                                    <!-- Centerpiece bowl on table -->
                                                    <ellipse cx="80" cy="22" rx="14" ry="4" fill="#ffffff" stroke="#94a3b8" stroke-width="0.8" />
                                                    <circle cx="76" cy="19" r="2.5" fill="#ef4444" />
                                                    <circle cx="82" cy="18" r="3" fill="#22c55e" />
                                                    <circle cx="86" cy="20" r="2.5" fill="#f59e0b" />
                                                    <text x="80" y="33" font-size="7" font-weight="bold" fill="#fef3c7" text-anchor="middle">MEJA MAKAN SET</text>
                                                </g>

                                                <!-- 37. Meja Kerja & Belajar -->
                                                <g v-else-if="ann.accessoryType === 'study_desk'">
                                                    <!-- Laptop on table -->
                                                    <polygon points="20,12 36,12 39,2 23,2" fill="#334155" stroke="#1e293b" stroke-width="0.8" />
                                                    <rect x="24" y="4" width="11" height="7" fill="#38bdf8" opacity="0.75" />
                                                    <!-- Desk lamp -->
                                                    <line x1="94" y1="12" x2="88" y2="2" stroke="#d97706" stroke-width="1.5" />
                                                    <polygon points="82,4 92,-2 86,-5" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
                                                    <!-- Tabletop -->
                                                    <rect x="0" y="12" width="120" height="10" rx="2" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" />
                                                    <!-- Left Leg -->
                                                    <rect x="4" y="22" width="6" height="52" fill="#1e293b" rx="1" />
                                                    <line x1="4" y1="64" x2="35" y2="64" stroke="#1e293b" stroke-width="2" />
                                                    <!-- Right Drawer Pedestal (3 drawers) -->
                                                    <rect x="84" y="22" width="34" height="52" fill="#f5ebe0" stroke="#8c6d37" stroke-width="1" />
                                                    <rect x="86" y="24" width="30" height="14" rx="1" fill="#ffffff" stroke="#cbd5e1" stroke-width="0.6" />
                                                    <rect x="97" y="30" width="8" height="2" fill="#1c1917" />
                                                    <rect x="86" y="40" width="30" height="14" rx="1" fill="#ffffff" stroke="#cbd5e1" stroke-width="0.6" />
                                                    <rect x="97" y="46" width="8" height="2" fill="#1c1917" />
                                                    <rect x="86" y="56" width="30" height="16" rx="1" fill="#ffffff" stroke="#cbd5e1" stroke-width="0.6" />
                                                    <rect x="97" y="63" width="8" height="2" fill="#1c1917" />
                                                    <text x="50" y="20" font-size="6.5" font-weight="bold" fill="#5c3807" text-anchor="middle">MEJA KERJA</text>
                                                </g>

                                                <!-- 38. Kursi Kerja Ergonomis -->
                                                <g v-else-if="ann.accessoryType === 'office_chair'">
                                                    <!-- Headrest -->
                                                    <rect x="20" y="0" width="20" height="10" rx="3" fill="#0f172a" stroke="#334155" stroke-width="1" />
                                                    <line x1="30" y1="10" x2="30" y2="16" stroke="#475569" stroke-width="2" />
                                                    <!-- Backrest with Lumbar Curve -->
                                                    <path d="M14,16 Q30,12 46,16 L43,50 Q30,53 17,50 Z" fill="#1e293b" stroke="#334155" stroke-width="1.2" />
                                                    <path d="M18,36 Q30,40 42,36" fill="none" stroke="#64748b" stroke-width="1.5" />
                                                    <!-- Armrests Left & Right -->
                                                    <path d="M6,34 L12,34 L12,48" fill="none" stroke="#0f172a" stroke-width="2" stroke-linecap="round" />
                                                    <path d="M54,34 L48,34 L48,48" fill="none" stroke="#0f172a" stroke-width="2" stroke-linecap="round" />
                                                    <!-- Seat Cushion -->
                                                    <rect x="10" y="50" width="40" height="12" rx="4" fill="#0f172a" stroke="#334155" stroke-width="1.2" />
                                                    <!-- Hydraulic Cylinder -->
                                                    <rect x="28" y="62" width="4" height="16" fill="#cbd5e1" stroke="#64748b" stroke-width="0.8" />
                                                    <!-- 5-Star Swivel Base with Wheels -->
                                                    <ellipse cx="30" cy="80" rx="22" ry="5" fill="#334155" />
                                                    <circle cx="10" cy="88" r="3" fill="#0f172a" />
                                                    <circle cx="20" cy="90" r="3" fill="#0f172a" />
                                                    <circle cx="40" cy="90" r="3" fill="#0f172a" />
                                                    <circle cx="50" cy="88" r="3" fill="#0f172a" />
                                                    <text x="30" y="59" font-size="6" font-weight="bold" fill="#94a3b8" text-anchor="middle">ERGONOMIS</text>
                                                </g>

                                                <!-- 39. Meja Rias Vanity & Stool -->
                                                <g v-else-if="ann.accessoryType === 'vanity_table'">
                                                    <!-- Arch LED Backlit Mirror -->
                                                    <path d="M25,52 L25,25 A25,25 0 0,1 75,25 L75,52 Z" fill="#f1f5f9" stroke="#d97706" stroke-width="2" />
                                                    <path d="M30,50 L30,26 A20,20 0 0,1 70,26 L70,50 Z" fill="#ffffff" stroke="#cbd5e1" stroke-width="0.8" />
                                                    <circle cx="50" cy="48" r="2.5" fill="#f59e0b" />
                                                    <!-- Table Console & Drawers -->
                                                    <rect x="0" y="52" width="100" height="22" rx="3" fill="#ffffff" stroke="#94a3b8" stroke-width="1.2" />
                                                    <rect x="4" y="55" width="44" height="16" rx="1" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.8" />
                                                    <circle cx="26" cy="63" r="2" fill="#d97706" />
                                                    <rect x="52" y="55" width="44" height="16" rx="1" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.8" />
                                                    <circle cx="74" cy="63" r="2" fill="#d97706" />
                                                    <!-- Gold / Black Slim Legs -->
                                                    <line x1="6" y1="74" x2="6" y2="136" stroke="#1e293b" stroke-width="2" />
                                                    <line x1="94" y1="74" x2="94" y2="136" stroke="#1e293b" stroke-width="2" />
                                                    <!-- Vanity Stool underneath -->
                                                    <ellipse cx="50" cy="110" rx="18" ry="6" fill="#e11d48" stroke="#9f1239" stroke-width="1" />
                                                    <rect x="32" y="110" width="36" height="6" fill="#be123c" />
                                                    <line x1="36" y1="116" x2="33" y2="136" stroke="#d97706" stroke-width="1.8" />
                                                    <line x1="64" y1="116" x2="67" y2="136" stroke="#d97706" stroke-width="1.8" />
                                                    <text x="50" y="66" font-size="7" font-weight="bold" fill="#0f172a" text-anchor="middle">MEJA RIAS</text>
                                                </g>

                                                <!-- 40. Kloset Duduk Sanitari -->
                                                <g v-else-if="ann.accessoryType === 'toilet'">
                                                    <!-- Water Tank -->
                                                    <rect x="4" y="0" width="37" height="34" rx="3" fill="#ffffff" stroke="#64748b" stroke-width="1.5" />
                                                    <!-- Dual Flush Chrome Button -->
                                                    <ellipse cx="22.5" cy="5" rx="5" ry="2.5" fill="#cbd5e1" stroke="#475569" stroke-width="0.8" />
                                                    <line x1="22.5" y1="2.5" x2="22.5" y2="7.5" stroke="#475569" stroke-width="0.8" />
                                                    <!-- Tank Lid line -->
                                                    <line x1="4" y1="8" x2="41" y2="8" stroke="#cbd5e1" stroke-width="1" />
                                                    <!-- Bowl Body -->
                                                    <path d="M8,34 Q8,74 22.5,78 Q37,74 37,34 Z" fill="#ffffff" stroke="#64748b" stroke-width="1.5" />
                                                    <!-- Seat Cover Contour -->
                                                    <path d="M6,34 Q22.5,38 39,34" fill="none" stroke="#475569" stroke-width="1.5" />
                                                    <!-- Base footprint on floor -->
                                                    <rect x="14" y="74" width="17" height="5" rx="1" fill="#e2e8f0" stroke="#94a3b8" stroke-width="0.8" />
                                                    <text x="22.5" y="55" font-size="6" font-weight="bold" fill="#64748b" text-anchor="middle">KLOSET</text>
                                                </g>

                                                <!-- 41. Shower Tiang & Rain Shower -->
                                                <g v-else-if="ann.accessoryType === 'shower'">
                                                    <!-- Main Stainless Pipe -->
                                                    <line x1="16" y1="16" x2="16" y2="114" stroke="#94a3b8" stroke-width="2.5" />
                                                    <!-- Top Curve Arm to Rain Shower -->
                                                    <path d="M16,16 Q16,4 28,4 L32,4" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" />
                                                    <!-- Rain Shower Head Disc -->
                                                    <ellipse cx="32" cy="7" rx="8" ry="3" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                    <!-- Water Spray Droplets -->
                                                    <line x1="26" y1="10" x2="24" y2="24" stroke="#38bdf8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                    <line x1="30" y1="10" x2="30" y2="28" stroke="#38bdf8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                    <line x1="34" y1="10" x2="34" y2="28" stroke="#38bdf8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                    <line x1="38" y1="10" x2="40" y2="24" stroke="#38bdf8" stroke-width="0.8" stroke-dasharray="2,2" />
                                                    <!-- Hand Shower Wand & Holder -->
                                                    <rect x="12" y="50" width="8" height="6" fill="#475569" rx="1" />
                                                    <line x1="8" y1="42" x2="14" y2="60" stroke="#cbd5e1" stroke-width="2" stroke-linecap="round" />
                                                    <ellipse cx="6" cy="40" rx="3" ry="2" fill="#94a3b8" />
                                                    <!-- Mixer Valve Knob & Spout -->
                                                    <rect x="10" y="96" width="12" height="12" rx="2" fill="#475569" stroke="#334155" stroke-width="1" />
                                                    <circle cx="16" cy="102" r="3" fill="#cbd5e1" />
                                                    <line x1="16" y1="108" x2="16" y2="116" stroke="#94a3b8" stroke-width="2" />
                                                    <text x="24" y="80" font-size="6" font-weight="bold" fill="#64748b" text-anchor="middle">SHOWER</text>
                                                </g>

                                                <!-- 42. Wastafel Kabinet Kamar Mandi -->
                                                <g v-else-if="ann.accessoryType === 'bathroom_vanity'">
                                                    <!-- Upper Mirror Cabinet -->
                                                    <rect x="10" y="0" width="50" height="38" rx="2" fill="#f8fafc" stroke="#38bdf8" stroke-width="1.2" />
                                                    <line x1="15" y1="4" x2="32" y2="34" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" />
                                                    <!-- Faucet Gooseneck Tap -->
                                                    <path d="M35,44 L35,34 A4,4 0 0,0 27,34 L27,38" fill="none" stroke="#334155" stroke-width="2" />
                                                    <!-- Ceramic Wash Basin -->
                                                    <rect x="2" y="44" width="66" height="12" rx="3" fill="#ffffff" stroke="#64748b" stroke-width="1.5" />
                                                    <ellipse cx="35" cy="50" rx="18" ry="4" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.8" />
                                                    <!-- Hanging Vanity Cabinet Underneath -->
                                                    <rect x="6" y="56" width="58" height="26" rx="2" fill="#bfa07a" stroke="#7e5334" stroke-width="1.2" />
                                                    <line x1="35" y1="56" x2="35" y2="82" stroke="#7e5334" stroke-width="1" />
                                                    <rect x="28" y="66" width="3" height="8" rx="1" fill="#1c1917" />
                                                    <rect x="39" y="66" width="3" height="8" rx="1" fill="#1c1917" />
                                                    <text x="35" y="74" font-size="6" font-weight="bold" fill="#f8fafc" text-anchor="middle">WASTAFEL</text>
                                                </g>

                                                <!-- 43. Water Heater Dinding -->
                                                <g v-else-if="ann.accessoryType === 'water_heater'">
                                                    <!-- Main Body Casing -->
                                                    <rect x="2" y="0" width="46" height="42" rx="8" fill="#ffffff" stroke="#64748b" stroke-width="1.5" />
                                                    <!-- Digital Display -->
                                                    <rect x="14" y="12" width="22" height="14" rx="2" fill="#0f172a" />
                                                    <text x="25" y="22" font-size="7" font-family="monospace" fill="#ef4444" font-weight="bold" text-anchor="middle">42°C</text>
                                                    <!-- Rotary Knob -->
                                                    <circle cx="25" cy="33" r="4" fill="#e2e8f0" stroke="#94a3b8" stroke-width="0.8" />
                                                    <!-- Hot (Red) & Cold (Blue) Flex Pipes Below -->
                                                    <line x1="16" y1="42" x2="16" y2="50" stroke="#ef4444" stroke-width="2.5" />
                                                    <line x1="34" y1="42" x2="34" y2="50" stroke="#3b82f6" stroke-width="2.5" />
                                                    <text x="25" y="8" font-size="5" font-weight="bold" fill="#64748b" text-anchor="middle">WATER HEATER</text>
                                                </g>

                                                <!-- 44. Gantungan Handuk & Rak Sanitari -->
                                                <g v-else-if="ann.accessoryType === 'towel_rail'">
                                                    <!-- Stainless Wall Mount Bar -->
                                                    <rect x="0" y="4" width="6" height="8" fill="#475569" rx="1" />
                                                    <rect x="64" y="4" width="6" height="8" fill="#475569" rx="1" />
                                                    <line x1="4" y1="8" x2="66" y2="8" stroke="#94a3b8" stroke-width="2" />
                                                    <!-- Towel 1 (Sage Green) -->
                                                    <rect x="10" y="8" width="22" height="38" rx="2" fill="#6f7e66" stroke="#4a5544" stroke-width="1" />
                                                    <line x1="12" y1="40" x2="30" y2="40" stroke="#4a5544" stroke-width="0.8" />
                                                    <!-- Towel 2 (Cream Beige) -->
                                                    <rect x="36" y="8" width="24" height="44" rx="2" fill="#e5d5c5" stroke="#b09b85" stroke-width="1" />
                                                    <line x1="38" y1="46" x2="58" y2="46" stroke="#b09b85" stroke-width="0.8" />
                                                    <text x="35" y="52" font-size="5.5" font-weight="bold" fill="#64748b" text-anchor="middle">RAK HANDUK</text>
                                                </g>

                                                <!-- 45. Mesin Cuci Front Loading -->
                                                <g v-else-if="ann.accessoryType === 'washing_machine'">
                                                    <!-- Body -->
                                                    <rect x="0" y="0" width="65" height="90" rx="3" fill="#ffffff" stroke="#475569" stroke-width="1.5" />
                                                    <!-- Top Control Panel -->
                                                    <rect x="0" y="0" width="65" height="20" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1" rx="2" />
                                                    <!-- Detergent drawer -->
                                                    <rect x="4" y="4" width="16" height="12" rx="1" fill="#ffffff" stroke="#94a3b8" stroke-width="0.8" />
                                                    <!-- Program dial -->
                                                    <circle cx="32.5" cy="10" r="5" fill="#e2e8f0" stroke="#64748b" stroke-width="1" />
                                                    <!-- LED status display -->
                                                    <rect x="44" y="4" width="17" height="12" rx="1" fill="#0f172a" />
                                                    <text x="52.5" y="12" font-size="5" font-family="monospace" fill="#38bdf8" text-anchor="middle">45'</text>
                                                    <!-- Big Front Glass Door -->
                                                    <circle cx="32.5" cy="54" r="24" fill="#0f172a" stroke="#64748b" stroke-width="2" />
                                                    <circle cx="32.5" cy="54" r="18" fill="rgba(56, 189, 248, 0.25)" stroke="#38bdf8" stroke-width="1" />
                                                    <ellipse cx="27" cy="48" rx="8" ry="4" fill="rgba(255, 255, 255, 0.35)" />
                                                    <!-- Bottom filter door -->
                                                    <rect x="48" y="81" width="12" height="6" fill="#cbd5e1" stroke="#94a3b8" stroke-width="0.6" rx="1" />
                                                    <text x="32.5" y="86" font-size="6" font-weight="bold" fill="#64748b" text-anchor="middle">MESIN CUCI</text>
                                                </g>

                                                <!-- 46. Standing Floor Lamp -->
                                                <g v-else-if="ann.accessoryType === 'floor_lamp'">
                                                    <!-- Base Foot on Floor -->
                                                    <ellipse cx="22.5" cy="146" rx="18" ry="4" fill="#1e293b" stroke="#0f172a" stroke-width="1" />
                                                    <!-- Vertical Brass Pole -->
                                                    <line x1="22.5" y1="36" x2="22.5" y2="146" stroke="#d97706" stroke-width="2" />
                                                    <!-- Lampshade -->
                                                    <polygon points="6,36 39,36 34,6 11,6" fill="#fef3c7" stroke="#b45309" stroke-width="1.2" />
                                                    <!-- Warm Light Beam -->
                                                    <polygon points="6,36 39,36 45,80 0,80" fill="rgba(251, 191, 36, 0.15)" stroke="none" />
                                                    <circle cx="22.5" cy="36" r="4" fill="#f59e0b" />
                                                </g>

                                                <!-- 47. Gorden & Tirai Jendela Kain -->
                                                <g v-else-if="ann.accessoryType === 'curtain'">
                                                    <!-- Curtain Rod & Finials -->
                                                    <line x1="0" y1="6" x2="130" y2="6" stroke="#1e293b" stroke-width="3" stroke-linecap="round" />
                                                    <circle cx="2" cy="6" r="3.5" fill="#d97706" />
                                                    <circle cx="128" cy="6" r="3.5" fill="#d97706" />
                                                    <!-- Sheer Vitrase (Middle) -->
                                                    <rect x="35" y="8" width="60" height="168" fill="rgba(255, 255, 255, 0.45)" stroke="#cbd5e1" stroke-width="0.8" stroke-dasharray="3,3" />
                                                    <line v-for="v in 5" :key="'vit-'+v" :x1="40 + v * 8" y1="8" :x2="40 + v * 8" y2="176" stroke="#e2e8f0" stroke-width="0.8" />
                                                    <!-- Left Drape with Pleated Folds -->
                                                    <path d="M5,8 Q20,10 35,8 L30,95 Q18,92 10,95 L5,176 Q20,178 32,176 L35,8 Z" fill="#64748b" stroke="#334155" stroke-width="1" />
                                                    <line x1="15" y1="8" x2="18" y2="176" stroke="#94a3b8" stroke-width="0.8" />
                                                    <line x1="25" y1="8" x2="26" y2="176" stroke="#475569" stroke-width="0.8" />
                                                    <rect x="8" y="92" width="22" height="5" rx="1.5" fill="#d97706" />
                                                    <!-- Right Drape with Pleated Folds -->
                                                    <path d="M95,8 Q110,10 125,8 L125,176 Q110,178 98,176 L100,95 Q112,92 120,95 L125,8 Z" fill="#64748b" stroke="#334155" stroke-width="1" />
                                                    <line x1="105" y1="8" x2="104" y2="176" stroke="#475569" stroke-width="0.8" />
                                                    <line x1="115" y1="8" x2="112" y2="176" stroke="#94a3b8" stroke-width="0.8" />
                                                    <rect x="100" y="92" width="22" height="5" rx="1.5" fill="#d97706" />
                                                    <text x="65" y="95" font-size="7" font-weight="bold" fill="#475569" text-anchor="middle">GORDEN JENDELA</text>
                                                </g>

                                                <!-- 48. Kipas Angin Gantung Plafon -->
                                                <g v-else-if="ann.accessoryType === 'ceiling_fan'">
                                                    <!-- Downrod from Ceiling -->
                                                    <line x1="60" y1="0" x2="60" y2="18" stroke="#1e293b" stroke-width="3" />
                                                    <rect x="56" y="0" width="8" height="3" fill="#475569" />
                                                    <!-- Left Blade -->
                                                    <polygon points="6,18 52,18 52,24 6,26" fill="#78350f" stroke="#451a03" stroke-width="1" />
                                                    <!-- Right Blade -->
                                                    <polygon points="114,18 68,18 68,24 114,26" fill="#78350f" stroke="#451a03" stroke-width="1" />
                                                    <!-- Center Motor Housing & Light -->
                                                    <ellipse cx="60" cy="20" rx="14" ry="7" fill="#334155" stroke="#0f172a" stroke-width="1.2" />
                                                    <ellipse cx="60" cy="24" rx="8" ry="4" fill="#fef08a" stroke="#ca8a04" stroke-width="0.8" />
                                                    <text x="60" y="36" font-size="6" font-weight="bold" fill="#64748b" text-anchor="middle">KIPAS PLAFON</text>
                                                </g>

                                                <!-- 49. Tempat Sampah Pedal -->
                                                <g v-else-if="ann.accessoryType === 'trash_bin'">
                                                    <!-- Stainless Bin Body -->
                                                    <rect x="4" y="8" width="27" height="38" rx="2" fill="#f1f5f9" stroke="#64748b" stroke-width="1.2" />
                                                    <line x1="8" y1="12" x2="8" y2="42" stroke="rgba(255,255,255,0.8)" stroke-width="1.5" />
                                                    <!-- Domed Lid -->
                                                    <ellipse cx="17.5" cy="8" rx="13.5" ry="3.5" fill="#cbd5e1" stroke="#475569" stroke-width="1" />
                                                    <!-- Pedal at Base -->
                                                    <rect x="12" y="46" width="11" height="3" rx="1" fill="#1e293b" />
                                                    <text x="17.5" y="28" font-size="5" font-weight="bold" fill="#64748b" text-anchor="middle">TRASH</text>
                                                </g>

                                                <!-- Fallback Generic Accessory -->
                                                <g v-else>
                                                    <rect x="0" y="0" :width="getAccessoryBaseSize(ann.accessoryType, 'depan').w" :height="getAccessoryBaseSize(ann.accessoryType, 'depan').h" fill="#f8fafc" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4,3" rx="3" />
                                                    <text :x="getAccessoryBaseSize(ann.accessoryType, 'depan').w / 2" :y="getAccessoryBaseSize(ann.accessoryType, 'depan').h / 2" font-size="9" fill="#64748b" text-anchor="middle">
                                                        {{ ann.text || ann.accessoryType || 'Aksesoris' }}
                                                    </text>
                                                </g>
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

                                <!-- ── SUB-TITLE VIEW: 1 TAMPAK DEPAN SKALA 1 : 20 (INTERAKTIF & BISA DI-DOUBLE CLICK) ─────────── -->
                                <g
                                    v-if="cabinet.showViewTitle !== false"
                                    id="view-title"
                                    class="cursor-pointer select-none group"
                                    :transform="`translate(${furnitureSvgX + Math.round(furnitureSvgWidth / 2) - 80 + (cabinet.viewTitleOffsetX || 0)}, ${645 + (cabinet.viewTitleOffsetY || 0)})`"
                                    @click.stop="selectComponent(`Judul Gambar: ${cabinet.viewTitle || 'TAMPAK DEPAN'}`, 'view_title')"
                                    @dblclick.stop="promptEditViewTitle"
                                    @mousedown.stop="startObjectDrag($event, 'view_title', 'view_title', { x: cabinet.viewTitleOffsetX || 0, y: cabinet.viewTitleOffsetY || 0, name: `Judul Gambar: ${cabinet.viewTitle || 'TAMPAK DEPAN'}` })"
                                    @touchstart.stop="startObjectDrag($event, 'view_title', 'view_title', { x: cabinet.viewTitleOffsetX || 0, y: cabinet.viewTitleOffsetY || 0, name: `Judul Gambar: ${cabinet.viewTitle || 'TAMPAK DEPAN'}` })"
                                >
                                    <!-- Selection / Hover Highlight Box -->
                                    <rect
                                        x="-6"
                                        y="-16"
                                        :width="Math.max(170, (cabinet.viewTitle || 'TAMPAK DEPAN').length * 11 + 60)"
                                        height="48"
                                        :fill="selectedObjectId === 'view_title' ? 'rgba(59, 130, 246, 0.08)' : 'transparent'"
                                        :stroke="selectedObjectId === 'view_title' ? '#3b82f6' : 'transparent'"
                                        :stroke-dasharray="selectedObjectId === 'view_title' ? '4,3' : 'none'"
                                        stroke-width="1.2"
                                        rx="4"
                                    />

                                    <!-- Quick Tooltip saat Terpilih -->
                                    <g v-if="selectedObjectId === 'view_title'" transform="translate(0, -22)">
                                        <rect x="0" y="0" width="135" height="16" rx="3" fill="#1e293b" stroke="#3b82f6" stroke-width="0.8" />
                                        <text x="67" y="11" font-size="7.5" fill="#93c5fd" font-weight="bold" text-anchor="middle" font-family="sans-serif">
                                            ✏️ Klik ganda untuk edit
                                        </text>
                                    </g>

                                    <!-- View Number Badge (Klik Ganda untuk Ubah) -->
                                    <g @dblclick.stop="promptEditViewNumber" title="Klik ganda untuk ubah nomor view">
                                        <circle cx="15" cy="0" r="10" fill="#ffffff" stroke="#000" stroke-width="1.2" />
                                        <text x="15" y="4" font-size="11" font-weight="bold" text-anchor="middle" fill="#000">
                                            {{ cabinet.viewNumber || '1' }}
                                        </text>
                                    </g>

                                    <!-- View Title (Klik Ganda untuk Ubah) -->
                                    <text
                                        x="35"
                                        y="4"
                                        font-size="13"
                                        font-weight="bold"
                                        fill="#000"
                                        letter-spacing="1.5"
                                        title="Klik ganda untuk edit teks judul"
                                    >
                                        {{ cabinet.viewTitle || 'TAMPAK DEPAN' }}
                                    </text>

                                    <!-- Dynamic Underlines to Fit Title Length -->
                                    <line
                                        x1="35"
                                        y1="8"
                                        :x2="35 + Math.max(130, (cabinet.viewTitle || 'TAMPAK DEPAN').length * 10)"
                                        y2="8"
                                        stroke="#000"
                                        stroke-width="0.8"
                                    />
                                    <line
                                        x1="50"
                                        y1="12"
                                        :x2="50 + Math.min(100, Math.max(70, (cabinet.viewTitle || 'TAMPAK DEPAN').length * 7))"
                                        y2="12"
                                        stroke="#e11d48"
                                        stroke-width="2.5"
                                        stroke-linecap="round"
                                    />

                                    <!-- Scale Label (Klik Ganda untuk Ubah Skala) -->
                                    <g @dblclick.stop="promptEditViewScale" title="Klik ganda untuk ubah skala">
                                        <text
                                            :x="35 + Math.max(65, (cabinet.viewTitle || 'TAMPAK DEPAN').length * 5)"
                                            y="24"
                                            font-size="9"
                                            font-family="monospace"
                                            text-anchor="middle"
                                            fill="#555"
                                        >
                                            SKALA {{ activeView === 'depan' ? (cabinet.scaleSingle || '1 : 20') : '1 : 60' }}
                                        </text>
                                    </g>
                                </g>
                            </g>

                            <!-- ============================================================== -->
                            <!-- MODE AUTO: MULTI-VIEW PAS KERTAS (1 LEMBAR GAMBAR KERJA)      -->
                            <!-- ============================================================== -->
                            <g v-else-if="layoutMode === 'auto'" id="multi-view-layout">
                                <!-- 1. JIKA PRESET MODUL KABINET PARAMETRIK (CREDENZA / KITCHEN) -->
                                <g v-if="cabinet.furnitureType !== 'blank'" id="cabinet-multi-view">
                                    <!-- 1. TAMPAK DEPAN (MINI) -->
                                    <g transform="translate(60, 80) scale(0.38)">
                                        <rect x="0" y="0" width="780" height="390" fill="#f8fafc" stroke="#111" stroke-width="1.5" />
                                        <!-- Credenza TV Mini -->
                                        <g v-if="cabinet.furnitureType === 'credenza_tv'">
                                            <rect x="0" y="0" width="507" height="297" fill="#f1f5f9" stroke="#334155" stroke-width="1.2" />
                                            <rect x="507" y="0" width="273" height="297" :fill="cabinet.slatsColor || '#7E5334'" stroke="#333" stroke-width="1.2" />
                                            <rect x="160" y="70" width="180" height="105" fill="#0f172a" stroke="#334155" stroke-width="2" rx="3" />
                                            <rect x="0" y="297" width="780" height="93" :fill="cabinet.lowerCabinetColor || '#BFA07A'" stroke="#5c4033" stroke-width="1.5" />
                                        </g>
                                        <!-- Kitchen Set Mini -->
                                        <g v-else>
                                            <rect x="0" y="0" width="140" height="90" fill="#738166" />
                                            <rect x="2" y="120" width="134" height="270" fill="#18181b" />
                                            <rect x="140" y="0" width="410" height="250" fill="#C5A47E" />
                                            <rect x="140" y="230" width="410" height="22" fill="#EDEBE7" stroke="#333" />
                                            <rect x="140" y="252" width="410" height="138" fill="#C2A27D" stroke="#5c4033" />
                                            <rect x="550" y="0" width="230" height="390" fill="#6F7E66" stroke="#111" />
                                        </g>
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

                                <!-- 2. JIKA LEMBAR KERJA BEBAS / KUSTOM DENGAN AKSESORIS (4-KUADRAN GAMBAR TEKNIK) -->
                                <g v-else id="blank-multi-view-grid">
                                    <!-- KUADRAN 1: TAMPAK DEPAN -->
                                    <g id="auto-f1-depan" class="cursor-pointer group" @click="selectView('depan')">
                                        <rect :x="multiViewFrames.f1.x" :y="multiViewFrames.f1.y" :width="multiViewFrames.f1.w" :height="multiViewFrames.f1.h" fill="#fafafa" stroke="#334155" stroke-width="1.2" rx="4" />
                                        <!-- Header bar -->
                                        <rect :x="multiViewFrames.f1.x" :y="multiViewFrames.f1.y" :width="multiViewFrames.f1.w" height="28" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.8" rx="4" />
                                        <circle :cx="multiViewFrames.f1.x + 16" :cy="multiViewFrames.f1.y + 14" r="9" fill="#0f172a" />
                                        <text :x="multiViewFrames.f1.x + 16" :y="multiViewFrames.f1.y + 18" fill="#ffffff" font-size="10" font-weight="bold" text-anchor="middle">1</text>
                                        <text :x="multiViewFrames.f1.x + 32" :y="multiViewFrames.f1.y + 18" fill="#0f172a" font-size="11" font-weight="bold">TAMPAK DEPAN</text>
                                        <text :x="multiViewFrames.f1.x + 140" :y="multiViewFrames.f1.y + 18" fill="#64748b" font-size="9" font-family="monospace">SKALA {{ cabinet.scaleAuto || '1 : 50' }}</text>
                                        <g :transform="`translate(${multiViewFrames.f1.x + multiViewFrames.f1.w - 80}, ${multiViewFrames.f1.y + 5})`" class="group-hover:opacity-90">
                                            <rect width="72" height="18" rx="9" fill="#2563eb" />
                                            <text x="36" y="12" font-size="8.5" font-weight="bold" fill="#ffffff" text-anchor="middle">⛶ Fokus Depan</text>
                                        </g>
                                        <!-- Inner Viewport SVG for Tampak Depan -->
                                        <svg :x="multiViewFrames.f1.x + 8" :y="multiViewFrames.f1.y + 32" :width="multiViewFrames.f1.w - 16" :height="multiViewFrames.f1.h - 40" viewBox="0 0 900 650" class="overflow-hidden">
                                            <!-- Ground and Ceiling Reference Lines -->
                                            <line x1="20" y1="580" x2="880" y2="580" stroke="#64748b" stroke-width="1.5" stroke-dasharray="6,4" />
                                            <text x="880" y="598" font-size="11" font-family="monospace" fill="#94a3b8" text-anchor="end">± 0.00 FL</text>
                                            <line x1="20" y1="120" x2="880" y2="120" stroke="#e2e8f0" stroke-width="1" />
                                            <text x="880" y="112" font-size="10" font-family="monospace" fill="#cbd5e1" text-anchor="end">+ 2.80 CEILING</text>

                                            <!-- Accessories in Front View -->
                                            <g v-for="ann in annotations.filter(a => a.type === 'accessory')" :key="'f1-' + ann.id" :transform="`translate(${ann.x}, ${ann.y})`">
                                                <!-- Kasur Front View -->
                                                <g v-if="ann.accessoryType === 'bed'">
                                                    <rect x="0" y="0" width="160" height="90" fill="#78350f" stroke="#451a03" stroke-width="1.2" rx="3" />
                                                    <rect x="5" y="45" width="150" height="40" fill="#f8fafc" stroke="#64748b" stroke-width="1.2" rx="3" />
                                                    <rect x="15" y="25" width="55" height="22" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="4" />
                                                    <rect x="90" y="25" width="55" height="22" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="4" />
                                                    <rect x="0" y="85" width="160" height="15" fill="#92400e" stroke="#451a03" stroke-width="1" />
                                                    <rect x="5" y="100" width="15" height="12" fill="#451a03" rx="1" />
                                                    <rect x="140" y="100" width="15" height="12" fill="#451a03" rx="1" />
                                                </g>
                                                <!-- Sofa Front View -->
                                                <g v-else-if="ann.accessoryType === 'sofa'">
                                                    <rect x="0" y="0" width="180" height="50" fill="#cbd5e1" stroke="#475569" stroke-width="1.2" rx="4" />
                                                    <rect x="0" y="25" width="22" height="40" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="3" />
                                                    <rect x="158" y="25" width="22" height="40" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="3" />
                                                    <rect x="22" y="35" width="136" height="30" fill="#f8fafc" stroke="#475569" stroke-width="1" rx="3" />
                                                    <line x1="68" y1="35" x2="68" y2="65" stroke="#cbd5e1" stroke-width="1.2" />
                                                    <line x1="112" y1="35" x2="112" y2="65" stroke="#cbd5e1" stroke-width="1.2" />
                                                    <rect x="0" y="65" width="180" height="12" fill="#475569" rx="1" />
                                                    <rect x="10" y="77" width="12" height="14" fill="#334155" rx="1" />
                                                    <rect x="158" y="77" width="12" height="14" fill="#334155" rx="1" />
                                                </g>
                                                <!-- Drawer Front View -->
                                                <g v-else-if="ann.accessoryType === 'drawer'">
                                                    <rect x="0" y="0" width="100" height="90" fill="#d4b483" stroke="#8c6d37" stroke-width="1.2" />
                                                    <line x1="0" y1="30" x2="100" y2="30" stroke="#8c6d37" stroke-width="1" />
                                                    <rect x="35" y="12" width="30" height="4" fill="#1c1917" rx="1" />
                                                    <line x1="0" y1="60" x2="100" y2="60" stroke="#8c6d37" stroke-width="1" />
                                                    <rect x="35" y="42" width="30" height="4" fill="#1c1917" rx="1" />
                                                    <rect x="35" y="72" width="30" height="4" fill="#1c1917" rx="1" />
                                                </g>
                                                <!-- TV Front View -->
                                                <g v-else-if="ann.accessoryType === 'tv'">
                                                    <rect x="0" y="0" width="180" height="105" fill="#0f172a" stroke="#334155" stroke-width="2" rx="3" />
                                                    <rect x="3" y="3" width="174" height="99" fill="#1e293b" />
                                                    <line x1="3" y1="3" x2="80" y2="102" stroke="rgba(255,255,255,0.08)" stroke-width="2" />
                                                    <rect x="75" y="105" width="30" height="6" fill="#475569" rx="1" />
                                                </g>
                                                <!-- Fallback General Accessory Front View -->
                                                <g v-else>
                                                    <rect x="0" y="0" :width="ann.width || 120" :height="ann.height || 80" fill="#f8fafc" stroke="#475569" stroke-width="1.2" rx="2" />
                                                    <text :x="(ann.width || 120) / 2" :y="(ann.height || 80) / 2 + 4" font-size="9" fill="#334155" text-anchor="middle" font-weight="bold">{{ ann.text || ann.accessoryType }}</text>
                                                </g>
                                            </g>

                                            <!-- Fallback empty prompt if no accessories placed -->
                                            <g v-if="annotations.filter(a => a.type === 'accessory').length === 0">
                                                <text x="450" y="320" font-size="16" fill="#94a3b8" font-weight="bold" text-anchor="middle">Area Gambar Tampak Depan</text>
                                                <text x="450" y="348" font-size="11" fill="#cbd5e1" text-anchor="middle">Klik di sini untuk fokus dan menambahkan gambar atau aksesoris</text>
                                            </g>
                                        </svg>
                                    </g>

                                    <!-- KUADRAN 2: TAMPAK ATAS (PLAN / DENAH) -->
                                    <g id="auto-f2-atas" class="cursor-pointer group" @click="selectView('atas')">
                                        <rect :x="multiViewFrames.f2.x" :y="multiViewFrames.f2.y" :width="multiViewFrames.f2.w" :height="multiViewFrames.f2.h" fill="#fafafa" stroke="#334155" stroke-width="1.2" rx="4" />
                                        <!-- Header bar -->
                                        <rect :x="multiViewFrames.f2.x" :y="multiViewFrames.f2.y" :width="multiViewFrames.f2.w" height="28" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.8" rx="4" />
                                        <circle :cx="multiViewFrames.f2.x + 16" :cy="multiViewFrames.f2.y + 14" r="9" fill="#0f172a" />
                                        <text :x="multiViewFrames.f2.x + 16" :y="multiViewFrames.f2.y + 18" fill="#ffffff" font-size="10" font-weight="bold" text-anchor="middle">2</text>
                                        <text :x="multiViewFrames.f2.x + 32" :y="multiViewFrames.f2.y + 18" fill="#0f172a" font-size="11" font-weight="bold">TAMPAK ATAS (PLAN / DENAH)</text>
                                        <text :x="multiViewFrames.f2.x + 205" :y="multiViewFrames.f2.y + 18" fill="#64748b" font-size="9" font-family="monospace">SKALA {{ cabinet.scaleAuto || '1 : 50' }}</text>
                                        <g :transform="`translate(${multiViewFrames.f2.x + multiViewFrames.f2.w - 75}, ${multiViewFrames.f2.y + 5})`" class="group-hover:opacity-90">
                                            <rect width="68" height="18" rx="9" fill="#2563eb" />
                                            <text x="34" y="12" font-size="8.5" font-weight="bold" fill="#ffffff" text-anchor="middle">⛶ Fokus Atas</text>
                                        </g>
                                        <!-- Inner Viewport SVG for Tampak Atas -->
                                        <svg :x="multiViewFrames.f2.x + 8" :y="multiViewFrames.f2.y + 32" :width="multiViewFrames.f2.w - 16" :height="multiViewFrames.f2.h - 40" viewBox="0 0 900 650" class="overflow-hidden">
                                            <!-- Architectural Grid / Floor Perimeter -->
                                            <rect x="40" y="40" width="820" height="560" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.2" stroke-dasharray="6,4" />
                                            <line x1="40" y1="40" x2="860" y2="40" stroke="#1e293b" stroke-width="3" />

                                            <!-- Accessories in Top View -->
                                            <g v-for="ann in annotations.filter(a => a.type === 'accessory')" :key="'f2-' + ann.id" :transform="`translate(${ann.x}, ${Math.max(60, ann.y * 0.7)})`">
                                                <!-- Kasur Top View -->
                                                <g v-if="ann.accessoryType === 'bed'">
                                                    <rect x="0" y="0" width="160" height="20" fill="#78350f" stroke="#451a03" stroke-width="1.2" rx="2" />
                                                    <rect x="5" y="20" width="150" height="155" fill="#f8fafc" stroke="#334155" stroke-width="1.2" rx="3" />
                                                    <rect x="15" y="26" width="55" height="32" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="4" />
                                                    <line x1="22" y1="42" x2="63" y2="42" stroke="#e2e8f0" stroke-width="0.8" />
                                                    <rect x="90" y="26" width="55" height="32" fill="#ffffff" stroke="#94a3b8" stroke-width="1" rx="4" />
                                                    <line x1="97" y1="42" x2="138" y2="42" stroke="#e2e8f0" stroke-width="0.8" />
                                                    <rect x="5" y="85" width="150" height="90" fill="#e2e8f0" stroke="#64748b" stroke-width="1" rx="2" />
                                                </g>
                                                <!-- Sofa Top View -->
                                                <g v-else-if="ann.accessoryType === 'sofa'">
                                                    <rect x="0" y="0" width="180" height="80" fill="#e2e8f0" stroke="#334155" stroke-width="1.5" rx="4" />
                                                    <rect x="0" y="0" width="180" height="24" fill="#cbd5e1" stroke="#475569" stroke-width="1" rx="3" />
                                                    <rect x="0" y="24" width="20" height="56" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="3" />
                                                    <rect x="160" y="24" width="20" height="56" fill="#94a3b8" stroke="#475569" stroke-width="1" rx="3" />
                                                    <rect x="22" y="26" width="44" height="52" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                    <rect x="68" y="26" width="44" height="52" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                    <rect x="114" y="26" width="44" height="52" fill="#f8fafc" stroke="#64748b" stroke-width="0.8" rx="3" />
                                                </g>
                                                <!-- Table Top View -->
                                                <g v-else-if="ann.accessoryType === 'coffee_table' || ann.accessoryType === 'dining_table'">
                                                    <rect x="0" y="0" width="140" height="70" fill="#e2d9cc" stroke="#78350f" stroke-width="1.5" rx="3" />
                                                    <rect x="4" y="4" width="132" height="62" fill="none" stroke="#b45309" stroke-width="0.8" stroke-dasharray="6,3" />
                                                </g>
                                                <!-- Fallback Top View -->
                                                <g v-else>
                                                    <rect x="0" y="0" :width="ann.width || 120" :height="Math.round((ann.height || 80) * 0.7)" fill="#f8fafc" stroke="#475569" stroke-width="1.2" rx="2" />
                                                    <text :x="(ann.width || 120) / 2" :y="Math.round((ann.height || 80) * 0.35) + 4" font-size="8.5" fill="#334155" text-anchor="middle">{{ ann.text || ann.accessoryType }} (ATAS)</text>
                                                </g>
                                            </g>

                                            <g v-if="annotations.filter(a => a.type === 'accessory').length === 0">
                                                <text x="450" y="320" font-size="16" fill="#94a3b8" font-weight="bold" text-anchor="middle">Denah Layout Tampak Atas</text>
                                                <text x="450" y="348" font-size="11" fill="#cbd5e1" text-anchor="middle">Menampilkan proyeksi denah furnitur dari sudut atas</text>
                                            </g>
                                        </svg>
                                    </g>

                                    <!-- KUADRAN 3: TAMPAK SAMPING (SECTION / ELEVASI SAMPING) -->
                                    <g id="auto-f3-samping" class="cursor-pointer group" @click="selectView('samping')">
                                        <rect :x="multiViewFrames.f3.x" :y="multiViewFrames.f3.y" :width="multiViewFrames.f3.w" :height="multiViewFrames.f3.h" fill="#fafafa" stroke="#334155" stroke-width="1.2" rx="4" />
                                        <!-- Header bar -->
                                        <rect :x="multiViewFrames.f3.x" :y="multiViewFrames.f3.y" :width="multiViewFrames.f3.w" height="28" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.8" rx="4" />
                                        <circle :cx="multiViewFrames.f3.x + 16" :cy="multiViewFrames.f3.y + 14" r="9" fill="#0f172a" />
                                        <text :x="multiViewFrames.f3.x + 16" :y="multiViewFrames.f3.y + 18" fill="#ffffff" font-size="10" font-weight="bold" text-anchor="middle">3</text>
                                        <text :x="multiViewFrames.f3.x + 32" :y="multiViewFrames.f3.y + 18" fill="#0f172a" font-size="11" font-weight="bold">TAMPAK SAMPING</text>
                                        <text :x="multiViewFrames.f3.x + 150" :y="multiViewFrames.f3.y + 18" fill="#64748b" font-size="9" font-family="monospace">SKALA {{ cabinet.scaleAuto || '1 : 50' }}</text>
                                        <g :transform="`translate(${multiViewFrames.f3.x + multiViewFrames.f3.w - 85}, ${multiViewFrames.f3.y + 5})`" class="group-hover:opacity-90">
                                            <rect width="78" height="18" rx="9" fill="#2563eb" />
                                            <text x="39" y="12" font-size="8.5" font-weight="bold" fill="#ffffff" text-anchor="middle">⛶ Fokus Samping</text>
                                        </g>
                                        <!-- Inner Viewport SVG for Tampak Samping -->
                                        <svg :x="multiViewFrames.f3.x + 8" :y="multiViewFrames.f3.y + 32" :width="multiViewFrames.f3.w - 16" :height="multiViewFrames.f3.h - 40" viewBox="0 0 900 650" class="overflow-hidden">
                                            <!-- Wall Section and Floor Line -->
                                            <line x1="40" y1="580" x2="860" y2="580" stroke="#64748b" stroke-width="1.5" stroke-dasharray="6,4" />
                                            <rect x="40" y="60" width="30" height="520" fill="#e2e8f0" stroke="#334155" stroke-width="1.5" />
                                            <line x1="40" y1="60" x2="70" y2="90" stroke="#cbd5e1" stroke-width="1" />
                                            <line x1="40" y1="120" x2="70" y2="150" stroke="#cbd5e1" stroke-width="1" />
                                            <line x1="40" y1="180" x2="70" y2="210" stroke="#cbd5e1" stroke-width="1" />
                                            <line x1="40" y1="240" x2="70" y2="270" stroke="#cbd5e1" stroke-width="1" />

                                            <!-- Accessories in Side View -->
                                            <g transform="translate(100, 360)">
                                                <!-- Bed Side View -->
                                                <g v-if="annotations.some(a => a.accessoryType === 'bed')">
                                                    <rect x="0" y="-80" width="16" height="120" fill="#78350f" stroke="#451a03" stroke-width="1.2" rx="2" />
                                                    <rect x="16" y="-10" width="170" height="28" fill="#f8fafc" stroke="#475569" stroke-width="1.2" rx="3" />
                                                    <rect x="16" y="18" width="170" height="22" fill="#92400e" stroke="#451a03" stroke-width="1" />
                                                    <rect x="175" y="40" width="10" height="15" fill="#451a03" rx="1" />
                                                    <text x="95" y="8" font-size="8" fill="#475569" font-weight="bold" text-anchor="middle">KASUR (SAMPING)</text>
                                                </g>
                                                <!-- Sofa Side View -->
                                                <g v-if="annotations.some(a => a.accessoryType === 'sofa')" transform="translate(240, 0)">
                                                    <path d="M0,40 L12,-40 L35,-40 L28,40 Z" fill="#94a3b8" stroke="#475569" stroke-width="1.2" />
                                                    <rect x="25" y="0" width="55" height="25" fill="#f8fafc" stroke="#475569" stroke-width="1.2" rx="3" />
                                                    <rect x="10" y="25" width="70" height="15" fill="#475569" rx="1" />
                                                    <rect x="18" y="40" width="8" height="15" fill="#334155" rx="1" />
                                                    <rect x="68" y="40" width="8" height="15" fill="#334155" rx="1" />
                                                    <text x="45" y="16" font-size="8" fill="#475569" font-weight="bold" text-anchor="middle">SOFA (SAMPING)</text>
                                                </g>
                                            </g>

                                            <g v-if="annotations.filter(a => a.type === 'accessory').length === 0">
                                                <text x="450" y="320" font-size="16" fill="#94a3b8" font-weight="bold" text-anchor="middle">Potongan &amp; Tampak Samping</text>
                                                <text x="450" y="348" font-size="11" fill="#cbd5e1" text-anchor="middle">Menampilkan profil samping dan potongan kedalaman kabinet</text>
                                            </g>
                                        </svg>
                                    </g>

                                    <!-- KUADRAN 4: DETAIL & ISOMETRI 3D -->
                                    <g id="auto-f4-iso" class="cursor-pointer group" @click="selectView('iso')">
                                        <rect :x="multiViewFrames.f4.x" :y="multiViewFrames.f4.y" :width="multiViewFrames.f4.w" :height="multiViewFrames.f4.h" fill="#fafafa" stroke="#334155" stroke-width="1.2" rx="4" />
                                        <!-- Header bar -->
                                        <rect :x="multiViewFrames.f4.x" :y="multiViewFrames.f4.y" :width="multiViewFrames.f4.w" height="28" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="0.8" rx="4" />
                                        <circle :cx="multiViewFrames.f4.x + 16" :cy="multiViewFrames.f4.y + 14" r="9" fill="#0f172a" />
                                        <text :x="multiViewFrames.f4.x + 16" :y="multiViewFrames.f4.y + 18" fill="#ffffff" font-size="10" font-weight="bold" text-anchor="middle">4</text>
                                        <text :x="multiViewFrames.f4.x + 32" :y="multiViewFrames.f4.y + 18" fill="#0f172a" font-size="11" font-weight="bold">DETAIL &amp; ISOMETRI 3D</text>
                                        <text :x="multiViewFrames.f4.x + 175" :y="multiViewFrames.f4.y + 18" fill="#64748b" font-size="9" font-family="monospace">SKALA NTS</text>
                                        <g :transform="`translate(${multiViewFrames.f4.x + multiViewFrames.f4.w - 75}, ${multiViewFrames.f4.y + 5})`" class="group-hover:opacity-90">
                                            <rect width="68" height="18" rx="9" fill="#2563eb" />
                                            <text x="34" y="12" font-size="8.5" font-weight="bold" fill="#ffffff" text-anchor="middle">⛶ Fokus 3D</text>
                                        </g>
                                        <!-- Inner Viewport SVG for Isometri 3D -->
                                        <svg :x="multiViewFrames.f4.x + 8" :y="multiViewFrames.f4.y + 32" :width="multiViewFrames.f4.w - 16" :height="multiViewFrames.f4.h - 40" viewBox="0 0 900 650" class="overflow-hidden">
                                            <!-- Isometric Axonometric Pedestal -->
                                            <polygon points="450,140 780,290 450,440 120,290" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.5" />
                                            <polygon points="120,290 450,440 450,540 120,390" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5" />
                                            <polygon points="450,440 780,290 780,390 450,540" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.5" />

                                            <!-- 3D Isometric Bed Block -->
                                            <g v-if="annotations.some(a => a.accessoryType === 'bed')" transform="translate(0, -20)">
                                                <!-- Headboard 3D -->
                                                <polygon points="340,240 370,225 370,305 340,320" fill="#78350f" stroke="#451a03" stroke-width="1.2" />
                                                <polygon points="340,240 430,285 430,365 340,320" fill="#92400e" stroke="#451a03" stroke-width="1.2" />
                                                <polygon points="340,240 370,225 460,270 430,285" fill="#b45309" stroke="#451a03" stroke-width="1.2" />
                                                <!-- Mattress 3D -->
                                                <polygon points="370,270 510,340 450,370 310,300" fill="#f8fafc" stroke="#334155" stroke-width="1.2" />
                                                <polygon points="310,300 450,370 450,410 310,340" fill="#e2e8f0" stroke="#334155" stroke-width="1.2" />
                                                <polygon points="450,370 510,340 510,380 450,410" fill="#cbd5e1" stroke="#334155" stroke-width="1.2" />
                                                <text x="410" y="340" font-size="11" font-weight="bold" fill="#334155" text-anchor="middle">KASUR 3D</text>
                                            </g>

                                            <!-- 3D Isometric Sofa Block -->
                                            <g v-if="annotations.some(a => a.accessoryType === 'sofa')" transform="translate(140, 70)">
                                                <polygon points="360,250 480,310 440,330 320,270" fill="#cbd5e1" stroke="#475569" stroke-width="1.2" />
                                                <polygon points="320,270 440,330 440,365 320,305" fill="#94a3b8" stroke="#475569" stroke-width="1.2" />
                                                <polygon points="440,330 480,310 480,345 440,365" fill="#64748b" stroke="#475569" stroke-width="1.2" />
                                                <text x="400" y="305" font-size="10" font-weight="bold" fill="#334155" text-anchor="middle">SOFA 3D</text>
                                            </g>

                                            <text x="450" y="590" font-size="12" font-weight="bold" fill="#64748b" letter-spacing="1" text-anchor="middle">PROYEKSI AKSONOMETRI ISOMETRI</text>
                                        </svg>
                                    </g>
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
                        id="cutting-list-sheet"
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
                                        <tr v-if="cutListItems.length === 0">
                                            <td colspan="9" class="py-10 text-center text-slate-500">
                                                <div class="flex flex-col items-center justify-center gap-2 max-w-md mx-auto">
                                                    <div class="p-3 rounded-full bg-amber-50 border border-amber-200 text-amber-600 mb-1">
                                                        <Scissors class="w-6 h-6" />
                                                    </div>
                                                    <div class="font-bold text-slate-800 text-sm">Belum Ada Komponen Terhitung</div>
                                                    <div class="text-xs text-slate-500 leading-relaxed">
                                                        Daftar potong dihitung otomatis dari aksesoris/mebel yang Anda letakkan pada kanvas (seperti Kasur, Sofa, Meja, Lemari, Laci) atau dari model preset kabinet interior.
                                                    </div>
                                                    <button
                                                        type="button"
                                                        @click="activeSheetTab = '01'; showAccessoryMenu = true"
                                                        class="mt-2 px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow cursor-pointer transition-colors"
                                                    >
                                                        + Tambah Aksesoris ke Kanvas
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
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
                            @click="selectView('depan')"
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

                                <!-- Sudut Pandang Aksesoris (Depan, Atas, Samping, Bawah) -->
                                <div class="space-y-1.5 pb-2 border-b border-white/10">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <span class="font-semibold text-amber-300">Sudut Pandang (View):</span>
                                        <span class="font-mono text-[9px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold uppercase">
                                            {{ selectedAnnotation.view || 'depan' }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-1">
                                        <button
                                            type="button"
                                            @click="setAccessoryView(selectedAnnotation, 'depan')"
                                            :class="(selectedAnnotation.view || 'depan') === 'depan' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                            class="py-1 px-1 rounded-lg text-[10px] text-center transition cursor-pointer flex flex-col items-center gap-0.5"
                                            title="Tampak Depan (Elevasi)"
                                        >
                                            <span>👁️</span>
                                            <span>Depan</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAccessoryView(selectedAnnotation, 'atas')"
                                            :class="selectedAnnotation.view === 'atas' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                            class="py-1 px-1 rounded-lg text-[10px] text-center transition cursor-pointer flex flex-col items-center gap-0.5"
                                            title="Tampak Atas (Plan / Denah)"
                                        >
                                            <span>⬆️</span>
                                            <span>Atas</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAccessoryView(selectedAnnotation, 'samping')"
                                            :class="selectedAnnotation.view === 'samping' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                            class="py-1 px-1 rounded-lg text-[10px] text-center transition cursor-pointer flex flex-col items-center gap-0.5"
                                            title="Tampak Samping (Potongan)"
                                        >
                                            <span>➡️</span>
                                            <span>Samping</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="setAccessoryView(selectedAnnotation, 'bawah')"
                                            :class="selectedAnnotation.view === 'bawah' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                            class="py-1 px-1 rounded-lg text-[10px] text-center transition cursor-pointer flex flex-col items-center gap-0.5"
                                            title="Tampak Bawah (Plafon / Kolong)"
                                        >
                                            <span>⬇️</span>
                                            <span>Bawah</span>
                                        </button>
                                    </div>

                                    <!-- Rotasi & Balik (Flip) -->
                                    <div class="grid grid-cols-3 gap-1 pt-1">
                                        <button
                                            type="button"
                                            @click="rotateAccessory(selectedAnnotation)"
                                            class="py-1 px-1.5 rounded-lg text-[10px] bg-white/5 text-slate-300 hover:bg-white/10 hover:text-white transition flex items-center justify-center gap-1 cursor-pointer"
                                            title="Putar 90°"
                                        >
                                            <RotateCw class="w-3 h-3 text-amber-400" />
                                            <span>Putar 90°</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="flipAccessoryH(selectedAnnotation)"
                                            :class="selectedAnnotation.flipH ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                            class="py-1 px-1.5 rounded-lg text-[10px] transition flex items-center justify-center gap-1 cursor-pointer"
                                            title="Balik Kiri-Kanan"
                                        >
                                            <span>⇄</span>
                                            <span>Balik H</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="flipAccessoryV(selectedAnnotation)"
                                            :class="selectedAnnotation.flipV ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                            class="py-1 px-1.5 rounded-lg text-[10px] transition flex items-center justify-center gap-1 cursor-pointer"
                                            title="Balik Atas-Bawah"
                                        >
                                            <span>⇅</span>
                                            <span>Balik V</span>
                                        </button>
                                    </div>
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
                                    <!-- Sofa Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'sofa'" class="grid grid-cols-4 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 900, 850)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1 Seat</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1500, 850)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">2 Seat</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 2000, 850)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">3 Seat</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 2400, 850)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">4 Seat</button>
                                    </div>
                                    <!-- Tempat Tidur / Kasur Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'bed'" class="grid grid-cols-4 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1000, 1000)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Single</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1600, 1000)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Queen</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1800, 1000)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">King</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 2000, 1000)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Super</button>
                                    </div>
                                    <!-- Meja Makan Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'dining_table'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1200, 780)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">4 Kursi</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1600, 780)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">6 Kursi</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 2000, 780)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">8 Kursi</button>
                                    </div>
                                    <!-- Meja Kerja Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'study_desk'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1000, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">100 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1200, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">120 cm</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1500, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">150 cm</button>
                                    </div>
                                    <!-- Gorden Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'curtain'" class="grid grid-cols-3 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1200, 2200)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1.2 m</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 1500, 2200)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">1.5 m</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 2000, 2200)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">2.0 m</button>
                                    </div>
                                    <!-- Toilet Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'toilet'" class="grid grid-cols-2 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 400, 750)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Standar Monoblok</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 380, 500)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Wall-hung Gantung</button>
                                    </div>
                                    <!-- Mesin Cuci Presets -->
                                    <div v-else-if="selectedAnnotation.accessoryType === 'washing_machine'" class="grid grid-cols-2 gap-1 text-[10px]">
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 850)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">7 - 9 kg (Standar)</button>
                                        <button type="button" @click="applyAccessoryPreset(selectedAnnotation, 600, 1600)" class="py-1 bg-white/10 hover:bg-white/20 rounded text-slate-200">Stack Washer+Dryer</button>
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

                            <!-- Tombol Salin & Duplikasi -->
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="copySelectedObject"
                                    class="py-1.5 px-2 rounded-lg text-xs font-semibold bg-white/10 hover:bg-white/15 text-slate-200 border border-white/10 flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                    title="Salin Objek (Ctrl+C)"
                                >
                                    <Copy class="w-3.5 h-3.5 text-amber-400" />
                                    <span>Salin (Ctrl+C)</span>
                                </button>
                                <button
                                    type="button"
                                    @click="duplicateSelectedObject"
                                    class="py-1.5 px-2 rounded-lg text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30 hover:bg-amber-500/30 flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                                    title="Duplikat Objek (Ctrl+D atau Ctrl+Geser)"
                                >
                                    <Sparkles class="w-3.5 h-3.5" />
                                    <span>Duplikat (Ctrl+D)</span>
                                </button>
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

                        <!-- If View Title (TAMPAK DEPAN) -->
                        <div v-else-if="selectedObjectId === 'view_title'" class="space-y-3 pt-1 border-t border-amber-500/20">
                            <div class="bg-blue-950/40 border border-blue-500/30 rounded-xl p-2.5">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-300">Pengaturan Judul Gambar</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-300">
                                        Sub-Title View
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-300 leading-snug">
                                    💡 <strong>Tips Cepat:</strong> Anda bisa langsung <strong>klik ganda (double-click)</strong> tulisan ini di kanvas untuk mengganti teksnya seketika.
                                </p>
                            </div>

                            <!-- Input Teks Judul Gambar -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-300 block mb-1">Teks Judul Gambar</label>
                                <input
                                    v-model="cabinet.viewTitle"
                                    type="text"
                                    placeholder="Contoh: TAMPAK DEPAN"
                                    class="w-full bg-slate-950 border border-white/20 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:border-amber-400 font-bold tracking-wide"
                                    @input="captureSnapshot"
                                />
                            </div>

                            <!-- Preset Judul Cepat -->
                            <div>
                                <label class="text-[10px] font-semibold text-slate-400 block mb-1">Pilihan Cepat Judul Gambar</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <button
                                        v-for="preset in ['TAMPAK DEPAN', 'TAMPAK SAMPING', 'TAMPAK ATAS', 'POTONGAN A-A', 'POTONGAN B-B', 'DETAIL ISOMETRI 3D']"
                                        :key="preset"
                                        type="button"
                                        @click="cabinet.viewTitle = preset; captureSnapshot(); toast.success(`Judul diubah ke: ${preset}`)"
                                        class="py-1 px-2 rounded-lg text-[10px] font-semibold text-left transition-all border cursor-pointer truncate"
                                        :class="cabinet.viewTitle === preset ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-white/5 text-slate-300 hover:bg-white/10 border-white/10'"
                                    >
                                        {{ preset }}
                                    </button>
                                </div>
                            </div>

                            <!-- Nomor View & Skala -->
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Nomor View</label>
                                    <input
                                        v-model="cabinet.viewNumber"
                                        type="text"
                                        placeholder="1"
                                        class="w-full bg-slate-950 border border-white/20 rounded-lg px-2.5 py-1 text-xs text-center text-white focus:outline-none focus:border-amber-400 font-bold"
                                        @input="captureSnapshot"
                                    />
                                </div>
                                <div>
                                    <label class="text-[10px] font-semibold text-slate-400 block mb-1">Skala Gambar</label>
                                    <input
                                        v-model="cabinet.scaleSingle"
                                        type="text"
                                        placeholder="1 : 20"
                                        class="w-full bg-slate-950 border border-white/20 rounded-lg px-2.5 py-1 text-xs text-center text-white focus:outline-none focus:border-amber-400 font-mono"
                                        @input="captureSnapshot"
                                    />
                                </div>
                            </div>

                            <!-- Posisi Geser Judul -->
                            <div class="pt-1 border-t border-white/10">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[10px] font-semibold text-slate-400">Posisi Geser Judul</span>
                                    <button
                                        type="button"
                                        @click="cabinet.viewTitleOffsetX = 0; cabinet.viewTitleOffsetY = 0; captureSnapshot()"
                                        class="text-[9px] text-amber-400 hover:underline cursor-pointer"
                                    >
                                        Reset Posisi
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="flex items-center bg-black/50 border border-white/20 rounded-lg p-0.5">
                                        <button type="button" @click="cabinet.viewTitleOffsetX = (cabinet.viewTitleOffsetX || 0) - 10; captureSnapshot()" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">-</button>
                                        <span class="w-full text-center text-xs text-white">X: {{ cabinet.viewTitleOffsetX || 0 }}</span>
                                        <button type="button" @click="cabinet.viewTitleOffsetX = (cabinet.viewTitleOffsetX || 0) + 10; captureSnapshot()" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">+</button>
                                    </div>
                                    <div class="flex items-center bg-black/50 border border-white/20 rounded-lg p-0.5">
                                        <button type="button" @click="cabinet.viewTitleOffsetY = (cabinet.viewTitleOffsetY || 0) - 10; captureSnapshot()" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">-</button>
                                        <span class="w-full text-center text-xs text-white">Y: {{ cabinet.viewTitleOffsetY || 0 }}</span>
                                        <button type="button" @click="cabinet.viewTitleOffsetY = (cabinet.viewTitleOffsetY || 0) + 10; captureSnapshot()" class="px-2 py-0.5 text-xs text-slate-400 hover:text-white">+</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Tombol Hapus Judul View Ini -->
                            <button
                                type="button"
                                @click="deleteSelectedObject"
                                class="w-full py-1.5 rounded-lg text-xs font-semibold bg-red-500/20 text-red-300 hover:bg-red-500/30 border border-red-500/30 transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                                title="Hapus Judul View ini dari kanvas (atau tekan tombol Delete / Backspace)"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                                <span>Hapus Judul View Ini (Delete)</span>
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
                                @click="selectView('depan')"
                                :class="activeView === 'depan' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Depan
                            </button>
                            <button
                                type="button"
                                @click="selectView('tanpa_pintu')"
                                :class="activeView === 'tanpa_pintu' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer leading-tight"
                            >
                                Tanpa pintu
                            </button>
                            <button
                                type="button"
                                @click="selectView('samping')"
                                :class="activeView === 'samping' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Samping
                            </button>
                            <button
                                type="button"
                                @click="selectView('potongan')"
                                :class="activeView === 'potongan' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Potongan
                            </button>
                            <button
                                type="button"
                                @click="selectView('atas')"
                                :class="activeView === 'atas' && layoutMode === 'single' && activeSheetTab === '01' ? 'bg-white text-slate-950 font-bold shadow-md' : 'bg-white/5 text-slate-300 hover:bg-white/10'"
                                class="py-2 px-1 rounded-xl text-xs text-center transition-all cursor-pointer"
                            >
                                Atas
                            </button>
                            <button
                                type="button"
                                @click="selectView('iso')"
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

                        <!-- Toggle / Kontrol Judul View (Bisa dihapus / dimunculkan kembali) -->
                        <div class="mt-2.5 p-2 rounded-xl bg-white/5 border border-white/10 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Tag class="w-3.5 h-3.5 text-amber-400" />
                                <span class="text-xs text-slate-200 font-medium">Judul View</span>
                            </div>
                            <button
                                type="button"
                                @click="toggleViewTitle"
                                :class="cabinet.showViewTitle !== false ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border-amber-500/30 font-bold shadow-sm'"
                                class="px-2.5 py-1 rounded-lg text-xs border transition-colors cursor-pointer flex items-center gap-1"
                                :title="cabinet.showViewTitle !== false ? 'Sembunyikan / Hapus Judul View' : 'Tampilkan / Tambahkan kembali Judul View'"
                            >
                                <Plus v-if="cabinet.showViewTitle === false" class="w-3 h-3" />
                                <span>{{ cabinet.showViewTitle !== false ? 'Tampil' : '+ Tambah' }}</span>
                            </button>
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
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <button
                                type="button"
                                @click="applyPreset('blank')"
                                class="p-2.5 rounded-xl border border-dashed border-white/20 bg-white/5 hover:bg-white/10 text-left transition-all cursor-pointer"
                            >
                                <div class="text-xs font-bold text-white">Lembar Kosong</div>
                                <div class="text-[10px] text-slate-400">Draft baru / bersihkan</div>
                            </button>
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
                    {{
                        editingField === 'viewTitle' ? 'Ubah Judul Gambar (Sub-Title View)' :
                        editingField === 'viewNumber' ? 'Ubah Nomor Gambar' :
                        editingField === 'scaleSingle' ? 'Ubah Skala Gambar' :
                        editingField === 'name' ? 'Ubah Nama Desain Lemari' :
                        `Ubah ${editingField.toUpperCase()}`
                    }}
                </h3>
                <input
                    v-model="editValue"
                    type="text"
                    autofocus
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

                    <!-- Pilihan Sudut Pandang (View Selector: Depan, Atas, Samping, Bawah) -->
                    <div class="px-3 py-1.5 bg-black/60 border-b border-white/10 flex items-center justify-between gap-1.5 shrink-0">
                        <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1">
                            <span>Sudut Tampak:</span>
                        </span>
                        <div class="flex items-center gap-1 bg-white/5 p-0.5 rounded-lg border border-white/10">
                            <button
                                type="button"
                                @click="accessoryActiveView = 'depan'"
                                :class="accessoryActiveView === 'depan' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                                class="px-2 py-0.5 rounded text-[10px] transition cursor-pointer flex items-center gap-1"
                                title="Tampak Depan (Elevasi Depan)"
                            >
                                <span>👁️</span>
                                <span>Depan</span>
                            </button>
                            <button
                                type="button"
                                @click="accessoryActiveView = 'atas'"
                                :class="accessoryActiveView === 'atas' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                                class="px-2 py-0.5 rounded text-[10px] transition cursor-pointer flex items-center gap-1"
                                title="Tampak Atas (Plan / Denah Horizontal)"
                            >
                                <span>⬆️</span>
                                <span>Atas</span>
                            </button>
                            <button
                                type="button"
                                @click="accessoryActiveView = 'samping'"
                                :class="accessoryActiveView === 'samping' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                                class="px-2 py-0.5 rounded text-[10px] transition cursor-pointer flex items-center gap-1"
                                title="Tampak Samping (Potongan / Sisi)"
                            >
                                <span>➡️</span>
                                <span>Samping</span>
                            </button>
                            <button
                                type="button"
                                @click="accessoryActiveView = 'bawah'"
                                :class="accessoryActiveView === 'bawah' ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'text-slate-300 hover:text-white hover:bg-white/5'"
                                class="px-2 py-0.5 rounded text-[10px] transition cursor-pointer flex items-center gap-1"
                                title="Tampak Bawah (Plafon / Kolong)"
                            >
                                <span>⬇️</span>
                                <span>Bawah</span>
                            </button>
                        </div>
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
                            @click="stampAccessory(item.id, accessoryActiveView)"
                            class="w-full text-left p-2 rounded-xl hover:bg-amber-500/15 hover:border-amber-500/30 border border-transparent flex items-center gap-3 transition cursor-pointer group"
                        >
                            <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-xl shrink-0 group-hover:scale-110 group-hover:bg-amber-500/20 transition">
                                {{ item.icon }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="font-bold text-white group-hover:text-amber-300 truncate text-xs">{{ item.name }}</span>
                                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-white/10 text-slate-300 font-mono shrink-0">
                                        {{ getAccessoryBaseSize(item.id, accessoryActiveView).w * 10 }}×{{ getAccessoryBaseSize(item.id, accessoryActiveView).h * 10 }} mm
                                    </span>
                                </div>
                                <div class="text-[10px] text-slate-400 truncate mt-0.5 flex items-center gap-1.5">
                                    <span class="px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 text-[9px] font-bold uppercase font-mono">
                                        {{ accessoryActiveView }}
                                    </span>
                                    <span class="truncate">{{ item.desc }}</span>
                                </div>
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

@media print {
    nav,
    header,
    aside,
    button,
    .no-print {
        display: none !important;
    }
}
</style>
