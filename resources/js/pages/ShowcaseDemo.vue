<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Bookmark,
    Check,
    CheckCircle2,
    Eye,
    Focus,
    GripVertical,
    Hand,
    MapPin,
    Maximize2,
    MessageSquare,
    Minimize2,
    Pencil,
    Plus,
    RotateCw,
    Scissors,
    Send,
    Sparkles,
    Trash2,
    X,
} from '@lucide/vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { MeshoptDecoder } from 'three/examples/jsm/libs/meshopt_decoder.module.js';

// ─── Types ───────────────────────────────────────────────
interface SpatialPin {
    id: number;
    title: string;
    content: string;
    author: string;
    time: string;
    position: { x: number; y: number; z: number };
    resolved: boolean;
}

interface ProjectedPin {
    id: number;
    title: string;
    content: string;
    author: string;
    time: string;
    resolved: boolean;
    screenX: number;
    screenY: number;
    anchorX: number;
    anchorY: number;
    cardX: number;
    cardY: number;
    isRightSide: boolean;
    isVisible: boolean;
}

// ─── Three.js instances ──────────────────────────────────
const canvasContainer = ref<HTMLDivElement | null>(null);
const isLoading = ref(true);
const loadingProgress = ref(0);
let scene: THREE.Scene;
let camera: THREE.PerspectiveCamera;
let renderer: THREE.WebGLRenderer;
let controls: OrbitControls;
let animationFrameId: number;
let modelGroup: THREE.Group;
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2();

// ─── UI State ────────────────────────────────────────────
const interactionMode = ref<'rotate' | 'pan' | 'pin'>('rotate');
const autoRotate = ref(false);
const showAnnotations = ref(true);
const isDrawerOpen = ref(true);
const isFullscreen = ref(false);
const activeCardId = ref<number | null>(1);

// ─── Camera reset ─────────────────────────────────────────
const defaultCamPos    = new THREE.Vector3(12, 6, 14);
const defaultCamTarget = new THREE.Vector3(0, 1.5, 0);

let targetCamPos: THREE.Vector3 | null = null;
let targetLookAt: THREE.Vector3 | null = null;

const applyPreset = (pos: THREE.Vector3, target: THREE.Vector3) => {
    targetCamPos = pos.clone();
    targetLookAt = target.clone();
    autoRotate.value = false;
};

// ─── Dummy camera presets (lokal, tidak disimpan ke DB) ───
interface ShowcasePreset {
    id:   string;
    name: string;
    pos:  THREE.Vector3;
    tgt:  THREE.Vector3;
    desc: string;
}

const showcasePresets: ShowcasePreset[] = [
    {
        id:   'eksterior',
        name: 'Eksterior Utama',
        desc: 'Fasad depan 360°',
        pos:  new THREE.Vector3(12,   6,   14),
        tgt:  new THREE.Vector3(0,    1.5,  0),
    },
    {
        id:   'ruang-tamu',
        name: 'Ruang Tamu',
        desc: 'Interior lantai 1',
        pos:  new THREE.Vector3(-1.2, 1.4,  2.5),
        tgt:  new THREE.Vector3(0.5,  1.2, -1.0),
    },
    {
        id:   'dapur',
        name: 'Dapur & Ruang Makan',
        desc: 'Interior pantry',
        pos:  new THREE.Vector3(2.5,  1.4,  1.8),
        tgt:  new THREE.Vector3(1.2,  1.1, -1.2),
    },
    {
        id:   'kamar-utama',
        name: 'Kamar Utama',
        desc: 'Interior lantai 2',
        pos:  new THREE.Vector3(-0.8, 3.8,  2.2),
        tgt:  new THREE.Vector3(-0.2, 3.2, -0.5),
    },
    {
        id:   'atas',
        name: 'Bird Eye View',
        desc: 'Pandangan dari atas',
        pos:  new THREE.Vector3(0,   18,   0.1),
        tgt:  new THREE.Vector3(0,    0,   0),
    },
];

const isPresetPanelOpen = ref(false);

// ─── Clipping / Sectioning ────────────────────────────────
const clippingEnabled  = ref(false);
const clippingAxis     = ref<'x' | 'y' | 'z'>('y');
const clippingValue    = ref(3.5);
const clippingFlip     = ref(false);
const isClipPanelOpen  = ref(false);
const clippingRange    = ref<{ min: number; max: number; step: number }>({ min: -2, max: 12, step: 0.05 });

function applyClipping(): void {
    if (!renderer) return;
    if (!clippingEnabled.value) {
        renderer.clippingPlanes = [];
        renderer.localClippingEnabled = false;
        return;
    }
    renderer.localClippingEnabled = true;
    const sign = clippingFlip.value ? 1 : -1;
    let normal: THREE.Vector3;
    switch (clippingAxis.value) {
        case 'x': normal = new THREE.Vector3(sign, 0, 0); break;
        case 'z': normal = new THREE.Vector3(0, 0, sign); break;
        default:  normal = new THREE.Vector3(0, sign, 0); break;
    }
    renderer.clippingPlanes = [new THREE.Plane(normal, clippingValue.value * sign * -1)];
}

function resetClipping(): void {
    clippingEnabled.value = false;
    clippingAxis.value    = 'y';
    clippingValue.value   = 3.5;
    clippingFlip.value    = false;
    applyClipping();
}

function updateClippingRange(): void {
    if (!modelGroup.children.length) return;
    const box = new THREE.Box3().setFromObject(modelGroup);
    const min = box.min;
    const max = box.max;
    switch (clippingAxis.value) {
        case 'x': clippingRange.value = { min: parseFloat((min.x - 0.5).toFixed(1)), max: parseFloat((max.x + 0.5).toFixed(1)), step: 0.05 }; break;
        case 'z': clippingRange.value = { min: parseFloat((min.z - 0.5).toFixed(1)), max: parseFloat((max.z + 0.5).toFixed(1)), step: 0.05 }; break;
        default:  clippingRange.value = { min: parseFloat((min.y - 0.5).toFixed(1)), max: parseFloat((max.y + 0.5).toFixed(1)), step: 0.05 };
                  clippingValue.value  = parseFloat(((min.y + max.y) / 2).toFixed(2));
    }
}

watch([clippingEnabled, clippingAxis, clippingValue, clippingFlip], applyClipping);
watch(clippingAxis, updateClippingRange);

// ─── Pin Data (local demo) ───────────────────────────────
const pins = ref<SpatialPin[]>([
    {
        id: 1,
        title: 'Fasad Kaca Eksterior',
        content: 'Spesifikasi panel kaca double-glazing low-e untuk meredam radiasi matahari tropis.',
        author: 'Budi Prasetyo (Klien)',
        time: '1 jam lalu',
        position: { x: -2.8, y: 2.1, z: 3.4 },
        resolved: false,
    },
    {
        id: 2,
        title: 'Sofa Ruang Tamu',
        content: 'Posisikan perabot menghadap void taman terbuka agar sirkulasi udara lebih optimal.',
        author: 'Arsitek Tim',
        time: '30 menit lalu',
        position: { x: -0.6, y: 0.8, z: 0.2 },
        resolved: true,
    },
    {
        id: 3,
        title: 'Lampu Recessed Plafon',
        content: 'Gunakan profil linear LED hangat 2800K di sepanjang cove ceiling lantai atas.',
        author: 'Lighting Designer',
        time: '15 menit lalu',
        position: { x: 0.4, y: 4.2, z: 0.8 },
        resolved: false,
    },
]);

// ─── Projected Pins ──────────────────────────────────────
const projectedPins = ref<ProjectedPin[]>([]);
const visibleProjectedPins = computed(() => projectedPins.value.filter(p => p.isVisible));

// ─── Draggable card offsets ──────────────────────────────
const cardOffsets = ref<Record<number, { dx: number; dy: number }>>({});
const pendingCardOffset = ref({ dx: 0, dy: 0 });

let isDraggingCard = false;
let dragTargetId: number | 'pending' | null = null;
let dragStartPointer = { x: 0, y: 0 };
let dragInitialOffset = { dx: 0, dy: 0 };
let activeDragEl: HTMLElement | null = null;
let activePointerId: number | null = null;

function startDrag(id: number | 'pending', e: PointerEvent) {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    e.stopPropagation(); e.preventDefault();
    isDraggingCard = true; dragTargetId = id;
    dragStartPointer = { x: e.clientX, y: e.clientY };
    activePointerId = e.pointerId;
    activeDragEl = e.currentTarget as HTMLElement;
    try { activeDragEl?.setPointerCapture(e.pointerId); } catch {}
    dragInitialOffset = id === 'pending'
        ? { ...pendingCardOffset.value }
        : { ...(cardOffsets.value[id as number] || { dx: 0, dy: 0 }) };
    window.addEventListener('pointermove', onCardDragMove, { passive: false });
    window.addEventListener('pointerup', onCardDragEnd);
    window.addEventListener('pointercancel', onCardDragEnd);
}

function onCardDragMove(e: PointerEvent) {
    if (!isDraggingCard || dragTargetId === null) return;
    e.preventDefault();
    const dx = dragInitialOffset.dx + (e.clientX - dragStartPointer.x);
    const dy = dragInitialOffset.dy + (e.clientY - dragStartPointer.y);
    if (dragTargetId === 'pending') { pendingCardOffset.value = { dx, dy }; }
    else { cardOffsets.value[dragTargetId as number] = { dx, dy }; }
    updateProjections();
}

function onCardDragEnd() {
    try { activeDragEl?.releasePointerCapture(activePointerId!); } catch {}
    activeDragEl = null; activePointerId = null; isDraggingCard = false; dragTargetId = null;
    window.removeEventListener('pointermove', onCardDragMove);
    window.removeEventListener('pointerup', onCardDragEnd);
    window.removeEventListener('pointercancel', onCardDragEnd);
}

function startTouchDrag(id: number | 'pending', e: TouchEvent) {
    if (e.touches.length !== 1) return;
    e.stopPropagation(); e.preventDefault();
    const t = e.touches[0];
    isDraggingCard = true; dragTargetId = id;
    dragStartPointer = { x: t.clientX, y: t.clientY };
    dragInitialOffset = id === 'pending'
        ? { ...pendingCardOffset.value }
        : { ...(cardOffsets.value[id as number] || { dx: 0, dy: 0 }) };
    const onMove = (ev: TouchEvent) => {
        if (!isDraggingCard || ev.touches.length !== 1) return;
        ev.preventDefault();
        const touch = ev.touches[0];
        const dx = dragInitialOffset.dx + (touch.clientX - dragStartPointer.x);
        const dy = dragInitialOffset.dy + (touch.clientY - dragStartPointer.y);
        if (dragTargetId === 'pending') { pendingCardOffset.value = { dx, dy }; }
        else { cardOffsets.value[dragTargetId as number] = { dx, dy }; }
        updateProjections();
    };
    const onEnd = () => {
        isDraggingCard = false; dragTargetId = null;
        window.removeEventListener('touchmove', onMove);
        window.removeEventListener('touchend', onEnd);
        window.removeEventListener('touchcancel', onEnd);
    };
    window.addEventListener('touchmove', onMove, { passive: false });
    window.addEventListener('touchend', onEnd);
    window.addEventListener('touchcancel', onEnd);
}

// ─── Edit / Delete pin ───────────────────────────────────
const editingPinId = ref<number | null>(null);
const editPinText = ref('');
const editPinTitle = ref('');

function startEditing(pin: SpatialPin, e?: Event) {
    e?.stopPropagation();
    editingPinId.value = pin.id;
    editPinTitle.value = pin.title;
    editPinText.value = pin.content;
    activeCardId.value = pin.id;
}

function cancelEditing(e?: Event) {
    e?.stopPropagation();
    editingPinId.value = null;
}

function saveEditing(id: number) {
    const pin = pins.value.find(p => p.id === id);
    if (pin) { pin.title = editPinTitle.value; pin.content = editPinText.value; }
    editingPinId.value = null;
}

const deletingPinId = ref<number | null>(null);

function promptDelete(id: number, e?: Event) {
    e?.stopPropagation();
    deletingPinId.value = id;
    activeCardId.value = id;
}

function cancelDelete(e?: Event) {
    e?.stopPropagation();
    deletingPinId.value = null;
}

function executeDelete(id: number) {
    pins.value = pins.value.filter(p => p.id !== id);
    delete cardOffsets.value[id];
    if (activeCardId.value === id) activeCardId.value = pins.value[0]?.id ?? null;
    deletingPinId.value = null;
    updateProjections();
}

function toggleResolved(id: number) {
    const pin = pins.value.find(p => p.id === id);
    if (pin) pin.resolved = !pin.resolved;
}

// ─── Pending pin (form input) ────────────────────────────
const pendingPin = ref<{
    x: number; y: number; z: number;
    screenX: number; screenY: number;
    anchorX: number; anchorY: number;
    cardX: number; cardY: number;
    isRightSide: boolean; isVisible: boolean;
} | null>(null);

const newPinTitle = ref('');
const newPinText = ref('');
const newPinInputRef = ref<HTMLTextAreaElement | null>(null);
const isSubmitting = ref(false);

function focusPinInput() {
    nextTick(() => { newPinInputRef.value?.focus({ preventScroll: true }); });
    setTimeout(() => { newPinInputRef.value?.focus({ preventScroll: true }); }, 50);
}

function createPendingPin(hit: { x: number; y: number; z: number }, screenX: number, screenY: number, rect: DOMRect) {
    pendingCardOffset.value = { dx: 0, dy: 0 };
    newPinTitle.value = '';
    newPinText.value = '';
    const defaultIsRight = screenX < rect.width * 0.6;
    const cardWidth = 320;
    const defaultDx = defaultIsRight ? 60 : -350;
    const cardX = Math.max(10, Math.min(rect.width - cardWidth - 10, screenX + defaultDx));
    const cardY = Math.max(20, Math.min(rect.height - 180, screenY - 40));
    const isRightSide = cardX + cardWidth * 0.5 >= screenX;
    pendingPin.value = {
        x: hit.x, y: hit.y, z: hit.z,
        screenX, screenY,
        anchorX: isRightSide ? cardX : cardX + cardWidth,
        anchorY: cardY + 24,
        cardX, cardY, isRightSide, isVisible: true,
    };
    updateProjections();
    focusPinInput();
}

function cancelPendingPin() {
    pendingPin.value = null;
    pendingCardOffset.value = { dx: 0, dy: 0 };
}

function submitPin() {
    if (!pendingPin.value || !newPinText.value.trim()) return;
    isSubmitting.value = true;
    const id = Date.now();
    const defaultTitles = ['Dinding Partisi Akustik', 'Finishing Kusen Titanium', 'Pencahayaan Ambient Warm', 'Dek Kolam Renang', 'Plafon Drop Ceiling', 'Struktur Beton Kantilever'];
    pins.value.push({
        id,
        title: newPinTitle.value.trim() || defaultTitles[pins.value.length % defaultTitles.length],
        content: newPinText.value.trim(),
        author: 'Pengunjung Demo',
        time: 'Baru saja',
        position: { x: pendingPin.value.x, y: pendingPin.value.y, z: pendingPin.value.z },
        resolved: false,
    });
    activeCardId.value = id;
    pendingPin.value = null;
    pendingCardOffset.value = { dx: 0, dy: 0 };
    newPinTitle.value = ''; newPinText.value = '';
    isDrawerOpen.value = true;
    setInteractionMode('rotate');
    isSubmitting.value = false;
    updateProjections();
}

// ─── Projection ──────────────────────────────────────────
function getLeaderLinePath(fromX: number, fromY: number, toX: number, toY: number, isRight: boolean): string {
    const land = 24;
    const kneeX = isRight ? toX - land : toX + land;
    return `M ${fromX} ${fromY} L ${kneeX} ${toY} L ${toX} ${toY}`;
}

function updateProjections() {
    if (!canvasContainer.value || !camera) return;
    const width = canvasContainer.value.clientWidth;
    const height = canvasContainer.value.clientHeight;
    if (!width || !height) return;
    const tmp = new THREE.Vector3();

    // Pending pin
    if (pendingPin.value) {
        tmp.set(pendingPin.value.x, pendingPin.value.y, pendingPin.value.z);
        tmp.project(camera);
        const isVisible = tmp.z < 1.0;
        const sx = (tmp.x * 0.5 + 0.5) * width;
        const sy = (-tmp.y * 0.5 + 0.5) * height;
        const off = pendingCardOffset.value;
        const cardWidth = 320;
        const defaultIsRight = sx < width * 0.6;
        const cardX = sx + (defaultIsRight ? 60 : -350) + off.dx;
        const cardY = sy - 40 + off.dy;
        const isRight = cardX + cardWidth * 0.5 >= sx;
        pendingPin.value.screenX = sx; pendingPin.value.screenY = sy;
        pendingPin.value.cardX = cardX; pendingPin.value.cardY = cardY;
        pendingPin.value.anchorX = isRight ? cardX : cardX + cardWidth;
        pendingPin.value.anchorY = cardY + 24;
        pendingPin.value.isRightSide = isRight;
        pendingPin.value.isVisible = isVisible;
    }

    projectedPins.value = pins.value.map(pin => {
        tmp.set(pin.position.x, pin.position.y, pin.position.z);
        tmp.project(camera!);
        const isVisible = tmp.z < 1.0;
        const sx = (tmp.x * 0.5 + 0.5) * width;
        const sy = (-tmp.y * 0.5 + 0.5) * height;
        const off = cardOffsets.value[pin.id] || { dx: 0, dy: 0 };
        const cardWidth = 280;
        const defaultIsRight = sx < width * 0.6;
        const cardX = sx + (defaultIsRight ? 50 : -310) + off.dx;
        const cardY = sy - 35 + off.dy;
        const isRight = cardX + cardWidth * 0.5 >= sx;
        return {
            id: pin.id, title: pin.title, content: pin.content,
            author: pin.author, time: pin.time, resolved: pin.resolved,
            screenX: sx, screenY: sy,
            anchorX: isRight ? cardX : cardX + cardWidth, anchorY: cardY + 24,
            cardX, cardY, isRightSide: isRight, isVisible,
        };
    });
}

// ─── Interaction ─────────────────────────────────────────
let pointerDownPos = { x: 0, y: 0 };

function onPointerDown(e: MouseEvent) {
    pointerDownPos = { x: e.clientX, y: e.clientY };
}

function onPointerUp(e: MouseEvent) {
    if (Math.hypot(e.clientX - pointerDownPos.x, e.clientY - pointerDownPos.y) > 5) return;
    if (e.button !== 0 || interactionMode.value === 'pan') return;
    if (interactionMode.value !== 'pin' || !canvasContainer.value) return;

    const rect = canvasContainer.value.getBoundingClientRect();
    mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
    raycaster.setFromCamera(mouse, camera);
    const hits = raycaster.intersectObjects(modelGroup.children, true);
    if (hits.length > 0) {
        const h = hits[0].point;
        createPendingPin({ x: h.x, y: h.y, z: h.z }, e.clientX - rect.left, e.clientY - rect.top, rect);
    }
}

const setInteractionMode = (mode: 'rotate' | 'pan' | 'pin') => {
    interactionMode.value = mode;
    if (!controls) return;
    if (mode === 'pan') {
        controls.mouseButtons = { LEFT: THREE.MOUSE.PAN, MIDDLE: THREE.MOUSE.DOLLY, RIGHT: THREE.MOUSE.ROTATE };
        controls.touches = { ONE: THREE.TOUCH.PAN, TWO: THREE.TOUCH.DOLLY_PAN };
    } else {
        controls.mouseButtons = { LEFT: THREE.MOUSE.ROTATE, MIDDLE: THREE.MOUSE.DOLLY, RIGHT: THREE.MOUSE.PAN };
        controls.touches = { ONE: THREE.TOUCH.ROTATE, TWO: THREE.TOUCH.DOLLY_PAN };
    }
};

// ─── Fullscreen ───────────────────────────────────────────
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen().catch(() => {});
    }
};
const handleFullscreenChange = () => { isFullscreen.value = !!document.fullscreenElement; };

// ─── Three.js Init ────────────────────────────────────────
const initThree = () => {
    if (!canvasContainer.value) return;
    const w = canvasContainer.value.clientWidth;
    const h = canvasContainer.value.clientHeight;

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x06070a);
    scene.fog = new THREE.FogExp2(0x06070a, 0.02);

    camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 1000);
    camera.position.set(12, 6, 14);

    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(w, h);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    canvasContainer.value.appendChild(renderer.domElement);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.06;
    controls.enablePan = true;
    controls.screenSpacePanning = true;
    controls.panSpeed = 1.0;
    controls.rotateSpeed = 0.8;
    controls.maxPolarAngle = Math.PI / 2 + 0.05;
    controls.minDistance = 1;
    controls.maxDistance = 45;
    controls.target.set(0, 1.5, 0);
    setInteractionMode('rotate');

    scene.add(new THREE.AmbientLight(0xdde8ff, 0.9));
    const sun = new THREE.DirectionalLight(0xffeedd, 1.8);
    sun.position.set(15, 20, 10); sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048); sun.shadow.camera.far = 60; sun.shadow.bias = -0.0005;
    scene.add(sun);
    const warm = new THREE.PointLight(0xffa755, 3.5, 15);
    warm.position.set(0, 2.2, 0); scene.add(warm);
    const fill = new THREE.DirectionalLight(0x7da4ff, 0.8);
    fill.position.set(-15, 10, -10); scene.add(fill);

    const ground = new THREE.Mesh(
        new THREE.PlaneGeometry(120, 120),
        new THREE.MeshStandardMaterial({ color: 0x070b14, roughness: 0.15, metalness: 0.85 })
    );
    ground.rotation.x = -Math.PI / 2; ground.position.y = -0.05; ground.receiveShadow = true;
    scene.add(ground);

    modelGroup = new THREE.Group(); scene.add(modelGroup);

    const loader = new GLTFLoader();
    loader.setMeshoptDecoder(MeshoptDecoder);
    loader.load(
        '/models/modern_villa.glb',
        gltf => {
            const model = gltf.scene;
            model.traverse(child => {
                if ((child as THREE.Mesh).isMesh) {
                    const m = child as THREE.Mesh;
                    m.castShadow = true; m.receiveShadow = true;
                    const geo = m.geometry;
                    if (geo) {
                        for (let i = 4; i <= 7; i++) {
                            const n = i === 0 ? 'uv' : `uv${i}`;
                            if (geo.hasAttribute(n)) geo.deleteAttribute(n);
                        }
                        const mats = Array.isArray(m.material) ? m.material : [m.material];
                        mats.forEach(mat => {
                            if (!mat) return;
                            const a = mat as any;
                            ['map','normalMap','roughnessMap','metalnessMap','aoMap','emissiveMap','alphaMap','lightMap',
                             'displacementMap','bumpMap','clearcoatMap','clearcoatNormalMap','clearcoatRoughnessMap',
                             'sheenColorMap','sheenRoughnessMap','transmissionMap','thicknessMap'].forEach(p => {
                                const t = a[p] as THREE.Texture | null;
                                if (t?.channel !== undefined && t.channel > 3) t.channel = 0;
                            });
                        });
                    }
                }
            });
            const box = new THREE.Box3().setFromObject(model);
            const c = box.getCenter(new THREE.Vector3());
            model.position.x -= c.x; model.position.y -= box.min.y; model.position.z -= c.z;
            modelGroup.add(model);
            isLoading.value = false;
            updateProjections();
            updateClippingRange();
            applyClipping();
        },
        xhr => { if (xhr.total > 0) loadingProgress.value = Math.round((xhr.loaded / xhr.total) * 100); },
        () => { isLoading.value = false; }
    );

    const animate = () => {
        animationFrameId = requestAnimationFrame(animate);
        controls.autoRotate = autoRotate.value;
        controls.autoRotateSpeed = 1.2;
        if (targetCamPos && targetLookAt) {
            camera.position.lerp(targetCamPos, 0.05);
            controls.target.lerp(targetLookAt, 0.05);
            if (camera.position.distanceTo(targetCamPos) < 0.05) { targetCamPos = null; targetLookAt = null; }
        }
        controls.update();
        renderer.render(scene, camera);
        updateProjections();
    };
    animate();
};

const onResize = () => {
    if (!canvasContainer.value || !renderer || !camera) return;
    const w = canvasContainer.value.clientWidth;
    const h = canvasContainer.value.clientHeight;
    camera.aspect = w / h; camera.updateProjectionMatrix();
    renderer.setSize(w, h); updateProjections();
};

onMounted(() => {
    initThree();
    window.addEventListener('resize', onResize);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
});

onBeforeUnmount(() => {
    cancelAnimationFrame(animationFrameId);
    window.removeEventListener('resize', onResize);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
    if (renderer?.domElement && canvasContainer.value) {
        canvasContainer.value.removeChild(renderer.domElement);
    }
});
</script>

<template>
    <Head title="AETHER 3D — Live Interactive Showcase Demo">
        <meta name="description" content="Jelajahi model 3D arsitektur Modern Villa secara interaktif di browser." />
    </Head>

    <div class="relative h-screen w-screen overflow-hidden bg-[#06070a] text-white flex flex-col select-none">

        <!-- ── TOP APP BAR ── -->
        <header class="relative z-30 flex h-14 shrink-0 items-center justify-between border-b border-white/10 bg-black/80 px-4 sm:px-6 backdrop-blur-2xl">
            <div class="flex items-center gap-3">
                <Link href="/" class="flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10 hover:text-white">
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Kembali ke Beranda</span>
                </Link>
                <div class="h-4 w-px bg-white/10 hidden sm:block"></div>
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold tracking-wider text-white">AETHER 3D</span>
                    <span class="rounded bg-indigo-500/20 border border-indigo-500/30 px-2 py-0.5 text-[10px] font-mono text-indigo-300 hidden sm:inline">LIVE SHOWCASE</span>
                    <span class="text-xs text-neutral-400 hidden md:inline">• Modern Minimalist Villa</span>
                </div>
            </div>
            <div class="flex items-center gap-2.5">
                <button type="button" @click="isDrawerOpen = !isDrawerOpen" class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10">
                    <MessageSquare class="h-3.5 w-3.5 text-indigo-400" />
                    <span>{{ pins.length }} Pin</span>
                </button>
                <button type="button" @click="toggleFullscreen" class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10">
                    <Minimize2 v-if="isFullscreen" class="h-3.5 w-3.5 text-rose-400" />
                    <Maximize2 v-else class="h-3.5 w-3.5 text-neutral-300" />
                    <span class="hidden sm:inline">{{ isFullscreen ? 'Keluar Penuh' : 'Layar Penuh' }}</span>
                </button>
                <Link href="/register" class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-black transition hover:bg-neutral-200">
                    <Sparkles class="h-3 w-3 text-indigo-600" />
                    <span>Buat Proyek 3D Anda</span>
                </Link>
            </div>
        </header>

        <!-- ── MAIN VIEWPORT ── -->
        <div class="relative flex-1 w-full overflow-hidden">

            <!-- Canvas -->
            <div
                ref="canvasContainer"
                class="absolute inset-0 h-full w-full"
                :class="{
                    'cursor-grab active:cursor-grabbing': interactionMode === 'rotate',
                    'cursor-move': interactionMode === 'pan',
                    'cursor-crosshair': interactionMode === 'pin',
                }"
                @pointerdown="onPointerDown"
                @pointerup="onPointerUp"
            ></div>

            <!-- Loading -->
            <div v-if="isLoading" class="pointer-events-none absolute inset-0 z-40 flex flex-col items-center justify-center bg-black/85 backdrop-blur-md text-center p-6">
                <div class="relative flex items-center justify-center">
                    <div class="h-16 w-16 rounded-full border-2 border-indigo-500/20 border-t-indigo-500 animate-spin"></div>
                    <Sparkles class="absolute h-6 w-6 text-indigo-400 animate-pulse" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-white">Memuat Model 3D Arsitektur...</h3>
                <p class="mt-1 text-xs text-neutral-400 max-w-sm">Menguraikan geometri mesh, tekstur interior PBR, dan pencahayaan fotometrik.</p>
                <div class="mt-4 h-1.5 w-48 rounded-full bg-white/10 overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-300" :style="{ width: `${loadingProgress || 5}%` }"></div>
                </div>
                <span class="mt-2 text-[10px] font-mono text-neutral-400">{{ loadingProgress }}% Terunduh</span>
            </div>

            <!-- ── TOOLBAR TOP-LEFT: Controls ── -->
            <div class="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-1.5 rounded-2xl border border-white/15 bg-black/80 p-1.5 shadow-2xl backdrop-blur-xl">
                <span class="text-[11px] font-mono text-neutral-400 px-2 hidden sm:inline">KONTROL:</span>
                <button type="button" @click="setInteractionMode('rotate')" class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all" :class="interactionMode === 'rotate' ? 'bg-white text-black shadow-md font-semibold' : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'">
                    <RotateCw class="h-3.5 w-3.5" /><span>Putar</span>
                </button>
                <button type="button" @click="setInteractionMode('pan')" class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all" :class="interactionMode === 'pan' ? 'bg-white text-black shadow-md font-semibold' : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'">
                    <Hand class="h-3.5 w-3.5" /><span>Geser</span>
                </button>

                <div class="h-4 w-px bg-white/15 mx-0.5"></div>

                <!-- Tombol Preset -->
                <button
                    type="button"
                    @click="isPresetPanelOpen = !isPresetPanelOpen"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="isPresetPanelOpen
                        ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                >
                    <Bookmark class="h-3.5 w-3.5" :class="isPresetPanelOpen ? 'text-amber-400' : ''" />
                    <span>Preset</span>
                    <span class="ml-0.5 text-[10px] font-mono px-1 rounded"
                          :class="isPresetPanelOpen ? 'bg-amber-500/20 text-amber-300' : 'bg-white/10 text-neutral-400'">
                        {{ showcasePresets.length }}
                    </span>
                </button>

                <div class="h-4 w-px bg-white/15 mx-0.5"></div>

                <!-- Tombol Clipping -->
                <button
                    type="button"
                    @click="isClipPanelOpen = !isClipPanelOpen; isPresetPanelOpen = false"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="isClipPanelOpen || clippingEnabled
                        ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30'
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Sectioning / Clipping Tool"
                >
                    <Scissors class="h-3.5 w-3.5" :class="clippingEnabled ? 'text-cyan-400' : ''" />
                    <span>Potong</span>
                    <span v-if="clippingEnabled" class="ml-0.5 h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                </button>
            </div>

            <!-- ── CAMERA PRESET PANEL ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-x-4 scale-95"
                enter-to-class="opacity-100 translate-x-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0 scale-100"
                leave-to-class="opacity-0 -translate-x-4 scale-95"
            >
                <div
                    v-if="isPresetPanelOpen"
                    class="absolute top-16 left-4 z-30 w-64 rounded-2xl border border-white/15 bg-black/90 backdrop-blur-2xl shadow-2xl overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Bookmark class="h-4 w-4 text-amber-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-white">Preset Kamera</span>
                        </div>
                        <button type="button" @click="isPresetPanelOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Preset list -->
                    <div>
                        <button
                            v-for="preset in showcasePresets"
                            :key="preset.id"
                            type="button"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-white/5 transition-colors border-b border-white/5 last:border-0"
                            @click="applyPreset(preset.pos, preset.tgt); isPresetPanelOpen = false"
                        >
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 border border-amber-500/20">
                                <Bookmark class="h-3.5 w-3.5 text-amber-400" />
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-white">{{ preset.name }}</p>
                                <p class="text-[10px] text-neutral-500">{{ preset.desc }}</p>
                            </div>
                        </button>
                    </div>

                    <!-- Note demo -->
                    <div class="px-4 py-3 border-t border-white/8">
                        <p class="text-[10px] font-mono text-neutral-600 leading-relaxed">
                            Preset demo — di versi nyata preset disimpan ke database per proyek.
                        </p>
                    </div>
                </div>
            </Transition>

            <!-- ── CLIPPING / SECTIONING PANEL ── -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-x-4 scale-95"
                enter-to-class="opacity-100 translate-x-0 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0 scale-100"
                leave-to-class="opacity-0 -translate-x-4 scale-95"
            >
                <div
                    v-if="isClipPanelOpen"
                    class="absolute top-16 left-4 z-30 w-72 max-w-[90vw] rounded-2xl border border-white/15 bg-black/90 backdrop-blur-2xl shadow-2xl overflow-hidden"
                >
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-white/10 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Scissors class="h-4 w-4 text-cyan-400" />
                            <span class="text-xs font-bold uppercase tracking-wider text-white">Sectioning Tool</span>
                            <span v-if="clippingEnabled" class="h-1.5 w-1.5 rounded-full bg-cyan-400 animate-pulse"></span>
                        </div>
                        <button type="button" @click="isClipPanelOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="p-4 space-y-4">
                        <!-- Enable toggle -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-white">Aktifkan Potongan</p>
                                <p class="text-[11px] text-neutral-500 mt-0.5">Tampilkan interior tanpa mengubah model</p>
                            </div>
                            <button
                                type="button"
                                @click="clippingEnabled = !clippingEnabled"
                                class="relative h-6 w-11 rounded-full transition-all duration-200"
                                :class="clippingEnabled ? 'bg-cyan-500' : 'bg-white/15'"
                            >
                                <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-200"
                                      :class="clippingEnabled ? 'left-[22px]' : 'left-0.5'"></span>
                            </button>
                        </div>

                        <!-- Axis selector -->
                        <div class="space-y-1.5">
                            <p class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Arah Potongan</p>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="ax in (['x', 'y', 'z'] as const)"
                                    :key="ax"
                                    type="button"
                                    @click="clippingAxis = ax"
                                    class="flex-1 h-8 rounded-lg text-xs font-mono font-semibold uppercase transition-all"
                                    :class="clippingAxis === ax
                                        ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40'
                                        : 'bg-white/5 text-neutral-400 hover:bg-white/10 hover:text-white border border-transparent'"
                                >
                                    {{ ax === 'x' ? '← X →' : ax === 'y' ? '↑ Y ↓' : '↙ Z ↗' }}
                                </button>
                            </div>
                            <p class="text-[10px] text-neutral-600">
                                {{ clippingAxis === 'y' ? 'Potongan horizontal — lantai per lantai' :
                                   clippingAxis === 'x' ? 'Potongan vertikal kiri-kanan' :
                                   'Potongan vertikal depan-belakang' }}
                            </p>
                        </div>

                        <!-- Slider posisi -->
                        <div class="space-y-2" :class="!clippingEnabled && 'opacity-40 pointer-events-none'">
                            <div class="flex items-center justify-between">
                                <p class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Posisi Bidang</p>
                                <span class="text-[11px] font-mono text-cyan-400">{{ clippingValue.toFixed(2) }}m</span>
                            </div>
                            <input
                                type="range"
                                :min="clippingRange.min"
                                :max="clippingRange.max"
                                :step="clippingRange.step"
                                v-model.number="clippingValue"
                                class="w-full h-1.5 rounded-full appearance-none cursor-pointer bg-white/10 accent-cyan-500"
                            />
                            <div class="flex justify-between text-[10px] font-mono text-neutral-600">
                                <span>{{ clippingRange.min.toFixed(1) }}m</span>
                                <span>{{ clippingRange.max.toFixed(1) }}m</span>
                            </div>
                        </div>

                        <!-- Flip direction -->
                        <div class="flex items-center justify-between" :class="!clippingEnabled && 'opacity-40 pointer-events-none'">
                            <p class="text-xs text-neutral-400">Balik arah potongan</p>
                            <button
                                type="button"
                                @click="clippingFlip = !clippingFlip"
                                class="relative h-6 w-11 rounded-full transition-all duration-200"
                                :class="clippingFlip ? 'bg-cyan-500' : 'bg-white/15'"
                            >
                                <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all duration-200"
                                      :class="clippingFlip ? 'left-[22px]' : 'left-0.5'"></span>
                            </button>
                        </div>

                        <!-- Reset -->
                        <button
                            type="button"
                            @click="resetClipping"
                            class="w-full h-8 rounded-xl border border-white/10 bg-white/5 text-xs text-neutral-400 hover:bg-white/10 hover:text-white transition-all"
                        >
                            Reset Potongan
                        </button>
                    </div>
                </div>
            </Transition>

            <!-- ── TOOLBAR BOTTOM-CENTER ── -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex flex-wrap items-center justify-center gap-1.5 sm:gap-2 rounded-full border border-white/15 bg-black/85 p-1.5 sm:p-2 shadow-2xl backdrop-blur-2xl max-w-[95vw]">
                <button type="button" @click="applyPreset(defaultCamPos, defaultCamTarget)" class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium text-neutral-300 hover:bg-white/10 hover:text-white transition-all">
                    <Focus class="h-3.5 w-3.5 text-rose-400" /><span>Pusatkan</span>
                </button>
                <button type="button" @click="toggleFullscreen" class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium text-neutral-300 hover:bg-white/10 hover:text-white transition-all">
                    <Minimize2 v-if="isFullscreen" class="h-3.5 w-3.5 text-rose-400" />
                    <Maximize2 v-else class="h-3.5 w-3.5" />
                    <span class="hidden md:inline">{{ isFullscreen ? 'Keluar Penuh' : 'Layar Penuh' }}</span>
                </button>
                <div class="h-4 w-px bg-white/20 mx-0.5 hidden sm:block"></div>
                <button type="button" @click="autoRotate = !autoRotate" class="rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium transition-all" :class="autoRotate ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:bg-white/10 hover:text-white'">
                    Auto-Orbit
                </button>
                <button
                    type="button"
                    @click="setInteractionMode(interactionMode === 'pin' ? 'rotate' : 'pin')"
                    class="flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition-all shadow-md"
                    :class="interactionMode === 'pin' ? 'bg-emerald-500 text-black ring-2 ring-emerald-300' : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40'"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>{{ interactionMode === 'pin' ? 'Klik di Mana Saja!' : 'Tambah Pin' }}</span>
                </button>
                <button type="button" @click="showAnnotations = !showAnnotations" class="rounded-full p-2 text-neutral-300 hover:bg-white/10 hover:text-white transition-colors">
                    <Eye class="h-4 w-4" :class="showAnnotations ? 'text-indigo-400' : 'text-neutral-500'" />
                </button>
            </div>

            <!-- Pin mode banner -->
            <div v-if="interactionMode === 'pin'" class="absolute top-[72px] left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-1.5 text-xs font-semibold text-black shadow-2xl animate-pulse">
                <MapPin class="h-3.5 w-3.5" />
                <span>Mode Pin Spasial Aktif — Klik pada geometri model 3D!</span>
            </div>

            <!-- Tips -->
            <div class="absolute bottom-4 left-4 z-20 pointer-events-none hidden md:flex items-center gap-2 rounded-full bg-black/60 border border-white/10 px-3 py-1.5 text-[11px] text-neutral-400 backdrop-blur-xl">
                <span>💡 <strong class="text-neutral-300">Tips:</strong> Klik Kiri Drag = Putar | Klik Kanan Drag = Geser | Scroll = Zoom</span>
            </div>

            <!-- ── SVG LEADER LINES ── -->
            <svg v-if="showAnnotations" class="pointer-events-none absolute inset-0 z-10 h-full w-full overflow-visible">
                <defs>
                    <filter id="sc-shadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.5" />
                    </filter>
                    <linearGradient id="sc-lineGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#6366f1" />
                        <stop offset="100%" stop-color="#818cf8" />
                    </linearGradient>
                    <linearGradient id="sc-pendingGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#3b82f6" />
                        <stop offset="100%" stop-color="#6366f1" />
                    </linearGradient>
                </defs>

                <g v-for="item in visibleProjectedPins" :key="'line-' + item.id">
                    <circle :cx="item.screenX" :cy="item.screenY" r="5" :fill="item.resolved ? '#10b981' : '#6366f1'" stroke="#ffffff" stroke-width="2" filter="url(#sc-shadow)" />
                    <circle v-if="activeCardId === item.id" :cx="item.screenX" :cy="item.screenY" r="5" fill="none" :stroke="item.resolved ? '#10b981' : '#6366f1'" stroke-width="1.5">
                        <animate attributeName="r" from="5" to="16" dur="1.5s" repeatCount="indefinite" />
                        <animate attributeName="opacity" from="0.9" to="0" dur="1.5s" repeatCount="indefinite" />
                    </circle>
                    <path :d="getLeaderLinePath(item.screenX, item.screenY, item.anchorX, item.anchorY, item.isRightSide)" fill="none" stroke="url(#sc-lineGrad)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4,3" />
                    <circle :cx="item.anchorX" :cy="item.anchorY" r="3.5" :fill="item.resolved ? '#10b981' : '#6366f1'" />
                </g>

                <g v-if="pendingPin && pendingPin.isVisible">
                    <circle :cx="pendingPin.screenX" :cy="pendingPin.screenY" r="6" fill="#3b82f6" stroke="#ffffff" stroke-width="2" filter="url(#sc-shadow)" />
                    <circle :cx="pendingPin.screenX" :cy="pendingPin.screenY" r="6" fill="none" stroke="#3b82f6" stroke-width="2">
                        <animate attributeName="r" from="6" to="18" dur="1.5s" repeatCount="indefinite" />
                        <animate attributeName="opacity" from="0.9" to="0" dur="1.5s" repeatCount="indefinite" />
                    </circle>
                    <path :d="getLeaderLinePath(pendingPin.screenX, pendingPin.screenY, pendingPin.anchorX, pendingPin.anchorY, pendingPin.isRightSide)" fill="none" stroke="url(#sc-pendingGrad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    <circle :cx="pendingPin.anchorX" :cy="pendingPin.anchorY" r="4" fill="#3b82f6" />
                </g>
            </svg>

            <!-- ── HTML CALLOUT CARDS ── -->
            <div v-if="showAnnotations" class="pointer-events-none absolute inset-0 z-10 overflow-hidden">

                <!-- Existing pin cards (draggable) -->
                <div
                    v-for="item in visibleProjectedPins"
                    :key="'card-' + item.id"
                    :style="{ transform: `translate3d(${item.cardX}px, ${item.cardY}px, 0)` }"
                    class="pointer-events-auto absolute top-0 left-0 w-[280px] max-w-[85vw] touch-none"
                >
                    <div
                        @click="activeCardId = item.id"
                        :class="[
                            'group rounded-2xl border p-3.5 shadow-2xl backdrop-blur-2xl transition-colors animate-fadeIn',
                            activeCardId === item.id
                                ? 'border-indigo-500/50 bg-black/95 ring-2 ring-indigo-500/20'
                                : 'border-white/15 bg-black/85 hover:border-white/25',
                        ]"
                    >
                        <!-- Drag handle + header -->
                        <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2 mb-2 select-none cursor-grab active:cursor-grabbing touch-none"
                            @pointerdown="startDrag(item.id, $event)"
                            @touchstart="startTouchDrag(item.id, $event)"
                        >
                            <div class="flex items-center gap-1.5 min-w-0">
                                <GripVertical class="h-4 w-4 text-neutral-500 hover:text-neutral-300 shrink-0" />
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold" :class="item.resolved ? 'bg-emerald-600 text-white' : 'bg-indigo-600 text-white'">
                                    {{ item.id }}
                                </span>
                                <span class="text-xs font-semibold text-white truncate">{{ item.title }}</span>
                            </div>
                            <div class="flex items-center gap-1 shrink-0" @pointerdown.stop @touchstart.stop>
                                <button v-if="editingPinId !== item.id && deletingPinId !== item.id" @click.stop="startEditing(item as any, $event)" class="rounded p-1 text-neutral-500 hover:bg-white/10 hover:text-white transition">
                                    <Pencil class="h-3 w-3" />
                                </button>
                                <button v-if="deletingPinId !== item.id" @click.stop="promptDelete(item.id, $event)" class="rounded p-1 text-neutral-500 hover:bg-rose-500/20 hover:text-rose-400 transition">
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Delete confirm -->
                        <div v-if="deletingPinId === item.id" class="rounded-2xl bg-rose-950/50 border border-rose-500/30 p-2.5 text-xs space-y-2" @pointerdown.stop @touchstart.stop>
                            <p class="flex items-center gap-1.5 text-rose-200 font-semibold"><Trash2 class="h-3.5 w-3.5 text-rose-400 shrink-0" /> Hapus pin ini?</p>
                            <div class="flex justify-end gap-1.5">
                                <button @click.stop="cancelDelete($event)" class="rounded-lg px-2 py-1 text-neutral-300 hover:bg-white/10">Batal</button>
                                <button @click.stop="executeDelete(item.id)" class="flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1 font-semibold text-white hover:bg-rose-500">
                                    <Trash2 class="h-3 w-3" /> Ya, Hapus
                                </button>
                            </div>
                        </div>

                        <!-- Read mode -->
                        <div v-else-if="editingPinId !== item.id">
                            <p class="text-xs text-neutral-200 leading-relaxed break-words line-clamp-4">{{ item.content }}</p>
                            <div class="mt-3 flex items-center justify-between text-[10px] pt-2 border-t border-white/10">
                                <button @click.stop="toggleResolved(item.id)" class="font-medium transition-colors" :class="item.resolved ? 'text-emerald-400 hover:text-emerald-300' : 'text-amber-400 hover:text-amber-300'">
                                    {{ item.resolved ? '✓ Selesai' : '○ Tandai Selesai' }}
                                </button>
                                <span class="text-neutral-500 font-mono">{{ item.time }}</span>
                            </div>
                        </div>

                        <!-- Edit mode -->
                        <div v-else class="space-y-2" @pointerdown.stop @touchstart.stop>
                            <input v-model="editPinTitle" class="w-full rounded-xl border border-white/15 bg-black/80 px-2 py-1.5 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-none" placeholder="Judul pin..." />
                            <textarea v-model="editPinText" rows="3" class="w-full resize-none rounded-xl border border-white/15 bg-black/80 p-2 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-none" autofocus></textarea>
                            <div class="flex justify-end gap-1.5">
                                <button @click.stop="cancelEditing($event)" class="rounded-lg px-2 py-1 text-neutral-400 hover:bg-white/10">Batal</button>
                                <button @click.stop="saveEditing(item.id)" class="flex items-center gap-1 rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-500">
                                    <Check class="h-3 w-3" /> Simpan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending pin form -->
                <div
                    v-if="pendingPin && pendingPin.isVisible"
                    :style="{ transform: `translate3d(${pendingPin.cardX}px, ${pendingPin.cardY}px, 0)` }"
                    class="pointer-events-auto absolute top-0 left-0 w-[320px] max-w-[90vw] touch-none"
                >
                    <div class="rounded-2xl border-2 border-blue-500 bg-black/95 p-3.5 shadow-2xl backdrop-blur-2xl ring-4 ring-blue-500/20">
                        <div class="flex items-center justify-between pb-2 border-b border-white/10 select-none cursor-grab active:cursor-grabbing"
                            @pointerdown="startDrag('pending', $event)"
                            @touchstart="startTouchDrag('pending', $event)"
                        >
                            <div class="flex items-center gap-1.5">
                                <GripVertical class="h-4 w-4 text-blue-400" />
                                <div class="h-2 w-2 rounded-full bg-blue-500 animate-pulse"></div>
                                <span class="text-xs font-semibold text-white">Tambah Catatan Revisi</span>
                            </div>
                            <button @click="cancelPendingPin" class="rounded p-1 text-neutral-400 hover:bg-white/10 hover:text-white" @pointerdown.stop @touchstart.stop>
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                        <form @submit.prevent="submitPin" class="mt-2.5 space-y-2" @pointerdown.stop @touchstart.stop>
                            <input v-model="newPinTitle" class="w-full rounded-xl border border-white/15 bg-black/80 px-2 py-1.5 text-xs text-white placeholder-neutral-500 focus:border-blue-500 focus:outline-none" placeholder="Judul (opsional)..." />
                            <textarea ref="newPinInputRef" v-model="newPinText" placeholder="Tulis catatan revisi atau feedback arsitektur..." rows="3" required class="w-full resize-none rounded-xl border border-white/15 bg-black/80 p-2 text-xs text-white placeholder-neutral-500 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"></textarea>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="cancelPendingPin" class="rounded-lg px-2.5 py-1 text-xs text-neutral-400 hover:bg-white/10">Batal</button>
                                <button type="submit" :disabled="isSubmitting || !newPinText.trim()" class="flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                                    <Send class="h-3 w-3" /> Simpan Pin
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ── PANEL KANAN: Spatial Comments Thread ── -->
            <aside
                v-if="isDrawerOpen"
                class="absolute top-4 right-4 bottom-20 z-20 w-80 max-w-[90vw] rounded-3xl border border-white/15 bg-black/85 p-5 shadow-2xl backdrop-blur-2xl flex flex-col justify-between overflow-hidden"
            >
                <div class="flex flex-col min-h-0">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3 flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <MessageSquare class="h-4 w-4 text-indigo-400" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-white">Catatan Revisi ({{ pins.length }})</h3>
                        </div>
                        <button type="button" @click="isDrawerOpen = false" class="text-neutral-400 hover:text-white transition-colors">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Scrollable list -->
                    <div class="mt-4 space-y-2.5 overflow-y-auto pr-1 flex-1">
                        <div v-if="pins.length === 0" class="py-6 text-center text-xs text-neutral-500 space-y-3">
                            <p>Belum ada pin. Klik "+ Tambah Pin" lalu klik pada model.</p>
                            <button @click="setInteractionMode('pin')" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-500/30 px-3 py-1.5 text-xs font-medium text-emerald-300 hover:bg-emerald-500/30 transition">
                                <Plus class="h-3.5 w-3.5" /> Tambah Pin Pertama
                            </button>
                        </div>

                        <div
                            v-for="pin in pins"
                            :key="pin.id"
                            @click="activeCardId = pin.id"
                            class="rounded-2xl border p-3 cursor-pointer transition-all duration-200"
                            :class="activeCardId === pin.id
                                ? 'border-indigo-500/50 bg-indigo-500/10 shadow-lg'
                                : 'border-white/10 bg-white/[0.02] hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold" :class="pin.resolved ? 'bg-emerald-600 text-white' : 'bg-indigo-600 text-white'">
                                        {{ pin.id }}
                                    </span>
                                    <span class="font-semibold text-white truncate max-w-[130px]">{{ pin.title }}</span>
                                </div>
                                <span class="text-[10px] font-mono" :class="pin.resolved ? 'text-emerald-400' : 'text-amber-400'">
                                    {{ pin.resolved ? 'Resolved' : 'Pending' }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-xs text-neutral-300 line-clamp-2">{{ pin.content }}</p>
                            <div class="mt-2 flex items-center justify-between text-[10px] text-neutral-500">
                                <span>{{ pin.author }}</span>
                                <span>{{ pin.time }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer hint -->
                <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-3 text-xs text-neutral-400 flex-shrink-0">
                    <div class="flex items-center gap-1.5 text-indigo-400 font-medium text-[11px] mb-1">
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        <span>Raycasting Spasial 3D</span>
                    </div>
                    <p class="text-[10px] text-neutral-400 leading-tight">
                        Klik "+ Tambah Pin" lalu klik titik mana saja pada geometri model 3D untuk menambahkan catatan revisi.
                    </p>
                </div>
            </aside>

        </div>
    </div>
</template>

<style scoped>
canvas { touch-action: none; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
