<script setup lang="ts">
import { ref } from 'vue';
import { UploadCloud, ShieldCheck, MapPin, Sliders, MessageSquare, CheckCircle2, ChevronRight, FileCode, Lock, Sparkles } from '@lucide/vue';

const activeStage = ref(0);

const stages = [
    {
        id: 'upload',
        title: 'Kompresi Draco Otomatis',
        badge: 'Tahap 01',
        subtitle: 'Upload Model 3D Sekali Klik',
        description: 'Cukup tarik dan letakkan file .glb atau .gltf Anda. Pipeline gltf-pipeline di server otomatis mengompresi geometri mesh hingga 85% lebih ringan tanpa mengorbankan detail tekstur dan material fotorealistik.',
        icon: UploadCloud,
        highlightColor: 'from-blue-500/20 to-indigo-500/10',
        stats: [
            { label: 'Rata-rata Kompresi', val: '84.6%' },
            { label: 'Waktu Proses', val: '< 3.2 dtk' },
            { label: 'Target Viewer', val: 'WebGL 60fps' }
        ]
    },
    {
        id: 'invite',
        title: 'Undangan Klien Eksklusif',
        badge: 'Tahap 02',
        subtitle: 'Akses Aman Anti Bocor',
        description: 'Tinggalkan link publik read-only yang berisiko dicuri. Masukkan alamat email klien Anda. Klien wajib login via Google OAuth atau Email dengan verifikasi pencocokan identitas ketat (ProjectClientAccess).',
        icon: ShieldCheck,
        highlightColor: 'from-emerald-500/20 to-teal-500/10',
        stats: [
            { label: 'Tingkat Keamanan', val: 'End-to-End' },
            { label: 'Otorisasi', val: 'Email-Matching' },
            { label: 'Model Akun', val: 'Dual-Capacity' }
        ]
    },
    {
        id: 'spatial',
        title: 'Anotasi Pin Spasial (X, Y, Z)',
        badge: 'Tahap 03',
        subtitle: 'Feedback Tepat di Permukaan Desain',
        description: 'Klien cukup mengklik titik mana pun pada geometri bangunan 3D. Raycasting Three.js menghitung koordinat spasial dan normal vector seketika, membuka bubble komentar dengan garis leader line yang dinamis.',
        icon: MapPin,
        highlightColor: 'from-indigo-500/20 to-purple-500/10',
        stats: [
            { label: 'Presisi Koordinat', val: '3 Dimensi' },
            { label: 'Leader Line', val: 'SVG Realtime' },
            { label: 'Mobile Touch', val: 'Didukung Penuh' }
        ]
    },
    {
        id: 'gatekeeper',
        title: 'Revision Gatekeeper Wall',
        badge: 'Tahap 04',
        subtitle: 'Lindungi Batas Kuota Revisi',
        description: 'Arsitek menetapkan batas kuota revisi klien per proyek (misal: 3x revisi). Saat kuota tercapai, sistem secara otomatis mengunci form penambahan pin baru dan menampilkan badge transparan kepada klien.',
        icon: Sliders,
        highlightColor: 'from-amber-500/20 to-orange-500/10',
        stats: [
            { label: 'Batas Default', val: '3x Revisi' },
            { label: 'Proteksi API', val: '403 Forbidden' },
            { label: 'Transparansi', val: 'Counter Terbuka' }
        ]
    },
    {
        id: 'chat',
        title: 'In-App Real-time Chat',
        badge: 'Tahap 05',
        subtitle: 'Diskusi Langsung dalam Konteks 3D',
        description: 'Komunikasi teks umum terintegrasi langsung di samping 3D Viewer via Laravel Reverb WebSocket. Tanpa perlu beralih ke WhatsApp atau email yang memecah konsentrasi revisi.',
        icon: MessageSquare,
        highlightColor: 'from-purple-500/20 to-pink-500/10',
        stats: [
            { label: 'Protokol', val: 'Reverb WS' },
            { label: 'Latensi', val: '< 50ms' },
            { label: 'Private Channel', val: 'Per-Project' }
        ]
    }
];
</script>

<template>
    <section id="experience" class="relative py-28 sm:py-36 bg-[#07080b] border-t border-white/10">
        <!-- Ambient lighting -->
        <div class="pointer-events-none absolute top-1/3 right-0 h-96 w-96 rounded-full bg-indigo-600/10 blur-[150px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Section Header -->
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300">
                    <Sparkles class="h-3 w-3 text-indigo-400" />
                    <span>Scrollytelling Experience</span>
                </div>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-white sm:text-5xl font-sans">
                    Alur Kerja Spasial. <br />
                    <span class="bg-gradient-to-r from-neutral-200 via-neutral-400 to-neutral-500 bg-clip-text text-transparent">
                        Dari File Mentah ke Kesepakatan Klien.
                    </span>
                </h2>
                <p class="mt-4 text-sm sm:text-base text-neutral-400 leading-relaxed">
                    Setiap tahapan presentasi dirancang agar arsitek dan klien berada di halaman pemahaman yang sama, tanpa friksi software dan tanpa miskomunikasi.
                </p>
            </div>

            <!-- Stage Navigation Tabs -->
            <div class="mt-12 flex overflow-x-auto pb-4 gap-2.5 scrollbar-none sm:grid sm:grid-cols-5 border-b border-white/10">
                <button
                    v-for="(stage, idx) in stages"
                    :key="stage.id"
                    type="button"
                    @click="activeStage = idx"
                    class="flex items-center gap-2.5 rounded-2xl px-4 py-3 text-left transition-all duration-300 min-w-[200px] sm:min-w-0"
                    :class="activeStage === idx 
                        ? 'bg-white/10 border border-white/25 shadow-lg text-white' 
                        : 'bg-transparent border border-transparent text-neutral-400 hover:text-neutral-200 hover:bg-white/5'"
                >
                    <component :is="stage.icon" class="h-4 w-4 shrink-0" :class="activeStage === idx ? 'text-indigo-400' : 'text-neutral-500'" />
                    <div class="overflow-hidden">
                        <div class="text-[10px] uppercase font-mono tracking-wider text-neutral-400">{{ stage.badge }}</div>
                        <div class="text-xs font-semibold truncate">{{ stage.title }}</div>
                    </div>
                </button>
            </div>

            <!-- Active Stage Interactive Display Card -->
            <div class="mt-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <!-- Left Details Column (5 cols) -->
                <div class="lg:col-span-5 space-y-6">
                    <div class="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-300">
                        <span>{{ stages[activeStage].badge }}</span>
                        <span>•</span>
                        <span>{{ stages[activeStage].subtitle }}</span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-bold tracking-tight text-white leading-tight">
                        {{ stages[activeStage].title }}
                    </h3>

                    <p class="text-sm leading-relaxed text-neutral-300">
                        {{ stages[activeStage].description }}
                    </p>

                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-3 gap-3 pt-2">
                        <div
                            v-for="stat in stages[activeStage].stats"
                            :key="stat.label"
                            class="rounded-2xl border border-white/10 bg-white/[0.03] p-3 backdrop-blur-xl text-center"
                        >
                            <div class="text-sm font-bold text-white font-mono">{{ stat.val }}</div>
                            <div class="mt-1 text-[10px] text-neutral-400 leading-tight">{{ stat.label }}</div>
                        </div>
                    </div>

                    <!-- Stage Step Progression Buttons -->
                    <div class="flex items-center gap-3 pt-4">
                        <button
                            v-for="(_, dotIdx) in stages"
                            :key="dotIdx"
                            type="button"
                            @click="activeStage = dotIdx"
                            class="h-2 rounded-full transition-all duration-300"
                            :class="activeStage === dotIdx ? 'w-8 bg-indigo-500' : 'w-2 bg-white/20 hover:bg-white/40'"
                            :aria-label="`Pindah ke tahap ${dotIdx + 1}`"
                        ></button>
                    </div>
                </div>

                <!-- Right Visual Simulator Column (7 cols) -->
                <div class="lg:col-span-7">
                    <div class="relative overflow-hidden rounded-3xl border border-white/15 bg-gradient-to-br from-[#0e1017] via-[#090a0f] to-black p-6 sm:p-8 shadow-2xl backdrop-blur-2xl">
                        <!-- Stage 0: Draco Compression Visual Simulation -->
                        <div v-if="activeStage === 0" class="space-y-6 animate-fadeIn">
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-500/20 border border-blue-500/30">
                                        <FileCode class="h-5 w-5 text-blue-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-white">modern_luxury_residence.glb</div>
                                        <div class="text-xs text-neutral-400">Draco Geometry Compression Job</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-emerald-500/20 border border-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-400">
                                    Selesai (84% Disimpan)
                                </span>
                            </div>

                            <!-- Progress Comparison -->
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs text-neutral-400 mb-1.5 font-mono">
                                        <span>Ukuran Asli Mentah</span>
                                        <span class="text-neutral-300">89.4 MB</span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-white/10 overflow-hidden">
                                        <div class="h-full bg-neutral-500 rounded-full w-full"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-xs text-emerald-400 mb-1.5 font-mono">
                                        <span>Hasil Kompresi Draco</span>
                                        <span class="font-bold">14.3 MB (Muat dalam 1.2 detik)</span>
                                    </div>
                                    <div class="h-2.5 w-full rounded-full bg-white/10 overflow-hidden p-0.5">
                                        <div class="h-full bg-gradient-to-r from-emerald-500 to-indigo-500 rounded-full w-[16%]"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-black/40 p-4 text-xs text-neutral-300 space-y-1.5 font-mono">
                                <div class="flex items-center gap-2 text-indigo-400">
                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                    <span>DRACOLoader Buffer: Posisi, Normal, UV0 terenkapsulasi presisi</span>
                                </div>
                                <div class="flex items-center gap-2 text-indigo-400">
                                    <CheckCircle2 class="h-3.5 w-3.5" />
                                    <span>Material PBR & Tekstur 4K dipertahankan 100% fotorealistik</span>
                                </div>
                            </div>
                        </div>

                        <!-- Stage 1: Client Invitation Visual Simulation -->
                        <div v-else-if="activeStage === 1" class="space-y-6 animate-fadeIn">
                            <div class="flex items-center justify-between border-b border-white/10 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500/20 border border-emerald-500/30">
                                        <Lock class="h-5 w-5 text-emerald-400" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-white">Daftar Undangan Klien Proyek</div>
                                        <div class="text-xs text-neutral-400">ProjectClientAccessMiddleware Guard</div>
                                    </div>
                                </div>
                                <span class="rounded-full bg-indigo-500/20 border border-indigo-500/30 px-3 py-1 text-xs font-semibold text-indigo-300">
                                    Zero Link Leak
                                </span>
                            </div>

                            <!-- Invitation Rows -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/40 p-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-bold text-white">BP</div>
                                        <div>
                                            <div class="text-xs font-semibold text-white">budi.prasetyo@client.id</div>
                                            <div class="text-[10px] text-neutral-400">Diundang: 20 Sep 2026 • Status Klien</div>
                                        </div>
                                    </div>
                                    <span class="rounded bg-emerald-500/20 px-2 py-0.5 text-[10px] font-semibold text-emerald-400">
                                        Accepted (Aktif)
                                    </span>
                                </div>

                                <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-black/40 p-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold text-white">SA</div>
                                        <div>
                                            <div class="text-xs font-semibold text-white">siti.amanda@investor.com</div>
                                            <div class="text-[10px] text-neutral-400">Diundang: 22 Sep 2026 • Status Reviewer</div>
                                        </div>
                                    </div>
                                    <span class="rounded bg-yellow-500/20 px-2 py-0.5 text-[10px] font-semibold text-yellow-300">
                                        Pending Approval
                                    </span>
                                </div>
                            </div>

                            <p class="text-xs text-neutral-400">
                                🔒 Sistem menjamin jika link dibagikan ke orang lain tanpa email undangan yang sah, sistem langsung menolak dengan status <code class="text-indigo-300">403 Forbidden</code>.
                            </p>
                        </div>

                        <!-- Stage 2: Spatial Pin Visual Simulation -->
                        <div v-else-if="activeStage === 2" class="space-y-6 animate-fadeIn">
                            <div class="relative h-64 rounded-2xl border border-white/15 bg-black/60 overflow-hidden flex items-center justify-center">
                                <img
                                    src="/images/aether_villa_hero.jpg"
                                    alt="Mockup Villa Preview"
                                    class="absolute inset-0 h-full w-full object-cover opacity-60"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>

                                <!-- Dynamic Pin on Center -->
                                <div class="relative z-10 flex flex-col items-center">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500 text-xs font-bold text-white shadow-[0_0_25px_rgba(99,102,241,1)] border-2 border-white animate-bounce">
                                        2
                                    </div>
                                    <div class="mt-3 rounded-2xl border border-white/20 bg-black/90 px-4 py-2.5 shadow-2xl backdrop-blur-xl text-center max-w-xs">
                                        <div class="text-[11px] font-mono text-indigo-400">Vector3(12.4, 2.8, -9.1)</div>
                                        <div class="mt-1 text-xs font-medium text-white">"Material kaca tolong pilih low-e glass"</div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-neutral-300 pt-1">
                                <span class="flex items-center gap-1.5">
                                    <CheckCircle2 class="h-4 w-4 text-emerald-400" />
                                    Draggable Cards dengan Leader Line SVG
                                </span>
                                <span class="font-mono text-neutral-400">Three.Raycaster Accuracy: 99.9%</span>
                            </div>
                        </div>

                        <!-- Stage 3: Revision Gatekeeper Visual Simulation -->
                        <div v-else-if="activeStage === 3" class="space-y-6 animate-fadeIn">
                            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-amber-300 font-semibold text-sm">
                                        <Sliders class="h-4 w-4" />
                                        <span>Batas Revisi Klien Terkunci</span>
                                    </div>
                                    <span class="rounded-full bg-amber-400/20 px-2.5 py-0.5 text-xs font-mono font-bold text-amber-300">
                                        3 / 3 Revisi
                                    </span>
                                </div>
                                <p class="mt-3 text-xs text-neutral-300 leading-relaxed">
                                    Kuota revisi yang telah disepakati pada perjanjian arsitektur telah terpenuhi. Penambahan catatan revisi baru dihentikan secara otomatis untuk melindungi waktu kerja arsitek.
                                </p>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-black/50 p-4 space-y-2 text-xs">
                                <div class="flex justify-between text-neutral-400">
                                    <span>Revisi #1 (Denah & Struktur Kolom)</span>
                                    <span class="text-emerald-400 font-medium">Disetujui</span>
                                </div>
                                <div class="flex justify-between text-neutral-400">
                                    <span>Revisi #2 (Fasad Cantilever & Material Kaca)</span>
                                    <span class="text-emerald-400 font-medium">Disetujui</span>
                                </div>
                                <div class="flex justify-between text-neutral-400">
                                    <span>Revisi #3 (Pencahayaan Fasad & Interior Dusk)</span>
                                    <span class="text-amber-400 font-medium">Sedang Dikerjakan</span>
                                </div>
                            </div>
                        </div>

                        <!-- Stage 4: In-App Chat Visual Simulation -->
                        <div v-else-if="activeStage === 4" class="space-y-4 animate-fadeIn">
                            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                    <span class="text-xs font-semibold text-white">Ruang Diskusi: Villa Cantilever</span>
                                </div>
                                <span class="text-[10px] font-mono text-neutral-400">Broadcasting via Laravel Reverb</span>
                            </div>

                            <div class="space-y-3 py-2">
                                <!-- Message 1 (Client) -->
                                <div class="flex flex-col items-start max-w-[80%]">
                                    <div class="rounded-2xl rounded-tl-sm bg-white/10 border border-white/15 px-3.5 py-2 text-xs text-neutral-200">
                                        Halo Pak Arsitek, kami sudah meninjau fasad dusk di 3D Viewer. Apakah lampu strip bawah cantilever bisa diubah temperatur warnanya ke 3000K warm white?
                                    </div>
                                    <span class="mt-1 text-[10px] text-neutral-500 font-mono">Budi (Klien) • 14:02</span>
                                </div>

                                <!-- Message 2 (Architect) -->
                                <div class="flex flex-col items-end max-w-[80%] ml-auto">
                                    <div class="rounded-2xl rounded-tr-sm bg-indigo-600 text-white px-3.5 py-2 text-xs shadow-md">
                                        Tentu Pak Budi! Model sudah kami perbarui dengan warm white 3000K. Silakan refresh atau buka viewer, pin catatan sudah tersimpan rapi.
                                    </div>
                                    <span class="mt-1 text-[10px] text-indigo-400 font-mono">Anda (Arsitek) • 14:05</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 rounded-xl border border-white/10 bg-black/60 p-2 text-xs text-neutral-500">
                                <span class="h-1.5 w-1.5 rounded-full bg-neutral-500 animate-pulse"></span>
                                <span>Klien sedang membaca pesan...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
