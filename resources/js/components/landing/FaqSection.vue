<script setup lang="ts">
import { ref, computed } from 'vue';
import { HelpCircle, ChevronDown, MessageCircle, Smartphone, ShieldCheck, RefreshCw, Clock, Users } from '@lucide/vue';

const openIndex = ref<number | null>(0);

const toggleFaq = (idx: number) => {
    openIndex.value = openIndex.value === idx ? null : idx;
};

type FaqCategory = 'Akses & Kemudahan' | 'Revisi & Kolaborasi' | 'Keamanan';

const faqs: { q: string; a: string; icon: any; category: FaqCategory }[] = [
    {
        icon: Smartphone,
        category: 'Akses & Kemudahan',
        q: 'Apakah saya perlu menginstal aplikasi atau software khusus?',
        a: 'Tidak perlu sama sekali. Cukup buka link undangan di browser favorit Anda — Chrome, Safari, Edge, atau Firefox — baik di laptop, iPad, maupun smartphone. Tidak ada instalasi, tidak ada plugin, langsung bisa melihat model 3D bangunan Anda secara penuh.'
    },
    {
        icon: Users,
        category: 'Akses & Kemudahan',
        q: 'Bagaimana cara saya mengakses proyek yang dikirimkan arsitek?',
        a: 'Arsitek Anda akan mengirimkan tautan undangan ke email Anda. Klik tautan tersebut, masuk dengan Google atau akun terdaftar, dan Anda langsung berada di dalam tampilan 3D proyek. Prosesnya tidak lebih dari 30 detik.'
    },
    {
        icon: RefreshCw,
        category: 'Revisi & Kolaborasi',
        q: 'Bagaimana cara saya memberikan masukan atau meminta revisi?',
        a: 'Klik langsung pada bagian model yang ingin Anda komentari — dinding, jendela, material, atau area tertentu. Sebuah pin akan muncul di titik tersebut dan Anda bisa langsung menuliskan catatan. Arsitek mendapatkan notifikasi seketika, lengkap dengan koordinat spasial persis bagian yang Anda maksud. Tidak perlu bolak-balik email atau tangkapan layar yang membingungkan.'
    },
    {
        icon: Clock,
        category: 'Revisi & Kolaborasi',
        q: 'Berapa banyak revisi yang bisa saya ajukan?',
        a: 'Kuota revisi ditentukan oleh arsitek sesuai kesepakatan kontrak Anda. Sistem kami akan menampilkan sisa kuota secara transparan, sehingga Anda selalu tahu berapa banyak pin revisi yang masih tersedia sebelum perlu addendum.'
    },
    {
        icon: MessageCircle,
        category: 'Revisi & Kolaborasi',
        q: 'Apakah saya bisa langsung berdiskusi dengan arsitek di dalam platform?',
        a: 'Ya. Ada fitur live chat bawaan yang terhubung langsung ke proyek. Semua percakapan, pin revisi, dan catatan tersimpan di satu tempat — sehingga riwayat diskusi Anda dengan arsitek tidak pernah tercecer.'
    },
    {
        icon: ShieldCheck,
        category: 'Keamanan',
        q: 'Apakah orang lain bisa melihat desain rumah saya?',
        a: 'Tidak. Setiap proyek hanya bisa diakses oleh email yang secara eksplisit Anda undang. Tidak ada tautan publik yang bisa disebarkan sembarangan. Arsitek Anda pun bisa mencabut akses kapan saja jika diperlukan, menjaga privasi desain Anda sepenuhnya.'
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
    'Keamanan': 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10',
};
</script>

<template>
    <section class="relative py-24 sm:py-32 bg-[#07080b] border-t border-white/10 overflow-hidden">
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
