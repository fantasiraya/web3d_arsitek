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
        <!-- Act 1: Monumental Hero Section with Villa Showcase Window -->
        <HeroSection />

        <!-- Act 2: Scrollytelling 4-Step Product Journey (Pinned Sticky 100vh Viewport) -->
        <ScrollyExperienceSection />

        <!-- Act 3: Apple Pro Bento Grid (Security, Dual-Capacity, Draco, WebSocket) -->
        <BentoGridSection />

        <!-- Act 5: Interactive 3D Spatial Annotation Simulator -->
        <InteractiveShowcaseSection />

        <!-- Act 6: Tiered Apple Store-style Pricing Comparison -->
        <PricingSection />

        <!-- Act 7: Frequently Asked Questions (Apple-style Accordion) -->
        <FaqSection />

        <!-- Act 8: Monumental Closing CTA -->
        <CtaSection />
    </LandingLayout>
</template>
