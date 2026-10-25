import { ref } from 'vue';

export interface UpgradeModalOptions {
    feature?: 'rab' | 'drawing' | 'vision' | 'general';
    title?: string;
    description?: string;
    requiredPlan?: 'Pro' | 'Enterprise';
    featuresList?: string[];
}

interface UpgradeModalState extends UpgradeModalOptions {
    isOpen: boolean;
}

const state = ref<UpgradeModalState>({
    isOpen: false,
    feature: 'rab',
    title: 'Fitur RAB & Lembar Kerja',
    description: 'Fitur RAB (Rencana Anggaran Biaya) & Lembar Kerja hanya tersedia untuk pengguna Pro dan Enterprise.',
    requiredPlan: 'Pro',
    featuresList: [],
});

export function useUpgradeModal() {
    function openUpgradeModal(options?: UpgradeModalOptions) {
        const feature = options?.feature ?? 'rab';
        const requiredPlan = options?.requiredPlan ?? (feature === 'drawing' ? 'Enterprise' : 'Pro');

        let defaultTitle = 'Fitur Eksklusif Pro';
        let defaultDesc = 'Tingkatkan paket Anda untuk mengakses fitur profesional ini.';
        let defaultList: string[] = [];

        if (feature === 'rab') {
            defaultTitle = 'Fitur RAB & Lembar Kerja';
            defaultDesc = 'Fitur RAB (Rencana Anggaran Biaya) & Lembar Kerja hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda untuk menikmati fitur ini.';
            defaultList = [
                'Kalkulasi Kuantitas Otomatis dari Model 3D & SketchUp',
                'Master Data Harga Satuan Bahan, Upah & Analisa AHSP',
                'Penyusunan Rencana Anggaran Biaya Proyek Tanpa Batas',
                'Ekspor Laporan RAB ke Format Excel & PDF Siap Kirim ke Klien',
            ];
        } else if (feature === 'drawing') {
            defaultTitle = 'Fitur Lembar Kerja CAD 2D';
            defaultDesc = 'Fitur Lembar Kerja 2D (Drawing CAD & Shop Drawing) eksklusif untuk pengguna paket Enterprise.';
            defaultList = [
                'Editor Gambar Kerja 2D Interaktif Berbasis Vektor',
                'Shop Drawing Interior & Furniture Otomatis',
                'Ekspor Format AutoCAD (.DXF) & Cetak PDF Multi-skala',
                'Kolaborasi Tim Multi-desainer Tanpa Batas',
            ];
        } else if (feature === 'vision') {
            defaultTitle = 'AI Vision Studio';
            defaultDesc = 'Fitur AI Vision Studio hanya tersedia untuk pengguna paket Pro dan Enterprise.';
            defaultList = [
                'Render AI Photorealistik Berbasis Tangkapan Kamera 3D',
                'Multi-Preset Suasana (Daylight, Twilight, Rainy, Luxury Night)',
                'Prompt Builder Arsitektural Presisi Tinggi',
                'Simulasi Material & Pencahayaan Realistis Seketika',
            ];
        } else {
            defaultTitle = options?.title ?? 'Upgrade Paket Berlangganan';
            defaultDesc = options?.description ?? 'Akses fitur profesional ini dengan meningkatkan paket berlangganan Anda.';
            defaultList = [
                'Kapasitas Penyimpanan Model 3D Lebih Besar',
                'Akses Penuh Seluruh Fitur Studio Arsitektur',
                'Dukungan Prioritas & Pembaruan Sistem Berkala',
            ];
        }

        state.value = {
            isOpen: true,
            feature,
            title: options?.title ?? defaultTitle,
            description: options?.description ?? defaultDesc,
            requiredPlan,
            featuresList: options?.featuresList?.length ? options.featuresList : defaultList,
        };
    }

    function closeUpgradeModal() {
        state.value.isOpen = false;
    }

    return {
        state,
        openUpgradeModal,
        closeUpgradeModal,
    };
}
