<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronLeft,
    ChevronUp,
    CreditCard,
    FolderKanban,
    Layers,
    LayoutDashboard,
    LogOut,
    Menu,
    Moon,
    Palette,
    Settings,
    ShieldAlert,
    ShieldCheck,
    Sun,
    User,
    Users,
    X,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineProps<{
    title?: string;
}>();

const page = usePage();
const flashSuccess = computed(() => (page.props as any).flash?.success);
const authUser = computed(() => (page.props as any).auth?.user);

const currentUrl = computed(() => page.url);

// User popup state
const showUserPopup = ref(false);

function toggleUserPopup(e: MouseEvent) {
    e.stopPropagation();
    showUserPopup.value = !showUserPopup.value;
}

function closeUserPopup() {
    showUserPopup.value = false;
}

function handleLogout() {
    closeUserPopup();
    router.post('/logout');
}

// Close popup when clicking outside
function handleClickOutside(e: MouseEvent) {
    const target = e.target as HTMLElement;
    if (!target.closest('.user-section')) {
        showUserPopup.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));

const navItems = [
    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
    { title: 'Users', href: '/admin/users', icon: Users },
    { title: 'Projects', href: '/admin/projects', icon: FolderKanban },
    { title: 'Subscriptions', href: '/admin/subscriptions', icon: CreditCard },
    { title: 'Plans', href: '/admin/plans', icon: Layers },
    { title: 'Audit Logs', href: '/admin/audit-logs', icon: ShieldAlert },
];

// Mobile menu state
const isMobileMenuOpen = ref(false);

// Sidebar collapse state (desktop)
const isCollapsed = ref(false);

function isActive(href: string): boolean {
    if (href === '/admin/dashboard') {
        return currentUrl.value === '/admin/dashboard' || currentUrl.value === '/admin';
    }
    return currentUrl.value.startsWith(href);
}

// Dark mode toggle
const { appearance, updateAppearance } = useAppearance();
const isDark = computed(() => appearance.value === 'dark');

function toggleTheme() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}

function toggleMobileMenu() {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
}

function closeMobileMenu() {
    isMobileMenuOpen.value = false;
}
</script>

<template>
    <div class="admin-wrapper" :class="{ 'sidebar-collapsed': isCollapsed }">
        <Head :title="title ? `${title} - SaaS Admin Panel` : 'SaaS Admin Panel'" />

        <!-- Mobile Overlay -->
        <div 
            v-if="isMobileMenuOpen"
            class="mobile-overlay"
            @click="closeMobileMenu"
        ></div>

        <!-- Sidebar -->
        <aside 
            class="admin-sidebar"
            :class="{ 'mobile-open': isMobileMenuOpen }"
        >
            <div class="sidebar-inner">
                <!-- Brand / Logo -->
                <div class="sidebar-header">
                    <div class="logo-section">
                        <div class="logo-icon">
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                        <Transition name="slide-fade">
                            <div v-if="!isCollapsed" class="logo-text">
                                <div class="logo-title">ARCHITECT 3D</div>
                                <div class="logo-subtitle">Super Admin</div>
                            </div>
                        </Transition>
                    </div>
                    <div class="header-actions">
                        <!-- <Transition name="fade">
                            <Badge v-if="!isCollapsed" variant="outline" class="badge-panel">
                                Panel
                            </Badge>
                        </Transition> -->
                        <!-- Close button for mobile -->
                        <Button
                            variant="ghost"
                            size="icon"
                            class="close-mobile"
                            @click="closeMobileMenu"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="sidebar-nav">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        :class="['nav-item', { active: isActive(item.href) }]"
                        :title="isCollapsed ? item.title : ''"
                        @click="closeMobileMenu"
                    >
                        <component :is="item.icon" class="nav-icon" />
                        <Transition name="slide-fade">
                            <span v-if="!isCollapsed" class="nav-label">{{ item.title }}</span>
                        </Transition>
                    </Link>
                </nav>

                <!-- Footer actions -->
                <div class="sidebar-footer">
                    <Link
                        href="/dashboard"
                        class="footer-link"
                        :title="isCollapsed ? 'Kembali ke Aplikasi 3D' : ''"
                        @click="closeMobileMenu"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        <Transition name="slide-fade">
                            <span v-if="!isCollapsed">Kembali ke Aplikasi 3D</span>
                        </Transition>
                    </Link>

                    <div class="user-section">
                        <!-- User pill — klik untuk buka popup -->
                        <button
                            class="user-pill"
                            :class="{ active: showUserPopup }"
                            @click="toggleUserPopup"
                            :title="isCollapsed ? `${authUser?.name} — klik untuk menu` : ''"
                        >
                            <div class="user-info">
                                <div class="user-avatar">
                                    {{ authUser?.name?.charAt(0) ?? 'A' }}
                                </div>
                                <Transition name="slide-fade">
                                    <div v-if="!isCollapsed" class="user-text">
                                        <div class="user-name">{{ authUser?.name }}</div>
                                        <div class="user-email">{{ authUser?.email }}</div>
                                    </div>
                                </Transition>
                            </div>
                            <Transition name="slide-fade">
                                <ChevronUp
                                    v-if="!isCollapsed"
                                    class="h-4 w-4 text-muted-foreground transition-transform duration-200 shrink-0"
                                    :class="{ 'rotate-180': showUserPopup }"
                                />
                            </Transition>
                        </button>

                        <!-- Popup -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 scale-95 translate-y-2"
                            enter-to-class="opacity-100 scale-100 translate-y-0"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 scale-100 translate-y-0"
                            leave-to-class="opacity-0 scale-95 translate-y-2"
                        >
                            <div
                                v-if="showUserPopup"
                                class="user-popup"
                                :class="{ 'popup-collapsed': isCollapsed }"
                                @click.stop
                            >
                                <!-- Header user info -->
                                <div class="popup-header">
                                    <div class="popup-avatar">{{ authUser?.name?.charAt(0) ?? 'A' }}</div>
                                    <div class="popup-user-info">
                                        <span class="popup-name">{{ authUser?.name }}</span>
                                        <span class="popup-email">{{ authUser?.email }}</span>
                                    </div>
                                    <span class="popup-role-pill">Super Admin</span>
                                </div>

                                <div class="popup-divider" />

                                <!-- Menu items -->
                                <Link href="/settings/profile" class="popup-item" @click="closeUserPopup">
                                    <User class="popup-icon-svg" />
                                    <div class="popup-item-text">
                                        <span class="popup-item-label">Profil Saya</span>
                                        <span class="popup-item-desc">Edit data & foto profil</span>
                                    </div>
                                </Link>

                                <Link href="/settings/profile" class="popup-item" @click="closeUserPopup">
                                    <Settings class="popup-icon-svg" />
                                    <div class="popup-item-text">
                                        <span class="popup-item-label">Pengaturan Akun</span>
                                        <span class="popup-item-desc">Keamanan & preferensi</span>
                                    </div>
                                </Link>

                                <Link href="/settings/appearance" class="popup-item" @click="closeUserPopup">
                                    <Palette class="popup-icon-svg" />
                                    <div class="popup-item-text">
                                        <span class="popup-item-label">Tampilan</span>
                                        <span class="popup-item-desc">Mode gelap & preferensi UI</span>
                                    </div>
                                </Link>

                                <Link href="/settings/security" class="popup-item" @click="closeUserPopup">
                                    <ShieldCheck class="popup-icon-svg" />
                                    <div class="popup-item-text">
                                        <span class="popup-item-label">Keamanan</span>
                                        <span class="popup-item-desc">Password & autentikasi</span>
                                    </div>
                                </Link>

                                <div class="popup-divider" />

                                <!-- Logout -->
                                <button class="popup-item popup-logout" @click="handleLogout">
                                    <LogOut class="popup-icon-svg" />
                                    <span class="popup-item-label">Keluar dari Akun</span>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Toggle FAB (Desktop only) -->
        <button
            class="sidebar-toggle-fab"
            :title="isCollapsed ? 'Buka Sidebar' : 'Tutup Sidebar'"
            @click="isCollapsed = !isCollapsed"
        >
            <span class="fab-tooltip p-1">{{ isCollapsed ? 'Buka' : 'Tutup' }}</span>
            <ChevronLeft class="fab-icon" :class="{ rotated: isCollapsed }" />
        </button>

        <!-- Main Content Area -->
        <div class="main-area">
            <!-- Header bar -->
            <header class="admin-header">
                <div class="header-left">
                    <!-- Hamburger Menu Button (Mobile Only) -->
                    <Button
                        variant="ghost"
                        size="icon"
                        class="hamburger-btn"
                        @click="toggleMobileMenu"
                    >
                        <Menu class="h-5 w-5" />
                    </Button>
                    
                    <Badge class="badge-mode">
                        Super Admin Mode
                    </Badge>
                    <span class="divider">|</span>
                    <span class="subtitle">Backend & Subscription Management</span>
                </div>

                <div class="header-right">
                    <!-- Dark Mode Toggle -->
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-9 w-9"
                        :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                        @click="toggleTheme"
                    >
                        <Transition name="fade" mode="out-in">
                            <Sun v-if="isDark" :key="'sun'" class="h-4 w-4" />
                            <Moon v-else :key="'moon'" class="h-4 w-4" />
                        </Transition>
                    </Button>
                    
                    <Link href="/dashboard" class="client-view-link">
                        <Button variant="outline" size="sm" class="text-xs h-8 gap-1.5">
                            <ArrowLeft class="h-3.5 w-3.5" />
                            Client View
                        </Button>
                    </Link>
                </div>
            </header>

            <!-- Flash Alert -->
            <div v-if="flashSuccess" class="flash-container">
                <div class="flash-alert">
                    <CheckCircle2 class="flash-icon" />
                    <p class="flash-text">{{ flashSuccess }}</p>
                </div>
            </div>

            <!-- Page Body -->
            <main class="main-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
/* Admin Wrapper - Similar to swiss-wrapper */
.admin-wrapper {
    /* CSS Variables */
    --admin-sidebar-w: 256px;
    --admin-sidebar-collapsed-w: 72px;
    --admin-header-h: 64px;
    --admin-transition: 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    
    display: flex;
    min-height: 100vh;
    background: hsl(var(--background));
    color: hsl(var(--foreground));
    position: relative;
}

/* Mobile Overlay */
.mobile-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 40;
}

@media (min-width: 768px) {
    .mobile-overlay {
        display: none;
    }
}

/* Sidebar */
.admin-sidebar {
    width: var(--admin-sidebar-w);
    height: 100vh;
    background: hsl(0 0% 100%); /* White background for light mode */
    border-right: 1px solid hsl(var(--sidebar-border));
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 50;
    transition: width var(--admin-transition);
    overflow: visible;
    transform: translateX(-100%);
}

/* Dark mode sidebar background */
.dark .admin-sidebar {
    background: hsl(240 10% 3.9%); /* Dark solid background */
}

@media (min-width: 768px) {
    .admin-sidebar {
        transform: translateX(0);
    }
}

.admin-sidebar.mobile-open {
    transform: translateX(0);
}

.admin-wrapper.sidebar-collapsed .admin-sidebar {
    width: var(--admin-sidebar-collapsed-w);
}

.sidebar-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* Sidebar Header */
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem;
    height: var(--admin-header-h);
    border-bottom: 1px solid hsl(var(--sidebar-border));
    flex-shrink: 0;
}

.logo-section {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
}

.logo-icon {
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.75rem;
    background: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px hsl(var(--primary) / 0.4);
}

.logo-text {
    overflow: hidden;
}

.logo-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: hsl(var(--sidebar-foreground));
    white-space: nowrap;
    letter-spacing: -0.01em;
}

.logo-subtitle {
    font-size: 0.6875rem;
    font-weight: 500;
    color: hsl(var(--muted-foreground));
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.badge-panel {
    font-size: 0.625rem;
    text-transform: uppercase;
    font-family: monospace;
    padding: 0.125rem 0.375rem;
    border-color: hsl(var(--primary) / 0.4);
    color: hsl(var(--primary));
    display: none;
}

@media (min-width: 768px) {
    .badge-panel {
        display: inline-flex;
    }
}

.close-mobile {
    height: 2rem;
    width: 2rem;
}

@media (min-width: 768px) {
    .close-mobile {
        display: none;
    }
}

/* Sidebar Navigation */
.sidebar-nav {
    flex: 1;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    overflow-y: auto;
    overflow-x: hidden;
}

.sidebar-nav::-webkit-scrollbar {
    width: 3px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: hsl(var(--border));
    border-radius: 4px;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: hsl(var(--sidebar-foreground) / 0.7);
    transition: all 0.15s;
    text-decoration: none;
}

.nav-item:hover {
    background: hsl(var(--sidebar-accent) / 0.5);
    color: hsl(var(--sidebar-foreground));
}

.nav-item.active {
    background: hsl(var(--sidebar-accent));
    color: hsl(var(--sidebar-accent-foreground));
    font-weight: 600;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

.admin-wrapper.sidebar-collapsed .nav-item {
    justify-content: center;
    padding-left: 0;
    padding-right: 0;
}

.nav-icon {
    height: 1rem;
    width: 1rem;
    flex-shrink: 0;
    transition: all 0.15s;
}

.nav-item.active .nav-icon {
    color: hsl(var(--primary));
}

.nav-label {
    white-space: nowrap;
    overflow: hidden;
}

/* Sidebar Footer */
.sidebar-footer {
    padding: 0.75rem;
    border-top: 1px solid hsl(var(--sidebar-border));
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
}

.footer-link {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: hsl(var(--sidebar-foreground) / 0.7);
    transition: all 0.15s;
    text-decoration: none;
}

.footer-link:hover {
    background: hsl(var(--sidebar-accent));
    color: hsl(var(--sidebar-foreground));
}

.admin-wrapper.sidebar-collapsed .footer-link {
    justify-content: center;
    padding-left: 0;
    padding-right: 0;
}

.user-section {
    padding-top: 0.5rem;
    border-top: 1px solid hsl(var(--sidebar-border) / 0.6);
    position: relative;
}

/* Pill button */
.user-pill {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.5rem 0.625rem;
    border-radius: 0.625rem;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    transition: all 0.17s ease;
    text-align: left;
}
.user-pill:hover { background: hsl(var(--sidebar-accent) / 0.5); }
.user-pill.active {
    background: hsl(var(--sidebar-accent));
    border-color: hsl(var(--sidebar-border));
}

.admin-wrapper.sidebar-collapsed .user-pill {
    justify-content: center;
    padding: 0.5rem 0;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow: hidden;
    min-width: 0;
}

.user-avatar {
    height: 2rem;
    width: 2rem;
    border-radius: 9999px;
    background: hsl(var(--primary) / 0.2);
    color: hsl(var(--primary));
    font-weight: 600;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-text {
    overflow: hidden;
    min-width: 0;
}

.user-name {
    font-size: 0.75rem;
    font-weight: 600;
    color: hsl(var(--sidebar-foreground));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-email {
    font-size: 0.625rem;
    color: hsl(var(--muted-foreground));
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Popup ── */
.user-popup {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    right: 0;
    background: hsl(0 0% 100%);
    border: 1px solid hsl(0 0% 89.8%);
    border-radius: 0.75rem;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
    z-index: 200;
    overflow: hidden;
    min-width: 220px;
}

.dark .user-popup {
    background: hsl(0 0% 7%);
    border-color: hsl(0 0% 14.9%);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.55);
}

.user-popup.popup-collapsed {
    left: calc(var(--admin-sidebar-collapsed-w) + 8px);
    right: auto;
    width: 256px;
    bottom: 0.5rem;
}

.popup-header {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.875rem 1rem;
    background: hsl(0 0% 96.1%);
    border-bottom: 1px solid hsl(0 0% 89.8%);
}
.dark .popup-header {
    background: hsl(0 0% 10%);
    border-bottom-color: hsl(0 0% 14.9%);
}

.popup-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.625rem;
    background: hsl(var(--primary) / 0.15);
    color: hsl(var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    flex-shrink: 0;
}

.popup-user-info {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

.popup-name {
    font-size: 0.875rem;
    font-weight: 700;
    color: hsl(0 0% 3.9%);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dark .popup-name { color: hsl(0 0% 98%); }

.popup-email {
    font-size: 0.6875rem;
    color: hsl(0 0% 45.1%);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dark .popup-email { color: hsl(0 0% 63.9%); }

.popup-role-pill {
    font-size: 0.625rem;
    font-weight: 700;
    color: hsl(var(--primary));
    background: hsl(var(--primary) / 0.1);
    border: 1px solid hsl(var(--primary) / 0.25);
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
    flex-shrink: 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.popup-divider {
    height: 1px;
    background: hsl(0 0% 89.8%);
    margin: 0.25rem 0;
}
.dark .popup-divider { background: hsl(0 0% 14.9%); }

.popup-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 1rem;
    background: transparent;
    border: none;
    color: hsl(0 0% 20%);
    cursor: pointer;
    transition: all 0.15s;
    text-align: left;
    text-decoration: none;
}
.dark .popup-item { color: hsl(0 0% 80%); }

.popup-item:hover {
    background: hsl(0 0% 94%);
    color: hsl(0 0% 3.9%);
}
.dark .popup-item:hover {
    background: hsl(0 0% 14.9%);
    color: hsl(0 0% 98%);
}

.popup-logout { color: hsl(0 84.2% 50%); }
.dark .popup-logout { color: hsl(0 84% 60%); }

.popup-logout:hover {
    background: hsl(0 84.2% 50% / 0.08);
    color: hsl(0 84.2% 45%);
}
.dark .popup-logout:hover {
    background: hsl(0 84% 60% / 0.1);
    color: hsl(0 84% 65%);
}

.popup-icon-svg {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
    opacity: 0.7;
}

.popup-item-text {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.popup-item-label {
    font-size: 0.84rem;
    font-weight: 600;
}

.popup-item-desc {
    font-size: 0.6875rem;
    color: hsl(0 0% 45.1%);
    margin-top: 1px;
}
.dark .popup-item-desc { color: hsl(0 0% 63.9%); }

/* Toggle FAB */
.sidebar-toggle-fab {
    position: fixed;
    top: calc(var(--admin-header-h) / 2);
    transform: translateY(-50%);
    left: calc(var(--admin-sidebar-w) - 18px);
    z-index: 60;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    background: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
    border: 4px solid hsl(var(--background));
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px hsl(var(--primary) / 0.4);
    transition: all var(--admin-transition);
}

@media (min-width: 768px) {
    .sidebar-toggle-fab {
        display: flex;
    }
}

.admin-wrapper.sidebar-collapsed .sidebar-toggle-fab {
    left: calc(var(--admin-sidebar-collapsed-w) - 18px);
}

.sidebar-toggle-fab:hover {
    box-shadow: 0 6px 16px hsl(var(--primary) / 0.5);
    transform: translateY(-50%) scale(1.05);
}

.sidebar-toggle-fab:active {
    transform: translateY(-50%) scale(0.95);
}

.fab-tooltip {
    position: absolute;
    right: calc(10% + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: hsl(var(--popover));
    color: hsl(var(--popover-foreground));
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.625rem;
    border-radius: 0.375rem;
    border: 1px solid hsl(var(--border));
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
    white-space: nowrap;
}

.sidebar-toggle-fab:hover .fab-tooltip {
    opacity: 1;
}

.fab-icon {
    height: 1rem;
    width: 1rem;
    transition: transform var(--admin-transition);
}

.fab-icon.rotated {
    transform: rotate(180deg);
}

/* Main Area - Similar to AppSwissLayout */
.main-area {
    margin-left: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-width: 0;
    transition: margin-left var(--admin-transition);
}

@media (min-width: 768px) {
    .main-area {
        margin-left: var(--admin-sidebar-w);
    }
    
    .admin-wrapper.sidebar-collapsed .main-area {
        margin-left: var(--admin-sidebar-collapsed-w);
    }
}

/* Admin Header */
.admin-header {
    height: var(--admin-header-h);
    border-bottom: 1px solid hsl(var(--border));
    background: hsl(var(--card) / 0.6);
    backdrop-filter: blur(12px);
    padding: 0 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    position: sticky;
    top: 0;
    z-index: 30;
}

@media (min-width: 768px) {
    .admin-header {
        padding: 0 1.5rem;
    }
}

.header-left,
.header-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.hamburger-btn {
    height: 2.25rem;
    width: 2.25rem;
}

@media (min-width: 768px) {
    .hamburger-btn {
        display: none;
    }
}

.badge-mode {
    background: hsl(var(--primary) / 0.1);
    color: hsl(var(--primary));
    border-color: hsl(var(--primary) / 0.2);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-mode:hover {
    background: hsl(var(--primary) / 0.2);
}

.divider,
.subtitle {
    font-size: 0.75rem;
    color: hsl(var(--muted-foreground));
    display: none;
}

@media (min-width: 640px) {
    .divider,
    .subtitle {
        display: inline;
    }
}

.client-view-link {
    display: none;
}

@media (min-width: 640px) {
    .client-view-link {
        display: inline-block;
    }
}

/* Flash Alert */
.flash-container {
    padding: 1rem 1rem 0;
}

@media (min-width: 768px) {
    .flash-container {
        padding: 1rem 1.5rem 0;
    }
}

.flash-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid hsl(142 76% 36% / 0.3);
    background: hsl(142 76% 36% / 0.1);
    padding: 0.875rem;
    color: hsl(142 76% 36%);
}

.flash-icon {
    height: 1rem;
    width: 1rem;
    flex-shrink: 0;
}

.flash-text {
    font-size: 0.875rem;
    font-weight: 500;
}

/* Main Content */
.main-content {
    flex: 1;
    padding: 1rem;
    overflow-y: auto;
}

@media (min-width: 768px) {
    .main-content {
        padding: 1.5rem;
    }
}

@media (min-width: 1024px) {
    .main-content {
        padding: 2rem;
    }
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.16s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-fade-enter-active {
    transition: all 0.22s ease;
}

.slide-fade-leave-active {
    transition: all 0.13s ease;
}

.slide-fade-enter-from {
    opacity: 0;
    transform: translateX(-10px);
}

.slide-fade-leave-to {
    opacity: 0;
}
</style>
