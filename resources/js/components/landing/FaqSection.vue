<script setup lang="ts">
import { ref, computed } from 'vue';
import { 
    HelpCircle, 
    ChevronDown, 
    MessageCircle, 
    Smartphone, 
    ShieldCheck, 
    RefreshCw, 
    Clock, 
    Users,
    DraftingCompass,
    BookOpen,
    Sparkles,
    Calculator
} from '@lucide/vue';

const openIndex = ref<number | null>(0);

const toggleFaq = (idx: number) => {
    openIndex.value = openIndex.value === idx ? null : idx;
};

type FaqCategory = 'Akses & Kemudahan' | 'Revisi & Kolaborasi' | 'CAD, AI & Dokumen' | 'Keamanan';

const faqs: { q: string; a: string; icon: any; category: FaqCategory }[] = [
    {
        icon: Smartphone,
        category: 'Akses & Kemudahan',
        q: 'Apakah saya perlu menginstal aplikasi atau software khusus?',
        a: 'Tidak perlu sama sekali. Cukup buka link undangan di browser favorit Anda — Chrome, Safari, Edge, atau Firefox — baik di laptop, iPad, maupun smartphone. Tidak ada instalasi plugin atau software CAD berat, langsung bisa melihat model 3D bangunan secara fotorealistik pada 60 FPS.'
    },
    {
        icon: Users,
        category: 'Akses & Kemudahan',
        q: 'Bagaimana cara klien mengakses proyek yang dikirimkan arsitek?',
        a: 'Arsitek mengirimkan tautan undangan ke alamat email klien. Klien cukup mengklik tautan tersebut dan masuk menggunakan akun Google atau email terdaftar. Sistem secara otomatis mencocokkan identitas dan memberikan akses instan tanpa langkah rumit.'
    },
    {
        icon: RefreshCw,
        category: 'Revisi & Kolaborasi',
        q: 'Bagaimana cara memberikan masukan atau meminta revisi?',
        a: 'Klik langsung pada bagian model 3D yang ingin dikomentari — dinding, lantai, bukaan fasad, atau material tertentu. Sebuah pin koordinat 3D presisi (X, Y, Z) akan muncul lengkap dengan garis penunjuk SVG dinamis dan form catatan. Arsitek mendapatkan notifikasi seketika dan riwayat diskusi terekam rapi.'
    },
    {
        icon: Clock,
        category: 'Revisi & Kolaborasi',
        q: 'Bagaimana sistem kuota revisi melindungi arsitek dari scope-creep?',
        a: 'Arsitek menetapkan batas kuota revisi per proyek (misalnya 3x per tahap desain). Penggunaan kuota terpantau secara transparan oleh kedua belah pihak. Setelah kuota habis, form revisi terkunci otomatis dan klien diarahkan untuk menyetujui desain akhir atau membuat kesepakatan addendum.'
    },
    {
        icon: MessageCircle,
        category: 'Revisi & Kolaborasi',
        q: 'Apakah tersedia fitur diskusi langsung di dalam platform?',
        a: 'Ya. Tersedia fitur in-app live chat realtime berbasis Laravel Reverb per proyek. Semua percakapan, tangkapan layar, status revisi, dan catatan terintegrasi di satu tempat sehingga riwayat keputusan desain tidak pernah hilang di chat WhatsApp.'
    },
    {
        icon: DraftingCompass,
        category: 'CAD, AI & Dokumen',
        q: 'Apakah PitchArch menyediakan lembar kerja CAD 2D dan bisa diekspor ke AutoCAD?',
        a: 'Ya. PitchArch dilengkapi editor CAD 2D interaktif berbasis browser (Konva.js). Anda dapat menggambar denah dengan dinding tebal, kolom, pintu swing arsitek, jendela, tangga, perabot, dan dimensi otomatis pada lembar kerja ISO (A4–A0). Gambar denah dapat diekspor langsung ke format AutoCAD DXF (dengan pemisahan layer WALLS, DOORS, DIMS yang rapi) serta SVG vektor.'
    },
    {
        icon: BookOpen,
        category: 'CAD, AI & Dokumen',
        q: 'Bagaimana Booklet Presentasi A3 dihasilkan dan apa saja isinya?',
        a: 'Booklet Presentasi A3 dapat diterbitkan secara instan sekali klik. Sistem otomatis merangkai Cover eksekutif proyek, Master Floor Plan Denah 2D, serta halaman interior & eksterior per ruangan yang dilengkapi visual render, catatan arsitek, Key Plan mini locator denah, dan Title Block (Kop Gambar). Siap diunduh dan dicetak langsung ke PDF landscape tanpa bleed.'
    },
    {
        icon: Sparkles,
        category: 'CAD, AI & Dokumen',
        q: 'Bagaimana cara kerja Direct AI Render pada model 3D?',
        a: 'Melalui integrasi Google Gemini API, arsitek dapat mengubah snapshot 3D mentah WebGL menjadi render fotorealistik arsitektur berkualitas tinggi dalam hitungan detik dengan 100% menjaga geometri dan proporsi asli model. Tersedia pilihan mood pencahayaan seperti Golden Hour, Blue Hour, hingga Interior Night.'
    },
    {
        icon: Calculator,
        category: 'CAD, AI & Dokumen',
        q: 'Apakah PitchArch dapat menghitung estimasi RAB otomatis?',
        a: 'Ya. PitchArch menyediakan GLB Estimator untuk mengekstrak volume dan luas mesh 3D secara otomatis, serta Worksheet-to-RAB Generator yang memetakan elemen denah CAD per ruangan ke database harga satuan arsitek. Dilengkapi kalkulasi overhead 10%, PPN 11%, dan ekspor 1-klik ke format Excel (.xlsx).'
    },
    {
        icon: ShieldCheck,
        category: 'Keamanan',
        q: 'Apakah orang lain bisa melihat atau membocorkan desain proyek saya?',
        a: 'Tidak. Setiap proyek diisolasi secara ketat dan hanya bisa diakses oleh email yang Anda undang secara eksplisit. Tidak ada URL publik terbuka. Arsitek juga dapat mencabut akses klien sewaktu-waktu dengan 1-klik untuk perlindungan hak cipta maksimal.'
    },
];

const categories = computed<FaqCategory[]>(() => {
    const seen = new Set<FaqCategory>();
    const result: FaqCategory[] = [];
    for (const f of faqs) {
        if (!seen.has(f.category)) {
            seen.add(f.category);
            result.push(f.category);
        }
    }
    return result;
});

const activeCategory = ref<FaqCategory | 'Semua'>('Semua');

const filteredFaqs = computed(() =>
    activeCategory.value === 'Semua'
        ? faqs
        : faqs.filter((f) => f.category === activeCategory.value)
);

const categoryColor: Record<FaqCategory, string> = {
    'Akses & Kemudahan': 'text-sky-400 border-sky-500/30 bg-sky-500/10',
    'Revisi & Kolaborasi': 'text-purple-400 border-purple-500/30 bg-purple-500/10',
    'CAD, AI & Dokumen': 'text-amber-400 border-amber-500/30 bg-amber-500/10',
    'Keamanan': 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
};
</script>

<template>
    <section id="faq" class="relative py-24 sm:py-32 bg-[#07080b] border-t border-white/10 overflow-hidden">
        <!-- Ambient Glow -->
        <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[600px] w-full max-w-5xl rounded-full bg-gradient-to-r from-indigo-600/8 via-purple-600/6 to-blue-600/8 blur-[180px]"></div>

        <div class="relative mx-auto max-w-3xl px-6">
            <!-- Header -->
            <div class="text-center space-y-5">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300 backdrop-blur-xl">
                    <HelpCircle class="h-3 w-3 text-indigo-400" />
                    <span>Pertanyaan Umum</span>
                </div>
                <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-white font-sans">
                    Ada yang Ingin Anda<br class="hidden sm:inline" />
                    <span class="bg-gradient-to-r from-white via-neutral-200 to-neutral-500 bg-clip-text text-transparent"> Tanyakan?</span>
                </h2>
                <p class="text-sm sm:text-base text-neutral-400 max-w-lg mx-auto leading-relaxed">
                    Jawaban jujur untuk arsitek, klien pemilik properti, dan siapa saja yang ingin tahu lebih banyak sebelum memulai.
                </p>
            </div>

            <!-- Category Filter Pills -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-2">
                <button
                    type="button"
                    @click="activeCategory = 'Semua'; openIndex = null"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition-all duration-200"
                    :class="activeCategory === 'Semua'
                        ? 'border-white/30 bg-white/10 text-white'
                        : 'border-white/10 bg-white/[0.03] text-neutral-400 hover:border-white/20 hover:text-neutral-200'"
                >
                    Semua
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat"
                    type="button"
                    @click="activeCategory = cat; openIndex = null"
                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition-all duration-200"
                    :class="activeCategory === cat
                        ? categoryColor[cat]
                        : 'border-white/10 bg-white/[0.03] text-neutral-400 hover:border-white/20 hover:text-neutral-200'"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Accordion List -->
            <div class="mt-8 space-y-3">
                <div
                    v-for="(faq, idx) in filteredFaqs"
                    :key="faq.q"
                    class="group rounded-2xl border bg-white/[0.02] backdrop-blur-xl transition-all duration-300 overflow-hidden"
                    :class="openIndex === idx
                        ? 'border-indigo-500/40 bg-white/[0.05] shadow-[0_0_30px_rgba(99,102,241,0.08)]'
                        : 'border-white/[0.08] hover:border-white/20 hover:bg-white/[0.03]'"
                >
                    <button
                        type="button"
                        @click="toggleFaq(idx)"
                        class="flex w-full items-start gap-4 p-5 sm:p-6 text-left"
                    >
                        <!-- Icon -->
                        <div
                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition-colors duration-300"
                            :class="openIndex === idx
                                ? 'border-indigo-500/40 bg-indigo-500/15 text-indigo-400'
                                : 'border-white/10 bg-white/[0.04] text-neutral-500 group-hover:text-neutral-300'"
                        >
                            <component :is="faq.icon" class="h-4 w-4" />
                        </div>

                        <div class="flex-1 min-w-0">
                            <!-- Category badge -->
                            <span
                                class="inline-block rounded-full border px-2 py-0.5 text-[10px] font-semibold mb-1.5"
                                :class="categoryColor[faq.category]"
                            >
                                {{ faq.category }}
                            </span>
                            <p class="text-sm sm:text-base font-semibold text-white leading-snug pr-2">
                                {{ faq.q }}
                            </p>
                        </div>

                        <!-- Chevron -->
                        <div
                            class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border transition-all duration-300"
                            :class="openIndex === idx
                                ? 'rotate-180 border-indigo-500/40 bg-indigo-500/15 text-indigo-400'
                                : 'border-white/10 bg-white/[0.03] text-neutral-500'"
                        >
                            <ChevronDown class="h-3.5 w-3.5" />
                        </div>
                    </button>

                    <!-- Answer with smooth expand -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div v-if="openIndex === idx" class="overflow-hidden">
                            <div class="px-5 sm:px-6 pb-5 sm:pb-6 pl-[4.25rem] border-t border-white/[0.06]">
                                <p class="pt-4 text-sm text-neutral-300 leading-relaxed">
                                    {{ faq.a }}
                                </p>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Bottom CTA nudge -->
            <div class="mt-14 text-center">
                <p class="text-sm text-neutral-400">
                    Masih ada pertanyaan lain?
                </p>
                <a
                    href="mailto:support@pitcharch.id"
                    class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-400 hover:text-indigo-300 transition-colors duration-200"
                >
                    <MessageCircle class="h-4 w-4" />
                    Hubungi kami langsung
                </a>
            </div>
        </div>
    </section>
</template>
