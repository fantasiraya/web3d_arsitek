<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { 
    Menu, 
    Search, 
    Bell, 
    Plus,
    ChevronDown,
    Sun,
    Moon,
    LogOut,
    User,
    Settings
} from '@lucide/vue'
import { useSidebar } from '@/composables/useSidebar'
import { useAppearance } from '@/composables/useAppearance'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const { toggleSidebar } = useSidebar()
const { isDark, toggleTheme } = useAppearance()

// User dropdown
const showUserDropdown = ref(false)

const toggleUserDropdown = () => {
    showUserDropdown.value = !showUserDropdown.value
}

const closeUserDropdown = () => {
    showUserDropdown.value = false
}

const logout = () => {
    router.post('/logout')
}

// Emit event to parent
const emit = defineEmits(['create-project'])

// Click outside handler
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement
    if (!target.closest('.user-dropdown-container')) {
        closeUserDropdown()
    }
}
</script>

<template>
    <!-- TOP APPLICATION HEADER -->
    <header class="sticky top-0 z-40 h-16 bg-white/95 dark:bg-[#0b0c10]/85 backdrop-blur-xl border-b border-slate-200 dark:border-white/5 px-6 flex items-center justify-between transition-colors duration-300">
        <!-- Left: Collapse & Breadcrumbs -->
        <div class="flex items-center gap-4">
            <button 
                @click="toggleSidebar"
                aria-label="Toggle menu" 
                class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 dark:text-[#6b7280] hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 transition-colors"
            >
                <Menu class="h-5 w-5" :stroke-width="2" />
            </button>
            
            <nav class="hidden sm:flex items-center gap-2 text-xs font-mono text-slate-500 dark:text-[#6b7280]">
                <Link href="/dashboard" class="hover:text-slate-900 dark:hover:text-[#f3f4f6] cursor-pointer transition-colors">Dashboard</Link>
                <span class="text-slate-300 dark:text-white/20">/</span>
                <span class="hover:text-slate-900 dark:hover:text-[#f3f4f6] cursor-pointer transition-colors">Proyek 3D</span>
                <span class="text-slate-300 dark:text-white/20">/</span>
                <span class="text-slate-900 dark:text-white font-semibold">Overview</span>
            </nav>
        </div>

        <!-- Right: Telemetry, Global Search & CTA -->
        <div class="flex items-center gap-3">
            <!-- Edge CDN Status Pill -->
            <div class="hidden xl:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-50 dark:bg-[#13141a] border border-slate-200 dark:border-white/5 text-[11px] font-mono text-slate-600 dark:text-[#6b7280] font-medium">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Edge CDN: SGP-01 • 14ms (Operational)</span>
            </div>

            <!-- Quick Search Bar -->
            <div class="relative w-64 lg:w-72 hidden sm:block">
                <input 
                    class="w-full bg-slate-50 dark:bg-[#13141a] border border-slate-200 dark:border-white/10 rounded-xl pl-9 pr-12 py-1.5 text-xs text-slate-900 dark:text-[#f3f4f6] placeholder:text-slate-400 dark:placeholder:text-[#6b7280] focus:outline-none focus:border-sky-500 dark:focus:border-[#38bdf8] focus:ring-1 focus:ring-sky-500/30 dark:focus:ring-[#38bdf8]/30 transition-all" 
                    placeholder="Cari proyek, klien, revisi..." 
                    type="text"
                >
                <Search class="absolute left-2.5 top-2 h-[16px] w-[16px] text-slate-400 dark:text-[#6b7280]" :stroke-width="2" />
                <kbd class="absolute right-2 top-1.5 px-1.5 py-0.5 text-[9px] font-mono bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 rounded text-slate-400 dark:text-[#6b7280] shadow-sm">⌘K</kbd>
            </div>

            <!-- Theme Toggle -->
            <div class="relative flex items-center">
                <button 
                    @click="toggleTheme"
                    type="button" 
                    aria-label="Ganti Mode Tema" 
                    class="group relative flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-[#13141a] border border-slate-200 dark:border-white/10 hover:border-slate-300 dark:hover:border-white/20 hover:bg-slate-200 dark:hover:bg-[#181920] transition-all text-xs font-mono select-none"
                >
                    <Moon v-if="isDark" class="h-[17px] w-[17px] text-amber-400 dark:text-amber-300 transition-transform group-hover:rotate-45 duration-300" :stroke-width="2" />
                    <Sun v-else class="h-[17px] w-[17px] text-amber-500 transition-transform group-hover:rotate-45 duration-300" :stroke-width="2" />
                    <span class="hidden md:inline-block text-[11px] text-slate-500 dark:text-[#6b7280] group-hover:text-slate-900 dark:group-hover:text-[#f3f4f6] transition-colors font-medium">
                        {{ isDark ? 'Mode Gelap' : 'Mode Terang' }}
                    </span>
                </button>
            </div>

            <!-- Notification Bell -->
            <button 
                aria-label="Notifikasi" 
                class="relative w-9 h-9 rounded-xl flex items-center justify-center text-slate-400 dark:text-[#6b7280] hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-white/5 border border-transparent hover:border-slate-200 dark:hover:border-white/5 transition-all"
            >
                <Bell class="h-[19px] w-[19px]" :stroke-width="2" />
                <span class="absolute top-2 right-2 w-2 h-2 bg-sky-500 dark:bg-[#38bdf8] rounded-full ring-2 ring-white dark:ring-[#0b0c10]"></span>
            </button>

            <!-- Primary Action CTA -->
            <button 
                @click="emit('create-project')"
                class="hidden sm:flex px-4 py-2 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-[#0b0c10] font-medium text-xs hover:bg-slate-700 dark:hover:bg-[#c4e7ff] hover:shadow-[0_0_20px_rgba(56,189,248,0.2)] active:scale-[0.98] transition-all items-center gap-2"
            >
                <Plus class="h-[17px] w-[17px]" :stroke-width="2.5" />
                <span class="font-semibold tracking-tight">Buat Proyek 3D</span>
            </button>

            <!-- Profile Quick Dropdown -->
            <div class="relative user-dropdown-container">
                <div 
                    @click="toggleUserDropdown"
                    class="flex items-center gap-2 pl-2 border-l border-slate-200 dark:border-white/10 cursor-pointer group"
                >
                    <div class="w-8 h-8 rounded-full bg-slate-200 dark:bg-[#2e303d] border border-slate-300 dark:border-white/10 flex items-center justify-center text-xs font-semibold text-slate-700 dark:text-white">
                        {{ user?.name?.charAt(0).toUpperCase() || 'U' }}
                    </div>
                    <div class="hidden md:block text-left leading-tight">
                        <div class="text-xs font-medium text-slate-800 dark:text-white flex items-center gap-1">
                            <span>{{ user?.name || 'User' }}</span>
                            <ChevronDown 
                                :class="['h-[15px] w-[15px] text-slate-400 dark:text-[#6b7280] group-hover:text-slate-700 dark:group-hover:text-white transition-all duration-200', showUserDropdown && 'rotate-180']" 
                                :stroke-width="2" 
                            />
                        </div>
                        <span class="text-[10px] font-mono text-sky-600 dark:text-[#38bdf8]">{{ user?.is_pro ? 'PRO TIER' : 'FREE TIER' }}</span>
                    </div>
                </div>

                <!-- Dropdown Menu -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 -translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 -translate-y-2"
                >
                    <div 
                        v-if="showUserDropdown"
                        @click.stop
                        class="absolute right-0 top-full mt-2 w-72 bg-white dark:bg-[#13141a]/95 backdrop-blur-2xl border border-slate-200 dark:border-white/10 rounded-2xl shadow-xl dark:shadow-2xl shadow-slate-200/60 dark:shadow-black/50 overflow-hidden z-50"
                    >
                        <!-- User Info Header -->
                        <div class="p-4 border-b border-slate-100 dark:border-white/5 bg-gradient-to-br from-sky-50 dark:from-[#38bdf8]/10 to-transparent">
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 dark:bg-gradient-to-tr dark:from-[#2e303d] dark:to-[#323442] border border-slate-200 dark:border-white/20 flex items-center justify-center text-slate-700 dark:text-white text-lg font-bold shadow-sm dark:shadow-lg">
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
                                href="/settings"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-slate-700 dark:text-[#f3f4f6] hover:bg-slate-50 dark:hover:bg-white/5 transition-all text-sm group"
                            >
                                <Settings class="h-4 w-4 text-slate-400 dark:text-[#6b7280] group-hover:text-sky-600 dark:group-hover:text-[#38bdf8] transition-colors" :stroke-width="2" />
                                <span>Pengaturan</span>
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
    </header>
</template>
