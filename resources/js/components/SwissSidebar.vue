<template>
    <aside class="sidebar" :class="{ collapsed: isCollapsed }">
        <div class="sidebar-inner">

            <!-- ── Logo ── -->
            <div class="sidebar-logo">
                <div class="logo-icon">
                    <!-- House / Arsitek icon -->
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z" />
                        <path d="M9 21V12h6v9" />
                    </svg>
                </div>
                <Transition name="slide-fade">
                    <span v-if="!isCollapsed" class="logo-text">Web3D Arsitek</span>
                </Transition>
            </div>

            <!-- ── Scrollable nav ── -->
            <div class="nav-scroll-area">
                <nav class="sidebar-nav">

                    <!-- Section: Main Menu -->
                    <div class="nav-section-header">
                        <Transition name="fade" mode="out-in">
                            <span v-if="!isCollapsed" class="nav-label" key="main-label">MAIN MENU</span>
                            <span v-else class="nav-divider-line" key="main-line" />
                        </Transition>
                    </div>

                    <div class="nav-items-scroll">
                        <SidebarNavItem
                            v-for="item in mainNavItems"
                            :key="item.id"
                            :item="item"
                            :is-active="page.url === item.href || page.url.startsWith(item.href + '/')"
                            :collapsed="isCollapsed"
                        />
                    </div>

                    <!-- Section: Pengaturan -->
                    <div class="nav-section-header" style="margin-top: 1.25rem">
                        <Transition name="fade" mode="out-in">
                            <span v-if="!isCollapsed" class="nav-label" key="set-label">PENGATURAN</span>
                            <span v-else class="nav-divider-line" key="set-line" />
                        </Transition>
                    </div>

                    <div class="nav-items-scroll">
                        <SidebarNavItem
                            v-for="item in settingNavItems"
                            :key="item.id"
                            :item="item"
                            :is-active="page.url === item.href || page.url.startsWith(item.href + '/')"
                            :collapsed="isCollapsed"
                        />
                    </div>

                </nav>
            </div>

            <!-- ── User footer ── -->
            <div class="sidebar-footer">
                <button
                    class="sidebar-user-btn"
                    :class="{ active: showUserPopup, 'is-collapsed': isCollapsed }"
                    :title="isCollapsed ? `${user?.name} — klik untuk menu` : ''"
                    @click.stop="showUserPopup = !showUserPopup"
                >
                    <div class="user-avatar-wrap">
                        <div class="user-avatar">{{ getInitial(user?.name) }}</div>
                        <span class="user-status-dot" />
                    </div>
                    <Transition name="slide-fade">
                        <div v-if="!isCollapsed" class="user-text">
                            <span class="user-name">{{ user?.name }}</span>
                            <span class="user-role">{{ user?.email }}</span>
                        </div>
                    </Transition>
                    <Transition name="fade">
                        <svg
                            v-if="!isCollapsed"
                            class="user-chevron"
                            :class="{ rotated: showUserPopup }"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                        >
                            <polyline points="18 15 12 9 6 15" />
                        </svg>
                    </Transition>
                </button>

                <!-- User popup -->
                <UserPopup
                    :visible="showUserPopup"
                    :user="user"
                    @action="handleUserAction"
                    @click.stop
                />
            </div>

        </div>
    </aside>

    <!-- ── FAB Toggle ── -->
    <button
        class="sidebar-toggle-fab"
        :class="{ collapsed: isCollapsed }"
        :title="isCollapsed ? 'Buka Sidebar' : 'Tutup Sidebar'"
        @click.stop="emit('update:isCollapsed', !isCollapsed)"
    >
        <span class="fab-tooltip">{{ isCollapsed ? 'Buka' : 'Tutup' }}</span>
        <svg
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            class="fab-icon" :class="{ rotated: isCollapsed }"
        >
            <polyline points="15 18 9 12 15 6" />
        </svg>
    </button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { LayoutGrid, FolderOpen, Users, Settings, Palette, ShieldCheck } from '@lucide/vue';
import SidebarNavItem from '@/components/SidebarNavItem.vue';
import UserPopup from '@/components/SwissUserPopup.vue';

/* ─────────────────────────────
   PROPS & EMITS
───────────────────────────── */
defineProps<{
    isCollapsed: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:isCollapsed', val: boolean): void;
    (e: 'close-popups'): void;
}>();

/* ─────────────────────────────
   AUTH
───────────────────────────── */
const page = usePage();
const user = computed(() => page.props.auth.user);

/* ─────────────────────────────
   HELPER
───────────────────────────── */
function getInitial(name?: string): string {
    if (!name) { return '?'; }
    return name.trim()[0].toUpperCase();
}

/* ─────────────────────────────
   NAV ITEMS
   Tambah / kurangi item di sini
───────────────────────────── */
const mainNavItems = [
    { id: 'dashboard', label: 'Dashboard',  href: '/dashboard',  icon: LayoutGrid },
    { id: 'projects',  label: 'Proyek 3D',  href: '/projects',   icon: FolderOpen },
    { id: 'teams',     label: 'Tim',        href: '/teams',      icon: Users },
];

const settingNavItems = [
    { id: 'settings',   label: 'Profil',    href: '/settings/profile',    icon: Settings },
    { id: 'appearance', label: 'Tampilan',  href: '/settings/appearance', icon: Palette },
    { id: 'security',   label: 'Keamanan',  href: '/settings/security',   icon: ShieldCheck },
];

/* ─────────────────────────────
   USER POPUP
───────────────────────────── */
const showUserPopup = ref(false);

function handleUserAction(id: string) {
    showUserPopup.value = false;
    if (id === 'logout') {
        router.post('/logout');
    }
}

/* ─────────────────────────────
   EXPOSE (untuk closePopup dari layout)
───────────────────────────── */
defineExpose({
    closePopup: () => { showUserPopup.value = false; },
});
</script>

<style scoped>
/* ══════════════════════════════════════
   SIDEBAR
══════════════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    height: 100vh;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    z-index: 200;
    transition: width var(--t);
    overflow: visible;
}
.sidebar.collapsed { width: var(--sidebar-collapsed-w); }

.sidebar-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* ── Logo ── */
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 18px;
    height: var(--header-h);
    border-bottom: 1px solid var(--border);
    overflow: hidden;
    flex-shrink: 0;
}
.logo-icon {
    width: 36px; height: 36px;
    background: var(--primary);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px var(--primary-glow);
}
.logo-icon svg { width: 18px; height: 18px; stroke: white; }
.logo-text {
    font-size: 1.05rem; font-weight: 800;
    color: var(--text);
    white-space: nowrap;
    letter-spacing: -0.025em;
}

/* ── Scroll area ── */
.nav-scroll-area {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
}
.nav-scroll-area::-webkit-scrollbar { width: 3px; }
.nav-scroll-area::-webkit-scrollbar-track { background: transparent; }
.nav-scroll-area::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 4px; }
.nav-scroll-area::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

.sidebar-nav { padding: 14px 10px; }

.nav-section-header {
    padding: 6px 8px 8px;
    min-height: 26px;
    display: flex;
    align-items: center;
}
.nav-label {
    font-size: 0.6rem; font-weight: 700;
    color: var(--text-muted);
    letter-spacing: 0.12em;
    white-space: nowrap;
}
.nav-divider-line {
    display: block;
    width: 100%; height: 1px;
    background: var(--border);
}
.nav-items-scroll { overflow-x: auto; overflow-y: visible; padding-bottom: 4px; }
.nav-items-scroll::-webkit-scrollbar { height: 3px; }
.nav-items-scroll::-webkit-scrollbar-thumb { background: var(--border-2); border-radius: 4px; }

/* ── Footer user ── */
.sidebar-footer {
    padding: 10px;
    border-top: 1px solid var(--border);
    position: relative;
    flex-shrink: 0;
}
.sidebar-user-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: var(--radius-sm);
    border: 1px solid transparent;
    background: var(--surface-2);
    color: var(--text);
    cursor: pointer;
    transition: all 0.17s ease;
    text-align: left;
    overflow: hidden;
}
.sidebar-user-btn:hover { background: var(--surface-3); border-color: var(--border-2); }
.sidebar-user-btn.active { border-color: var(--primary); background: var(--primary-light); }
.sidebar-user-btn.is-collapsed { justify-content: center; padding: 9px 0; }

.user-avatar-wrap { position: relative; flex-shrink: 0; }
.user-avatar {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--primary), #059669);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 800;
    color: white;
    box-shadow: 0 4px 12px var(--primary-glow);
}
.user-status-dot {
    position: absolute;
    bottom: -2px; right: -2px;
    width: 10px; height: 10px;
    background: var(--success);
    border-radius: 50%;
    border: 2px solid var(--surface);
}
.user-text { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
.user-name {
    font-size: 0.84rem; font-weight: 700;
    color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role { font-size: 0.69rem; color: var(--text-muted); }

.user-chevron {
    width: 15px; height: 15px;
    color: var(--text-muted); flex-shrink: 0;
    transition: transform var(--t);
}
.user-chevron.rotated { transform: rotate(180deg); }

/* ── FAB Toggle ── */
.sidebar-toggle-fab {
    position: fixed;
    top: calc(var(--header-h) / 2);
    transform: translateY(-50%);
    left: calc(var(--sidebar-w) - 18px);
    transition: left var(--t), box-shadow 0.18s;
    z-index: 300;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--primary);
    border: 3px solid var(--bg);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    color: white;
    box-shadow: 0 0 0 1px var(--primary-glow), 0 4px 18px rgba(22, 163, 74, 0.55);
}
.sidebar-toggle-fab.collapsed { left: calc(var(--sidebar-collapsed-w) - 18px); }
.sidebar-toggle-fab:hover {
    background: #15803d;
    box-shadow: 0 0 0 4px var(--primary-light), 0 6px 24px rgba(22, 163, 74, 0.6);
}
.sidebar-toggle-fab:active { transform: translateY(-50%) scale(0.9); }

.fab-tooltip {
    position: absolute;
    right: calc(100% + 10px);
    top: 50%; transform: translateY(-50%);
    background: var(--surface-3);
    border: 1px solid var(--border-2);
    color: var(--text);
    font-size: 0.72rem; font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
    box-shadow: var(--shadow-md);
}
.sidebar-toggle-fab:hover .fab-tooltip { opacity: 1; }

.fab-icon { width: 15px; height: 15px; transition: transform var(--t); flex-shrink: 0; }
.fab-icon.rotated { transform: rotate(180deg); }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.16s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-fade-enter-active { transition: all 0.22s ease; }
.slide-fade-leave-active { transition: all 0.13s ease; }
.slide-fade-enter-from { opacity: 0; transform: translateX(-10px); }
.slide-fade-leave-to { opacity: 0; }
</style>
