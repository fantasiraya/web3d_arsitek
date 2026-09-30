<script setup lang="ts">
/**
 * UnifiedLayout
 *
 * Layout adaptif untuk halaman yang bisa diakses oleh admin maupun user biasa.
 * - Admin (can_access_admin = true)  → shell gaya AdminLayout
 * - User biasa                       → shell gaya Dashboard (AppSidebar + AppHeader)
 *
 * Settings/Layout.vue tetap menjadi inner layout (di-slot di dalam komponen ini).
 */
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Auth } from '@/types/auth';
import { useSidebar } from '@/composables/useSidebar';

// Admin shell
import AdminLayout from '@/layouts/AdminLayout.vue';

// User / Dashboard shell
import AppSidebar from '@/components/app/AppSidebar.vue';
import AppHeader from '@/components/app/AppHeader.vue';

const page = usePage();
const auth  = computed(() => page.props.auth as Auth);
const isAdmin = computed(() => auth.value.can_access_admin === true);

// Reactive sidebar state — sama persis dengan Dashboard.vue
const { isSidebarOpen, isMobile } = useSidebar();
</script>

<template>
    <!-- ═══════════════════════════════════════
         ADMIN SHELL
         Cukup bungkus AdminLayout, slot akan diteruskan
    ════════════════════════════════════════ -->
    <AdminLayout v-if="isAdmin">
        <slot />
    </AdminLayout>

    <!-- ═══════════════════════════════════════
         USER SHELL  (identik dengan Dashboard.vue wrapper)
    ════════════════════════════════════════ -->
    <div
        v-else
        class="min-h-screen bg-[#F8F9FA] dark:bg-[#0b0c10] text-slate-900 dark:text-[#f3f4f6] font-['Geist',sans-serif] antialiased relative overflow-x-hidden flex transition-colors duration-300"
    >
        <!-- Sidebar — komponen yang sama dengan Dashboard -->
        <AppSidebar />

        <!-- Main area — margin kiri reaktif sama dengan Dashboard -->
        <div
            :class="[
                'flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out',
                isSidebarOpen && !isMobile ? 'ml-64' : 'ml-0 lg:ml-16',
            ]"
        >
            <!-- Header — komponen yang sama dengan Dashboard -->
            <AppHeader />

            <!-- Konten halaman settings -->
            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
