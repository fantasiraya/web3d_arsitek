import { Scene, PerspectiveCamera, WebGLRenderer, Vector2 } from 'three';
import { ref } from 'vue';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';
import { GTAOPass } from 'three/examples/jsm/postprocessing/GTAOPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';

export function usePostProcessing() {
    let composer: EffectComposer | null = null;
    let renderPass: RenderPass | null = null;
    let gtaoPass: GTAOPass | null = null;
    let bloomPass: UnrealBloomPass | null = null;
    let outputPass: OutputPass | null = null;

    let activeRenderer: WebGLRenderer | null = null;
    let activeScene: Scene | null = null;
    let activeCamera: PerspectiveCamera | null = null;

    // Reactive states
    const isEnhancedRenderingActive = ref(true);
    const isAoEnabled = ref(true);
    const isBloomEnabled = ref(true);
    const aoIntensity = ref(1.0);
    const bloomStrength = ref(0.4);
    const bloomRadius = ref(0.3);
    const bloomThreshold = ref(0.85);

    function initPostProcessing(
        renderer: WebGLRenderer,
        scene: Scene,
        camera: PerspectiveCamera,
        width: number,
        height: number
    ) {
        activeRenderer = renderer;
        activeScene = scene;
        activeCamera = camera;

        // Create EffectComposer
        composer = new EffectComposer(renderer);
        composer.setSize(width, height);

        // 1. Base RenderPass
        renderPass = new RenderPass(scene, camera);
        composer.addPass(renderPass);

        // 2. GTAO Pass (Ground Truth Ambient Occlusion for realistic room corner shadows)
        try {
            gtaoPass = new GTAOPass(scene, camera, width, height);
            gtaoPass.blendIntensity = aoIntensity.value;
            gtaoPass.output = GTAOPass.OUTPUT.Default;
            gtaoPass.enabled = isAoEnabled.value;
            composer.addPass(gtaoPass);
        } catch (e) {
            console.warn('[PostProcessing] GTAO not supported on this device/browser:', e);
            gtaoPass = null;
        }

        // 3. UnrealBloomPass (Warm architectural glow for LAMPU_* and sunlight highlights)
        bloomPass = new UnrealBloomPass(
            new Vector2(width, height),
            bloomStrength.value,
            bloomRadius.value,
            bloomThreshold.value
        );
        bloomPass.enabled = isBloomEnabled.value;
        composer.addPass(bloomPass);

        // 4. OutputPass (sRGB & Tone Mapping)
        outputPass = new OutputPass();
        composer.addPass(outputPass);
    }

    function renderScene(delta?: number): boolean {
        if (!activeRenderer || !activeScene || !activeCamera) return false;

        if (isEnhancedRenderingActive.value && composer) {
            // Synchronize effect parameters
            if (gtaoPass) {
                gtaoPass.enabled = isAoEnabled.value;
                gtaoPass.blendIntensity = aoIntensity.value;
            }
            if (bloomPass) {
                bloomPass.enabled = isBloomEnabled.value;
                bloomPass.strength = bloomStrength.value;
                bloomPass.radius = bloomRadius.value;
                bloomPass.threshold = bloomThreshold.value;
            }

            composer.render(delta);
            return true;
        }

        // Fallback default rasterizer
        activeRenderer.render(activeScene, activeCamera);
        return true;
    }

    function setSize(width: number, height: number) {
        if (composer) {
            composer.setSize(width, height);
        }
        if (gtaoPass) {
            gtaoPass.setSize(width, height);
        }
        if (bloomPass) {
            bloomPass.resolution.set(width, height);
        }
    }

    /**
     * Render a super-sampled / high-resolution snapshot with all post-processing passes applied.
     */
    function renderHighQualitySnapshot(
        resolution: 'viewport' | '1080p' | '4k',
        container: HTMLElement | null
    ): string {
        if (!activeRenderer || !activeScene || !activeCamera) return '';

        let targetW: number;
        let targetH: number;

        if (resolution === 'viewport' || !container) {
            targetW = container ? container.clientWidth : 1280;
            targetH = container ? container.clientHeight : 720;
        } else if (resolution === '1080p') {
            targetW = 1920;
            targetH = 1080;
        } else {
            // 4K UHD
            targetW = 3840;
            targetH = 2160;
        }

        // Simpan dimensi dan aspek rasio asli
        const origWidth = container ? container.clientWidth : 1280;
        const origHeight = container ? container.clientHeight : 720;
        const origAspect = activeCamera.aspect;

        try {
            // Atur ukuran target
            activeRenderer.setSize(targetW, targetH, false);
            activeCamera.aspect = targetW / targetH;
            activeCamera.updateProjectionMatrix();

            if (composer) {
                composer.setSize(targetW, targetH);
                if (gtaoPass) gtaoPass.setSize(targetW, targetH);
                if (bloomPass) bloomPass.resolution.set(targetW, targetH);

                // Render frame menggunakan EffectComposer
                composer.render();
            } else {
                activeRenderer.render(activeScene, activeCamera);
            }

            const dataUrl = activeRenderer.domElement.toDataURL('image/png');
            return dataUrl;
        } finally {
            // Kembalikan ke resolusi viewport asli
            activeRenderer.setSize(origWidth, origHeight);
            activeCamera.aspect = origAspect;
            activeCamera.updateProjectionMatrix();

            if (composer) {
                composer.setSize(origWidth, origHeight);
                if (gtaoPass) gtaoPass.setSize(origWidth, origHeight);
                if (bloomPass) bloomPass.resolution.set(origWidth, origHeight);
                composer.render();
            } else {
                activeRenderer.render(activeScene, activeCamera);
            }
        }
    }

    function dispose() {
        composer = null;
        renderPass = null;
        gtaoPass = null;
        bloomPass = null;
        outputPass = null;
        activeRenderer = null;
        activeScene = null;
        activeCamera = null;
    }

    return {
        initPostProcessing,
        renderScene,
        setSize,
        renderHighQualitySnapshot,
        dispose,
        isEnhancedRenderingActive,
        isAoEnabled,
        isBloomEnabled,
        aoIntensity,
        bloomStrength,
        bloomRadius,
        bloomThreshold,
    };
}

