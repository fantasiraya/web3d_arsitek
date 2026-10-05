<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppHeader from '@/components/app/AppHeader.vue';
import PricingSection from '@/components/landing/PricingSection.vue';
import { useSidebar } from '@/composables/useSidebar';

interface PlanItem {
    id: string;
    slug: string;
    display_name: string;
    tagline: string;
    badge_text: string;
    cta_text: string;
    cta_url: string;
    is_featured: boolean;
    price_monthly: string;
    price_annual: string;
    period_label: string;
    benefits: string[];
}

defineProps<{ plans?: PlanItem[] }>();

const { isSidebarOpen, isMobile } = useSidebar();
</script>

<template>
    <Head title="Pilih Plan · AETHER 3D" />

    <div class="flex h-screen bg-slate-50 dark:bg-[#09090c] overflow-hidden">
        <AppSidebar />

        <!-- Main content area -->
        <div
            class="flex flex-col flex-1 min-w-0 transition-all duration-300"
            :class="isSidebarOpen && !isMobile ? 'ml-64' : (!isMobile ? 'ml-16' : 'ml-0')"
        >
            <AppHeader title="Paket & Harga" />

            <main class="flex-1 overflow-y-auto">
                <!-- Breadcrumb strip -->
                <div class="border-b border-slate-200 dark:border-white/5 bg-white dark:bg-[#0d0e13]/80 px-6 py-3">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-[#6b7280]">
                        <span>Dashboard</span>
                        <span>/</span>
                        <span class="text-slate-700 dark:text-neutral-200 font-medium">Paket & Harga</span>
                    </div>
                </div>

                <!-- PricingSection reused directly -->
                <PricingSection :plans="plans" />
            </main>
        </div>
    </div>
</template>
