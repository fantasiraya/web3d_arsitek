<template>
    <div
        class="swiss-wrapper"
        :class="[{ 'sidebar-collapsed': isCollapsed }, isDark ? 'theme-dark' : 'theme-light']"
        @click="sidebarRef?.closePopup()"
    >
        <SwissSidebar
            ref="sidebarRef"
            v-model:isCollapsed="isCollapsed"
        />

        <div ref="mainAreaRef" class="main-area">
            <SwissHeader
                :title="currentPageTitle"
                :breadcrumbs="breadcrumbs"
            />

            <main class="main-conten">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SwissSidebar from '@/components/SwissSidebar.vue';
import SwissHeader from '@/components/SwissHeader.vue';
import { useAppearance } from '@/composables/useAppearance';

/* ─────────────────────────────
   DARK MODE
───────────────────────────── */
const { appearance } = useAppearance();
const isDark = computed(() => appearance.value === 'dark');

/* ─────────────────────────────
   STATE
───────────────────────────── */
const isCollapsed = ref(false);
const sidebarRef  = ref<InstanceType<typeof SwissSidebar> | null>(null);
const mainAreaRef = ref<HTMLDivElement | null>(null);

/**
 * Dispatch custom event 'sidebar-transition-end' setelah
 * transisi margin-left selesai — berguna untuk komponen
 * yang punya canvas / 3D viewer agar bisa invalidate size tepat waktu.
 */
watch(isCollapsed, () => {
    const el = mainAreaRef.value;
    if (!el) { return; }

    function onTransitionEnd(e: TransitionEvent) {
        if (e.propertyName !== 'margin-left') { return; }
        el!.removeEventListener('transitionend', onTransitionEnd);
        window.dispatchEvent(new CustomEvent('sidebar-transition-end'));
    }

    el.addEventListener('transitionend', onTransitionEnd);
});

/* ─────────────────────────────
   TYPE
───────────────────────────── */
type PageId =
    | 'dashboard'
    | 'projects'
    | 'teams'
    | 'settings'
    | 'appearance'
    | 'security';

/* ─────────────────────────────
   MENU MAP (SINGLE SOURCE)
───────────────────────────── */
const menuMap: Record<PageId, { title: string; crumbs: string[] }> = {
    dashboard:  { title: 'Dashboard',    crumbs: ['Dashboard'] },
    projects:   { title: 'Proyek 3D',    crumbs: ['Proyek'] },
    teams:      { title: 'Tim',          crumbs: ['Tim'] },
    settings:   { title: 'Pengaturan',   crumbs: ['Pengaturan'] },
    appearance: { title: 'Tampilan',     crumbs: ['Pengaturan', 'Tampilan'] },
    security:   { title: 'Keamanan',     crumbs: ['Pengaturan', 'Keamanan'] },
};

/* ─────────────────────────────
   ACTIVE PAGE (AUTO DARI URL)
───────────────────────────── */
const page = usePage();

const activePage = computed<PageId>(() => {
    const path = page.url;
    if (path.startsWith('/projects'))             { return 'projects'; }
    if (path.startsWith('/teams'))                { return 'teams'; }
    if (path.startsWith('/settings/appearance'))  { return 'appearance'; }
    if (path.startsWith('/settings/security'))    { return 'security'; }
    if (path.startsWith('/settings'))             { return 'settings'; }
    return 'dashboard';
});

/* ─────────────────────────────
   HEADER DATA
───────────────────────────── */
const currentPageTitle = computed(() => menuMap[activePage.value].title);
const breadcrumbs      = computed(() => menuMap[activePage.value].crumbs);
</script>

<style>
/* ══════════════════════════════════════
   GLOBAL BASE
══════════════════════════════════════ */
/* *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; } */

.swiss-wrapper {
    /* ── Ukuran ── */
    --sidebar-w:           264px;
    --sidebar-collapsed-w: 72px;
    --header-h:            64px;

    /* ── Brand hijau (house) ── */
    --primary:       #16a34a;
    --primary-light: rgba(22, 163, 74, 0.13);
    --primary-glow:  rgba(22, 163, 74, 0.35);
    --success:  #10b981;
    --warning:  #f59e0b;
    --danger:   #ef4444;

    /* ── Shape ── */
    --radius:    12px;
    --radius-sm: 8px;

    /* ── Animation ── */
    --t: 0.28s cubic-bezier(0.4, 0, 0.2, 1);

    /* ── Dark palette (default) ── */
    --bg:          #0d0f1a;
    --surface:     #151828;
    --surface-2:   #1c2038;
    --surface-3:   #232847;
    --border:      rgba(255, 255, 255, 0.065);
    --border-2:    rgba(255, 255, 255, 0.12);
    --text:        #e2e8f0;
    --text-dim:    #8da2bf;
    --text-muted:  #4a5a73;
    --popup-bg:    #1c2038;
    --popup-shadow: 0 24px 64px rgba(0, 0, 0, 0.65);
    --shadow-md:   0 8px 32px rgba(0, 0, 0, 0.4);

    display: flex;
    min-height: 100vh;
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Sans', 'Nunito', ui-sans-serif, system-ui, sans-serif;
    position: relative;
}

/* ── Light palette ── */
.swiss-wrapper.theme-light {
    --bg:          #f0f4f8;
    --surface:     #ffffff;
    --surface-2:   #f7f9fc;
    --surface-3:   #edf1f7;
    --border:      rgba(0, 0, 0, 0.075);
    --border-2:    rgba(0, 0, 0, 0.13);
    --text:        #1a2236;
    --text-dim:    #4a5a73;
    --text-muted:  #8da2bf;
    --popup-bg:    #ffffff;
    --popup-shadow: 0 16px 48px rgba(0, 0, 0, 0.14);
    --shadow-md:   0 4px 20px rgba(0, 0, 0, 0.1);
}

/* ── Main area ── */
.main-area {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    min-width: 0;
    transition: margin-left var(--t);
}
.swiss-wrapper.sidebar-collapsed .main-area {
    margin-left: var(--sidebar-collapsed-w);
}

.main-content { flex: 1; padding: 24px; overflow-y: auto; }
</style>
