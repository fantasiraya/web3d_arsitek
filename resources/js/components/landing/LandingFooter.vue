<script setup lang="ts">
import { ArrowRight, Mail, Layers, Shield, Globe } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { login, register } from '@/routes';

const currentYear = new Date().getFullYear();

const trustBadges = [
    { icon: Globe,  label: 'Berjalan 100% di Browser' },
    { icon: Shield, label: 'Akses Klien Terenkripsi' },
    { icon: Layers, label: 'Mendukung GLB / glTF' },
];

const links = {
    fitur: [
        { label: 'Revisi Langsung di Model 3D', href: '#experience' },
        { label: 'Kuota Revisi per Tahap', href: '#experience' },
        { label: 'Live Chat dengan Klien', href: '#experience' },
        { label: 'Kompresi Draco Otomatis', href: '#experience' },
        { label: 'Akses via Email Undangan', href: '#features' },
    ],
    kompatibilitas: [
        { label: 'Revit & ArchiCAD', href: '#' },
        { label: 'SketchUp & Rhino 3D', href: '#' },
        { label: 'Blender & 3ds Max', href: '#' },
        { label: 'Format GLB / glTF', href: '#' },
        { label: 'iPad, Tablet & Smartphone', href: '#' },
    ],
    akun: [
        { label: 'Masuk ke Studio Anda',    href: login.url(),    inertia: true  },
        { label: 'Mulai Presentasi Gratis', href: register.url(), inertia: true  },
        { label: 'Paket Harga',             href: '#pricing',     inertia: false },
        { label: 'Kebijakan Privasi',       href: '#',            inertia: false },
        { label: 'Syarat Penggunaan',       href: '#',            inertia: false },
    ],
};

/** Social media items with inline SVG paths (brand icons not in Lucide) */
const socials = [
    {
        label: 'Instagram',
        href: '#',
        svg: `<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>`,
    },
    {
        label: 'LinkedIn',
        href: '#',
        svg: `<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>`,
    },
    {
        label: 'Twitter / X',
        href: '#',
        svg: `<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.259 5.631 5.905-5.631zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>`,
    },
    {
        label: 'YouTube',
        href: '#',
        svg: `<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>`,
    },
];
</script>

<template>
    <footer class="relative border-t border-white/10 bg-[#050608] text-neutral-400 overflow-hidden">
        <!-- Ambient Glow -->
        <div class="pointer-events-none absolute bottom-0 left-1/2 -translate-x-1/2 h-80 w-full max-w-5xl rounded-full bg-gradient-to-r from-indigo-600/6 via-purple-600/4 to-blue-600/6 blur-[140px]"></div>

        <!-- Trust Bar -->
        <div class="relative border-b border-white/[0.06]">
            <div class="mx-auto max-w-6xl px-6 py-4">
                <div class="flex flex-wrap items-center justify-center gap-6 sm:gap-10">
                    <div
                        v-for="badge in trustBadges"
                        :key="badge.label"
                        class="flex items-center gap-2 text-[11px] text-neutral-400"
                    >
                        <component :is="badge.icon" class="h-3.5 w-3.5 text-indigo-400 shrink-0" />
                        <span>{{ badge.label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Footer Body -->
        <div class="relative mx-auto max-w-6xl px-6 pt-14 pb-10">
            <div class="grid grid-cols-2 gap-10 md:grid-cols-5">

                <!-- Brand Column -->
                <div class="col-span-2">
                    <!-- Logo -->
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl overflow-hidden drop-shadow-[0_0_12px_rgba(59,130,246,0.7)]">
                            <img src="/logo.png" alt="Pitcharch" class="w-full h-full object-contain ring-1 ring-white/20 rounded-xl" />
                        </div>
                        <div>
                            <span class="block text-sm font-bold tracking-widest text-white uppercase">Aether 3D</span>
                            <span class="block text-[10px] tracking-wide text-neutral-500 uppercase">Architectural Presentation</span>
                        </div>
                    </div>

                    <!-- Tagline -->
                    <p class="mt-5 max-w-xs text-xs leading-relaxed text-neutral-400">
                        Platform presentasi desain arsitektur berbasis web. Tunjukkan model bangunan langsung di browser, terima masukan klien tepat di titik yang dimaksud, dan lindungi karya Anda lewat akses berbasis undangan.
                    </p>

                    <!-- CTA Compact -->
                    <Link
                        :href="register()"
                        class="group mt-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold text-white transition-all duration-200 hover:border-indigo-500/40 hover:bg-indigo-500/10 hover:text-indigo-300"
                    >
                        <span>Coba Gratis Sekarang</span>
                        <ArrowRight class="h-3 w-3 transition-transform group-hover:translate-x-0.5" />
                    </Link>

                    <!-- Social Icons -->
                    <div class="mt-7 flex items-center gap-3">
                        <a
                            v-for="social in socials"
                            :key="social.label"
                            :href="social.href"
                            :aria-label="social.label"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/[0.03] text-neutral-500 transition-all duration-200 hover:border-white/25 hover:text-white"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <g v-html="social.svg" />
                            </svg>
                        </a>
                        <a
                            href="mailto:hello@aether3d.id"
                            aria-label="Email"
                            class="flex h-8 w-8 items-center justify-center rounded-full border border-white/10 bg-white/[0.03] text-neutral-500 transition-all duration-200 hover:border-indigo-500/40 hover:text-indigo-400"
                        >
                            <Mail class="h-3.5 w-3.5" />
                        </a>
                    </div>
                </div>

                <!-- Fitur -->
                <div>
                    <h4 class="text-[10px] font-bold uppercase tracking-widest text-neutral-300">Fitur</h4>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in links.fitur" :key="link.label">
                            <a
                                :href="link.href"
                                class="text-xs text-neutral-400 transition-colors duration-200 hover:text-white"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kompatibilitas -->
                <div>
                    <h4 class="text-[10px] font-bold uppercase tracking-widest text-neutral-300">Kompatibilitas</h4>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in links.kompatibilitas" :key="link.label">
                            <a
                                :href="link.href"
                                class="text-xs text-neutral-400 transition-colors duration-200 hover:text-white"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Akun -->
                <div>
                    <h4 class="text-[10px] font-bold uppercase tracking-widest text-neutral-300">Akun & Legal</h4>
                    <ul class="mt-4 space-y-2.5">
                        <li v-for="link in links.akun" :key="link.label">
                            <component
                                :is="link.inertia ? Link : 'a'"
                                :href="link.href"
                                class="text-xs text-neutral-400 transition-colors duration-200 hover:text-white"
                            >
                                {{ link.label }}
                            </component>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="mt-14 flex flex-col items-center justify-between gap-4 border-t border-white/[0.07] pt-7 sm:flex-row">
                <p class="text-[11px] text-neutral-500">
                    © {{ currentYear }} AETHER 3D. Seluruh hak cipta dilindungi.
                </p>
                <p class="text-[11px] text-neutral-600">
                    Dibuat untuk arsitek yang ingin presentasi lebih dari sekadar gambar.
                </p>
            </div>
        </div>
    </footer>
</template>
