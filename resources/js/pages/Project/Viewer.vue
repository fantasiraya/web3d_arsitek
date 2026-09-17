<template>
    <div class="flex h-screen flex-col">
        <div class="border-b border-gray-200 bg-gray-100 p-2">
            <RevisionBadge :project="project" />
        </div>

        <div ref="viewerContainer" class="relative min-h-0 flex-1 bg-slate-950">
            <div
                v-if="isLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-slate-950/80 text-sm text-white"
            >
                Memuat model 3D...
            </div>
            <div
                v-if="modelError"
                class="absolute inset-0 z-10 flex items-center justify-center p-6 text-center text-sm text-rose-200"
            >
                {{ modelError }}
            </div>
        </div>

        <div class="max-h-[30vh] overflow-y-auto border-t border-gray-200 p-4">
            <h2 class="mb-2 text-lg font-medium">Pins (Comments)</h2>
            <ul>
                <li v-for="comment in comments" :key="comment.id" class="mb-2">
                    <div class="text-sm text-gray-800">
                        <strong>{{ comment.user.name }}</strong
                        >: {{ comment.content }}
                    </div>
                    <div class="text-xs text-gray-500">
                        Position: ({{ comment.position_x }},
                        {{ comment.position_y }}, {{ comment.position_z }})
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { useRaycast } from '@/composables/useRaycast';
import RevisionBadge from '@/components/RevisionBadge.vue';
import {
    index as commentIndex,
    store as storeComment,
} from '@/routes/projects/comments';

interface Comment {
    id: string;
    content: string;
    position_x: number;
    position_y: number;
    position_z: number;
    user: {
        name: string;
    };
}

const { props } = usePage();
const project = props.project as {
    id: string;
    file_path: string;
    current_revision_count: number;
    max_revisions_allowed: number;
    comments?: Comment[];
};
const viewerContainer = ref<HTMLDivElement | null>(null);
const comments = ref<Comment[]>([]);
const isLoading = ref(true);
const modelError = ref('');

let renderer: any = null;
let scene: any = null;
let camera: any = null;
let controls: any = null;
let model: any = null;
let animationFrame: number | null = null;
let resizeObserver: ResizeObserver | null = null;
const markerGroup = new THREE.Group();

async function loadComments(): Promise<void> {
    if (project.comments) {
        comments.value = project.comments;
    } else {
        const response = await fetch(commentIndex.url(project.id));

        if (response.ok) {
            const data = await response.json();
            comments.value = data.data ?? [];
        }
    }

    renderCommentMarkers();
}

function renderCommentMarkers(): void {
    markerGroup.clear();

    for (const comment of comments.value) {
        const marker = new THREE.Mesh(
            new THREE.SphereGeometry(0.05, 16, 16),
            new THREE.MeshStandardMaterial({
                color: 0xef4444,
                emissive: 0x7f1d1d,
            }),
        );
        marker.position.set(
            comment.position_x,
            comment.position_y,
            comment.position_z,
        );
        markerGroup.add(marker);
    }
}

function frameModel(): void {
    if (model === null || camera === null || controls === null) {
        return;
    }

    const bounds = new THREE.Box3().setFromObject(model);
    const center = bounds.getCenter(new THREE.Vector3());
    const size = bounds.getSize(new THREE.Vector3());
    const distance = Math.max(size.length() * 0.8, 1);

    camera.position.set(
        center.x + distance,
        center.y + distance * 0.6,
        center.z + distance,
    );
    camera.near = distance / 100;
    camera.far = distance * 100;
    camera.updateProjectionMatrix();
    controls.target.copy(center);
    controls.update();
}

function loadModel(): void {
    if (scene === null || typeof project.file_path !== 'string') {
        modelError.value = 'Path file model tidak tersedia.';
        isLoading.value = false;
        return;
    }

    const loader = new GLTFLoader();
    const modelUrl = `/storage/${project.file_path}`;

    loader.load(
        modelUrl,
        (gltf: { scene: any }) => {
            model = gltf.scene;
            scene?.add(model);
            frameModel();
            isLoading.value = false;
        },
        undefined,
        () => {
            modelError.value =
                'Model 3D tidak dapat dimuat. Pastikan file GLB tersedia.';
            isLoading.value = false;
        },
    );
}

function resizeRenderer(): void {
    if (
        renderer === null ||
        camera === null ||
        viewerContainer.value === null
    ) {
        return;
    }

    const { clientWidth: width, clientHeight: height } = viewerContainer.value;
    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
}

function render(): void {
    if (renderer === null || scene === null || camera === null) {
        return;
    }

    controls?.update();
    renderer.render(scene, camera);
    animationFrame = window.requestAnimationFrame(render);
}

async function handleCanvasClick(event: MouseEvent): Promise<void> {
    if (camera === null || scene === null) {
        return;
    }

    const { x, y, z, normal } = await useRaycast(event, camera, scene);
    const payload = {
        content: 'Pin added via 3D viewer',
        position_x: x,
        position_y: y,
        position_z: z,
        normal_x: normal.x,
        normal_y: normal.y,
        normal_z: normal.z,
    };

    router.post(storeComment.url(project.id), payload, {
        preserveState: true,
        onSuccess: () => loadComments(),
    });
}

function initializeViewer(): void {
    if (viewerContainer.value === null) {
        return;
    }

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x020617);
    scene.add(new THREE.AmbientLight(0xffffff, 1.5));

    const keyLight = new THREE.DirectionalLight(0xffffff, 2);
    keyLight.position.set(5, 10, 7.5);
    scene.add(keyLight);
    scene.add(markerGroup);

    camera = new THREE.PerspectiveCamera(45, 1, 0.1, 1000);
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    viewerContainer.value.appendChild(renderer.domElement);
    renderer.domElement.classList.add('h-full', 'w-full');
    renderer.domElement.addEventListener('click', handleCanvasClick);

    controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;

    resizeObserver = new ResizeObserver(resizeRenderer);
    resizeObserver.observe(viewerContainer.value);
    resizeRenderer();
    render();
    loadModel();
}

onMounted(async () => {
    initializeViewer();
    await loadComments();
});

onBeforeUnmount(() => {
    if (animationFrame !== null) {
        window.cancelAnimationFrame(animationFrame);
    }

    resizeObserver?.disconnect();
    renderer?.domElement.removeEventListener('click', handleCanvasClick);
    controls?.dispose();
    renderer?.dispose();
});
</script>
