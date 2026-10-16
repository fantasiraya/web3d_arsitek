<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/examples/jsm/loaders/DRACOLoader.js';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Loader2 } from '@lucide/vue';

const props = defineProps<{
    filePath: string;
    isDraco?: boolean;
}>();

const emit = defineEmits<{
    (e: 'snapshot', dataUrl: string): void;
}>();

const containerRef = ref<HTMLDivElement | null>(null);
const isLoading = ref(true);
const hasError = ref(false);

let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let renderer: THREE.WebGLRenderer | null = null;
let controls: OrbitControls | null = null;
let model: THREE.Object3D | null = null;
let animFrameId: number | null = null;
let dracoLoader: DRACOLoader | null = null;
let snapshotTaken = false;

function initThree() {
    if (!containerRef.value) return;

    const width = containerRef.value.clientWidth || 320;
    const height = containerRef.value.clientHeight || 144;

    // 1. Scene
    scene = new THREE.Scene();

    // 2. Camera
    camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
    camera.position.set(0, 0, 5);

    // 3. Renderer (Ringan: low-power, capped pixel ratio)
    renderer = new THREE.WebGLRenderer({
        antialias: true,
        alpha: true,
        powerPreference: 'low-power',
        preserveDrawingBuffer: true,
    });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.2;

    containerRef.value.appendChild(renderer.domElement);

    // 4. OrbitControls
    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.enableZoom = false; // Hindari hijack scroll halaman
    controls.enablePan = false;
    controls.autoRotate = true;
    controls.autoRotateSpeed = 2.5;

    // 5. Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 1.4);
    scene.add(ambientLight);

    const dirLight1 = new THREE.DirectionalLight(0xffffff, 1.8);
    dirLight1.position.set(5, 10, 7);
    scene.add(dirLight1);

    const dirLight2 = new THREE.DirectionalLight(0x90b0ff, 0.8);
    dirLight2.position.set(-5, -5, -5);
    scene.add(dirLight2);

    // 6. Loader setup
    const loader = new GLTFLoader();
    dracoLoader = new DRACOLoader();
    dracoLoader.setDecoderPath('/draco/');
    loader.setDRACOLoader(dracoLoader);

    // 7. Load GLB
    const modelUrl = props.filePath.startsWith('/') || props.filePath.startsWith('http')
        ? props.filePath
        : `/storage/${props.filePath}`;

    loader.load(
        modelUrl,
        (gltf) => {
            if (!scene || !camera) return;

            model = gltf.scene;

            // Hitung bounding box agar kamera terfokus pas di tengah
            const box = new THREE.Box3().setFromObject(model);
            const size = box.getSize(new THREE.Vector3());
            const center = box.getCenter(new THREE.Vector3());

            // Pusatkan model ke (0,0,0)
            model.position.sub(center);
            scene.add(model);

            // Sesuaikan posisi kamera berdasarkan ukuran model
            const maxDim = Math.max(size.x, size.y, size.z);
            const fov = camera.fov * (Math.PI / 180);
            let cameraZ = Math.abs(maxDim / 2 / Math.tan(fov / 2)) * 1.4;
            cameraZ = Math.max(cameraZ, 2);

            camera.position.set(cameraZ * 0.7, cameraZ * 0.5, cameraZ);
            camera.lookAt(0, 0, 0);

            if (controls) {
                controls.target.set(0, 0, 0);
                controls.update();
            }

            isLoading.value = false;
        },
        undefined,
        (err) => {
            console.warn('[Project3DPreview] Error loading GLB:', err);
            isLoading.value = false;
            hasError.value = true;
        }
    );

    // 8. Render Loop
    function animate() {
        animFrameId = requestAnimationFrame(animate);
        if (controls) controls.update();
        if (renderer && scene && camera) {
            renderer.render(scene, camera);
            if (!snapshotTaken && !isLoading.value && model) {
                snapshotTaken = true;
                setTimeout(() => {
                    if (renderer) {
                        try {
                            const dataUrl = renderer.domElement.toDataURL('image/jpeg', 0.85);
                            emit('snapshot', dataUrl);
                        } catch (e) {
                            // ignore security error if any
                        }
                    }
                }, 80);
            }
        }
    }
    animate();
}

onMounted(() => {
    initThree();
});

onBeforeUnmount(() => {
    if (animFrameId !== null) {
        cancelAnimationFrame(animFrameId);
        animFrameId = null;
    }

    if (controls) {
        controls.dispose();
        controls = null;
    }

    if (model && scene) {
        scene.remove(model);
        model.traverse((child: any) => {
            if (child.isMesh) {
                if (child.geometry) child.geometry.dispose();
                if (child.material) {
                    if (Array.isArray(child.material)) {
                        child.material.forEach((m: any) => m.dispose());
                    } else {
                        child.material.dispose();
                    }
                }
            }
        });
        model = null;
    }

    if (renderer) {
        renderer.dispose();
        renderer.forceContextLoss();
        if (renderer.domElement && renderer.domElement.parentNode) {
            renderer.domElement.parentNode.removeChild(renderer.domElement);
        }
        renderer = null;
    }

    if (dracoLoader) {
        dracoLoader.dispose();
        dracoLoader = null;
    }

    scene = null;
    camera = null;
});
</script>

<template>
    <div class="relative w-full h-full cursor-grab active:cursor-grabbing">
        <!-- Canvas mount target -->
        <div ref="containerRef" class="w-full h-full"></div>

        <!-- Loading spinner -->
        <div
            v-if="isLoading"
            class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-black/40 backdrop-blur-xs text-white text-xs gap-1.5 transition-opacity"
        >
            <Loader2 class="h-5 w-5 animate-spin text-indigo-400" />
            <span class="text-[11px] font-medium text-slate-200">Memuat 3D...</span>
        </div>

        <!-- Error state fallback -->
        <div
            v-if="hasError"
            class="absolute inset-0 z-20 flex items-center justify-center bg-black/60 text-slate-400 text-[11px] p-2 text-center"
        >
            Gagal memuat preview 3D
        </div>
    </div>
</template>

