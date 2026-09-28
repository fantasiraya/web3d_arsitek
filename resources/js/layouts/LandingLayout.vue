<script setup lang="ts">
import LandingNavbar from '@/components/landing/LandingNavbar.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
</script>

<template>
    <div class="min-h-screen bg-[#050608] text-neutral-100 antialiased selection:bg-indigo-500 selection:text-white flex flex-col justify-between">
        <!-- Persistent Ambient Glow Layers -->
        <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
            <div class="absolute -top-[30%] left-1/2 -translate-x-1/2 h-[800px] w-full max-w-7xl bg-indigo-600/10 blur-[160px]"></div>
            <div class="absolute top-[40%] -left-64 h-[600px] w-[600px] rounded-full bg-blue-600/5 blur-[180px]"></div>
            <div class="absolute bottom-10 right-0 h-[500px] w-[500px] rounded-full bg-purple-600/5 blur-[160px]"></div>
        </div>

        <!-- Sticky Floating Frosted Navbar -->
        <LandingNavbar />

        <!-- Main Dynamic Content Slot -->
        <main class="relative z-10 flex-grow">
            <slot />
        </main>

        <!-- Minimalist Apple Footer -->
        <LandingFooter class="relative z-10" />
    </div>
</template>
