<script setup lang="ts">
import { computed } from 'vue';

interface DrawingElementData {
    x?: number;
    y?: number;
    x1?: number;
    y1?: number;
    x2?: number;
    y2?: number;
    width?: number;
    height?: number;
    thickness?: number;
    text?: string;
    label?: string;
    capture_code?: string;
    stroke?: string;
    fill?: string;
    category?: string;
    [key: string]: any;
}

interface DrawingElement {
    id: string;
    drawing_sheet_id: string;
    type: string;
    data: DrawingElementData;
    sort_order?: number;
}

const props = withDefaults(
    defineProps<{
        elements?: DrawingElement[];
        activeRoomName?: string;
        activeElementId?: string;
        activeCode?: string;
        width?: number | string;
        height?: number | string;
        className?: string;
        showFooter?: boolean;
    }>(),
    {
        elements: () => [],
        activeRoomName: '',
        activeElementId: '',
        activeCode: '',
        width: 320,
        height: 220,
        className: '',
        showFooter: true,
    }
);

// Normalisasi nama ruangan untuk matching
function normalize(str: string): string {
    return (str || '')
        .toUpperCase()
        .replace(/[\n\r]/g, ' ')
        .replace(/[^A-Z0-9 ]/g, '')
        .trim();
}

const normalizedActiveName = computed(() => normalize(props.activeRoomName));

// Hitung Bounding Box seluruh elemen
const bbox = computed(() => {
    const elems = props.elements || [];
    if (elems.length === 0) {
        return { minX: 400, minY: 50, maxX: 1000, maxY: 820, width: 600, height: 770 };
    }

    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const el of elems) {
        const d = el.data || {};
        const xs: number[] = [];
        const ys: number[] = [];

        if (d.x !== undefined) xs.push(d.x);
        if (d.y !== undefined) ys.push(d.y);
        if (d.x1 !== undefined) xs.push(d.x1);
        if (d.y1 !== undefined) ys.push(d.y1);
        if (d.x2 !== undefined) xs.push(d.x2);
        if (d.y2 !== undefined) ys.push(d.y2);
        if (d.x !== undefined && d.width !== undefined) xs.push(d.x + d.width);
        if (d.y !== undefined && d.height !== undefined) ys.push(d.y + d.height);

        for (const x of xs) {
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
        }
        for (const y of ys) {
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
        }
    }

    if (minX === Infinity || maxX === -Infinity) {
        return { minX: 400, minY: 50, maxX: 1000, maxY: 820, width: 600, height: 770 };
    }

    const pad = Math.max(30, (maxX - minX) * 0.06);
    return {
        minX: minX - pad,
        minY: minY - pad,
        maxX: maxX + pad,
        maxY: maxY + pad,
        width: Math.max(100, maxX - minX + pad * 2),
        height: Math.max(100, maxY - minY + pad * 2),
    };
});

const viewBox = computed(() => {
    return `${bbox.value.minX} ${bbox.value.minY} ${bbox.value.width} ${bbox.value.height}`;
});

function isRoomMatch(text: string): boolean {
    if (!text || !normalizedActiveName.value) return false;
    const n = normalize(text);
    return n.includes(normalizedActiveName.value) || normalizedActiveName.value.includes(n);
}

function isElementMatch(el: DrawingElement): boolean {
    if (props.activeElementId && el.id === props.activeElementId) return true;
    if (props.activeCode && el.data?.capture_code && el.data.capture_code.toUpperCase() === props.activeCode.toUpperCase()) return true;
    return isRoomMatch(el.data?.text ?? '');
}

// Pisahkan judul ruangan dan subtitle (keterangan/ukuran)
function getRoomTitle(text: string): string {
    return (text || '').split('\n')[0]?.trim() || '';
}

function getRoomSubtitle(text: string): string {
    const parts = (text || '').split('\n');
    return parts.length > 1 ? parts[1].trim() : '';
}
</script>

<template>
    <div
        class="key-plan-container relative bg-slate-50 border border-slate-300 rounded shadow-sm overflow-hidden flex flex-col justify-between"
        :style="{ width: typeof width === 'number' ? `${width}px` : width, height: typeof height === 'number' ? `${height}px` : height }"
        :class="className"
    >
        <!-- Header Key Plan Badge -->
        <div class="px-2 py-1 bg-slate-800 text-white flex items-center justify-between text-[10px] font-mono tracking-wider font-semibold uppercase shrink-0">
            <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                {{ activeRoomName ? 'KEY PLAN' : 'DENAH 2D ARSITEKTUR' }}
            </span>
            <span v-if="activeRoomName" class="text-orange-400 truncate max-w-[140px]">
                {{ activeRoomName }}
            </span>
            <span v-else class="text-slate-400 text-[9px]">
                MASTER LAYOUT
            </span>
        </div>

        <!-- SVG Mini / Master Floor Plan -->
        <div class="flex-1 min-h-0 w-full p-1.5 flex items-center justify-center">
            <svg
                :viewBox="viewBox"
                class="w-full h-full max-h-full object-contain"
                preserveAspectRatio="xMidYMid meet"
            >
                <defs>
                    <!-- Wood Parquet Flooring Pattern -->
                    <pattern id="woodPlankPattern" width="24" height="12" patternUnits="userSpaceOnUse">
                        <rect width="24" height="12" fill="#fef3c7" />
                        <line x1="0" y1="0" x2="24" y2="0" stroke="#fde68a" stroke-width="0.8" />
                        <line x1="0" y1="6" x2="24" y2="6" stroke="#fde68a" stroke-width="0.8" />
                        <line x1="12" y1="0" x2="12" y2="6" stroke="#fde68a" stroke-width="0.8" />
                        <line x1="0" y1="6" x2="0" y2="12" stroke="#fde68a" stroke-width="0.8" />
                    </pattern>

                    <!-- Paving / Tile Pattern -->
                    <pattern id="tileFloorPattern" width="16" height="16" patternUnits="userSpaceOnUse">
                        <rect width="16" height="16" fill="#f8fafc" />
                        <rect width="16" height="16" fill="none" stroke="#e2e8f0" stroke-width="0.7" />
                    </pattern>

                    <!-- Shadow filter for room badges -->
                    <filter id="badgeShadow" x="-10%" y="-10%" width="120%" height="130%">
                        <feDropShadow dx="0" dy="1.5" stdDeviation="1.5" flood-color="#000000" flood-opacity="0.15" />
                    </filter>
                </defs>

                <!-- Fallback jika belum ada elemen -->
                <g v-if="!elements || elements.length === 0">
                    <rect x="50" y="50" width="700" height="500" fill="#f8fafc" stroke="#334155" stroke-width="4" />
                    <line x1="50" y1="260" x2="750" y2="260" stroke="#475569" stroke-width="3" />
                    <line x1="400" y1="50" x2="400" y2="550" stroke="#475569" stroke-width="3" />
                    <text x="400" y="300" text-anchor="middle" fill="#64748b" font-size="16" font-family="sans-serif">
                        Memuat Denah Arsitektur...
                    </text>
                </g>

                <!-- Render Elemen Lengkap Arsitektur -->
                <g v-else>
                    <!-- LAYER 1: ZONES & FLOOR FINISHES (Lantai Berwarna / Tekstur) -->
                    <template v-for="el in elements" :key="'zone-' + el.id">
                        <template v-if="['zone', 'floor_finish', 'rect'].includes(el.type)">
                            <!-- Lantai Kayu / Parquet -->
                            <rect
                                v-if="el.data.floor_type === 'wood' || el.data.fill === 'wood'"
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 100"
                                :height="el.data.height ?? 100"
                                fill="url(#woodPlankPattern)"
                                stroke="#fcd34d"
                                stroke-width="1"
                            />
                            <!-- Lantai Keramik / Tile -->
                            <rect
                                v-else-if="el.data.floor_type === 'tile'"
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 100"
                                :height="el.data.height ?? 100"
                                fill="url(#tileFloorPattern)"
                                stroke="#cbd5e1"
                                stroke-width="1"
                            />
                            <!-- Jalan / Aspal -->
                            <g v-else-if="el.data.category === 'road'">
                                <rect
                                    :x="el.data.x ?? 0"
                                    :y="el.data.y ?? 0"
                                    :width="el.data.width ?? 100"
                                    :height="el.data.height ?? 60"
                                    fill="#334155"
                                />
                                <line
                                    :x1="el.data.x ?? 0"
                                    :y1="(el.data.y ?? 0) + (el.data.height ?? 60) / 2"
                                    :x2="(el.data.x ?? 0) + (el.data.width ?? 100)"
                                    :y2="(el.data.y ?? 0) + (el.data.height ?? 60) / 2"
                                    stroke="#eab308"
                                    stroke-width="2.5"
                                    stroke-dasharray="8,6"
                                />
                            </g>
                            <!-- Standar Area / Ruangan -->
                            <rect
                                v-else
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 100"
                                :height="el.data.height ?? 100"
                                :fill="el.data.fill || '#ffffff'"
                                :stroke="el.data.stroke || '#e2e8f0'"
                                stroke-width="1"
                            />
                        </template>
                    </template>

                    <!-- LAYER 2: PERABOTAN & FURNITURE (Meja, Kursi, Barista Counter, Dapur, Toilet) -->
                    <template v-for="el in elements" :key="'furn-' + el.id">
                        <!-- Meja Persegi + Kursi (Dining Table) -->
                        <g v-if="el.type === 'table_dining' || el.type === 'table'" class="furn-table">
                            <!-- Meja Kayu Hangat -->
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 50"
                                :height="el.data.height ?? 30"
                                rx="3"
                                fill="#fed7aa"
                                stroke="#c2410c"
                                stroke-width="1.2"
                            />
                            <!-- Kursi Kiri & Kanan -->
                            <rect
                                :x="(el.data.x ?? 0) - 10"
                                :y="(el.data.y ?? 0) + 4"
                                width="7"
                                :height="(el.data.height ?? 30) - 8"
                                rx="2"
                                fill="#fdba74"
                                stroke="#ea580c"
                                stroke-width="0.8"
                            />
                            <rect
                                :x="(el.data.x ?? 0) + (el.data.width ?? 50) + 3"
                                :y="(el.data.y ?? 0) + 4"
                                width="7"
                                :height="(el.data.height ?? 30) - 8"
                                rx="2"
                                fill="#fdba74"
                                stroke="#ea580c"
                                stroke-width="0.8"
                            />
                        </g>

                        <!-- Meja Bundar + Kursi (VIP & Teras) -->
                        <g v-else-if="el.type === 'table_round'" class="furn-round-table">
                            <circle
                                :cx="el.data.x ?? 0"
                                :cy="el.data.y ?? 0"
                                :r="el.data.radius ?? 20"
                                fill="#fed7aa"
                                stroke="#ea580c"
                                stroke-width="1.5"
                            />
                            <!-- Kursi Melingkar -->
                            <circle :cx="(el.data.x ?? 0) - (el.data.radius ?? 20) - 6" :cy="el.data.y ?? 0" r="5" fill="#fdba74" stroke="#c2410c" stroke-width="0.8" />
                            <circle :cx="(el.data.x ?? 0) + (el.data.radius ?? 20) + 6" :cy="el.data.y ?? 0" r="5" fill="#fdba74" stroke="#c2410c" stroke-width="0.8" />
                            <circle :cx="el.data.x ?? 0" :cy="(el.data.y ?? 0) - (el.data.radius ?? 20) - 6" r="5" fill="#fdba74" stroke="#c2410c" stroke-width="0.8" />
                            <circle :cx="el.data.x ?? 0" :cy="(el.data.y ?? 0) + (el.data.radius ?? 20) + 6" r="5" fill="#fdba74" stroke="#c2410c" stroke-width="0.8" />
                        </g>

                        <!-- Counter Meja Kasir / Barista Bar -->
                        <g v-else-if="el.type === 'counter_bar'" class="furn-counter">
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 90"
                                :height="el.data.height ?? 22"
                                rx="3"
                                fill="#d97706"
                                stroke="#78350f"
                                stroke-width="1.5"
                            />
                            <text
                                :x="(el.data.x ?? 0) + (el.data.width ?? 90) / 2"
                                :y="(el.data.y ?? 0) + 14"
                                text-anchor="middle"
                                fill="#ffffff"
                                font-size="8.5"
                                font-weight="bold"
                                font-family="sans-serif"
                            >
                                ORDER & KASIR
                            </text>
                        </g>

                        <!-- Meja Dapur / Kitchen Island -->
                        <g v-else-if="el.type === 'kitchen_table'" class="furn-kitchen">
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 70"
                                :height="el.data.height ?? 25"
                                rx="2"
                                fill="#e2e8f0"
                                stroke="#475569"
                                stroke-width="1.2"
                            />
                            <circle :cx="(el.data.x ?? 0) + 20" :cy="(el.data.y ?? 0) + 12" r="6" fill="#cbd5e1" stroke="#64748b" stroke-width="1" />
                            <circle :cx="(el.data.x ?? 0) + 40" :cy="(el.data.y ?? 0) + 12" r="6" fill="#cbd5e1" stroke="#64748b" stroke-width="1" />
                        </g>

                        <!-- Sanitary Toilet / Closet -->
                        <g v-else-if="el.type === 'toilet'" class="furn-toilet">
                            <rect :x="el.data.x ?? 0" :y="el.data.y ?? 0" width="16" height="8" rx="1" fill="#e0f2fe" stroke="#0284c7" stroke-width="1" />
                            <ellipse :cx="(el.data.x ?? 0) + 8" :cy="(el.data.y ?? 0) + 15" rx="7" ry="9" fill="#ffffff" stroke="#0284c7" stroke-width="1.2" />
                        </g>

                        <!-- Generic furniture fallback -->
                        <rect
                            v-else-if="['stair', 'bed', 'sofa'].includes(el.type)"
                            :x="el.data.x ?? 0"
                            :y="el.data.y ?? 0"
                            :width="el.data.width ?? 40"
                            :height="el.data.height ?? 40"
                            rx="2"
                            fill="#f1f5f9"
                            stroke="#94a3b8"
                            stroke-width="1"
                        />
                    </template>

                    <!-- LAYER 3: WALLS & LINES (Dinding Arsitektur Tebal + Garis As) -->
                    <template v-for="el in elements" :key="'wall-' + el.id">
                        <template v-if="el.type === 'wall' || el.type === 'line'">
                            <g class="cad-wall">
                                <!-- Dinding struktural tebal solid -->
                                <line
                                    :x1="el.data.x1 ?? 0"
                                    :y1="el.data.y1 ?? 0"
                                    :x2="el.data.x2 ?? 100"
                                    :y2="el.data.y2 ?? 0"
                                    stroke="#0f172a"
                                    :stroke-width="el.type === 'wall' ? (el.data.thickness ?? 12) : 2"
                                    stroke-linecap="square"
                                />
                                <!-- Garis As Dinding Putus-putus (Centerline) -->
                                <line
                                    v-if="el.type === 'wall' && (el.data.thickness ?? 12) >= 8"
                                    :x1="el.data.x1 ?? 0"
                                    :y1="el.data.y1 ?? 0"
                                    :x2="el.data.x2 ?? 100"
                                    :y2="el.data.y2 ?? 0"
                                    stroke="#94a3b8"
                                    stroke-width="1"
                                    stroke-dasharray="4,3"
                                />
                            </g>
                        </template>
                    </template>

                    <!-- LAYER 4: STRUCTURAL COLUMNS (Kolom Beton dengan Silang Arsitektur "X") -->
                    <template v-for="el in elements" :key="'col-' + el.id">
                        <template v-if="el.type === 'column'">
                            <g class="cad-column">
                                <rect
                                    :x="el.data.x ?? 0"
                                    :y="el.data.y ?? 0"
                                    :width="el.data.width ?? 18"
                                    :height="el.data.height ?? 18"
                                    fill="#1e293b"
                                    stroke="#0f172a"
                                    stroke-width="1.5"
                                />
                                <!-- Silang Struktural Kolom Arsitektural ("X") -->
                                <line
                                    :x1="el.data.x ?? 0"
                                    :y1="el.data.y ?? 0"
                                    :x2="(el.data.x ?? 0) + (el.data.width ?? 18)"
                                    :y2="(el.data.y ?? 0) + (el.data.height ?? 18)"
                                    stroke="#64748b"
                                    stroke-width="1.2"
                                />
                                <line
                                    :x1="(el.data.x ?? 0) + (el.data.width ?? 18)"
                                    :y1="el.data.y ?? 0"
                                    :x2="el.data.x ?? 0"
                                    :y2="(el.data.y ?? 0) + (el.data.height ?? 18)"
                                    stroke="#64748b"
                                    stroke-width="1.2"
                                />
                            </g>
                        </template>
                    </template>

                    <!-- LAYER 5: DOORS & WINDOWS (Pintu dengan Swing Arc Hijau) -->
                    <template v-for="el in elements" :key="'door-' + el.id">
                        <!-- Pintu Arsitektur -->
                        <g v-if="el.type === 'door'" class="cad-door">
                            <!-- Bukaan Frame Pintu -->
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 40"
                                :height="el.data.height ?? 12"
                                fill="#ffffff"
                                stroke="#16a34a"
                                stroke-width="1.2"
                                rx="1"
                            />
                            <!-- Daun Pintu Tebal Hijau -->
                            <line
                                :x1="el.data.x ?? 0"
                                :y1="(el.data.y ?? 0) + (el.data.height ?? 12)"
                                :x2="el.data.x ?? 0"
                                :y2="(el.data.y ?? 0) - (el.data.width ?? 40) * 0.7"
                                stroke="#15803d"
                                stroke-width="2"
                                stroke-linecap="round"
                            />
                            <!-- Swing Arc Garis Putus-putus Hijau -->
                            <path
                                :d="`M ${el.data.x ?? 0} ${(el.data.y ?? 0) - (el.data.width ?? 40) * 0.7} A ${(el.data.width ?? 40) * 0.7} ${(el.data.width ?? 40) * 0.7} 0 0 1 ${(el.data.x ?? 0) + (el.data.width ?? 40)} ${(el.data.y ?? 0) + (el.data.height ?? 12)}`"
                                fill="none"
                                stroke="#22c55e"
                                stroke-width="1.5"
                                stroke-dasharray="3,2"
                            />
                        </g>

                        <!-- Jendela Kaca Biru -->
                        <g v-else-if="el.type === 'window'" class="cad-window">
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 50"
                                :height="el.data.height ?? 8"
                                fill="#e0f2fe"
                                stroke="#0284c7"
                                stroke-width="1.5"
                            />
                            <line
                                :x1="el.data.x ?? 0"
                                :y1="(el.data.y ?? 0) + 4"
                                :x2="(el.data.x ?? 0) + (el.data.width ?? 50)"
                                :y2="(el.data.y ?? 0) + 4"
                                stroke="#38bdf8"
                                stroke-width="1"
                            />
                        </g>
                    </template>

                    <!-- LAYER 6: DIMENSION LINES (Ukuran Satuan Meter Terukur Arsitektural) -->
                    <template v-for="el in elements" :key="'dim-' + el.id">
                        <template v-if="el.type === 'dimension'">
                            <g class="cad-dimension">
                                <!-- Garis Dimensi Utama -->
                                <line
                                    :x1="el.data.x1 ?? 0"
                                    :y1="el.data.y1 ?? 0"
                                    :x2="el.data.x2 ?? 100"
                                    :y2="el.data.y2 ?? 0"
                                    stroke="#475569"
                                    stroke-width="1.2"
                                />
                                <!-- Tick Mark Diagonal 45 Derajat Ujung 1 -->
                                <line
                                    :x1="(el.data.x1 ?? 0) - 4"
                                    :y1="(el.data.y1 ?? 0) + 4"
                                    :x2="(el.data.x1 ?? 0) + 4"
                                    :y2="(el.data.y1 ?? 0) - 4"
                                    stroke="#334155"
                                    stroke-width="1.6"
                                />
                                <!-- Tick Mark Diagonal 45 Derajat Ujung 2 -->
                                <line
                                    :x1="(el.data.x2 ?? 100) - 4"
                                    :y1="(el.data.y2 ?? 0) + 4"
                                    :x2="(el.data.x2 ?? 100) + 4"
                                    :y2="(el.data.y2 ?? 0) - 4"
                                    stroke="#334155"
                                    stroke-width="1.6"
                                />
                                <!-- Background Box Teks Angka Dimensi -->
                                <rect
                                    :x="((el.data.x1 ?? 0) + (el.data.x2 ?? 100)) / 2 - 24"
                                    :y="((el.data.y1 ?? 0) + (el.data.y2 ?? 0)) / 2 - 8"
                                    width="48"
                                    height="16"
                                    rx="2"
                                    fill="#ffffff"
                                    stroke="#94a3b8"
                                    stroke-width="0.8"
                                />
                                <!-- Teks Ukuran Meter (Misal: 4.00 m) -->
                                <text
                                    :x="((el.data.x1 ?? 0) + (el.data.x2 ?? 100)) / 2"
                                    :y="((el.data.y1 ?? 0) + (el.data.y2 ?? 0)) / 2 + 4"
                                    text-anchor="middle"
                                    fill="#1e293b"
                                    font-size="9"
                                    font-weight="bold"
                                    font-family="monospace"
                                >
                                    {{ el.data.text || el.data.label || '3.50 m' }}
                                </text>
                            </g>
                        </template>
                    </template>

                    <!-- LAYER 7: ROOM LABELS & 3D CAPTURE BADGES (Persis Gambar Kedua!) -->
                    <template v-for="el in elements" :key="'lbl-' + el.id">
                        <template v-if="el.type === 'room_label'">
                            <!-- Matching Room Highlight (Saat Di Booklet Halaman Interior) -->
                            <g
                                v-if="isElementMatch(el)"
                                class="active-room-highlight"
                                filter="url(#badgeShadow)"
                            >
                                <!-- Top Pill Badge: Kamera 3D Code -->
                                <g v-if="el.data.capture_code">
                                    <rect
                                        :x="(el.data.x ?? 0) - 52"
                                        :y="(el.data.y ?? 0) - 34"
                                        width="104"
                                        height="18"
                                        rx="4"
                                        fill="#ea580c"
                                    />
                                    <text
                                        :x="el.data.x ?? 0"
                                        :y="(el.data.y ?? 0) - 21"
                                        text-anchor="middle"
                                        fill="#ffffff"
                                        font-size="10.5"
                                        font-weight="bold"
                                        font-family="monospace"
                                    >
                                        📷 {{ el.data.capture_code }}
                                    </text>
                                </g>

                                <!-- Bottom Card: Nama Ruangan + Subtitle -->
                                <rect
                                    :x="(el.data.x ?? 0) - 58"
                                    :y="el.data.capture_code ? (el.data.y ?? 0) - 14 : (el.data.y ?? 0) - 20"
                                    width="116"
                                    height="40"
                                    rx="5"
                                    fill="#ffffff"
                                    stroke="#ea580c"
                                    stroke-width="2.5"
                                />
                                <!-- Bold White/Orange Text -->
                                <text
                                    :x="el.data.x ?? 0"
                                    :y="el.data.capture_code ? (el.data.y ?? 0) + 2 : (el.data.y ?? 0) - 2"
                                    text-anchor="middle"
                                    fill="#ea580c"
                                    font-size="12"
                                    font-weight="900"
                                    font-family="sans-serif"
                                    letter-spacing="0.5"
                                >
                                    {{ getRoomTitle(el.data.text ?? '') }}
                                </text>
                                <text
                                    :x="el.data.x ?? 0"
                                    :y="el.data.capture_code ? (el.data.y ?? 0) + 16 : (el.data.y ?? 0) + 12"
                                    text-anchor="middle"
                                    fill="#475569"
                                    font-size="9"
                                    font-family="sans-serif"
                                >
                                    {{ getRoomSubtitle(el.data.text ?? '') }}
                                </text>
                            </g>

                            <!-- Normal / Non-Matching Rooms (Denah Utama Halaman 2 atau Muted di Keyplan) -->
                            <g
                                v-else
                                class="normal-room-badge"
                                :opacity="activeRoomName ? 0.5 : 1"
                                filter="url(#badgeShadow)"
                            >
                                <!-- Top Pill Badge: Kamera 3D Code (Tampil jika ada kode) -->
                                <g v-if="el.data.capture_code">
                                    <rect
                                        :x="(el.data.x ?? 0) - 48"
                                        :y="(el.data.y ?? 0) - 32"
                                        width="96"
                                        height="17"
                                        rx="4"
                                        fill="#ea580c"
                                    />
                                    <text
                                        :x="el.data.x ?? 0"
                                        :y="(el.data.y ?? 0) - 20"
                                        text-anchor="middle"
                                        fill="#ffffff"
                                        font-size="9.5"
                                        font-weight="bold"
                                        font-family="monospace"
                                    >
                                        📷 {{ el.data.capture_code }}
                                    </text>
                                </g>

                                <!-- Bottom Card: Nama Ruangan + Subtitle -->
                                <rect
                                    :x="(el.data.x ?? 0) - 52"
                                    :y="el.data.capture_code ? (el.data.y ?? 0) - 13 : (el.data.y ?? 0) - 18"
                                    width="104"
                                    height="36"
                                    rx="4"
                                    fill="#ffffff"
                                    :stroke="el.data.capture_code ? '#ea580c' : '#94a3b8'"
                                    :stroke-width="el.data.capture_code ? 1.8 : 1.2"
                                />
                                <text
                                    :x="el.data.x ?? 0"
                                    :y="el.data.capture_code ? (el.data.y ?? 0) + 1 : (el.data.y ?? 0) - 2"
                                    text-anchor="middle"
                                    :fill="el.data.capture_code ? '#ea580c' : '#0f172a'"
                                    font-size="11"
                                    font-weight="bold"
                                    font-family="sans-serif"
                                >
                                    {{ getRoomTitle(el.data.text ?? '') }}
                                </text>
                                <text
                                    :x="el.data.x ?? 0"
                                    :y="el.data.capture_code ? (el.data.y ?? 0) + 14 : (el.data.y ?? 0) + 10"
                                    text-anchor="middle"
                                    fill="#64748b"
                                    font-size="8.5"
                                    font-family="sans-serif"
                                >
                                    {{ getRoomSubtitle(el.data.text ?? '') }}
                                </text>
                            </g>
                        </template>
                    </template>
                </g>
            </svg>
        </div>

        <!-- Footer Coordinates / Scale -->
        <div v-if="showFooter" class="px-2 py-0.5 bg-slate-100 border-t border-slate-200 flex items-center justify-between text-[9px] text-slate-500 font-mono">
            <span>N ↑ UTARA</span>
            <span>SKALA 1:100 • SATUAN METER (m)</span>
        </div>
    </div>
</template>

<style scoped>
.key-plan-container {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
