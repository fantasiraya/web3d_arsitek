<script setup lang="ts">
import { ShieldCheck, Users, Zap, Sliders, Cpu, Radio, Sparkles, Lock, ArrowUpRight } from '@lucide/vue';
</script>

<template>
    <section id="features" class="relative py-20 sm:py-24 bg-[#050608] text-white">
        <!-- Ambient Background Glow -->
        <div class="pointer-events-none absolute bottom-1/4 left-1/2 -translate-x-1/2 h-96 w-full max-w-5xl bg-blue-600/5 blur-[160px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Section Title -->
            <div class="mx-auto max-w-3xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300">
                    <Cpu class="h-3 w-3 text-indigo-400" />
                    <span>Dirancang untuk Alur Kerja Arsitek</span>
                </div>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl font-sans">
                    Presentasi Desain Lebih Meyakinkan. <br />
                    <span class="bg-gradient-to-r from-neutral-200 via-neutral-400 to-neutral-500 bg-clip-text text-transparent">
                        Karya Tetap Aman di Tangan Anda.
                    </span>
                </h2>
            </div>

            <!-- Apple Pro Bento Grid -->
            <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Bento 1 (Large 2 Cols): Client Invitation by Email -->
                <div class="md:col-span-2 group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 sm:p-10 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25">
                    <div class="pointer-events-none absolute -right-16 -bottom-16 h-64 w-64 rounded-full bg-emerald-500/10 blur-[80px]"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400">
                            <Lock class="h-6 w-6" />
                        </div>
                        <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-300">
                            Karya Terlindungi
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Undangan Klien via Email
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Desain Anda tidak akan beredar ke pihak yang tidak berkepentingan. Setiap proyek hanya bisa dibuka oleh klien yang Anda undang lewat email, dan identitas mereka dipastikan <span class="text-emerald-300 font-medium">terverifikasi</span> sebelum model 3D ditampilkan.
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3 text-xs text-neutral-400">
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">Hanya Klien Undangan</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">Masuk dengan Akun Google</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5">Cabut Akses Kapan Saja</span>
                    </div>
                </div>

                <!-- Bento 2 (1 Col): Dual-Capacity Model -->
                <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25 flex flex-col justify-between">
                    <div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/20 border border-indigo-500/30 text-indigo-400">
                            <Users class="h-6 w-6" />
                        </div>
                        <h3 class="mt-6 text-xl font-bold text-white tracking-tight">
                            Satu Akun, Dua Peran
                        </h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-neutral-400">
                            Presentasikan proyek Anda kepada klien, sekaligus beri masukan pada proyek rekan sesama arsitek. Cukup satu akun, tanpa berganti-ganti login.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-indigo-300 font-medium">
                        <span>Tanpa Pengaturan Rumit</span>
                        <ArrowUpRight class="h-4 w-4" />
                    </div>
                </div>

                <!-- Bento 3 (1 Col): Background Draco Compression -->
                <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25 flex flex-col justify-between">
                    <div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-500/20 border border-blue-500/30 text-blue-400">
                            <Zap class="h-6 w-6" />
                        </div>
                        <h3 class="mt-6 text-xl font-bold text-white tracking-tight">
                            Model 3D Tetap Ringan
                        </h3>
                        <p class="mt-2.5 text-sm leading-relaxed text-neutral-400">
                            Ukuran file model diperkecil otomatis hingga 85% tanpa mengurangi detail desain. Presentasi tetap lancar di laptop maupun smartphone klien, tanpa install aplikasi.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-blue-300 font-medium">
                        <span>Diproses Otomatis</span>
                        <span>~85% Lebih Ringan</span>
                    </div>
                </div>

                <!-- Bento 4 (Large 2 Cols): Spatial Raycasting & Real-Time Sync -->
                <div class="md:col-span-2 group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 sm:p-10 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25">
                    <div class="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full bg-purple-500/10 blur-[80px]"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-500/20 border border-purple-500/30 text-purple-400">
                            <Radio class="h-6 w-6" />
                        </div>
                        <span class="rounded-full border border-purple-500/30 bg-purple-500/10 px-3 py-1 text-xs font-medium text-purple-300">
                            Revisi Real-Time
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Revisi Tepat Sasaran, Diskusi Langsung
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Klien menandai langsung bagian model yang ingin diubah, entah dinding, jendela, atau material, lengkap dengan catatannya. Diskusi dan status revisi terupdate seketika, tanpa bolak-balik email atau WhatsApp.
                        </p>
                    </div>

                    <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center">
                            <div class="font-bold text-white">Pin Komentar</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Tandai Bagian yang Direvisi</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center">
                            <div class="font-bold text-white">Chat Langsung</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Diskusi & Status Revisi</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center col-span-2 sm:col-span-1">
                            <div class="font-bold text-white">Garis Penunjuk</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Catatan Terhubung ke Model</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>