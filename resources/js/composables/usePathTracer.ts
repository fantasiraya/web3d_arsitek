import { ref } from 'vue';
import { Scene, PerspectiveCamera, WebGLRenderer } from 'three';
import { WebGLPathTracer } from 'three-gpu-pathtracer';

export interface PathTraceOptions {
    scene: Scene;
    camera: PerspectiveCamera;
    renderer: WebGLRenderer;
    targetResolution: 'viewport' | '1080p' | '4k';
    maxSamples?: number;
    bounces?: number;
    container?: HTMLElement | null;
    onProgressUpdate?: (sample: number, total: number, percent: number) => void;
    onFrameUpdate?: (dataUrl: string) => void;
}

export function usePathTracer() {
    let pathTracerInstance: WebGLPathTracer | null = null;
    let isCancelled = false;

    const isPathTracing = ref(false);
    const isCompilingBvh = ref(false);
    const bvhProgress = ref(0);
    const currentSample = ref(0);
    const totalSamples = ref(50);
    const progressPercent = ref(0);
    const pathTraceError = ref('');
    const previewDataUrl = ref<string>('');

    function initPathTracer(renderer: WebGLRenderer) {
        if (!pathTracerInstance) {
            try {
                pathTracerInstance = new WebGLPathTracer(renderer);
                pathTracerInstance.renderToCanvas = true;
                pathTracerInstance.enablePathTracing = true;
                pathTracerInstance.dynamicLowRes = false;
                pathTracerInstance.fadeDuration = 0;
                pathTracerInstance.minSamples = 1;
            } catch (err: unknown) {
                console.error('[PathTracer] Init error:', err);
                pathTraceError.value = 'Browser atau kartu grafis tidak mendukung WebGL Path Tracing.';
            }
        }
        return pathTracerInstance;
    }

    async function renderPathTracedImage(options: PathTraceOptions): Promise<string> {
        const {
            scene,
            camera,
            renderer,
            targetResolution,
            maxSamples = 50,
            bounces = 4,
            container = null,
            onProgressUpdate,
            onFrameUpdate,
        } = options;

        isCancelled = false;
        isPathTracing.value = true;
        isCompilingBvh.value = true;
        bvhProgress.value = 0;
        currentSample.value = 0;
        totalSamples.value = maxSamples;
        progressPercent.value = 0;
        pathTraceError.value = '';

        const pt = initPathTracer(renderer);
        if (!pt) {
            isPathTracing.value = false;
            isCompilingBvh.value = false;
            throw new Error('Gagal menginisialisasi WebGLPathTracer.');
        }

        // Simpan dimensi asli
        const originalWidth = container?.clientWidth || window.innerWidth;
        const originalHeight = container?.clientHeight || window.innerHeight;
        const originalAspect = camera.aspect;

        let renderWidth = originalWidth;
        let renderHeight = originalHeight;

        if (targetResolution === '1080p') {
            renderWidth = 1920;
            renderHeight = 1080;
            pt.tiles.set(2, 2);
        } else if (targetResolution === '4k') {
            renderWidth = 3840;
            renderHeight = 2160;
            pt.tiles.set(3, 3);
        } else {
            pt.tiles.set(1, 1);
        }

        pt.bounces = bounces;

        try {
            // Set resolusi render sementara
            renderer.setSize(renderWidth, renderHeight, false);
            camera.aspect = renderWidth / renderHeight;
            camera.updateProjectionMatrix();

            // Set status compiling BVH
            isCompilingBvh.value = true;
            bvhProgress.value = 30;

            // Berikan jeda sejenak agar UI sempat render status progress
            await new Promise((resolve) => setTimeout(resolve, 50));

            // Bangun BVH Acceleration Hierarchy secara synchronous
            pt.setScene(scene, camera);

            bvhProgress.value = 100;
            isCompilingBvh.value = false;

            if (isCancelled) {
                restoreDimensions(renderer, camera, originalWidth, originalHeight, originalAspect);
                isPathTracing.value = false;
                return '';
            }

            pt.reset();

            // Progressive accumulation loop
            for (let i = 1; i <= maxSamples; i++) {
                if (isCancelled) {
                    break;
                }

                pt.renderSample();

                currentSample.value = i;
                const pct = Math.round((i / maxSamples) * 100);
                progressPercent.value = pct;

                if (onProgressUpdate) {
                    onProgressUpdate(i, maxSamples, pct);
                }

                // Update preview frame berkala (setiap 3 sample atau saat selesai)
                if (i % 3 === 0 || i === maxSamples) {
                    const currentFrame = renderer.domElement.toDataURL('image/png');
                    previewDataUrl.value = currentFrame;
                    if (onFrameUpdate) {
                        onFrameUpdate(currentFrame);
                    }
                }

                // Berikan kesempatan browser untuk repaint DOM dan progress bar
                await new Promise((resolve) => setTimeout(resolve, 12));
            }

            const finalDataUrl = renderer.domElement.toDataURL('image/png');
            previewDataUrl.value = finalDataUrl;

            // Kembalikan dimensi renderer ke viewport normal
            restoreDimensions(renderer, camera, originalWidth, originalHeight, originalAspect);
            isPathTracing.value = false;

            return finalDataUrl;
        } catch (err: unknown) {
            console.error('[PathTracer] Rendering error:', err);
            restoreDimensions(renderer, camera, originalWidth, originalHeight, originalAspect);
            isPathTracing.value = false;
            isCompilingBvh.value = false;
            pathTraceError.value = err instanceof Error ? err.message : 'Terjadi kesalahan saat rendering Path Tracing.';
            throw err;
        }
    }

    function cancelPathTracing() {
        isCancelled = true;
        isPathTracing.value = false;
        isCompilingBvh.value = false;
    }

    function restoreDimensions(
        renderer: WebGLRenderer,
        camera: PerspectiveCamera,
        width: number,
        height: number,
        aspect: number,
    ) {
        renderer.setSize(width, height);
        camera.aspect = aspect;
        camera.updateProjectionMatrix();
    }

    function disposePathTracer() {
        if (pathTracerInstance) {
            try {
                pathTracerInstance.dispose();
            } catch (err) {
                console.warn('[PathTracer] dispose warning:', err);
            }
            pathTracerInstance = null;
        }
    }

    return {
        isPathTracing,
        isCompilingBvh,
        bvhProgress,
        currentSample,
        totalSamples,
        progressPercent,
        pathTraceError,
        previewDataUrl,
        initPathTracer,
        renderPathTracedImage,
        cancelPathTracing,
        disposePathTracer,
    };
}
