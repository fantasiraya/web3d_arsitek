<template>
    <header class="header">
        <div class="header-left">
            <h2 class="page-title">{{ title }}</h2>
        </div>

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item">
                    <Link href="/dashboard" class="breadcrumb-link">
                        <svg class="home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                            <polyline points="9 22 9 12 15 12 15 22" />
                        </svg>
                        Home
                    </Link>
                </li>
                <li v-for="(crumb, i) in breadcrumbs" :key="i" class="breadcrumb-item">
                    <svg class="breadcrumb-sep" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6" />
                    </svg>
                    <span v-if="i === breadcrumbs.length - 1" class="breadcrumb-current">{{ crumb }}</span>
                    <a v-else href="#" class="breadcrumb-link">{{ crumb }}</a>
                </li>
            </ol>
        </nav>

        <!-- Actions -->
        <div class="header-actions">

            <!-- Theme toggle (dark / light) -->
            <button
                class="action-btn theme-btn"
                :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                @click.stop="toggleTheme"
            >
                <Transition name="fade" mode="out-in">
                    <!-- Sun — saat dark mode aktif -->
                    <svg v-if="isDark" key="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="5" />
                        <line x1="12" y1="1" x2="12" y2="3" />
                        <line x1="12" y1="21" x2="12" y2="23" />
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                        <line x1="1" y1="12" x2="3" y2="12" />
                        <line x1="21" y1="12" x2="23" y2="12" />
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                    </svg>
                    <!-- Moon — saat light mode aktif -->
                    <svg v-else key="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                    </svg>
                </Transition>
            </button>

        </div>
    </header>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';

/* ─────────────────────────────
   PROPS
───────────────────────────── */
defineProps<{
    title: string;
    breadcrumbs: string[];
}>();

/* ─────────────────────────────
   DARK / LIGHT MODE
───────────────────────────── */
const { appearance, updateAppearance } = useAppearance();
const isDark = computed(() => appearance.value === 'dark');

function toggleTheme() {
    updateAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<style scoped>
.header {
    height: var(--header-h);
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px 0 44px;
    position: sticky;
    top: 0; z-index: 100;
    gap: 16px;
}

.page-title { font-size: 1rem; font-weight: 800; color: var(--text); white-space: nowrap; }

.breadcrumb { flex: 1; display: flex; justify-content: flex-end; }
.breadcrumb-list { display: flex; align-items: center; list-style: none; gap: 2px; }
.breadcrumb-item { display: flex; align-items: center; gap: 2px; }

.breadcrumb-link {
    display: flex; align-items: center; gap: 5px;
    color: var(--text-muted);
    text-decoration: none;
    font-size: 0.79rem; font-weight: 500;
    padding: 4px 8px; border-radius: 6px;
    transition: all 0.15s;
}
.breadcrumb-link:hover { color: var(--primary); background: var(--primary-light); }
.home-icon { width: 13px; height: 13px; }
.breadcrumb-sep { width: 13px; height: 13px; color: var(--text-muted); opacity: 0.4; }
.breadcrumb-current {
    font-size: 0.79rem; font-weight: 700;
    color: var(--primary);
    padding: 4px 10px;
    background: var(--primary-light);
    border-radius: 6px;
}

.header-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

.action-btn {
    width: 38px; height: 38px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-dim); cursor: pointer;
    transition: all 0.17s; position: relative;
}
.action-btn svg { width: 17px; height: 17px; }
.action-btn:hover { background: var(--primary-light); color: var(--primary); border-color: rgba(22, 163, 74, 0.28); }

.theme-btn { color: var(--warning); }
.theme-btn:hover { background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: var(--warning); }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.16s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
