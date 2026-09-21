<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu, Moon, Settings, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';

/* ─────────────────────────────
   PROPS & EMITS
───────────────────────────── */
defineProps<{
    isCollapsed: boolean;
    pageTitle?: string;
}>();

const emit = defineEmits<{
    (e: 'toggleSidebar'): void;
}>();

/* ─────────────────────────────
   AUTH
───────────────────────────── */
const page = usePage();
const user = computed(() => page.props.auth.user);

/* ─────────────────────────────
   DARK / LIGHT MODE
───────────────────────────── */
const { appearance, updateAppearance } = useAppearance();
const isDark = computed(() => appearance.value === 'dark');

function toggleTheme() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}

/* ─────────────────────────────
   LOGOUT
───────────────────────────── */
function logout() {
    router.flushAll();
    router.post('/logout');
}
</script>

<template>
    <!--
        SWISS NAVBAR
        ═══════════════════════════════════════════════════════════
        Menggunakan CSS variables dari .swiss-wrapper (parent layout):
          var(--primary), var(--surface), var(--navbar-h), dll.

        Kiri  : [Hamburger] [Judul halaman]
        Kanan : [Dark/Light toggle] [Avatar + dropdown]
        ═══════════════════════════════════════════════════════════
    -->
    <header class="swiss-navbar">
        <div class="navbar-inner">

            <!-- ── Kiri: Hamburger + Judul ── -->
            <div class="navbar-left">

                <!-- Hamburger (lebar sama dengan sidebar) -->
                <button
                    class="nav-hamburger"
                    :class="{ 'is-collapsed': isCollapsed }"
                    aria-label="Toggle sidebar"
                    @click="emit('toggleSidebar')"
                >
                    <Menu class="icon" />
                </button>

                <!-- Judul halaman aktif -->
                <span v-if="pageTitle" class="nav-title">
                    {{ pageTitle }}
                </span>
            </div>

            <!-- ── Kanan: Theme toggle + Avatar ── -->
            <div class="navbar-right">

                <!-- Toggle dark / light -->
                <button
                    class="nav-icon-btn"
                    :aria-label="isDark ? 'Mode terang' : 'Mode gelap'"
                    @click="toggleTheme"
                >
                    <Sun v-if="isDark" class="icon text-primary" />
                    <Moon v-else class="icon" />
                </button>

                <!-- Avatar dropdown -->
                <div class="nav-user">
                    <button class="nav-avatar-btn" tabindex="0">
                        <span class="avatar-initials">
                            {{ getInitials(user?.name) }}
                        </span>
                        <span class="avatar-name">{{ user?.name }}</span>
                    </button>

                    <!-- Dropdown -->
                    <div class="nav-dropdown">
                        <Link href="/settings/profile" class="dropdown-item">
                            <Settings class="icon" />
                            Pengaturan
                        </Link>
                        <button class="dropdown-item danger" @click="logout">
                            <LogOut class="icon" />
                            Keluar
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </header>
</template>

<style scoped>
/* ══════════════════════════════════════
   SWISS NAVBAR
   Semua warna pakai CSS var dari parent
   (.swiss-wrapper) agar tema konsisten.
══════════════════════════════════════ */
.swiss-navbar {
    position: fixed;
    inset-inline: 0;
    top: 0;
    z-index: 60;
    height: var(--navbar-h);
    background: var(--surface);
    border-bottom: 2px solid var(--primary);
    box-shadow: var(--shadow);
}

.navbar-inner {
    display: flex;
    align-items: center;
    height: 100%;
}

/* ── Kiri ── */
.navbar-left {
    display: flex;
    align-items: center;
    height: 100%;
}

.nav-hamburger {
    display: flex;
    align-items: center;
    justify-content: center;
    width: var(--sidebar-w);
    height: 100%;
    background: var(--primary);
    color: #fff;
    border: none;
    border-right: 2px solid var(--primary-dark);
    cursor: pointer;
    transition: width var(--t), background var(--t);
    flex-shrink: 0;
}
.nav-hamburger.is-collapsed {
    width: var(--sidebar-collapsed-w);
}
.nav-hamburger:hover {
    background: var(--primary-dark);
}

.nav-title {
    padding-inline: 20px;
    font-size: 0.85rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--primary);
    border-right: 1px solid var(--border);
    height: 100%;
    display: flex;
    align-items: center;
}

/* ── Kanan ── */
.navbar-right {
    display: flex;
    align-items: center;
    height: 100%;
    margin-left: auto;
    border-left: 1px solid var(--border);
}

.nav-icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 100%;
    background: transparent;
    border: none;
    border-right: 1px solid var(--border);
    color: var(--text-dim);
    cursor: pointer;
    transition: background var(--t), color var(--t);
}
.nav-icon-btn:hover {
    background: var(--primary-light);
    color: var(--primary);
}

/* ── User ── */
.nav-user {
    position: relative;
    height: 100%;
    display: flex;
    align-items: center;
}

.nav-avatar-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-inline: 16px;
    height: 100%;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background var(--t);
}
.nav-avatar-btn:hover,
.nav-user:focus-within .nav-avatar-btn {
    background: var(--primary-light);
}

.avatar-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: var(--primary);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    border-radius: 2px;
    flex-shrink: 0;
}

.avatar-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text);
    display: none;
}
@media (min-width: 1024px) { .avatar-name { display: block; } }

/* ── Dropdown ── */
.nav-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 192px;
    background: var(--surface);
    border: 2px solid var(--primary);
    box-shadow: var(--shadow);
    z-index: 70;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-6px);
    transition: opacity var(--t), transform var(--t), visibility var(--t);
}
.nav-user:focus-within .nav-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--text-dim);
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    cursor: pointer;
    text-decoration: none;
    border-bottom: 1px solid var(--border);
    transition: background var(--t), color var(--t);
}
.dropdown-item:hover {
    background: var(--primary-light);
    color: var(--primary);
}
.dropdown-item.danger {
    color: #ef4444;
    border-bottom: none;
}
.dropdown-item.danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

.icon { width: 16px; height: 16px; flex-shrink: 0; }
.text-primary { color: var(--primary); }
</style>
