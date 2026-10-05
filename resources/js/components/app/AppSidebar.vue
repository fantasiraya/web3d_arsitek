<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { 
    LayoutDashboard, 
    Box, 
    Users, 
    Settings, 
    BookOpen, 
    Server,
    ChevronRight,
    MoreVertical,
    Receipt,
    User,
    LogOut
} from '@lucide/vue'
import { useSidebar } from '@/composables/useSidebar'

const page = usePage()
const { isSidebarOpen, isMobile, setMobile, closeSidebar } = useSidebar()

// Get user from page props
const user = computed(() => page.props.auth?.user)

// Get stats for badges
const stats = computed(() => page.props.stats)

// Kuota proyek: jumlah proyek yang dimiliki vs batas plan
const projectUsed = computed(() => stats.value?.owned_count ?? 0);
const projectMax  = computed(() => stats.value?.max_projects ?? 1);
const projectIsUnlimited = computed(() => projectMax.value >= 999);

const projectPercent = computed(() =>
    projectIsUnlimited.value ? 0 : Math.min(100, (projectUsed.value / projectMax.value) * 100)
);

// Label tier berdasarkan plan_name dari backend (lebih akurat dari max_projects)
const planLabel = computed<'Free' | 'Pro' | 'Enterprise'>(() => {
    const name = (stats.value?.plan_name ?? '').toLowerCase();
    if (name.includes('enterprise')) return 'Enterprise';
    if (name.includes('pro'))        return 'Pro';
    return 'Free';
});

// Ukuran file maks per proyek berdasarkan plan (dari PRD: Free=50MB, Pro=100MB)
const maxFileSizeMb = computed(() => {
    if (projectIsUnlimited.value) return null;  // enterprise: sesuai config admin
    return projectMax.value >= 20 ? 100 : 50;
});

// User dropdown state
const showUserDropdown = ref(false)

// Check if current route matches
const isActiveRoute = (routeName: string) => {
    return page.url === `/${routeName}` || page.url.startsWith(`/${routeName}/`)
}

// Logout function
const logout = () => {
    router.post('/logout')
}

// Check window size
const checkMobile = () => {
    setMobile(window.innerWidth < 1024)
}

onMounted(() => {
    checkMobile()
    window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile)
})
</script>

<template>
    <!-- Overlay for mobile -->
    <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div 
            v-if="isMobile && isSidebarOpen"
            class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40"
            @click="closeSidebar"
        ></div>
    </Transition>

    <!-- PERSISTENT LEFT SIDEBAR -->
    <aside 
        :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col bg-white dark:bg-[#0d0e13]/90 backdrop-blur-2xl border-r border-slate-200 dark:border-white/5 text-slate-800 dark:text-on-surface select-none transition-all duration-300 ease-in-out',
            isSidebarOpen ? 'w-64' : (isMobile ? '-translate-x-full w-64' : 'w-16'),
        ]"
    >
        <!-- Brand Logo & Studio Tier -->
        <div class="h-16 px-5 flex items-center justify-between border-b border-slate-200 dark:border-white/5">
            <Link href="/dashboard" class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-lg bg-slate-900 dark:bg-gradient-to-br dark:from-white dark:to-gray-200 flex items-center justify-center shadow-sm shrink-0">
                    <Box class="h-5 w-5 text-white dark:text-[#0d0e13]" :stroke-width="2.5" />
                </div>
                <Transition
                    enter-active-class="transition-opacity duration-200 delay-100"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div v-if="isSidebarOpen" class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold tracking-tight text-slate-900 dark:text-white text-[15px]">AETHER</span>
                            <span class="text-[12px] font-mono font-semibold text-sky-600 dark:text-[#38bdf8]">3D</span>
                        </div>
                        <span class="text-[9px] font-mono uppercase tracking-widest text-slate-400 dark:text-[#6b7280] block -mt-0.5">Spatial CAD</span>
                    </div>
                </Transition>
            </Link>
            <Transition
                enter-active-class="transition-opacity duration-200 delay-100"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <span v-if="isSidebarOpen && planLabel === 'Free'">
                    <Link
                        href="/plans"
                        class="px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold transition-colors bg-sky-50 dark:bg-[#38bdf8]/10 text-sky-700 dark:text-[#38bdf8] border border-sky-200 dark:border-[#38bdf8]/20 hover:bg-sky-100 dark:hover:bg-[#38bdf8]/20"
                    >
                        Upgrade
                    </Link>
                </span>
                <span
                    v-else-if="isSidebarOpen"
                    class="px-1.5 py-0.5 rounded text-[10px] font-mono font-semibold shrink-0"
                    :class="planLabel === 'Enterprise'
                        ? 'bg-violet-50 dark:bg-violet-400/15 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-400/25'
                        : 'bg-amber-50 dark:bg-amber-400/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-400/25'"
                >
                    {{ planLabel.toUpperCase() }}
                </span>
            </Transition>
        </div>

        <!-- Navigation Menu List -->
        <div class="flex-1 overflow-y-auto custom-scroll px-3 py-4 space-y-6">
            <!-- Main Group -->
            <div class="space-y-1">
                <div 
                    v-if="isSidebarOpen" 
                    class="px-3 pb-1.5 text-[10px] font-mono uppercase tracking-wider text-slate-400 dark:text-[#6b7280]/80 font-semibold transition-opacity duration-200"
                >
                    Menu Utama
                </div>
                
                <!-- Dashboard -->
                <Link 
                    href="/dashboard"
                    :class="[
                        'group relative flex items-center gap-3 px-3 py-2 rounded-xl font-medium transition-all',
                        isActiveRoute('dashboard')
                            ? 'bg-slate-100 dark:bg-white/[0.06] text-slate-900 dark:text-white border border-slate-200 dark:border-white/10 shadow-sm'
                            : 'text-slate-500 dark:text-[#9ca3af] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/[0.04]',
                        !isSidebarOpen && 'justify-center'
                    ]"
                    :title="!isSidebarOpen ? 'Dashboard' : ''"
                >
                    <div v-if="isActiveRoute('dashboard')" class="absolute left-0 top-2 bottom-2 w-1 bg-sky-500 dark:bg-[#38bdf8] rounded-r-full"></div>
                    <LayoutDashboard class="h-[19px] w-[19px] shrink-0" :class="isActiveRoute('dashboard') ? 'text-sky-600 dark:text-[#38bdf8]' : 'text-slate-400 dark:text-[#6b7280] group-hover:text-slate-700 dark:group-hover:text-white'" :stroke-width="2" />
                    <Transition
                        enter-active-class="transition-opacity duration-200 delay-75"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition-opacity duration-150"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <span v-if="isSidebarOpen" class="text-[13px] tracking-tight">Dashboard</span>
                    </Transition>
                </Link>

                <!-- Proyek 3D -->
                <Link 
                    href="/projects"
                    :class="[
                        'group relative flex items-center justify-between px-3 py-2 rounded-xl transition-all',
                        isActiveRoute('projects')
                            ? 'bg-slate-100 dark:bg-white/[0.06] text-slate-900 dark:text-white border border-slate-200 dark:border-white/10 shadow-sm'
                            : 'text-slate-500 dark:text-[#9ca3af] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/[0.04]',
                        !isSidebarOpen && 'justify-center'
                    ]"
                    :title="!isSidebarOpen ? 'Proyek 3D' : ''"
                >
                    <div v-if="isActiveRoute('projects')" class="absolute left-0 top-2 bottom-2 w-1 bg-sky-500 dark:bg-[#38bdf8] rounded-r-full"></div>
                    <div class="flex items-center gap-3 min-w-0">
                        <Box
                            class="h-[19px] w-[19px] shrink-0 transition-colors"
                            :class="isActiveRoute('projects') ? 'text-sky-600 dark:text-[#38bdf8]' : 'text-slate-400 dark:text-[#6b7280] group-hover:text-slate-700 dark:group-hover:text-white'"
                            :stroke-width="2"
                        />
                        <Transition
                            enter-active-class="transition-opacity duration-200 delay-75"
                            enter-from-class="opacity-0"
                            enter-to-class="opacity-100"
                            leave-active-class="transition-opacity duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <span v-if="isSidebarOpen" class="text-[13px] tracking-tight font-medium">Proyek 3D</span>
                        </Transition>
                    </div>
                    <Transition
                        enter-active-class="transition-opacity duration-200 delay-100"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition-opacity duration-150"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <span v-if="isSidebarOpen && stats?.owned_count" class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-slate-100 dark:bg-white/[0.06] text-slate-600 dark:text-white border border-slate-200 dark:border-white/5 shrink-0">
                            {{ stats.owned_count }}
                        </span>
                    </Transition>
                </Link>

                <!-- Tim & Klien -->
                <Link 
                    href="/teams"
                    :class="[
                        'group relative flex items-center justify-between px-3 py-2 rounded-xl transition-all',
                        isActiveRoute('teams')
                            ? 'bg-slate-100 dark:bg-white/[0.06] text-slate-900 dark:text-white border border-slate-200 dark:border-white/10 shadow-sm'
                            : 'text-slate-500 dark:text-[#9ca3af] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/[0.04]',
                        !isSidebarOpen && 'justify-center'
                    ]"
                    :title="!isSidebarOpen ? 'Tim & Klien' : ''"
                >
                    <div v-if="isActiveRoute('teams')" class="absolute left-0 top-2 bottom-2 w-1 bg-sky-500 dark:bg-[#38bdf8] rounded-r-full"></div>
                    <div class="flex items-center gap-3 min-w-0">
                        <Users
                            class="h-[19px] w-[19px] shrink-0 transition-colors"
                            :class="isActiveRoute('teams') ? 'text-sky-600 dark:text-[#38bdf8]' : 'text-slate-400 dark:text-[#6b7280] group-hover:text-slate-700 dark:group-hover:text-white'"
                            :stroke-width="2"
                        />
                        <Transition
                            enter-active-class="transition-opacity duration-200 delay-75"
                            enter-from-class="opacity-0"
                            enter-to-class="opacity-100"
                            leave-active-class="transition-opacity duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <span v-if="isSidebarOpen" class="text-[13px] tracking-tight font-medium">Tim & Klien</span>
                        </Transition>
                    </div>
                </Link>

                <!-- Riwayat Pembelian -->
                <Link
                    href="/billing"
                    :class="[
                        'group relative flex items-center justify-between px-3 py-2 rounded-xl transition-all',
                        isActiveRoute('billing')
                            ? 'bg-slate-100 dark:bg-white/[0.06] text-slate-900 dark:text-white border border-slate-200 dark:border-white/10 shadow-sm'
                            : 'text-slate-500 dark:text-[#9ca3af] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-white/[0.04]',
                        !isSidebarOpen && 'justify-center'
                    ]"
                    :title="!isSidebarOpen ? 'Riwayat Pembelian' : ''"
                >
                    <div v-if="isActiveRoute('billing')" class="absolute left-0 top-2 bottom-2 w-1 bg-sky-500 dark:bg-[#38bdf8] rounded-r-full"></div>
                    <div class="flex items-center gap-3 min-w-0">
                        <Receipt
                            class="h-[19px] w-[19px] shrink-0 transition-colors"
                            :class="isActiveRoute('billing') ? 'text-sky-600 dark:text-[#38bdf8]' : 'text-slate-400 dark:text-[#6b7280] group-hover:text-slate-700 dark:group-hover:text-white'"
                            :stroke-width="2"
                        />
                        <Transition
                            enter-active-class="transition-opacity duration-200 delay-75"
                            enter-from-class="opacity-0"
                            enter-to-class="opacity-100"
                            leave-active-class="transition-opacity duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <span v-if="isSidebarOpen" class="text-[13px] tracking-tight font-medium">Riwayat Pembelian</span>
                        </Transition>
                    </div>
                </Link>
            </div>

            <!-- Support Group -->
            <div v-if="isSidebarOpen" class="space-y-1">
                <div class="px-3 pb-1.5 text-[10px] font-mono uppercase tracking-wider text-slate-400 dark:text-[#6b7280]/80 font-semibold">Support</div>
                
                <a href="#" class="group flex items-center justify-between px-3 py-1.5 rounded-lg text-xs text-slate-400 dark:text-[#6b7280] hover:text-slate-700 dark:hover:text-[#f3f4f6] transition-colors">
                    <span class="flex items-center gap-2">
                        <BookOpen class="h-[16px] w-[16px] shrink-0" :stroke-width="2" />
                        <span>Dokumentasi API</span>
                    </span>
                    <ChevronRight class="h-[14px] w-[14px] shrink-0" :stroke-width="2" />
                </a>

                <a href="#" class="group flex items-center justify-between px-3 py-1.5 rounded-lg text-xs text-slate-400 dark:text-[#6b7280] hover:text-slate-700 dark:hover:text-[#f3f4f6] transition-colors">
                    <span class="flex items-center gap-2">
                        <Server class="h-[16px] w-[16px] text-emerald-500 dark:text-emerald-400 shrink-0" :stroke-width="2" />
                        <span>Status Engine</span>
                    </span>
                    <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400">Operational</span>
                </a>
            </div>
        </div>

        <!-- Bottom Section: Storage & User Pill -->
        <div class="p-3 border-t border-slate-200 dark:border-white/5 space-y-3 bg-slate-50 dark:bg-[#0a0b0e]/70">
            <!-- Storage & Quota Widget -->
            <Transition
                enter-active-class="transition-opacity duration-200 delay-100"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="isSidebarOpen" class="p-3 rounded-xl bg-white dark:bg-[#13141a]/70 border border-slate-200 dark:border-white/5 space-y-2">
                    <!-- Header: jumlah proyek vs kuota -->
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-[#9ca3af] font-medium">Kuota Proyek</span>
                        <span class="font-mono text-[11px] font-semibold"
                            :class="projectPercent >= 100
                                ? 'text-rose-500 dark:text-rose-400'
                                : projectPercent >= 75
                                    ? 'text-amber-500 dark:text-amber-400'
                                    : 'text-sky-600 dark:text-[#38bdf8]'"   
                        >
                            {{ projectIsUnlimited ? `${projectUsed} Proyek` : `${projectUsed} / ${projectMax}` }}
                        </span>
                    </div>

                    <!-- Progress bar (tersembunyi jika unlimited) -->
                    <div v-if="!projectIsUnlimited" class="h-1.5 w-full bg-slate-100 dark:bg-white/5 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :class="projectPercent >= 100
                                ? 'bg-gradient-to-r from-rose-500 to-rose-400'
                                : projectPercent >= 75
                                    ? 'bg-gradient-to-r from-amber-500 to-amber-400'
                                    : 'bg-gradient-to-r from-sky-500 dark:from-[#38bdf8] to-sky-300 dark:to-[#8ed5ff]'"
                            :style="{ width: `${projectPercent}%` }"
                        />
                    </div>

                    <!-- Info row bawah -->
                    <div class="flex items-center justify-between text-[10px] font-mono text-slate-400 dark:text-[#6b7280]">
                        <!-- Kiri: batas file per proyek -->
                        <span>
                            <template v-if="maxFileSizeMb">File maks {{ maxFileSizeMb }} MB/proyek</template>
                            <template v-else>File maks: sesuai konfigurasi</template>
                        </span>
                        <!-- Kanan: plan badge atau tombol upgrade -->
                        <template v-if="planLabel === 'Free'">
                            <Link href="/billing" class="text-sky-600 dark:text-[#38bdf8] hover:underline font-semibold">
                                Upgrade
                            </Link>
                        </template>
                        <template v-else>
                            <span
                                class="px-1.5 py-0.5 rounded font-semibold"
                                :class="planLabel === 'Enterprise'
                                    ? 'bg-violet-50 dark:bg-violet-400/15 text-violet-600 dark:text-violet-300 border border-violet-200 dark:border-violet-400/25'
                                    : 'bg-amber-50 dark:bg-amber-400/15 text-amber-600 dark:text-amber-300 border border-amber-200 dark:border-amber-400/25'"
                            >
                                {{ planLabel }}
                            </span>
                        </template>
                    </div>

                    <!-- Peringatan jika kuota proyek habis -->
                    <div
                        v-if="!projectIsUnlimited && projectPercent >= 100"
                        class="flex items-center gap-1.5 rounded-lg bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 px-2 py-1.5 text-[10px] text-rose-600 dark:text-rose-400"
                    >
                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Kuota proyek penuh. Upgrade untuk melanjutkan.</span>
                    </div>
                </div>
            </Transition>

            <!-- User Profile Pill with Dropdown -->
            <div class="relative">
                <div 
                    @click="showUserDropdown = !showUserDropdown"
                    :class="[
                        'flex items-center p-2.5 rounded-xl bg-white dark:bg-[#13141a]/50 hover:bg-slate-100 dark:hover:bg-white/[0.04] border border-slate-200 dark:border-white/5 transition-colors cursor-pointer group',
                        isSidebarOpen ? 'justify-between' : 'justify-center'
                    ]"
                >
                    <div class="flex items-center gap-2.5 overflow-hidden min-w-0">
                        <div class="relative shrink-0">
                            <div class="w-7 h-7 rounded-full bg-slate-200 dark:bg-gradient-to-tr dark:from-[#2e303d] dark:to-[#323442] border border-slate-300 dark:border-white/10 flex items-center justify-center text-slate-700 dark:text-white text-[10px] font-semibold">
                                {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
                            </div>
                            <span class="absolute -bottom-0.5 -right-0.5 w-1.5 h-1.5 bg-emerald-500 border border-white dark:border-[#0b0c10] rounded-full"></span>
                        </div>
                        <Transition
                            enter-active-class="transition-opacity duration-200 delay-75"
                            enter-from-class="opacity-0"
                            enter-to-class="opacity-100"
                            leave-active-class="transition-opacity duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="isSidebarOpen" class="truncate min-w-0 flex-1">
                                <div class="text-xs font-semibold text-slate-800 dark:text-white truncate">{{ user?.name || 'User' }}</div>
                                <div class="flex items-center gap-1">
                                    <!-- Pro / Enterprise: badge -->
                                    <span
                                        class="text-[9px] font-mono px-1.5 py-0.5 rounded"
                                        :class="planLabel === 'Enterprise'
                                            ? 'bg-violet-50 dark:bg-violet-400/15 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-400/25'
                                            : 'bg-amber-50 dark:bg-amber-400/20 text-amber-600 dark:text-amber-300 border border-amber-200 dark:border-amber-400/30'"
                                    >
                                        {{ planLabel.toUpperCase() }} TIER
                                    </span>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <Transition
                        enter-active-class="transition-opacity duration-200 delay-100"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition-opacity duration-150"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div v-if="isSidebarOpen" class="text-slate-400 dark:text-[#6b7280] group-hover:text-slate-700 dark:group-hover:text-white transition-colors shrink-0">
                            <MoreVertical class="h-4 w-4" :stroke-width="2" />
                        </div>
                    </Transition>
                </div>

                <!-- User Dropdown Menu -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                >
                    <div 
                        v-if="showUserDropdown"
                        @click.stop
                        :class="[
                            'absolute bottom-full mb-2 bg-white dark:bg-[#13141a]/95 backdrop-blur-2xl border border-slate-200 dark:border-white/10 rounded-2xl shadow-xl dark:shadow-2xl shadow-slate-200/80 dark:shadow-black/50 overflow-hidden z-[60]',
                            isSidebarOpen ? 'left-0 right-0' : 'left-0 w-64'
                        ]"
                    >
                        <!-- User Info Header -->
                        <div class="p-4 border-b border-slate-100 dark:border-white/5 bg-gradient-to-br from-sky-50 dark:from-[#38bdf8]/10 to-transparent">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-gradient-to-tr dark:from-[#2e303d] dark:to-[#323442] border border-slate-200 dark:border-white/20 flex items-center justify-center text-slate-700 dark:text-white text-lg font-bold shadow-sm">
                                        {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
                                    </div>
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-400 border-2 border-white dark:border-[#13141a] rounded-full"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ user?.name || 'User' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-[#9ca3af] truncate">{{ user?.email || 'user@example.com' }}</div>
                                    <div class="mt-1">
                                        <span 
                                            :class="[
                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-medium',
                                                user?.is_pro 
                                                    ? 'bg-amber-50 dark:bg-amber-400/20 text-amber-600 dark:text-amber-300 border border-amber-200 dark:border-amber-400/30' 
                                                    : 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-[#9ca3af] border border-slate-200 dark:border-white/10'
                                            ]"
                                        >
                                            {{ user?.is_pro ? '⭐ PRO TIER' : 'FREE TIER' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Menu Items -->
                        <div class="p-2">
                            <Link 
                                href="/settings/profile"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-700 dark:text-[#f3f4f6] hover:bg-slate-50 dark:hover:bg-white/5 transition-all text-sm group"
                            >
                                <User class="h-4 w-4 text-slate-400 dark:text-[#6b7280] group-hover:text-sky-600 dark:group-hover:text-[#38bdf8] transition-colors" :stroke-width="2" />
                                <span>Profil Saya</span>
                            </Link>

                            <Link 
                                href="/settings/appearance"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-700 dark:text-[#f3f4f6] hover:bg-slate-50 dark:hover:bg-white/5 transition-all text-sm group"
                            >
                                <svg class="h-4 w-4 text-slate-400 dark:text-[#6b7280] group-hover:text-sky-600 dark:group-hover:text-[#38bdf8] transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                                <span>Tampilan</span>
                            </Link>

                            <Link 
                                href="/settings/security"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-700 dark:text-[#f3f4f6] hover:bg-slate-50 dark:hover:bg-white/5 transition-all text-sm group"
                            >
                                <svg class="h-4 w-4 text-slate-400 dark:text-[#6b7280] group-hover:text-sky-600 dark:group-hover:text-[#38bdf8] transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Keamanan</span>
                            </Link>

                            <div class="my-2 border-t border-slate-100 dark:border-white/5"></div>

                            <button
                                @click="logout"
                                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-rose-500 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all text-sm group"
                            >
                                <LogOut class="h-4 w-4 group-hover:translate-x-0.5 transition-transform" :stroke-width="2" />
                                <span>Keluar</span>
                            </button>
                        </div>

                        <!-- Footer Info -->
                        <div class="px-4 py-3 bg-slate-50 dark:bg-[#0a0b0e]/50 border-t border-slate-100 dark:border-white/5">
                            <div class="flex items-center justify-between text-[10px] font-mono">
                                <span class="text-slate-400 dark:text-[#6b7280]">Subscription Status</span>
                                <span class="text-sky-600 dark:text-[#38bdf8] font-semibold">{{ user?.subscription_status?.toUpperCase() || 'FREE' }}</span>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </aside>
</template>

<style scoped>
.custom-scroll::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 9999px;
}
.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.18);
}
:is(.dark *) .custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.08);
}
:is(.dark *) .custom-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.16);
}
</style>
