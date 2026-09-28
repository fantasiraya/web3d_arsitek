<script setup lang="ts">
import { Box, Heart } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { login, register } from '@/routes';
</script>

<template>
    <footer class="relative border-t border-white/10 bg-[#050608] pt-16 pb-12 text-neutral-400">
        <!-- Subtle Ambient Lighting at Footer -->
        <div class="pointer-events-none absolute bottom-0 left-1/2 -translate-x-1/2 h-64 w-full max-w-4xl bg-indigo-600/5 blur-[120px]"></div>

        <div class="relative mx-auto max-w-6xl px-6">
            <div class="grid grid-cols-2 gap-10 sm:grid-cols-2 md:grid-cols-5">
                <!-- Col 1: Brand & Bio -->
                <div class="col-span-2">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 p-0.5 border border-white/15">
                            <Box class="h-4 w-4 text-white" />
                        </div>
                        <span class="text-sm font-semibold tracking-wider text-white">AETHER 3D</span>
                    </div>
                    <p class="mt-4 max-w-sm text-xs leading-relaxed text-neutral-400">
                        Platform SaaS presentasi arsitektur Web 3D modern untuk arsitek visioner. Kolaborasi spasial presisi, batas revisi transparan, dan akses klien aman berbasis undangan.
                    </p>
                    <div class="mt-6 flex items-center gap-3 text-xs text-neutral-500">
                        <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Semua Sistem Beroperasi Normal</span>
                    </div>
                </div>

                <!-- Col 2: Fitur Utama -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-200">Fitur</h4>
                    <ul class="mt-4 space-y-2.5 text-xs">
                        <li><a href="#experience" class="hover:text-white transition-colors">Spatial Pin Annotation</a></li>
                        <li><a href="#experience" class="hover:text-white transition-colors">Revision Gatekeeper</a></li>
                        <li><a href="#experience" class="hover:text-white transition-colors">In-App Real-time Chat</a></li>
                        <li><a href="#experience" class="hover:text-white transition-colors">Draco 3D Optimization</a></li>
                        <li><a href="#features" class="hover:text-white transition-colors">Client Email Access</a></li>
                    </ul>
                </div>

                <!-- Col 3: Arsitektur & Tech -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-200">Teknologi</h4>
                    <ul class="mt-4 space-y-2.5 text-xs">
                        <li><span class="text-neutral-400">Three.js WebGL Engine</span></li>
                        <li><span class="text-neutral-400">Laravel 13 DDD</span></li>
                        <li><span class="text-neutral-400">Laravel Reverb WebSocket</span></li>
                        <li><span class="text-neutral-400">Vue 3 / Inertia Stack</span></li>
                        <li><span class="text-neutral-400">Cloudflare R2 Object Storage</span></li>
                    </ul>
                </div>

                <!-- Col 4: Akun & Legal -->
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-neutral-200">Akses & Legal</h4>
                    <ul class="mt-4 space-y-2.5 text-xs">
                        <li><Link :href="login()" class="hover:text-white transition-colors">Masuk Akun</Link></li>
                        <li><Link :href="register()" class="hover:text-white transition-colors">Daftar Akun Gratis</Link></li>
                        <li><a href="#pricing" class="hover:text-white transition-colors">Paket Langganan</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Kebijakan Privasi</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Syarat Penggunaan</a></li>
                    </ul>
                </div>
            </div>

            <!-- Divider -->
            <div class="mt-14 border-t border-white/10 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-neutral-400">
                <p>© 2026 AETHER 3D Platform. Seluruh Hak Cipta Dilindungi.</p>
                <div class="flex items-center gap-6">
                    <span class="flex items-center gap-1">
                        Didesain dengan presisi dan standar estetika modern
                    </span>
                </div>
            </div>
        </div>
    </footer>
</template>
