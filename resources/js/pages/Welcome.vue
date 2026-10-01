<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LandingLayout from '@/layouts/LandingLayout.vue';
import HeroSection from '@/components/landing/HeroSection.vue';
import ScrollyExperienceSection from '@/components/landing/ScrollyExperienceSection.vue';
import BentoGridSection from '@/components/landing/BentoGridSection.vue';
import InteractiveShowcaseSection from '@/components/landing/InteractiveShowcaseSection.vue';
import PricingSection from '@/components/landing/PricingSection.vue';
import FaqSection from '@/components/landing/FaqSection.vue';
import CtaSection from '@/components/landing/CtaSection.vue';
import SkeletonHero from '@/components/skeletons/SkeletonHero.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { usePageLoading } from '@/composables/usePageLoading';

const { isLoading } = usePageLoading(80);
</script>

<template>
    <Head title="AETHER 3D — Apple-Style Architectural Showcase & Presentation Platform">
        <meta
            name="description"
            content="Platform SaaS Web 3D untuk arsitek mempresentasikan desain ke klien secara fotorealistik dengan anotasi pin spasial presisi, batas revisi transparan, dan akses aman berbasis email."
        />
        <meta property="og:title" content="AETHER 3D — Apple-Style Architectural Showcase" />
        <meta
            property="og:description"
            content="Presentasikan model 3D arsitektur fotorealistik di browser, tanpa software berat, terproteksi kuota revisi dan keamanan akses email."
        />
        <meta property="og:image" content="/images/aether_villa_hero.jpg" />
    </Head>

    <LandingLayout>
        <!-- Skeleton loading state saat navigasi ke halaman ini -->
        <template v-if="isLoading">
            <SkeletonHero />
            <!-- Skeleton bento grid -->
            <div class="max-w-7xl mx-auto px-4 py-16 space-y-4">
                <div class="flex flex-col items-center gap-3 mb-8">
                    <Skeleton class="h-5 w-32 rounded-full" />
                    <Skeleton class="h-10 w-80" />
                    <Skeleton class="h-5 w-64" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Skeleton v-for="i in 6" :key="i" class="h-48 rounded-2xl" />
                </div>
            </div>
            <!-- Skeleton pricing -->
            <div class="max-w-7xl mx-auto px-4 py-16 space-y-6">
                <div class="flex flex-col items-center gap-3 mb-8">
                    <Skeleton class="h-5 w-24 rounded-full" />
                    <Skeleton class="h-10 w-64" />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <Skeleton v-for="i in 3" :key="i" class="h-80 rounded-2xl" />
                </div>
            </div>
        </template>

        <!-- Konten asli -->
        <template v-else>
            <HeroSection />
            <ScrollyExperienceSection />
            <BentoGridSection />
            <InteractiveShowcaseSection />
            <PricingSection />
            <FaqSection />
            <CtaSection />
        </template>
    </LandingLayout>
</template>
