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
    stroke?: string;
    fill?: string;
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
        width?: number | string;
        height?: number | string;
        className?: string;
    }>(),
    {
        elements: () => [],
        activeRoomName: '',
        width: 320,
        height: 220,
        className: '',
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
        return { minX: 0, minY: 0, maxX: 800, maxY: 600, width: 800, height: 600 };
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
        return { minX: 0, minY: 0, maxX: 800, maxY: 600, width: 800, height: 600 };
    }

    const pad = Math.max(30, (maxX - minX) * 0.08);
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

// Ekstrak baris pertama dari teks label ruangan
function getFirstLine(text: string): string {
    return (text || '').split('\n')[0].trim();
}
</script>

<template>
    <div
        class="key-plan-container relative bg-slate-50 border border-slate-300 rounded shadow-sm overflow-hidden flex flex-col justify-between"
        :style="{ width: typeof width === 'number' ? `${width}px` : width, height: typeof height === 'number' ? `${height}px` : height }"
        :class="className"
    >
        <!-- Header Key Plan Badge -->
        <div class="px-2 py-1 bg-slate-800 text-white flex items-center justify-between text-[10px] font-mono tracking-wider font-semibold uppercase">
            <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                KEY PLAN
            </span>
            <span v-if="activeRoomName" class="text-orange-400 truncate max-w-[140px]">
                {{ activeRoomName }}
            </span>
        </div>

        <!-- SVG Mini Floor Plan -->
        <div class="flex-1 w-full h-full p-1.5 flex items-center justify-center">
            <svg
                :viewBox="viewBox"
                class="w-full h-full max-h-full object-contain"
                preserveAspectRatio="xMidYMid meet"
            >
                <!-- Fallback jika belum ada elemen 2D: render denah skematik modern -->
                <g v-if="!elements || elements.length === 0">
                    <rect x="50" y="50" width="700" height="500" fill="#f8fafc" stroke="#334155" stroke-width="4" />
                    <!-- Pembagi ruangan -->
                    <line x1="50" y1="260" x2="750" y2="260" stroke="#475569" stroke-width="3" />
                    <line x1="400" y1="50" x2="400" y2="550" stroke="#475569" stroke-width="3" />
                    <line x1="230" y1="260" x2="230" y2="550" stroke="#475569" stroke-width="3" />
                    <line x1="580" y1="50" x2="580" y2="260" stroke="#475569" stroke-width="3" />

                    <!-- Highlight active zone dummy jika cocok -->
                    <rect x="55" y="55" width="340" height="200" fill="#ea580c" fill-opacity="0.25" stroke="#ea580c" stroke-width="3" stroke-dasharray="6,4" />
                    <rect x="170" y="135" width="110" height="36" rx="4" fill="#ea580c" />
                    <text x="225" y="158" text-anchor="middle" fill="#ffffff" font-size="14" font-weight="bold" font-family="sans-serif">
                        {{ activeRoomName || 'AREA AKTIF' }}
                    </text>
                </g>

                <!-- Render Elemen Real dari Modul 2D -->
                <g v-else>
                    <!-- Layer 1: Rooms / Rectangles (Zone Fills) -->
                    <template v-for="el in elements" :key="'rect-' + el.id">
                        <template v-if="el.type === 'rect'">
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 100"
                                :height="el.data.height ?? 100"
                                fill="#ffffff"
                                stroke="#475569"
                                stroke-width="2"
                            />
                        </template>
                    </template>

                    <!-- Layer 2: Walls & Lines (Dinding Monokrom / Gelap) -->
                    <template v-for="el in elements" :key="'wall-' + el.id">
                        <template v-if="el.type === 'wall' || el.type === 'line'">
                            <line
                                :x1="el.data.x1 ?? 0"
                                :y1="el.data.y1 ?? 0"
                                :x2="el.data.x2 ?? 100"
                                :y2="el.data.y2 ?? 0"
                                stroke="#1e293b"
                                :stroke-width="el.type === 'wall' ? (el.data.thickness ?? 4) : 1.5"
                                stroke-linecap="square"
                            />
                        </template>
                    </template>

                    <!-- Layer 3: Columns (Kolom Beton Hitam) -->
                    <template v-for="el in elements" :key="'col-' + el.id">
                        <template v-if="el.type === 'column'">
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 16"
                                :height="el.data.height ?? 16"
                                fill="#0f172a"
                            />
                        </template>
                    </template>

                    <!-- Layer 4: Furniture & Stairs (Perabot Monokrom Faint) -->
                    <template v-for="el in elements" :key="'furn-' + el.id">
                        <template v-if="['door', 'window', 'stair', 'toilet', 'bed', 'table'].includes(el.type)">
                            <rect
                                :x="el.data.x ?? 0"
                                :y="el.data.y ?? 0"
                                :width="el.data.width ?? 40"
                                :height="el.data.height ?? 40"
                                fill="none"
                                stroke="#94a3b8"
                                stroke-width="1.2"
                            />
                        </template>
                    </template>

                    <!-- Layer 5: Room Labels & Active Orange Highlight -->
                    <template v-for="el in elements" :key="'lbl-' + el.id">
                        <template v-if="el.type === 'room_label'">
                            <!-- Matching Room Highlight (ORANGE MENYALA) -->
                            <g v-if="isRoomMatch(el.data.text ?? '')" class="active-room-highlight">
                                <!-- Solid Orange Badge -->
                                <rect
                                    :x="(el.data.x ?? 0) - 55"
                                    :y="(el.data.y ?? 0) - 18"
                                    width="110"
                                    height="36"
                                    rx="4"
                                    fill="#ea580c"
                                    stroke="#c2410c"
                                    stroke-width="2"
                                />
                                <!-- Bold White Text -->
                                <text
                                    :x="el.data.x ?? 0"
                                    :y="(el.data.y ?? 0) + 4"
                                    text-anchor="middle"
                                    fill="#ffffff"
                                    font-size="12"
                                    font-weight="bold"
                                    font-family="sans-serif"
                                    letter-spacing="0.5"
                                >
                                    {{ getFirstLine(el.data.text ?? '') }}
                                </text>
                            </g>

                            <!-- Non-Matching Rooms: Muted Gray Outlines -->
                            <g v-else opacity="0.6">
                                <rect
                                    :x="(el.data.x ?? 0) - 40"
                                    :y="(el.data.y ?? 0) - 14"
                                    width="80"
                                    height="28"
                                    rx="3"
                                    fill="#f1f5f9"
                                    stroke="#cbd5e1"
                                    stroke-width="1"
                                />
                                <text
                                    :x="el.data.x ?? 0"
                                    :y="(el.data.y ?? 0) + 3"
                                    text-anchor="middle"
                                    fill="#64748b"
                                    font-size="10"
                                    font-family="sans-serif"
                                >
                                    {{ getFirstLine(el.data.text ?? '') }}
                                </text>
                            </g>
                        </template>
                    </template>
                </g>
            </svg>
        </div>

        <!-- Footer Coordinates / Scale -->
        <div class="px-2 py-0.5 bg-slate-100 border-t border-slate-200 flex items-center justify-between text-[9px] text-slate-500 font-mono">
            <span>N ↑</span>
            <span>SKALA DENAH MINI</span>
        </div>
    </div>
</template>

<style scoped>
.key-plan-container {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>

