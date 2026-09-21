<template>
    <Link
        :href="item.href"
        preserve-scroll
        preserve-state
        class="nav-item"
        :class="{ active: isActive, loading: isLoading }"
        :title="collapsed ? item.label : ''"
        @click="handleClick"
    >
        <span class="nav-icon">
            <!-- Spinner saat loading, icon biasa saat idle -->
            <span v-if="isLoading" class="nav-spinner" />
            <component v-else :is="item.icon" :size="18" :stroke-width="1.75" />
        </span>
        <Transition name="slide-fade">
            <span v-if="!collapsed" class="nav-label-text">{{ item.label }}</span>
        </Transition>
        <Transition name="fade">
            <span v-if="!collapsed && item.badge && !isLoading" class="nav-badge">{{ item.badge }}</span>
        </Transition>
    </Link>
</template>

<script setup lang="ts">
import { onUnmounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import type { Component } from 'vue';

/* ─────────────────────────────
   TYPES
───────────────────────────── */
type NavLinkItem = {
    id: string;
    label: string;
    href: string;
    icon: Component;
    badge?: string | number;
};

/* ─────────────────────────────
   PROPS & EMITS
───────────────────────────── */
const props = defineProps<{
    item: NavLinkItem;
    isActive: boolean;
    collapsed: boolean;
}>();

const emit = defineEmits<{
    (e: 'select', id: string): void;
}>();

/* ─────────────────────────────
   LOADING STATE
───────────────────────────── */
const isLoading = ref(false);

function handleClick() {
    emit('select', props.item.id);
    isLoading.value = true;
}

// Bersihkan loading state saat navigasi selesai
const stopFinish = router.on('finish', () => {
    isLoading.value = false;
});

onUnmounted(() => {
    stopFinish();
});
</script>

<style scoped>
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 10px;
    border-radius: var(--radius-sm);
    color: var(--text-dim);
    text-decoration: none;
    transition: background 0.17s ease, border-color 0.17s ease, color 0.17s ease;
    cursor: pointer;
    position: relative;
    margin-bottom: 2px;
    border: 1px solid transparent;
    width: max-content;
    min-width: 100%;
}
.nav-item:hover {
    background: var(--surface-2);
    color: var(--text);
    border-color: var(--border);
}
.nav-item.active {
    background: var(--primary-light);
    color: var(--primary);
    border-color: rgba(22, 163, 74, 0.2);
}
.nav-item.active::before {
    content: '';
    position: absolute;
    left: 0; top: 50%;
    transform: translateY(-50%);
    width: 3px; height: 52%;
    background: var(--primary);
    border-radius: 0 3px 3px 0;
}

.nav-icon {
    width: 20px; height: 20px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.nav-label-text {
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
}

.nav-badge {
    margin-left: auto;
    background: var(--primary);
    color: white;
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 20px;
    flex-shrink: 0;
}

/* ── Loading ── */
.nav-item.loading {
    background: var(--primary-light);
    color: var(--primary);
    border-color: rgba(22, 163, 74, 0.2);
    pointer-events: none;
}

.nav-spinner {
    display: inline-block;
    width: 16px; height: 16px;
    border: 2px solid rgba(22, 163, 74, 0.25);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: nav-spin 0.6s linear infinite;
    flex-shrink: 0;
}

@keyframes nav-spin {
    to { transform: rotate(360deg); }
}

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.16s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-fade-enter-active { transition: all 0.22s ease; }
.slide-fade-leave-active { transition: all 0.13s ease; }
.slide-fade-enter-from { opacity: 0; transform: translateX(-10px); }
.slide-fade-leave-to { opacity: 0; }
</style>
