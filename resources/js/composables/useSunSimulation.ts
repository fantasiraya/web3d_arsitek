import { ref, computed } from 'vue';
import {
    Scene,
    DirectionalLight,
    AmbientLight,
    Group,
    Object3D,
    Color,
    PointLight,
    Mesh,
    MathUtils,
    Vector3,
} from 'three';
import * as SunCalc from 'suncalc';

export interface SunSettings {
    latitude: number;
    longitude: number;
    north_angle: number; // 0 to 360 degrees
    solar_date?: string;  // YYYY-MM-DD
}

export interface LampNode {
    object: Object3D;
    originalEmissive?: Color;
    originalIntensity?: number;
    light?: PointLight;
}

export function useSunSimulation() {
    // Current simulation state
    const currentHour = ref(12); // 0 to 23.99
    const latitude = ref(-6.2088); // Default Jakarta
    const longitude = ref(106.8456);
    const northAngle = ref(0); // degrees, 0 = North along -Z
    const solarDate = ref(new Date().toISOString().split('T')[0]);

    // Sun position calculations
    const sunAltitude = ref(0); // radians
    const sunAzimuth = ref(0); // radians
    const isNight = ref(false);

    // Three.js instances
    let directionalLight: DirectionalLight | null = null;
    let ambientLight: AmbientLight | null = null;
    let sceneRef: Scene | null = null;
    let lampGroup: Group = new Group();
    const lampNodes = ref<LampNode[]>([]);

    const formattedTime = computed(() => {
        const h = Math.floor(currentHour.value);
        const m = Math.floor((currentHour.value - h) * 60);
        return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    });

    const activePreset = computed(() => {
        const h = currentHour.value;
        if (Math.abs(h - 8) < 0.25) return 'pagi';
        if (Math.abs(h - 12) < 0.25) return 'siang';
        if (Math.abs(h - 17) < 0.25) return 'sore';
        if (Math.abs(h - 20) < 0.25) return 'malam';
        return null;
    });

    /**
     * Initialize Three.js sun and lighting in the given scene
     */
    function initSunLighting(scene: Scene, initialSettings?: Partial<SunSettings>) {
        sceneRef = scene;

        if (initialSettings?.latitude !== undefined) latitude.value = initialSettings.latitude;
        if (initialSettings?.longitude !== undefined) longitude.value = initialSettings.longitude;
        if (initialSettings?.north_angle !== undefined) northAngle.value = initialSettings.north_angle;
        if (initialSettings?.solar_date) solarDate.value = initialSettings.solar_date;

        // Directional Light (Sun)
        directionalLight = new DirectionalLight(0xfff8ee, 2.2);
        directionalLight.name = 'SunDirectionalLight';
        directionalLight.castShadow = true;
        directionalLight.shadow.mapSize.width = 2048;
        directionalLight.shadow.mapSize.height = 2048;
        directionalLight.shadow.camera.near = 0.5;
        directionalLight.shadow.camera.far = 300;
        directionalLight.shadow.bias = -0.0005;

        const d = 40;
        directionalLight.shadow.camera.left = -d;
        directionalLight.shadow.camera.right = d;
        directionalLight.shadow.camera.top = d;
        directionalLight.shadow.camera.bottom = -d;

        scene.add(directionalLight);

        // Ambient Light
        ambientLight = new AmbientLight(0xffffff, 1.4);
        ambientLight.name = 'SunAmbientLight';
        scene.add(ambientLight);

        // Group for dynamic lamp lights
        lampGroup = new Group();
        lampGroup.name = 'LampLightsGroup';
        scene.add(lampGroup);

        updateSunPosition();
    }

    /**
     * Scan loaded 3D model for lamp objects (LAMPU_* or case-insensitive lampu)
     */
    function scanLampObjects(root: Object3D) {
        // Clear previous lamp point lights
        while (lampGroup.children.length > 0) {
            lampGroup.remove(lampGroup.children[0]);
        }
        lampNodes.value = [];

        root.traverse((obj: Object3D) => {
            const name = (obj.name || '').toUpperCase();
            if (name.startsWith('LAMPU') || name.includes('LAMPU') || name.startsWith('LIGHT_')) {
                const node: LampNode = { object: obj };

                if (obj instanceof Mesh && obj.material) {
                    const materials = Array.isArray(obj.material) ? obj.material : [obj.material];
                    const firstMat = materials[0];
                    if (firstMat && 'emissive' in firstMat) {
                        node.originalEmissive = firstMat.emissive.clone();
                        node.originalIntensity = (firstMat as any).emissiveIntensity ?? 0;
                    }
                }

                // Create a cozy PointLight at this lamp's position
                const pointLight = new PointLight(0xffe29a, 0, 15, 1.5);
                pointLight.position.copy(obj.getWorldPosition(new Vector3()));
                pointLight.position.y += 0.2; // slightly offset from surface
                lampGroup.add(pointLight);
                node.light = pointLight;

                lampNodes.value.push(node);
            }
        });

        // Apply night/day state to newly found lamps
        applyLampState();
    }

    /**
     * Update sun position, light color, shadows, background and lamps
     */
    function updateSunPosition() {
        if (!directionalLight || !ambientLight || !sceneRef) return;

        // Build date object for calculation
        const dateParts = solarDate.value.split('-');
        const year = parseInt(dateParts[0], 10) || 2026;
        const month = (parseInt(dateParts[1], 10) || 1) - 1;
        const day = parseInt(dateParts[2], 10) || 1;

        const h = Math.floor(currentHour.value);
        const m = Math.floor((currentHour.value - h) * 60);

        const date = new Date(year, month, day, h, m, 0);

        // Calculate Sun Position using SunCalc
        const sunPos = SunCalc.getPosition(date, latitude.value, longitude.value);
        sunAltitude.value = sunPos.altitude;
        sunAzimuth.value = sunPos.azimuth;

        // Determine if sun is below horizon
        isNight.value = sunPos.altitude <= 0.02; // sunset threshold

        // Radius for sun distance from scene center
        const orbitRadius = 60;
        const northRad = (northAngle.value * Math.PI) / 180;

        // In SunCalc: azimuth 0 is South (+Z), PI/2 is West (-X), -PI/2 is East (+X)
        const adjustedAzimuth = sunPos.azimuth - northRad;

        const x = -Math.sin(adjustedAzimuth) * Math.cos(sunPos.altitude) * orbitRadius;
        const z = Math.cos(adjustedAzimuth) * Math.cos(sunPos.altitude) * orbitRadius;
        const y = Math.max(0.1, Math.sin(sunPos.altitude) * orbitRadius);

        directionalLight.position.set(x, y, z);
        directionalLight.target.position.set(0, 0, 0);
        directionalLight.target.updateMatrixWorld();

        // Dynamic Lighting Color & Intensity based on altitude
        const alt = sunPos.altitude;

        if (alt <= 0) {
            // Full Night
            directionalLight.intensity = 0;
            directionalLight.castShadow = false;

            ambientLight.color.setHex(0x35425e); // Soft moonlit blue
            ambientLight.intensity = 0.45;

            sceneRef.background = new Color(0x05070d); // Deep night
        } else if (alt < 0.22) {
            // Golden Hour / Sunrise / Sunset
            const t = alt / 0.22; // 0 to 1
            directionalLight.castShadow = true;
            directionalLight.intensity = MathUtils.lerp(0.8, 2.2, t);

            // Transition from golden-orange to warm sunlight
            const sunColor = new Color(0xff6f28).lerp(new Color(0xfff3d6), t);
            directionalLight.color.copy(sunColor);

            const ambColor = new Color(0x3d282e).lerp(new Color(0xe0d6cf), t);
            ambientLight.color.copy(ambColor);
            ambientLight.intensity = MathUtils.lerp(0.6, 1.4, t);

            const bgCol = new Color(0x191016).lerp(new Color(0x0c1017), t);
            sceneRef.background = bgCol;
        } else {
            // Full Daylight
            directionalLight.castShadow = true;
            directionalLight.intensity = 2.4;
            directionalLight.color.setHex(0xfffcf5); // Crisp clean sunlight

            ambientLight.color.setHex(0xfafafa);
            ambientLight.intensity = 1.6;

            sceneRef.background = new Color(0x0a0d14); // Clean dark canvas
        }

        applyLampState();
    }

    /**
     * Turn on/off lamps based on whether it is night
     */
    function applyLampState() {
        const night = isNight.value;

        lampNodes.value.forEach((node) => {
            // 1. Toggle mesh emissive
            if (node.object instanceof Mesh && node.object.material) {
                const materials = Array.isArray(node.object.material) ? node.object.material : [node.object.material];
                materials.forEach((mat: any) => {
                    if ('emissive' in mat) {
                        if (night) {
                            mat.emissive.setHex(0xffdf88);
                            mat.emissiveIntensity = 2.5;
                        } else {
                            if (node.originalEmissive) {
                                mat.emissive.copy(node.originalEmissive);
                            } else {
                                mat.emissive.setHex(0x000000);
                            }
                            mat.emissiveIntensity = node.originalIntensity ?? 0;
                        }
                        mat.needsUpdate = true;
                    }
                });
            }

            // 2. Toggle point light intensity
            if (node.light) {
                node.light.intensity = night ? 2.5 : 0;
            }
        });
    }

    /**
     * Quick Presets
     */
    function setPreset(preset: 'pagi' | 'siang' | 'sore' | 'malam') {
        switch (preset) {
            case 'pagi':
                currentHour.value = 8.0; // 08:00
                break;
            case 'siang':
                currentHour.value = 12.0; // 12:00
                break;
            case 'sore':
                currentHour.value = 17.0; // 17:00
                break;
            case 'malam':
                currentHour.value = 20.0; // 20:00
                break;
        }
        updateSunPosition();
    }

    function setHour(hour: number) {
        currentHour.value = Math.max(0, Math.min(23.99, hour));
        updateSunPosition();
    }

    function updateSettings(settings: Partial<SunSettings>) {
        if (settings.latitude !== undefined) latitude.value = settings.latitude;
        if (settings.longitude !== undefined) longitude.value = settings.longitude;
        if (settings.north_angle !== undefined) northAngle.value = settings.north_angle;
        if (settings.solar_date) solarDate.value = settings.solar_date;
        updateSunPosition();
    }

    return {
        currentHour,
        latitude,
        longitude,
        northAngle,
        solarDate,
        sunAltitude,
        sunAzimuth,
        isNight,
        formattedTime,
        activePreset,
        lampNodes,
        initSunLighting,
        scanLampObjects,
        updateSunPosition,
        applyLampState,
        setPreset,
        setHour,
        updateSettings,
    };
}

