<script setup lang="ts">
import { ref } from 'vue';
import { HelpCircle, ChevronDown, Sparkles } from '@lucide/vue';

const openIndex = ref<number | null>(0);

const toggleFaq = (idx: number) => {
    openIndex.value = openIndex.value === idx ? null : idx;
};

const faqs = [
    {
        q: 'Apakah klien saya perlu menginstal software khusus atau plugin 3D?',
        a: 'Sama sekali tidak. AETHER 3D berjalan 100% native di browser modern (Chrome, Safari, Edge, Firefox) memanfaatkan akselerasi hardware WebGL. Klien cukup membuka link undangan di laptop, iPad, maupun smartphone tanpa install apa pun.'
    },
    {
        q: 'Format file 3D apa saja yang didukung untuk diunggah?',
        a: 'Platform mendukung format standar web 3D yaitu .glb dan .gltf. Anda dapat mengekspor model langsung dari software arsitektur favorit seperti SketchUp, Autodesk Revit, Rhino 3D, Blender, maupun Archicad.'
    },
    {
        q: 'Bagaimana cara kerja sistem pembatasan kuota revisi (Revision Gatekeeper)?',
        a: 'Arsitek menetapkan batas kuota revisi (misalnya 3x revisi) saat membuat proyek. Setiap pin revisi baru yang ditempatkan klien akan memotong sisa kuota. Ketika kuota habis, form penambahan pin dikunci otomatis dan klien disarankan mengajukan addendum jika memerlukan revisi tambahan.'
    },
    {
        q: 'Apakah file desain 3D saya aman dari pencurian aset?',
        a: 'Sangat aman. Kami tidak menggunakan tautan publik tanpa proteksi. Akses proyek diamankan oleh ProjectClientAccessMiddleware dengan verifikasi email terdaftar. Selain itu, geometri model dikompresi dengan binary Draco sehingga tidak mudah diekstrak mentah.'
    },
    {
        q: 'Apakah arsitek dan klien dapat berdiskusi secara real-time?',
        a: 'Ya. AETHER 3D dilengkapi in-app real-time chat berbasis WebSocket (Laravel Reverb). Seluruh catatan dan koordinat spasial pin tersinkronisasi seketika, menjaga konteks diskusi tetap fokus di dalam model bangunan.'
    }
];
</script>

<template>
    <section class="relative py-28 sm:py-36 bg-[#07080b] border-t border-white/10">
        <!-- Ambient Glow -->
        <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-full max-w-4xl rounded-full bg-indigo-600/5 blur-[160px]"></div>

        <div class="relative mx-auto max-w-4xl px-6">
            <!-- Header -->
            <div class="text-center space-y-4">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300">
                    <HelpCircle class="h-3 w-3 text-indigo-400" />
                    <span>Pertanyaan Umum</span>
                </div>
                <h2 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-white font-sans">
                    Semua yang Perlu Anda Ketahui.
                </h2>
                <p class="text-sm sm:text-base text-neutral-400 max-w-xl mx-auto">
                    Jawaban transparan untuk arsitek studio, visualizer independen, dan klien pemilik properti.
                </p>
            </div>

            <!-- Accordion List -->
            <div class="mt-14 space-y-4">
                <div
                    v-for="(faq, idx) in faqs"
                    :key="idx"
                    class="rounded-2xl border border-white/10 bg-white/[0.02] backdrop-blur-xl transition-all duration-300 overflow-hidden"
                    :class="openIndex === idx ? 'border-indigo-500/40 bg-white/[0.04]' : 'hover:border-white/20'"
                >
                    <button
                        type="button"
                        @click="toggleFaq(idx)"
                        class="flex w-full items-center justify-between p-6 text-left"
                    >
                        <span class="text-base sm:text-lg font-semibold text-white pr-4">
                            {{ faq.q }}
                        </span>
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/15 bg-white/5 transition-transform duration-300"
                            :class="openIndex === idx ? 'rotate-180 bg-indigo-500/20 border-indigo-500/40 text-indigo-400' : 'text-neutral-400'"
                        >
                            <ChevronDown class="h-4 w-4" />
                        </div>
                    </button>

                    <div
                        v-show="openIndex === idx"
                        class="px-6 pb-6 pt-0 text-sm text-neutral-300 leading-relaxed border-t border-white/5 mt-2 pt-4"
                    >
                        {{ faq.a }}
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
