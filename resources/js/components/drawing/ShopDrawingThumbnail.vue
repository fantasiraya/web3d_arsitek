<script setup lang="ts">
import { computed } from 'vue';

interface SheetProp {
    id: string;
    title: string;
    scale?: string;
    paper_size?: string;
    orientation?: string;
    width?: number;
    height?: number;
    elements?: any[];
    project?: {
        id?: string;
        title?: string;
        client_name?: string | null;
        client_address?: string | null;
    };
}

const props = defineProps<{
    sheet: SheetProp;
}>();

// Ekstrak data konfigurasi kabinet dari elements atau inferensikan secara cerdas
const cabinetData = computed(() => {
    if (props.sheet.elements && props.sheet.elements.length > 0) {
        const shopEl = props.sheet.elements.find(e => e.type === 'shop_drawing' || e.data?.cabinet);
        if (shopEl?.data) {
            const d = typeof shopEl.data === 'string' ? JSON.parse(shopEl.data) : shopEl.data;
            if (d.cabinet) {
                return {
                    name: d.cabinet.name || props.sheet.title,
                    furnitureType: d.cabinet.furnitureType || 'credenza_tv',
                    width: Number(d.cabinet.width) || 3600,
                    height: Number(d.cabinet.height) || 2400,
                    depth: Number(d.cabinet.depth) || 450,
                    hasSlats: d.cabinet.hasSlats ?? true,
                    hasFridge: Boolean(d.cabinet.hasFridge),
                    hasGlassShelves: d.cabinet.hasGlassShelves ?? true,
                    hasSink: Boolean(d.cabinet.hasSink),
                    lowerCabinetColor: d.cabinet.lowerCabinetColor || '#BFA07A',
                    tallUnitColor: d.cabinet.tallUnitColor || '#7E5334',
                };
            }
        }
    }

    // Fallback berdasarkan judul lembar
    const titleLower = (props.sheet.title || '').toLowerCase();
    if (titleLower.includes('kitchen') || titleLower.includes('dapur') || titleLower.includes('pantry')) {
        return {
            name: props.sheet.title,
            furnitureType: 'kitchen_bar' as const,
            width: 3200,
            height: 2400,
            depth: 600,
            hasSlats: false,
            hasFridge: true,
            hasGlassShelves: true,
            hasSink: true,
            lowerCabinetColor: '#C2A27D',
            tallUnitColor: '#6F7E66',
        };
    }

    if (titleLower.includes('wardrobe') || titleLower.includes('lemari') || titleLower.includes('pakaian')) {
        return {
            name: props.sheet.title,
            furnitureType: 'wardrobe_3door' as const,
            width: 2400,
            height: 2200,
            depth: 600,
            hasSlats: false,
            hasFridge: false,
            hasGlassShelves: false,
            hasSink: false,
            lowerCabinetColor: '#BFA07A',
            tallUnitColor: '#475569',
        };
    }

    // Default: Backdrop TV & Floating Credenza
    return {
        name: props.sheet.title || 'Backdrop TV & Floating Credenza',
        furnitureType: 'credenza_tv' as const,
        width: 3600,
        height: 2400,
        depth: 450,
        hasSlats: true,
        hasFridge: false,
        hasGlassShelves: true,
        hasSink: false,
        lowerCabinetColor: '#BFA07A',
        tallUnitColor: '#7E5334',
    };
});
</script>

<template>
    <div class="w-full h-full relative overflow-hidden bg-[#070a13] select-none font-sans">
        <!-- PURE VECTOR BLUEPRINT SVG -->
        <svg
            viewBox="0 0 760 420"
            class="w-full h-full block"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <!-- Fine CAD Millimeter Grid -->
                <pattern id="cad-grid-sm" width="20" height="20" patternUnits="userSpaceOnUse">
                    <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#151d30" stroke-width="0.6" />
                </pattern>
                <pattern id="cad-grid-lg" width="100" height="100" patternUnits="userSpaceOnUse">
                    <rect width="100" height="100" fill="url(#cad-grid-sm)" />
                    <path d="M 100 0 L 0 0 0 100" fill="none" stroke="#1e2c45" stroke-width="1.1" />
                </pattern>

                <!-- Dimension Arrows -->
                <marker id="arrow-start" viewBox="0 0 10 10" refX="2" refY="5" markerWidth="5" markerHeight="5" orient="auto">
                    <path d="M 8 1 L 1 5 L 8 9 z" fill="#38bdf8" />
                </marker>
                <marker id="arrow-end" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="5" markerHeight="5" orient="auto">
                    <path d="M 2 1 L 9 5 L 2 9 z" fill="#38bdf8" />
                </marker>

                <!-- Wood Slats Pattern for TV backdrop -->
                <pattern id="slats-pattern" width="10" height="20" patternUnits="userSpaceOnUse">
                    <rect x="0" y="0" width="7" height="20" fill="#1b253b" />
                    <rect x="7" y="0" width="3" height="20" fill="#0d1424" />
                </pattern>

                <!-- Tile backsplash pattern -->
                <pattern id="tile-pattern" width="24" height="12" patternUnits="userSpaceOnUse">
                    <rect width="24" height="12" fill="#121b2f" stroke="#243452" stroke-width="0.6" />
                </pattern>
            </defs>

            <!-- Background CAD Blueprint Grid -->
            <rect width="760" height="420" fill="#070a13" />
            <rect width="760" height="420" fill="url(#cad-grid-lg)" opacity="0.9" />

            <!-- Technical Border & Corner Marks -->
            <rect x="14" y="14" width="732" height="392" fill="none" stroke="#1e293b" stroke-width="1.2" />
            <rect x="18" y="18" width="724" height="384" fill="none" stroke="#38bdf8" stroke-width="0.8" opacity="0.4" />

            <!-- Corner registration crosshairs -->
            <g stroke="#38bdf8" stroke-width="1" opacity="0.7">
                <line x1="8" y1="18" x2="28" y2="18" />
                <line x1="18" y1="8" x2="18" y2="28" />
                <line x1="732" y1="18" x2="752" y2="18" />
                <line x1="742" y1="8" x2="742" y2="28" />
                <line x1="8" y1="402" x2="28" y2="402" />
                <line x1="18" y1="392" x2="18" y2="412" />
                <line x1="732" y1="402" x2="752" y2="402" />
                <line x1="742" y1="392" x2="742" y2="412" />
            </g>

            <!-- ── ELEVATION DRAWING AREA ──────────────────────────────────────── -->
            <!-- 1. BACKDROP TV & FLOATING CREDENZA -->
            <g v-if="cabinetData.furnitureType === 'credenza_tv'" transform="translate(60, 48)">
                <!-- Dimension Lines: Top (Width = 3600 mm) -->
                <line x1="40" y1="20" x2="440" y2="20" stroke="#38bdf8" stroke-width="1.2" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <line x1="40" y1="14" x2="40" y2="34" stroke="#38bdf8" stroke-width="0.8" opacity="0.6" />
                <line x1="440" y1="14" x2="440" y2="34" stroke="#38bdf8" stroke-width="0.8" opacity="0.6" />
                <text x="240" y="15" fill="#38bdf8" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle">
                    {{ cabinetData.width }} mm (3.60 m)
                </text>

                <!-- Dimension Lines: Left (Height = 2400 mm) -->
                <line x1="20" y1="40" x2="20" y2="260" stroke="#38bdf8" stroke-width="1.2" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <line x1="14" y1="40" x2="34" y2="40" stroke="#38bdf8" stroke-width="0.8" opacity="0.6" />
                <line x1="14" y1="260" x2="34" y2="260" stroke="#38bdf8" stroke-width="0.8" opacity="0.6" />
                <text x="12" y="155" fill="#38bdf8" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle" transform="rotate(-90, 12, 155)">
                    {{ cabinetData.height }} mm
                </text>

                <!-- Elevation Backdrop: Slats Wooden Wall (Left Section) -->
                <rect x="40" y="40" width="110" height="220" fill="url(#slats-pattern)" stroke="#334155" stroke-width="1.2" />

                <!-- Elevation Backdrop: Marble Panel (Center & Right) -->
                <rect x="150" y="40" width="220" height="220" fill="#0f172a" stroke="#475569" stroke-width="1.2" />
                <!-- Marble Vein Accents -->
                <path d="M 160 50 Q 220 110, 260 90 T 360 170" fill="none" stroke="#1e293b" stroke-width="1.5" />
                <path d="M 210 40 Q 250 150, 310 140 T 370 230" fill="none" stroke="#1e293b" stroke-width="1.2" />

                <!-- Smart TV 65" Screen on Marble Panel -->
                <rect x="180" y="75" width="160" height="96" rx="3" fill="#030712" stroke="#64748b" stroke-width="1.8" />
                <line x1="180" y1="75" x2="340" y2="171" stroke="#1f2937" stroke-width="0.8" />
                <line x1="340" y1="75" x2="180" y2="171" stroke="#1f2937" stroke-width="0.8" />
                <rect x="245" y="115" width="30" height="16" rx="2" fill="#0f172a" stroke="#38bdf8" stroke-width="0.8" />
                <text x="260" y="127" fill="#38bdf8" font-size="8" font-weight="bold" font-family="monospace" text-anchor="middle">TV 65"</text>

                <!-- Right Display Tower with 3 Glass Shelves -->
                <rect x="370" y="40" width="70" height="220" fill="#0a0f1d" stroke="#334155" stroke-width="1.4" />
                <!-- Shelves -->
                <line x1="370" y1="95" x2="440" y2="95" stroke="#38bdf8" stroke-width="2" />
                <circle cx="405" cy="98" r="2" fill="#fbbf24" />
                <line x1="370" y1="150" x2="440" y2="150" stroke="#38bdf8" stroke-width="2" />
                <circle cx="405" cy="153" r="2" fill="#fbbf24" />
                <line x1="370" y1="205" x2="440" y2="205" stroke="#38bdf8" stroke-width="2" />
                <circle cx="405" cy="208" r="2" fill="#fbbf24" />

                <!-- Floating Credenza (Lower Module) -->
                <!-- Suspended 25px above floor level -->
                <rect x="60" y="215" width="310" height="45" fill="#1e293b" stroke="#f1f5f9" stroke-width="1.5" />
                <!-- 3 Drawers -->
                <line x1="163" y1="215" x2="163" y2="260" stroke="#475569" stroke-width="1.2" />
                <line x1="266" y1="215" x2="266" y2="260" stroke="#475569" stroke-width="1.2" />
                <!-- Drawer Handles -->
                <line x1="95" y1="223" x2="130" y2="223" stroke="#fbbf24" stroke-width="2" />
                <line x1="198" y1="223" x2="233" y2="223" stroke="#fbbf24" stroke-width="2" />
                <line x1="301" y1="223" x2="336" y2="223" stroke="#fbbf24" stroke-width="2" />
                <!-- Credenza Height Dimension -->
                <line x1="50" y1="215" x2="50" y2="260" stroke="#fbbf24" stroke-width="1" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <text x="36" y="242" fill="#fbbf24" font-size="9" font-family="monospace" font-weight="bold">450</text>

                <!-- Floor Plinth Line -->
                <line x1="20" y1="270" x2="460" y2="270" stroke="#64748b" stroke-width="2" />
                <!-- Floor Hatch Marks -->
                <path d="M 25 270 L 35 280 M 65 270 L 75 280 M 105 270 L 115 280 M 145 270 L 155 280 M 185 270 L 195 280 M 225 270 L 235 280 M 265 270 L 275 280 M 305 270 L 315 280 M 345 270 L 355 280 M 385 270 L 395 280 M 425 270 L 435 280 M 450 270 L 460 280" stroke="#334155" stroke-width="1" />
                <text x="465" y="274" fill="#64748b" font-size="9" font-family="monospace">±0.00 FLOOR</text>

                <!-- View Title Tag Bubble -->
                <g transform="translate(180, 298)">
                    <circle cx="16" cy="12" r="12" fill="#0f172a" stroke="#38bdf8" stroke-width="1.5" />
                    <text x="16" y="16" fill="#38bdf8" font-size="11" font-weight="bold" font-family="monospace" text-anchor="middle">01</text>
                    <text x="36" y="11" fill="#f8fafc" font-size="11" font-weight="bold" letter-spacing="1">TAMPAK DEPAN</text>
                    <text x="36" y="23" fill="#94a3b8" font-size="9" font-family="monospace">SKALA {{ props.sheet.scale || '1:20' }}</text>
                    <line x1="36" y1="14" x2="160" y2="14" stroke="#38bdf8" stroke-width="1" />
                </g>
            </g>

            <!-- 2. KITCHEN & PANTRY SET -->
            <g v-else-if="cabinetData.furnitureType === 'kitchen_bar'" transform="translate(60, 48)">
                <!-- Dimension Lines: Top (Width = 3200 mm) -->
                <line x1="40" y1="20" x2="440" y2="20" stroke="#38bdf8" stroke-width="1.2" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <line x1="40" y1="14" x2="40" y2="34" stroke="#38bdf8" stroke-width="0.8" opacity="0.6" />
                <line x1="440" y1="14" x2="440" y2="34" stroke="#38bdf8" stroke-width="0.8" opacity="0.6" />
                <text x="240" y="15" fill="#38bdf8" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle">
                    {{ cabinetData.width }} mm (3.20 m)
                </text>

                <!-- Dimension Lines: Left (Height = 2400 mm) -->
                <line x1="20" y1="40" x2="20" y2="260" stroke="#38bdf8" stroke-width="1.2" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <text x="12" y="155" fill="#38bdf8" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle" transform="rotate(-90, 12, 155)">
                    {{ cabinetData.height }} mm
                </text>

                <!-- Upper Wall Cabinets -->
                <rect x="40" y="40" width="280" height="80" fill="#132038" stroke="#38bdf8" stroke-width="1.4" />
                <!-- 3 Door divisions -->
                <line x1="133" y1="40" x2="133" y2="120" stroke="#334155" stroke-width="1.2" />
                <line x1="226" y1="40" x2="226" y2="120" stroke="#334155" stroke-width="1.2" />
                <!-- Handles -->
                <line x1="123" y1="105" x2="123" y2="115" stroke="#fbbf24" stroke-width="2" />
                <line x1="143" y1="105" x2="143" y2="115" stroke="#fbbf24" stroke-width="2" />
                <line x1="216" y1="105" x2="216" y2="115" stroke="#fbbf24" stroke-width="2" />

                <!-- Backsplash Area with Tile Hatch -->
                <rect x="40" y="120" width="280" height="60" fill="url(#tile-pattern)" stroke="#334155" stroke-width="1" />
                <!-- Faucet / Keran Air Outline -->
                <path d="M 175 178 L 175 152 Q 175 140 185 140 Q 195 140 195 148 L 195 156" fill="none" stroke="#fbbf24" stroke-width="2" />
                <rect x="160" y="176" width="50" height="4" fill="#64748b" />

                <!-- Solid Surface Countertop -->
                <rect x="35" y="180" width="290" height="12" fill="#e2e8f0" stroke="#000" stroke-width="0.8" />

                <!-- Lower Base Units -->
                <rect x="40" y="192" width="280" height="68" fill="#1e293b" stroke="#f1f5f9" stroke-width="1.4" />
                <line x1="133" y1="192" x2="133" y2="260" stroke="#475569" stroke-width="1.2" />
                <line x1="226" y1="192" x2="226" y2="260" stroke="#475569" stroke-width="1.2" />
                <!-- Drawer lines -->
                <line x1="40" y1="215" x2="133" y2="215" stroke="#334155" stroke-width="1" />
                <line x1="40" y1="238" x2="133" y2="238" stroke="#334155" stroke-width="1" />

                <!-- Tall Unit / Fridge Compartment (Right) -->
                <rect x="325" y="40" width="115" height="220" fill="#0f172a" stroke="#38bdf8" stroke-width="1.5" />
                <!-- Fridge Doors -->
                <rect x="333" y="55" width="99" height="190" rx="3" fill="#090d16" stroke="#475569" stroke-width="1.5" />
                <line x1="333" y1="140" x2="432" y2="140" stroke="#475569" stroke-width="1.5" />
                <!-- Fridge Handles -->
                <line x1="340" y1="110" x2="340" y2="135" stroke="#38bdf8" stroke-width="2.5" />
                <line x1="340" y1="145" x2="340" y2="175" stroke="#38bdf8" stroke-width="2.5" />
                <text x="382" y="100" fill="#64748b" font-size="8" font-family="monospace" text-anchor="middle">FRIDGE</text>

                <!-- Floor Plinth Line -->
                <line x1="20" y1="270" x2="460" y2="270" stroke="#64748b" stroke-width="2" />
                <text x="465" y="274" fill="#64748b" font-size="9" font-family="monospace">±0.00 FLOOR</text>

                <!-- View Title Tag Bubble -->
                <g transform="translate(180, 298)">
                    <circle cx="16" cy="12" r="12" fill="#0f172a" stroke="#38bdf8" stroke-width="1.5" />
                    <text x="16" y="16" fill="#38bdf8" font-size="11" font-weight="bold" font-family="monospace" text-anchor="middle">01</text>
                    <text x="36" y="11" fill="#f8fafc" font-size="11" font-weight="bold" letter-spacing="1">TAMPAK DEPAN KITCHEN</text>
                    <text x="36" y="23" fill="#94a3b8" font-size="9" font-family="monospace">SKALA {{ props.sheet.scale || '1:20' }}</text>
                    <line x1="36" y1="14" x2="200" y2="14" stroke="#38bdf8" stroke-width="1" />
                </g>
            </g>

            <!-- 3. WARDROBE / LEMARI PAKAIAN -->
            <g v-else transform="translate(60, 48)">
                <!-- Dimension Lines: Top (Width = 2400 mm) -->
                <line x1="60" y1="20" x2="420" y2="20" stroke="#38bdf8" stroke-width="1.2" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <text x="240" y="15" fill="#38bdf8" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle">
                    {{ cabinetData.width }} mm (2.40 m)
                </text>

                <!-- Dimension Lines: Left (Height = 2200 mm) -->
                <line x1="40" y1="40" x2="40" y2="260" stroke="#38bdf8" stroke-width="1.2" marker-start="url(#arrow-start)" marker-end="url(#arrow-end)" />
                <text x="32" y="155" fill="#38bdf8" font-size="11" font-family="monospace" font-weight="bold" text-anchor="middle" transform="rotate(-90, 32, 155)">
                    {{ cabinetData.height }} mm
                </text>

                <!-- Outer Wardrobe Carcase -->
                <rect x="60" y="40" width="360" height="220" fill="#10192e" stroke="#38bdf8" stroke-width="1.6" />

                <!-- 3 Main Doors -->
                <rect x="63" y="43" width="116" height="214" fill="#18233c" stroke="#334155" stroke-width="1.2" />
                <rect x="182" y="43" width="116" height="214" fill="#18233c" stroke="#334155" stroke-width="1.2" />
                <rect x="301" y="43" width="116" height="214" fill="#18233c" stroke="#334155" stroke-width="1.2" />

                <!-- Vertical Sleek Handles -->
                <line x1="170" y1="120" x2="170" y2="180" stroke="#fbbf24" stroke-width="2.5" />
                <line x1="190" y1="120" x2="190" y2="180" stroke="#fbbf24" stroke-width="2.5" />
                <line x1="310" y1="120" x2="310" y2="180" stroke="#fbbf24" stroke-width="2.5" />

                <!-- Upper Storage Flap Lines (Dashed) -->
                <line x1="63" y1="90" x2="417" y2="90" stroke="#475569" stroke-dasharray="4 3" stroke-width="1" />

                <!-- Floor Plinth Line -->
                <line x1="40" y1="270" x2="440" y2="270" stroke="#64748b" stroke-width="2" />
                <text x="445" y="274" fill="#64748b" font-size="9" font-family="monospace">±0.00 FLOOR</text>

                <!-- View Title Tag Bubble -->
                <g transform="translate(180, 298)">
                    <circle cx="16" cy="12" r="12" fill="#0f172a" stroke="#38bdf8" stroke-width="1.5" />
                    <text x="16" y="16" fill="#38bdf8" font-size="11" font-weight="bold" font-family="monospace" text-anchor="middle">01</text>
                    <text x="36" y="11" fill="#f8fafc" font-size="11" font-weight="bold" letter-spacing="1">TAMPAK DEPAN LEMARI</text>
                    <text x="36" y="23" fill="#94a3b8" font-size="9" font-family="monospace">SKALA {{ props.sheet.scale || '1:20' }}</text>
                    <line x1="36" y1="14" x2="190" y2="14" stroke="#38bdf8" stroke-width="1" />
                </g>
            </g>

            <!-- ── TECHNICAL KOP GAMBAR (TITLE BLOCK) ───────────────────────── -->
            <g transform="translate(545, 235)">
                <!-- Kop Outer Box -->
                <rect x="0" y="0" width="190" height="162" fill="#0b1120" stroke="#38bdf8" stroke-width="1.2" rx="2" />
                
                <!-- Section 1: Header / Badges -->
                <rect x="0" y="0" width="190" height="28" fill="#1e293b" />
                <text x="12" y="18" fill="#38bdf8" font-size="9" font-weight="bold" letter-spacing="1.5" font-family="monospace">SHOP DRAWING DETAIL</text>
                <circle cx="174" cy="14" r="4" fill="#22c55e" />

                <!-- Section 2: Sheet Title -->
                <text x="12" y="44" fill="#64748b" font-size="7.5" font-weight="bold">JUDUL GAMBAR :</text>
                <text x="12" y="58" fill="#f8fafc" font-size="9" font-weight="bold" class="truncate max-w-[170px]">
                    {{ cabinetData.name }}
                </text>
                <line x1="10" y1="67" x2="180" y2="67" stroke="#1e293b" stroke-width="1" />

                <!-- Section 3: Proyek & Klien -->
                <text x="12" y="80" fill="#64748b" font-size="7.5" font-weight="bold">PROYEK 3D :</text>
                <text x="12" y="93" fill="#cbd5e1" font-size="8.5" class="truncate max-w-[170px]">
                    {{ props.sheet.project?.title || 'Proyek Desain 3D' }}
                </text>
                <line x1="10" y1="101" x2="180" y2="101" stroke="#1e293b" stroke-width="1" />

                <!-- Section 4: Ukuran & Spek Teknis -->
                <text x="12" y="114" fill="#64748b" font-size="7.5" font-weight="bold">DIMENSI (P × L × T) :</text>
                <text x="12" y="126" fill="#38bdf8" font-size="8.5" font-family="monospace" font-weight="bold">
                    {{ cabinetData.width }} × {{ cabinetData.depth }} × {{ cabinetData.height }} mm
                </text>

                <!-- Section 5: Skala & Kertas -->
                <rect x="0" y="136" width="190" height="26" fill="#070a14" />
                <line x1="0" y1="136" x2="190" y2="136" stroke="#1e293b" stroke-width="1" />
                <text x="12" y="152" fill="#94a3b8" font-size="8" font-family="monospace">
                    SKALA <tspan fill="#f8fafc" font-weight="bold">{{ props.sheet.scale || '1:20' }}</tspan> • <tspan fill="#f8fafc">{{ props.sheet.paper_size || 'A3' }}</tspan>
                </text>
                <text x="155" y="152" fill="#22c55e" font-size="7.5" font-weight="bold">OK CUT</text>
            </g>
        </svg>
    </div>
</template>

