<script setup lang="ts">
import { ShieldCheck, Users, Zap, Sliders, Cpu, Radio, Sparkles, Lock, ArrowUpRight } from '@lucide/vue';
</script>

<template>
    <section id="features" class="relative py-28 sm:py-36 bg-[#050608] text-white">
        <!-- Ambient Background Glow -->
        <div class="pointer-events-none absolute bottom-1/4 left-1/2 -translate-x-1/2 h-96 w-full max-w-5xl bg-blue-600/5 blur-[160px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <!-- Section Title -->
            <div class="mx-auto max-w-3xl text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1 text-[11px] font-semibold tracking-wider uppercase text-neutral-300">
                    <Cpu class="h-3 w-3 text-indigo-400" />
                    <span>Fondasi Arsitektur Modern</span>
                </div>
                <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-5xl font-sans">
                    Dibuat untuk Kecepatan. <br />
                    <span class="bg-gradient-to-r from-neutral-200 via-neutral-400 to-neutral-500 bg-clip-text text-transparent">
                        Direkayasa untuk Keamanan.
                    </span>
                </h2>
                <p class="mt-4 text-sm sm:text-base text-neutral-400 max-w-xl mx-auto">
                    Kombinasi teknologi backend Domain-Driven Design (DDD) dan frontend reaktif yang memberikan keandalan kelas enterprise.
                </p>
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
                        <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-mono font-medium text-emerald-300">
                            P0 Security Protocol
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Client Invitation by Email
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Hilangkan risiko bocornya desain ke pihak yang tidak bertanggung jawab. Setiap proyek diproteksi verifikasi email undangan. Klien wajib terautentikasi dan diverifikasi oleh <code class="text-emerald-300 font-mono text-xs">ProjectClientAccessMiddleware</code> sebelum model 3D dapat dirender.
                        </p>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-3 text-xs text-neutral-400">
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5 font-mono">Bypass Dilarang Keras</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5 font-mono">Google OAuth One-Tap</span>
                        <span class="rounded-xl border border-white/10 bg-black/40 px-3 py-1.5 font-mono">Revoke Akses Seketika</span>
                    </div>
                </div>

                <!-- Bento 2 (1 Col): Dual-Capacity Model -->
                <div class="group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25 flex flex-col justify-between">
                    <div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/20 border border-indigo-500/30 text-indigo-400">
                            <Users class="h-6 w-6" />
                        </div>
                        <h3 class="mt-6 text-xl font-bold text-white tracking-tight">
                            Dual-Capacity Identity
                        </h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-neutral-400">
                            Satu akun merangkap dua peran: arsitek yang mengunggah proyek sendiri, sekaligus klien reviewer pada proyek rekan arsitek lain tanpa perlu switch akun.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-indigo-300 font-medium">
                        <span>Tanpa Form Role Rumit</span>
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
                            Draco Compression
                        </h3>
                        <p class="mt-2.5 text-xs leading-relaxed text-neutral-400">
                            Pipeline otomatis di background worker mengompresi ukuran geometri hingga 85%. Rendering 60 FPS mulus di laptop klien maupun smartphone.
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-blue-300 font-mono">
                        <span>gltf-pipeline Server Queue</span>
                        <span>~85% Hemat</span>
                    </div>
                </div>

                <!-- Bento 4 (Large 2 Cols): Spatial Raycasting & Real-Time Sync -->
                <div class="md:col-span-2 group relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/[0.06] via-white/[0.02] to-transparent p-8 sm:p-10 shadow-2xl backdrop-blur-2xl transition-all duration-300 hover:border-white/25">
                    <div class="pointer-events-none absolute -left-16 -top-16 h-64 w-64 rounded-full bg-purple-500/10 blur-[80px]"></div>

                    <div class="flex items-center justify-between">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-500/20 border border-purple-500/30 text-purple-400">
                            <Radio class="h-6 w-6" />
                        </div>
                        <span class="rounded-full border border-purple-500/30 bg-purple-500/10 px-3 py-1 text-xs font-mono font-medium text-purple-300">
                            WebSocket & Raycast
                        </span>
                    </div>

                    <div class="mt-8 max-w-lg">
                        <h3 class="text-2xl font-bold text-white tracking-tight">
                            Spatial Precision & Instant Messaging
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-neutral-300">
                            Kalkulasi presisi titik normal dan koordinat $X, Y, Z$ saat pin ditancapkan. Terhubung langsung dengan Laravel Reverb WebSocket untuk sinkronisasi pesan chat dan perubahan status revisi secara real-time.
                        </p>
                    </div>

                    <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center">
                            <div class="font-bold text-white font-mono">THREE.Raycaster</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Deteksi Titik Geometri</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center">
                            <div class="font-bold text-white font-mono">Laravel Reverb</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Broadcasting WebSocket</div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/40 p-3 text-center col-span-2 sm:col-span-1">
                            <div class="font-bold text-white font-mono">SVG Leader Lines</div>
                            <div class="text-[10px] text-neutral-400 mt-0.5">Garis Anotasi Dinamis</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
