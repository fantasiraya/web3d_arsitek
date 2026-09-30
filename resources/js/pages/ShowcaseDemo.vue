<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { 
    ArrowLeft, 
    RotateCw, 
    Hand, 
    Focus,
    Maximize2,
    Minimize2,
    MapPin, 
    Plus, 
    Eye, 
    Trash2, 
    CheckCircle2, 
    Layers, 
    Home, 
    Sparkles, 
    MessageSquare, 
    Compass, 
    X,
    GripVertical,
    Send
} from '@lucide/vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { MeshoptDecoder } from 'three/examples/jsm/libs/meshopt_decoder.module.js';

interface SpatialPin {
    id: number;
    title: string;
    comment: string;
    author: string;
    time: string;
    position: THREE.Vector3;
    screenX: number;
    screenY: number;
    resolved: boolean;
}

const canvasContainer = ref<HTMLDivElement | null>(null);
const isLoading = ref(true);
const loadingProgress = ref(0);
const loadError = ref<string | null>(null);

// Three.js instances
let scene: THREE.Scene;
let camera: THREE.PerspectiveCamera;
let renderer: THREE.WebGLRenderer;
let controls: OrbitControls;
let animationFrameId: number;
let modelGroup: THREE.Group;
const raycaster = new THREE.Raycaster();
const mouse = new THREE.Vector2();

// Interaction States
const interactionMode = ref<'rotate' | 'pan' | 'pin'>('rotate');
const autoRotate = ref(false);
const showAnnotations = ref(true);
const activePinId = ref<number | null>(1);
const isDrawerOpen = ref(true);
const currentPreset = ref('Eksterior Utama');

// Preset Camera Angles
const cameraPresets = [
    {
        name: 'Eksterior Utama',
        icon: Home,
        desc: 'Tampak Fasad 360°',
        pos: new THREE.Vector3(12, 6, 14),
        target: new THREE.Vector3(0, 1.5, 0)
    },
    {
        name: 'Ruang Tamu',
        icon: Layers,
        desc: 'Interior Lantai 1',
        pos: new THREE.Vector3(-1.2, 1.4, 2.5),
        target: new THREE.Vector3(0.5, 1.2, -1.0)
    },
    {
        name: 'Dapur & Makan',
        icon: Compass,
        desc: 'Interior Pantry',
        pos: new THREE.Vector3(2.5, 1.4, 1.8),
        target: new THREE.Vector3(1.2, 1.1, -1.2)
    },
    {
        name: 'Kamar Utama',
        icon: Sparkles,
        desc: 'Interior Lantai 2',
        pos: new THREE.Vector3(-0.8, 3.8, 2.2),
        target: new THREE.Vector3(-0.2, 3.2, -0.5)
    }
];

// Spatial Pins Reactive State
const pins = ref<SpatialPin[]>([
    {
        id: 1,
        title: 'Fasad Kaca Eksterior',
        comment: 'Spesifikasi panel kaca double-glazing low-e untuk meredam radiasi matahari tropis.',
        author: 'Budi Prasetyo (Klien)',
        time: '1 jam lalu',
        position: new THREE.Vector3(-2.8, 2.1, 3.4),
        screenX: 0,
        screenY: 0,
        resolved: false
    },
    {
        id: 2,
        title: 'Sofa Ruang Tamu',
        comment: 'Posisikan perabot menghadap void taman terbuka agar sirkulasi udara lebih optimal.',
        author: 'Arsitek Tim',
        time: '30 menit lalu',
        position: new THREE.Vector3(-0.6, 0.8, 0.2),
        screenX: 0,
        screenY: 0,
        resolved: true
    },
    {
        id: 3,
        title: 'Lampu Recessed Plafon',
        comment: 'Gunakan profil linear LED hangat 2800K di sepanjang cove ceiling lantai atas.',
        author: 'Lighting Designer',
        time: '15 menit lalu',
        position: new THREE.Vector3(0.4, 4.2, 0.8),
        screenX: 0,
        screenY: 0,
        resolved: false
    }
]);

// Project 3D vector coordinates to screen 2D pixels
const updateScreenCoordinates = () => {
    if (!camera || !canvasContainer.value) return;
    const width = canvasContainer.value.clientWidth;
    const height = canvasContainer.value.clientHeight;

    pins.value.forEach(pin => {
        const v = pin.position.clone();
        v.project(camera);

        // Check if point is in front of the camera
        const isBehind = v.z > 1;
        if (isBehind) {
            pin.screenX = -9999;
            pin.screenY = -9999;
        } else {
            pin.screenX = Math.round(((v.x + 1) * width) / 2);
            pin.screenY = Math.round(((-v.y + 1) * height) / 2);
        }
    });
};

// Smooth Camera Transition (Lerp)
let targetCamPos: THREE.Vector3 | null = null;
let targetLookAt: THREE.Vector3 | null = null;

const applyPreset = (presetName: string) => {
    currentPreset.value = presetName;
    const preset = cameraPresets.find(p => p.name === presetName);
    if (!preset) return;

    targetCamPos = preset.pos.clone();
    targetLookAt = preset.target.clone();
    autoRotate.value = false;
};

// Reset / Frame Model View
const resetModelView = () => {
    applyPreset('Eksterior Utama');
};

// Interaction Mode Setting (Putar, Geser, Pin)
const setInteractionMode = (mode: 'rotate' | 'pan' | 'pin') => {
    interactionMode.value = mode;
    if (!controls) return;

    if (mode === 'pan') {
        controls.mouseButtons = {
            LEFT: THREE.MOUSE.PAN,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.ROTATE,
        };
        controls.touches = {
            ONE: THREE.TOUCH.PAN,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
    } else if (mode === 'rotate') {
        controls.mouseButtons = {
            LEFT: THREE.MOUSE.ROTATE,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.PAN,
        };
        controls.touches = {
            ONE: THREE.TOUCH.ROTATE,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
    } else if (mode === 'pin') {
        controls.mouseButtons = {
            LEFT: THREE.MOUSE.ROTATE,
            MIDDLE: THREE.MOUSE.DOLLY,
            RIGHT: THREE.MOUSE.PAN,
        };
        controls.touches = {
            ONE: THREE.TOUCH.ROTATE,
            TWO: THREE.TOUCH.DOLLY_PAN,
        };
    }
};

// Fullscreen State & Methods
const isFullscreen = ref(false);

const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(() => {
            isFullscreen.value = true;
        }).catch(err => {
            console.error('Error attempting to enable fullscreen:', err);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen().then(() => {
                isFullscreen.value = false;
            }).catch(err => {
                console.error('Error attempting to exit fullscreen:', err);
            });
        }
    }
};

const handleFullscreenChange = () => {
    isFullscreen.value = !!document.fullscreenElement;
};

// Canvas Click for Unlimited Spatial Pin Dropping
const onCanvasClick = (e: MouseEvent) => {
    if (interactionMode.value !== 'pin' || !canvasContainer.value) return;

    const rect = canvasContainer.value.getBoundingClientRect();
    mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(mouse, camera);
    const intersects = raycaster.intersectObjects(modelGroup.children, true);

    if (intersects.length > 0) {
        const hit = intersects[0];
        const newId = pins.value.length + 1;
        const defaultTitles = [
            'Dinding Partisi Akustik',
            'Finishing Kusen Titanium',
            'Pencahayaan Ambient Warm',
            'Dek Kolam Renang',
            'Plafon Drop Ceiling',
            'Struktur Beton Kantilever'
        ];
        const title = defaultTitles[(newId - 1) % defaultTitles.length];

        pins.value.push({
            id: newId,
            title: `${title} #${newId}`,
            comment: `Catatan revisi spasial pada koordinat X: ${hit.point.x.toFixed(2)}m, Y: ${hit.point.y.toFixed(2)}m, Z: ${hit.point.z.toFixed(2)}m.`,
            author: 'Klien (Pengunjung)',
            time: 'Baru saja',
            position: hit.point.clone(),
            screenX: 0,
            screenY: 0,
            resolved: false
        });

        activePinId.value = newId;
        updateScreenCoordinates();
    }
};

const deletePin = (id: number) => {
    pins.value = pins.value.filter(p => p.id !== id);
    if (activePinId.value === id) {
        activePinId.value = pins.value[0]?.id ?? null;
    }
};

const togglePinResolved = (id: number) => {
    const pin = pins.value.find(p => p.id === id);
    if (pin) {
        pin.resolved = !pin.resolved;
    }
};

// Setup Three.js Scene
const initThree = () => {
    if (!canvasContainer.value) return;

    const width = canvasContainer.value.clientWidth;
    const height = canvasContainer.value.clientHeight;

    // 1. Scene
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x06070a);
    scene.fog = new THREE.FogExp2(0x06070a, 0.02);

    // 2. Camera
    camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.set(12, 6, 14);

    // 3. Renderer
    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    canvasContainer.value.appendChild(renderer.domElement);

    // 4. Controls
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.06;
    controls.enablePan = true;
    controls.screenSpacePanning = true;
    controls.panSpeed = 1.0;
    controls.rotateSpeed = 0.8;
    controls.maxPolarAngle = Math.PI / 2 + 0.05; // Don't flip below ground
    controls.minDistance = 1;
    controls.maxDistance = 45;
    controls.target.set(0, 1.5, 0);
    setInteractionMode(interactionMode.value);

    // 5. Lighting Setup (Photometric Warm Interior & Cool Dusk Exterior)
    const ambientLight = new THREE.AmbientLight(0xdde8ff, 0.9);
    scene.add(ambientLight);

    const sunLight = new THREE.DirectionalLight(0xffeedd, 1.8);
    sunLight.position.set(15, 20, 10);
    sunLight.castShadow = true;
    sunLight.shadow.mapSize.width = 2048;
    sunLight.shadow.mapSize.height = 2048;
    sunLight.shadow.camera.near = 0.5;
    sunLight.shadow.camera.far = 60;
    sunLight.shadow.bias = -0.0005;
    scene.add(sunLight);

    const interiorWarmLight = new THREE.PointLight(0xffa755, 3.5, 15);
    interiorWarmLight.position.set(0, 2.2, 0);
    scene.add(interiorWarmLight);

    const fillCoolLight = new THREE.DirectionalLight(0x7da4ff, 0.8);
    fillCoolLight.position.set(-15, 10, -10);
    scene.add(fillCoolLight);

    // 6. Ground Water Mirror Plane
    const groundGeo = new THREE.PlaneGeometry(120, 120);
    const groundMat = new THREE.MeshStandardMaterial({
        color: 0x070b14,
        roughness: 0.15,
        metalness: 0.85
    });
    const ground = new THREE.Mesh(groundGeo, groundMat);
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.05;
    ground.receiveShadow = true;
    scene.add(ground);

    // 7. Load GLB Model with MeshoptDecoder
    modelGroup = new THREE.Group();
    scene.add(modelGroup);

    const loader = new GLTFLoader();
    loader.setMeshoptDecoder(MeshoptDecoder);

    loader.load(
        '/models/modern_villa.glb',
        gltf => {
            const model = gltf.scene;
            model.traverse(child => {
                if ((child as THREE.Mesh).isMesh) {
                    const m = child as THREE.Mesh;
                    m.castShadow = true;
                    m.receiveShadow = true;

                    // Three.js only supports uv, uv1, uv2, uv3 (channels 0-3).
                    // Remove any UV attributes beyond channel 3 to prevent
                    // "uv7: undeclared identifier" shader compile errors.
                    const geo = m.geometry;
                    if (geo) {
                        const UV_MAX = 3;
                        for (let i = UV_MAX + 1; i <= 7; i++) {
                            const attrName = i === 0 ? 'uv' : `uv${i}`;
                            if (geo.hasAttribute(attrName)) {
                                geo.deleteAttribute(attrName);
                            }
                        }

                        // Also strip UV references from material maps that point
                        // to unsupported channels, to avoid follow-up errors.
                        const mats = Array.isArray(m.material) ? m.material : [m.material];
                        mats.forEach(mat => {
                            if (!mat) return;
                            const anyMat = mat as any;
                            const mapProps = [
                                'map', 'normalMap', 'roughnessMap', 'metalnessMap',
                                'aoMap', 'emissiveMap', 'alphaMap', 'lightMap',
                                'displacementMap', 'bumpMap', 'clearcoatMap',
                                'clearcoatNormalMap', 'clearcoatRoughnessMap',
                                'sheenColorMap', 'sheenRoughnessMap', 'specularMap',
                                'specularColorMap', 'specularIntensityMap',
                                'transmissionMap', 'thicknessMap', 'iridescenceMap',
                            ];
                            mapProps.forEach(prop => {
                                const tex = anyMat[prop] as THREE.Texture | null | undefined;
                                if (tex && tex.channel !== undefined && tex.channel > UV_MAX) {
                                    tex.channel = 0;
                                }
                            });
                        });
                    }
                }
            });

            // Center and scale model properly
            const box = new THREE.Box3().setFromObject(model);
            const center = box.getCenter(new THREE.Vector3());
            const size = box.getSize(new THREE.Vector3());
            
            // Adjust position so foundation touches ground
            model.position.x -= center.x;
            model.position.y -= box.min.y;
            model.position.z -= center.z;

            modelGroup.add(model);
            isLoading.value = false;
            updateScreenCoordinates();
        },
        xhr => {
            if (xhr.total > 0) {
                loadingProgress.value = Math.round((xhr.loaded / xhr.total) * 100);
            }
        },
        error => {
            console.error('Error loading modern villa GLB:', error);
            loadError.value = 'Gagal memuat model 3D. Menyiapkan visualisasi alternatif.';
            isLoading.value = false;
        }
    );

    // 8. Animation Loop
    const animate = () => {
        animationFrameId = requestAnimationFrame(animate);

        // Auto rotate logic
        if (autoRotate.value) {
            controls.autoRotate = true;
            controls.autoRotateSpeed = 1.2;
        } else {
            controls.autoRotate = false;
        }

        // Camera Lerp Transition
        if (targetCamPos && targetLookAt) {
            camera.position.lerp(targetCamPos, 0.05);
            controls.target.lerp(targetLookAt, 0.05);

            if (camera.position.distanceTo(targetCamPos) < 0.05) {
                targetCamPos = null;
                targetLookAt = null;
            }
        }

        controls.update();
        renderer.render(scene, camera);
        updateScreenCoordinates();
    };

    animate();
};

const onResize = () => {
    if (!canvasContainer.value || !renderer || !camera) return;
    const width = canvasContainer.value.clientWidth;
    const height = canvasContainer.value.clientHeight;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height);
    updateScreenCoordinates();
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
    if (renderer && renderer.domElement && canvasContainer.value) {
        canvasContainer.value.removeChild(renderer.domElement);
    }
});
</script>

<template>
    <Head title="AETHER 3D — Live Interactive Showcase Demo (Villa 3D Eksterior & Interior)">
        <meta
            name="description"
            content="Jelajahi model 3D arsitektur Modern Villa secara interaktif di browser. Nikmati navigasi 360°, inspeksi interior-eksterior, dan penambahan pin revisi spasial secara real-time."
        />
    </Head>

    <div class="relative h-screen w-screen overflow-hidden bg-[#06070a] text-white flex flex-col select-none">
        
        <!-- Top App Bar -->
        <header class="relative z-30 flex h-14 shrink-0 items-center justify-between border-b border-white/10 bg-black/80 px-4 sm:px-6 backdrop-blur-2xl">
            <div class="flex items-center gap-3">
                <Link
                    href="/"
                    class="flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10 hover:text-white"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Kembali ke Beranda</span>
                </Link>

                <div class="h-4 w-px bg-white/10 hidden sm:block"></div>

                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold tracking-wider text-white">AETHER 3D</span>
                    <span class="rounded bg-indigo-500/20 border border-indigo-500/30 px-2 py-0.5 text-[10px] font-mono text-indigo-300">
                        LIVE SHOWCASE
                    </span>
                    <span class="text-xs text-neutral-400 hidden md:inline">
                        • Modern Minimalist Villa (Eksterior & Interior)
                    </span>
                </div>
            </div>

            <!-- Right Actions -->
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    @click="isDrawerOpen = !isDrawerOpen"
                    class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                >
                    <MessageSquare class="h-3.5 w-3.5 text-indigo-400" />
                    <span>{{ pins.length }} Pin Catatan</span>
                </button>

                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium text-neutral-200 transition hover:bg-white/10"
                    :title="isFullscreen ? 'Keluar Layar Penuh (Esc)' : 'Layar Penuh (Fullscreen)'"
                >
                    <Minimize2 v-if="isFullscreen" class="h-3.5 w-3.5 text-rose-400" />
                    <Maximize2 v-else class="h-3.5 w-3.5 text-neutral-300" />
                    <span class="hidden sm:inline">{{ isFullscreen ? 'Keluar Penuh' : 'Layar Penuh' }}</span>
                </button>

                <Link
                    href="/register"
                    class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-black transition hover:bg-neutral-200"
                >
                    <Sparkles class="h-3 w-3 text-indigo-600" />
                    <span>Buat Proyek 3D Anda</span>
                </Link>
            </div>
        </header>

        <!-- Main Viewport Canvas -->
        <div class="relative flex-1 w-full overflow-hidden">
            
            <!-- Three.js Canvas Container -->
            <div
                ref="canvasContainer"
                class="absolute inset-0 h-full w-full"
                :class="{
                    'cursor-grab active:cursor-grabbing': interactionMode === 'rotate',
                    'cursor-move': interactionMode === 'pan',
                    'cursor-crosshair': interactionMode === 'pin'
                }"
                @click="onCanvasClick"
            ></div>

            <!-- Loading Spinner & Progress Overlay -->
            <div
                v-if="isLoading"
                class="pointer-events-none absolute inset-0 z-40 flex flex-col items-center justify-center bg-black/85 backdrop-blur-md text-center p-6"
            >
                <div class="relative flex items-center justify-center">
                    <div class="h-16 w-16 rounded-full border-2 border-indigo-500/20 border-t-indigo-500 animate-spin"></div>
                    <Sparkles class="absolute h-6 w-6 text-indigo-400 animate-pulse" />
                </div>
                <h3 class="mt-4 text-base font-semibold text-white">Memuat Model 3D Arsitektur...</h3>
                <p class="mt-1 text-xs text-neutral-400 max-w-sm">
                    Menguraikan geometri mesh, tekstur interior PBR, dan pencahayaan fotometrik.
                </p>
                <div class="mt-4 h-1.5 w-48 rounded-full bg-white/10 overflow-hidden">
                    <div
                        class="h-full bg-indigo-500 rounded-full transition-all duration-300"
                        :style="{ width: `${loadingProgress}%` }"
                    ></div>
                </div>
                <span class="mt-2 text-[10px] font-mono text-neutral-400">{{ loadingProgress }}% Terunduh</span>
            </div>

            <!-- Floating Top Camera Preset Toolbar -->
            <div class="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-1.5 rounded-2xl border border-white/15 bg-black/80 p-1.5 shadow-2xl backdrop-blur-xl">
                <span class="text-[11px] font-mono text-neutral-400 px-2 hidden sm:inline">PRESET:</span>
                <button
                    v-for="preset in cameraPresets"
                    :key="preset.name"
                    type="button"
                    @click="applyPreset(preset.name)"
                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-medium transition-all"
                    :class="currentPreset === preset.name 
                        ? 'bg-white text-black shadow-md font-semibold' 
                        : 'bg-white/5 text-neutral-300 hover:bg-white/10 hover:text-white'"
                >
                    <component :is="preset.icon" class="h-3.5 w-3.5" />
                    <span>{{ preset.name }}</span>
                </button>
            </div>

            <!-- Floating Interaction Modes Toolbar -->
            <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex flex-wrap items-center justify-center gap-1.5 sm:gap-2 rounded-full border border-white/15 bg-black/85 p-1.5 sm:p-2 shadow-2xl backdrop-blur-2xl max-w-[95vw]">
                <!-- Rotate Mode Button -->
                <button
                    type="button"
                    @click="setInteractionMode('rotate')"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-medium transition-all"
                    :class="interactionMode === 'rotate' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Putar kamera secara dinamis (Orbit)"
                >
                    <RotateCw class="h-3.5 w-3.5" />
                    <span>Putar</span>
                </button>

                <!-- Pan Mode Button -->
                <button
                    type="button"
                    @click="setInteractionMode('pan')"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-medium transition-all"
                    :class="interactionMode === 'pan' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-300 hover:bg-white/10 hover:text-white'"
                    title="Geser kamera ke kanan, kiri, atas, bawah (Pan)"
                >
                    <Hand class="h-3.5 w-3.5" />
                    <span>Geser</span>
                </button>

                <!-- Reset Camera / Pusatkan Kembali -->
                <button
                    type="button"
                    @click="resetModelView"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-medium text-neutral-300 hover:bg-white/10 hover:text-white transition-all"
                    title="Pusatkan kembali model 3D di tengah layar"
                >
                    <Focus class="h-3.5 w-3.5 text-indigo-400" />
                    <span>Pusatkan</span>
                </button>

                <!-- Fullscreen Toggle Button -->
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 sm:px-3.5 sm:py-2 text-xs font-medium text-neutral-300 hover:bg-white/10 hover:text-white transition-all"
                    :title="isFullscreen ? 'Keluar Layar Penuh (Esc)' : 'Tampilan Layar Penuh (Fullscreen)'"
                >
                    <Minimize2 v-if="isFullscreen" class="h-3.5 w-3.5 text-rose-400" />
                    <Maximize2 v-else class="h-3.5 w-3.5 text-slate-300" />
                    <span class="hidden md:inline">{{ isFullscreen ? 'Keluar Penuh' : 'Layar Penuh' }}</span>
                </button>

                <div class="h-4 w-px bg-white/20 mx-0.5 hidden sm:block"></div>

                <!-- Auto Rotate Toggle -->
                <button
                    type="button"
                    @click="autoRotate = !autoRotate"
                    class="rounded-full px-3 py-1.5 sm:py-2 text-xs font-medium transition-all"
                    :class="autoRotate ? 'bg-purple-600 text-white' : 'text-neutral-400 hover:bg-white/10 hover:text-white'"
                    title="Rotasi otomatis melingkar"
                >
                    <span>Auto-Orbit</span>
                </button>

                <!-- Add Pin Mode Button (Unlimited) -->
                <button
                    type="button"
                    @click="interactionMode = interactionMode === 'pin' ? 'rotate' : 'pin'"
                    class="flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold transition-all shadow-md"
                    :class="interactionMode === 'pin' 
                        ? 'bg-emerald-500 text-black ring-2 ring-emerald-300' 
                        : 'bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 border border-emerald-500/40'"
                >
                    <Plus class="h-3.5 w-3.5" />
                    <span>{{ interactionMode === 'pin' ? 'Klik di Mana Saja!' : '+ Tambah Pin' }}</span>
                </button>

                <!-- Toggle Annotations Visibility -->
                <button
                    type="button"
                    @click="showAnnotations = !showAnnotations"
                    class="rounded-full p-2 text-neutral-300 hover:bg-white/10 hover:text-white transition-colors"
                    :title="showAnnotations ? 'Sembunyikan Pin' : 'Tampilkan Pin'"
                >
                    <Eye class="h-4 w-4" :class="showAnnotations ? 'text-indigo-400' : 'text-neutral-500'" />
                </button>
            </div>

            <!-- Active Pin Placement Mode Banner -->
            <div
                v-if="interactionMode === 'pin'"
                class="absolute top-18 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 rounded-full bg-emerald-500 px-4 py-1.5 text-xs font-semibold text-black shadow-2xl animate-pulse"
            >
                <MapPin class="h-3.5 w-3.5" />
                <span>Mode Pin Spasial Aktif — Klik pada fasad luar atau furnitur interior untuk menambahkan pin!</span>
            </div>

            <!-- 2D Overlay for 3D Projected Spatial Pins -->
            <div v-if="showAnnotations" class="pointer-events-none absolute inset-0 z-10 overflow-hidden">
                <div
                    v-for="pin in pins"
                    :key="pin.id"
                    class="absolute transition-transform duration-75"
                    :style="{
                        transform: `translate3d(${pin.screenX}px, ${pin.screenY}px, 0)`,
                        display: pin.screenX < -100 ? 'none' : 'block'
                    }"
                >
                    <!-- Pin Marker Anchor -->
                    <button
                        type="button"
                        @click.stop="activePinId = activePinId === pin.id ? null : pin.id"
                        class="pointer-events-auto relative -translate-x-1/2 -translate-y-1/2 flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white shadow-xl transition-transform hover:scale-125 focus:outline-none"
                        :class="pin.resolved 
                            ? 'bg-emerald-600 border border-emerald-300 ring-2 ring-emerald-500/50' 
                            : 'bg-indigo-600 border-2 border-white shadow-[0_0_20px_rgba(99,102,241,1)]'"
                    >
                        {{ pin.id }}
                    </button>

                    <!-- Leader Line SVG & Popover Card -->
                    <div
                        v-if="activePinId === pin.id"
                        class="pointer-events-auto absolute left-6 -top-6 w-60 sm:w-68 rounded-2xl border border-white/20 bg-black/90 p-3.5 shadow-2xl backdrop-blur-2xl z-30 animate-fadeIn"
                        @click.stop
                    >
                        <div class="flex items-center justify-between border-b border-white/10 pb-2">
                            <span class="text-xs font-semibold text-white truncate max-w-[150px]">{{ pin.title }}</span>
                            <button
                                type="button"
                                @click.stop="deletePin(pin.id)"
                                class="text-neutral-500 hover:text-red-400 p-1"
                                title="Hapus Pin Ini"
                            >
                                <Trash2 class="h-3 w-3" />
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-neutral-300 leading-snug">
                            {{ pin.comment }}
                        </p>
                        <div class="mt-3 flex items-center justify-between text-[10px] pt-2 border-t border-white/10">
                            <button
                                type="button"
                                @click.stop="togglePinResolved(pin.id)"
                                class="font-medium transition-colors"
                                :class="pin.resolved ? 'text-emerald-400 hover:text-emerald-300' : 'text-amber-400 hover:text-amber-300'"
                            >
                                {{ pin.resolved ? '✓ Selesai (Resolved)' : '○ Tandai Selesai' }}
                            </button>
                            <span class="text-neutral-500 font-mono">{{ pin.time }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide-in Right Sidebar Drawer: Spatial Comments Thread -->
            <aside
                v-if="isDrawerOpen"
                class="absolute top-4 right-4 bottom-20 z-20 w-80 max-w-[90vw] rounded-3xl border border-white/15 bg-black/85 p-5 shadow-2xl backdrop-blur-2xl flex flex-col justify-between overflow-hidden"
            >
                <div>
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <div class="flex items-center gap-2">
                            <MessageSquare class="h-4 w-4 text-indigo-400" />
                            <h3 class="text-xs font-bold uppercase tracking-wider text-white">
                                Catatan Revisi Spasial ({{ pins.length }})
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="isDrawerOpen = false"
                            class="text-neutral-400 hover:text-white"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Scrollable Pin List -->
                    <div class="mt-4 space-y-2.5 max-h-[calc(100vh-280px)] overflow-y-auto pr-1">
                        <div
                            v-for="pin in pins"
                            :key="pin.id"
                            @click="activePinId = pin.id"
                            class="rounded-2xl border p-3 cursor-pointer transition-all duration-200"
                            :class="activePinId === pin.id 
                                ? 'border-indigo-500/50 bg-indigo-500/10 shadow-lg' 
                                : 'border-white/10 bg-white/[0.02] hover:bg-white/5'"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                                        :class="pin.resolved ? 'bg-emerald-600 text-white' : 'bg-indigo-600 text-white'"
                                    >
                                        {{ pin.id }}
                                    </span>
                                    <span class="font-semibold text-white truncate max-w-[130px]">{{ pin.title }}</span>
                                </div>
                                <span class="text-[10px] font-mono" :class="pin.resolved ? 'text-emerald-400' : 'text-amber-400'">
                                    {{ pin.resolved ? 'Resolved' : 'Pending' }}
                                </span>
                            </div>
                            <p class="mt-1.5 text-xs text-neutral-300 line-clamp-2">
                                {{ pin.comment }}
                            </p>
                            <div class="mt-2 flex items-center justify-between text-[10px] text-neutral-500">
                                <span>{{ pin.author }}</span>
                                <span>{{ pin.time }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Hint -->
                <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.03] p-3 text-xs text-neutral-400">
                    <div class="flex items-center gap-1.5 text-indigo-400 font-medium text-[11px]">
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        <span>Raycasting Spasial 3D</span>
                    </div>
                    <p class="mt-1 text-[10px] text-neutral-400 leading-tight">
                        Klik tombol "+ Tambah Pin" lalu klik titik mana saja pada geometri model 3D untuk menambahkan revisi.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
