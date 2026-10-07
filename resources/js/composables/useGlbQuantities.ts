/**
 * useGlbQuantities.ts
 *
 * Composable untuk menghitung kuantitas geometri dari file .glb menggunakan Three.js.
 * Mendukung dua sumber data:
 *
 * 1. Nama mesh (konvensi: KATEGORI_JENIS_SPEK, contoh: DINDING_BATA_15)
 * 2. Custom Properties Blender via mesh.userData.extras:
 *    - unit_price  : harga satuan (number)
 *    - unit        : satuan (string, contoh: "m2", "m3", "unit")
 *    - category    : kategori pekerjaan (string)
 *    - section     : bagian pekerjaan (string)
 *    - quantity_basis: area | volume | count | length
 */

import { ref, readonly } from 'vue';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/examples/jsm/loaders/DRACOLoader.js';

// ── Types ─────────────────────────────────────────────────────────────────────

export interface MeshQuantity {
    /** Nama objek dari scene .glb */
    name: string;
    /** Luas permukaan total semua segitiga (m²) */
    area: number;
    /** Volume solid — hanya akurat untuk mesh watertight (m³), 0 jika terbuka */
    volume: number;
    /** Jumlah mesh dengan nama yang sama (count) */
    count: number;
    /** True jika mesh kemungkinan tidak tertutup (watertight check) */
    is_open_mesh: boolean;
    /** Peringatan spesifik untuk mesh ini */
    warnings: string[];
    /** Dimensi bounding box (untuk heuristik skala) */
    bbox: { x: number; y: number; z: number };
    /**
     * Custom properties dari Blender (mesh.userData.extras).
     * Diisi jika arsitek menyertakan properti di Blender sebelum export.
     */
    extras: {
        unit_price?:      number;   // harga satuan dari custom property
        unit?:            string;   // satuan (m2, m3, unit, dll.)
        category?:        string;   // kategori pekerjaan
        section?:         string;   // bagian pekerjaan
        quantity_basis?:  string;   // area | volume | count | length
        [key: string]:    unknown;  // properti lain yang mungkin ada
    };
    /** True jika mesh punya custom properties dari Blender */
    has_extras: boolean;
}

export interface GlbQuantityResult {
    /** Array kuantitas per mesh/objek unik */
    meshes: MeshQuantity[];
    /** Total luas seluruh geometri */
    total_area: number;
    /** Total volume seluruh mesh tertutup */
    total_volume: number;
    /** Jumlah total objek di scene */
    total_count: number;
    /** Peringatan global */
    warnings: string[];
    /** Skala unit yang dideteksi (asumsi meter) */
    assumed_unit: 'meter';
}

// ── Helper: hitung luas segitiga ─────────────────────────────────────────────

/**
 * Hitung luas permukaan semua segitiga dari BufferGeometry.
 * Return luas dalam satuan unit geometri (asumsi: meter).
 */
function computeSurfaceArea(geometry: THREE.BufferGeometry): number {
    const position = geometry.attributes.position;
    if (!position) return 0;

    const index = geometry.index;
    let area = 0;

    const vA = new THREE.Vector3();
    const vB = new THREE.Vector3();
    const vC = new THREE.Vector3();
    const cross = new THREE.Vector3();

    if (index) {
        for (let i = 0; i < index.count; i += 3) {
            vA.fromBufferAttribute(position, index.getX(i));
            vB.fromBufferAttribute(position, index.getX(i + 1));
            vC.fromBufferAttribute(position, index.getX(i + 2));
            cross.crossVectors(vB.sub(vA.clone()), vC.sub(vA.clone()));
            area += cross.length() / 2;
        }
    } else {
        for (let i = 0; i < position.count; i += 3) {
            vA.fromBufferAttribute(position, i);
            vB.fromBufferAttribute(position, i + 1);
            vC.fromBufferAttribute(position, i + 2);
            cross.crossVectors(vB.sub(vA.clone()), vC.sub(vA.clone()));
            area += cross.length() / 2;
        }
    }

    return area;
}

/**
 * Hitung volume menggunakan divergence theorem (signed volume method).
 * Hanya akurat untuk mesh tertutup (watertight solid).
 * Return nilai absolut volume dalam satuan unit³.
 */
function computeSignedVolume(geometry: THREE.BufferGeometry): number {
    const position = geometry.attributes.position;
    if (!position) return 0;

    const index = geometry.index;
    let signedVol = 0;

    const vA = new THREE.Vector3();
    const vB = new THREE.Vector3();
    const vC = new THREE.Vector3();

    const processTriangle = (ia: number, ib: number, ic: number) => {
        vA.fromBufferAttribute(position, ia);
        vB.fromBufferAttribute(position, ib);
        vC.fromBufferAttribute(position, ic);
        // Signed volume of tetrahedron from origin
        signedVol += vA.dot(vB.cross(vC)) / 6;
    };

    if (index) {
        for (let i = 0; i < index.count; i += 3) {
            processTriangle(index.getX(i), index.getX(i + 1), index.getX(i + 2));
        }
    } else {
        for (let i = 0; i < position.count; i += 3) {
            processTriangle(i, i + 1, i + 2);
        }
    }

    return Math.abs(signedVol);
}

/**
 * Cek apakah mesh kemungkinan terbuka (open mesh).
 * Heuristik: jika volume / area ratio sangat kecil → kemungkinan flat/terbuka.
 */
function isLikelyOpenMesh(area: number, volume: number): boolean {
    if (area <= 0) return true;
    const ratio = volume / area;
    return ratio < 0.001; // threshold: mesh sangat tipis/flat
}

// ── Scale detection ───────────────────────────────────────────────────────────

/**
 * Deteksi apakah skala model kemungkinan bukan meter.
 * Heuristik: bounding box seluruh scene.
 */
function detectScaleWarning(scene: THREE.Object3D): string | null {
    const box = new THREE.Box3().setFromObject(scene);
    const size = new THREE.Vector3();
    box.getSize(size);
    const maxDim = Math.max(size.x, size.y, size.z);

    if (maxDim < 0.1) {
        return `Dimensi model sangat kecil (${maxDim.toFixed(4)} unit). Model mungkin menggunakan satuan milimeter atau centimeter, bukan meter. Kalikan estimasi luas/volume dengan faktor konversi yang sesuai.`;
    }
    if (maxDim > 10000) {
        return `Dimensi model sangat besar (${maxDim.toFixed(0)} unit). Pastikan skala model sudah benar sebelum estimasi.`;
    }
    return null;
}

// ── Main composable ───────────────────────────────────────────────────────────

export function useGlbQuantities() {
    const isLoading  = ref(false);
    const progress   = ref(0);   // 0–100
    const error      = ref<string | null>(null);
    const result     = ref<GlbQuantityResult | null>(null);

    /**
     * Load file .glb dari URL dan hitung kuantitas semua mesh di scene.
     *
     * @param glbUrl  URL ke file .glb (bisa dari /storage/...)
     */
    async function calculate(glbUrl: string): Promise<GlbQuantityResult> {
        isLoading.value = true;
        progress.value  = 0;
        error.value     = null;
        result.value    = null;

        return new Promise((resolve, reject) => {
            // Setup Draco loader (model mungkin sudah dikompres)
            const dracoLoader = new DRACOLoader();
            dracoLoader.setDecoderPath('/draco/');

            const loader = new GLTFLoader();
            loader.setDRACOLoader(dracoLoader);

            loader.load(
                glbUrl,
                (gltf) => {
                    try {
                        progress.value = 80;
                        const quantityResult = processScene(gltf.scene);
                        result.value    = quantityResult;
                        isLoading.value = false;
                        progress.value  = 100;
                        resolve(quantityResult);
                    } catch (err: any) {
                        error.value     = err.message ?? 'Gagal memproses model.';
                        isLoading.value = false;
                        reject(err);
                    }
                },
                (xhr) => {
                    if (xhr.total > 0) {
                        progress.value = Math.round((xhr.loaded / xhr.total) * 75);
                    }
                },
                (err: any) => {
                    error.value     = `Gagal memuat file .glb: ${err.message ?? err}`;
                    isLoading.value = false;
                    reject(new Error(error.value));
                },
            );
        });
    }

    /**
     * Proses scene Three.js dan kembalikan GlbQuantityResult.
     */
    function processScene(scene: THREE.Object3D): GlbQuantityResult {
        const globalWarnings: string[] = [];

        // Scale detection
        const scaleWarning = detectScaleWarning(scene);
        if (scaleWarning) globalWarnings.push(scaleWarning);

        // Kumpulkan semua Mesh dari scene (traverse recursive)
        const allMeshes: THREE.Mesh[] = [];
        scene.traverse((child) => {
            if (child instanceof THREE.Mesh && child.geometry) {
                allMeshes.push(child);
            }
        });

        if (allMeshes.length === 0) {
            globalWarnings.push('Tidak ada mesh ditemukan di model ini.');
            return {
                meshes: [],
                total_area: 0,
                total_volume: 0,
                total_count: 0,
                warnings: globalWarnings,
                assumed_unit: 'meter',
            };
        }

        // Kelompokkan mesh berdasarkan nama (untuk count)
        const nameMap = new Map<string, THREE.Mesh[]>();

        for (const mesh of allMeshes) {
            // Normalisasi nama — gunakan nama parent jika mesh sendiri tidak bernama
            const rawName = mesh.name || mesh.parent?.name || 'Objek_Tanpa_Nama';
            // Bersihkan nama dari suffix numerik Three.js (contoh: "DINDING_001" → "DINDING_001")
            const name = rawName.trim();

            if (!nameMap.has(name)) nameMap.set(name, []);
            nameMap.get(name)!.push(mesh);
        }

        const meshQuantities: MeshQuantity[] = [];
        let totalArea   = 0;
        let totalVolume = 0;

        for (const [name, meshGroup] of nameMap.entries()) {
            const warnings: string[] = [];

            // Hitung area dan volume gabungan semua mesh dengan nama sama
            let groupArea   = 0;
            let groupVolume = 0;

            // Kumpulkan extras dari mesh pertama yang punya userData
            let extras: MeshQuantity['extras'] = {};
            let hasExtras = false;

            for (const mesh of meshGroup) {
                // Apply world matrix ke geometry untuk ukuran dunia nyata
                const geoCopy = mesh.geometry.clone();
                geoCopy.applyMatrix4(mesh.matrixWorld);

                groupArea   += computeSurfaceArea(geoCopy);
                groupVolume += computeSignedVolume(geoCopy);

                geoCopy.dispose();

                // Baca custom properties Blender dari userData
                // Blender menyimpan custom properties di .extras saat export .glb
                if (!hasExtras) {
                    const ud = mesh.userData as Record<string, unknown>;
                    // Blender GLB exporter menyimpan custom props di userData langsung
                    // atau di userData.extras tergantung versi
                    const rawExtras = (ud.extras ?? ud) as Record<string, unknown>;

                    const unitPrice     = _parseNumber(rawExtras.unit_price ?? rawExtras.harga ?? rawExtras.price);
                    const unit          = _parseString(rawExtras.unit ?? rawExtras.satuan);
                    const category      = _parseString(rawExtras.category ?? rawExtras.kategori);
                    const section       = _parseString(rawExtras.section ?? rawExtras.bagian);
                    const quantityBasis = _parseString(rawExtras.quantity_basis ?? rawExtras.basis);

                    if (unitPrice !== undefined || unit || category || section) {
                        extras    = { unit_price: unitPrice, unit, category, section, quantity_basis: quantityBasis };
                        hasExtras = true;
                    }
                }
            }

            const openMesh = isLikelyOpenMesh(groupArea, groupVolume);

            // Peringatan untuk kondisi tertentu
            if (name === 'Objek_Tanpa_Nama') {
                warnings.push('Objek tidak bernama — tidak dapat dipetakan ke harga satuan otomatis. Beri nama sesuai konvensi: KATEGORI_JENIS_SPEK (contoh: DINDING_BATA_15).');
            }
            if (openMesh && groupVolume > 0) {
                warnings.push('Mesh kemungkinan tidak tertutup (open mesh). Volume mungkin tidak akurat. Gunakan nilai luas sebagai acuan.');
            }

            // Bounding box representatif (dari mesh pertama)
            const bbox = new THREE.Box3().setFromObject(meshGroup[0]);
            const bboxSize = new THREE.Vector3();
            bbox.getSize(bboxSize);

            meshQuantities.push({
                name,
                area:         Math.round(groupArea * 100) / 100,
                volume:       Math.round(groupVolume * 1000) / 1000,
                count:        meshGroup.length,
                is_open_mesh: openMesh,
                warnings,
                bbox: {
                    x: Math.round(bboxSize.x * 100) / 100,
                    y: Math.round(bboxSize.y * 100) / 100,
                    z: Math.round(bboxSize.z * 100) / 100,
                },
                extras,
                has_extras: hasExtras,
            });

            totalArea   += groupArea;
            totalVolume += openMesh ? 0 : groupVolume; // hanya count volume mesh tertutup
        }

        // Sort: objek bernama dulu, lalu tanpa nama — descending area
        meshQuantities.sort((a, b) => {
            if (a.name === 'Objek_Tanpa_Nama') return 1;
            if (b.name === 'Objek_Tanpa_Nama') return -1;
            return b.area - a.area;
        });

        return {
            meshes:       meshQuantities,
            total_area:   Math.round(totalArea * 100) / 100,
            total_volume: Math.round(totalVolume * 1000) / 1000,
            total_count:  allMeshes.length,
            warnings:     globalWarnings,
            assumed_unit: 'meter',
        };
    }

    function reset() {
        isLoading.value = false;
        progress.value  = 0;
        error.value     = null;
        result.value    = null;
    }

    return {
        isLoading: readonly(isLoading),
        progress:  readonly(progress),
        error:     readonly(error),
        result:    readonly(result),
        calculate,
        reset,
    };
}

// ── Module-level helpers (bukan di dalam composable) ─────────────────────────

function _parseNumber(v: unknown): number | undefined {
    if (v === undefined || v === null || v === '') return undefined;
    const n = Number(v);
    return isNaN(n) ? undefined : n;
}

function _parseString(v: unknown): string | undefined {
    if (v === undefined || v === null) return undefined;
    const s = String(v).trim();
    return s === '' ? undefined : s;
}
