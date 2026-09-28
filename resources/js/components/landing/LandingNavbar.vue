<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Box, ChevronRight, Menu, X, Sparkles } from '@lucide/vue';
import { dashboard, login, register } from '@/routes';

const page = usePage();
const mobileMenuOpen = ref(false);

const navLinks = [
    { name: 'Showcase', href: '/showcase', isPage: true },
    { name: 'Pengalaman 3D', href: '#experience' },
    { name: 'Arsitektur', href: '#features' },
    { name: 'Harga', href: '#pricing' },
];

const handleNavClick = (link: { name: string; href: string; isPage?: boolean }) => {
    mobileMenuOpen.value = false;
    if (link.isPage) {
        router.visit(link.href);
        return;
    }
    const element = document.querySelector(link.href);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    } else {
        router.visit('/' + link.href);
    }
};
</script>

<template>
    <header class="fixed top-0 left-0 right-0 z-50 w-full border-b border-white/10 bg-black/70 backdrop-blur-2xl transition-all duration-300">
        <nav
            class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
        >
            <!-- Brand Logo -->
            <Link href="/" class="group flex items-center gap-2.5">
                <div
                    class="relative flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/30 via-slate-800 to-black p-0.5 shadow-inner shadow-indigo-500/20"
                >
                    <Box class="h-4 w-4 text-white transition-transform duration-300 group-hover:scale-110" />
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-sm font-semibold tracking-wider text-white">AETHER</span>
                    <span class="rounded bg-white/10 px-1 py-0.5 text-[10px] font-medium tracking-widest text-neutral-300">
                        3D
                    </span>
                </div>
            </Link>

            <!-- Desktop Nav Links -->
            <div class="hidden items-center gap-8 md:flex">
                <template v-for="link in navLinks" :key="link.name">
                    <Link
                        v-if="link.isPage"
                        :href="link.href"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-white transition-colors duration-200 hover:text-indigo-400 group"
                    >
                        <span>{{ link.name }}</span>
                        <span class="rounded-full bg-indigo-500/20 border border-indigo-500/40 px-1.5 py-0.5 text-[9px] font-mono text-indigo-300 animate-pulse">
                            LIVE 3D
                        </span>
                    </Link>
                    <a
                        v-else
                        :href="link.href"
                        @click.prevent="handleNavClick(link)"
                        class="text-xs font-medium text-neutral-400 transition-colors duration-200 hover:text-white"
                    >
                        {{ link.name }}
                    </a>
                </template>
            </div>

            <!-- Right Actions -->
            <div class="hidden items-center gap-3 sm:flex">
                <template v-if="page.props.auth?.user">
                    <Link
                        :href="dashboard()"
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-4 py-1.5 text-xs font-medium text-white shadow-sm transition-all duration-200 hover:bg-white/20 hover:border-white/30"
                    >
                        <span>Dashboard</span>
                        <ChevronRight class="h-3.5 w-3.5" />
                    </Link>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-xs font-medium text-neutral-300 transition-colors duration-200 hover:text-white px-3 py-1.5"
                    >
                        Masuk
                    </Link>
                    <Link
                        :href="register()"
                        class="group relative inline-flex items-center gap-1.5 overflow-hidden rounded-full bg-gradient-to-r from-neutral-100 to-neutral-300 px-4 py-1.5 text-xs font-semibold text-black shadow-[0_0_20px_rgba(255,255,255,0.2)] transition-all duration-300 hover:scale-[1.02] hover:shadow-[0_0_25px_rgba(255,255,255,0.35)]"
                    >
                        <Sparkles class="h-3 w-3 text-indigo-600 transition-transform group-hover:rotate-12" />
                        <span>Mulai Presentasi</span>
                    </Link>
                </template>
            </div>

            <!-- Mobile Menu Toggle Button -->
            <button
                type="button"
                @click="mobileMenuOpen = !mobileMenuOpen"
                class="flex h-8 w-8 items-center justify-center rounded-full text-neutral-300 hover:text-white md:hidden"
                aria-label="Toggle menu"
            >
                <X v-if="mobileMenuOpen" class="h-5 w-5" />
                <Menu v-else class="h-5 w-5" />
            </button>
        </nav>

        <!-- Mobile Drawer -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="mobileMenuOpen"
                class="absolute top-16 left-0 right-0 z-50 flex flex-col gap-4 border-b border-white/10 bg-black/95 px-6 py-6 shadow-2xl backdrop-blur-3xl md:hidden"
            >
                <div class="flex flex-col gap-3">
                    <template v-for="link in navLinks" :key="link.name">
                        <Link
                            v-if="link.isPage"
                            :href="link.href"
                            @click="mobileMenuOpen = false"
                            class="flex items-center justify-between text-sm font-semibold text-white hover:text-indigo-400 py-1"
                        >
                            <span>{{ link.name }}</span>
                            <span class="rounded-full bg-indigo-500/20 border border-indigo-500/40 px-2 py-0.5 text-[9px] font-mono text-indigo-300">
                                LIVE 3D
                            </span>
                        </Link>
                        <a
                            v-else
                            :href="link.href"
                            @click.prevent="handleNavClick(link)"
                            class="text-sm font-medium text-neutral-300 hover:text-white py-1"
                        >
                            {{ link.name }}
                        </a>
                    </template>
                </div>
                <div class="h-px bg-white/10 my-1"></div>
                <div class="flex flex-col gap-2.5">
                    <template v-if="page.props.auth?.user">
                        <Link
                            :href="dashboard()"
                            class="flex items-center justify-center gap-1.5 rounded-full bg-white/10 py-2.5 text-xs font-semibold text-white"
                        >
                            Ke Dashboard
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="flex items-center justify-center rounded-full border border-white/10 py-2 text-xs font-medium text-neutral-200 hover:bg-white/5"
                        >
                            Masuk
                        </Link>
                        <Link
                            :href="register()"
                            class="flex items-center justify-center rounded-full bg-white py-2.5 text-xs font-semibold text-black"
                        >
                            Mulai Presentasi
                        </Link>
                    </template>
                </div>
            </div>
        </transition>
    </header>
</template>
