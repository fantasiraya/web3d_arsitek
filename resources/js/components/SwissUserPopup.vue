<template>
    <Transition name="popup">
        <div v-if="visible" class="user-popup" @click.stop>

            <!-- Header -->
            <div class="popup-header">
                <div class="popup-avatar">{{ getInitial(user?.name) }}</div>
                <div class="popup-user-info">
                    <span class="popup-name">{{ user?.name }}</span>
                    <span class="popup-email">{{ user?.email }}</span>
                </div>
                <span class="popup-status-pill">● Online</span>
            </div>

            <div class="popup-divider" />

            <!-- Actions -->
            <button
                v-for="item in popupItems"
                :key="item.id"
                class="popup-item"
                @click="$emit('action', item.id)"
            >
                <span class="popup-icon" v-html="item.icon" />
                <div class="popup-item-text">
                    <span class="popup-item-label">{{ item.label }}</span>
                    <span v-if="item.desc" class="popup-item-desc">{{ item.desc }}</span>
                </div>
                <span v-if="item.badge" class="popup-item-badge">{{ item.badge }}</span>
            </button>

            <div class="popup-divider" />

            <!-- Logout -->
            <button class="popup-item popup-logout" @click="$emit('action', 'logout')">
                <span class="popup-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </span>
                <span class="popup-item-label">Keluar dari Akun</span>
            </button>

        </div>
    </Transition>
</template>

<script setup lang="ts">
/* ─────────────────────────────
   PROPS & EMITS
───────────────────────────── */
defineProps<{
    visible: boolean;
    user: { name: string; email: string } | null;
}>();

defineEmits<{
    (e: 'action', id: string): void;
}>();

/* ─────────────────────────────
   HELPER
───────────────────────────── */
function getInitial(name?: string): string {
    if (!name) { return '?'; }
    return name.trim()[0].toUpperCase();
}

/* ─────────────────────────────
   POPUP ITEMS
   Tambah / kurangi item di sini
───────────────────────────── */
const popupItems = [
    {
        id: 'profile',
        label: 'Profil Saya',
        desc: 'Edit data & foto profil',
        badge: null,
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
    },
    {
        id: 'settings',
        label: 'Pengaturan Akun',
        desc: 'Keamanan & preferensi',
        badge: null,
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>`,
    },
];
</script>

<style scoped>
.user-popup {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 10px;
    right: 10px;
    background: var(--popup-bg);
    border: 1px solid var(--border-2);
    border-radius: var(--radius);
    box-shadow: var(--popup-shadow);
    z-index: 9999;
    overflow: hidden;
    min-width: 220px;
}

/* Collapsed sidebar: popup muncul di kanan */
:global(.sidebar.collapsed) .user-popup {
    left: calc(var(--sidebar-collapsed-w) + 10px);
    right: auto;
    width: 248px;
    bottom: 10px;
}

.popup-header {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 14px;
    background: var(--surface-3);
}

.popup-avatar {
    width: 42px; height: 42px;
    background: linear-gradient(135deg, var(--primary), #059669);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 800;
    color: white; flex-shrink: 0;
    box-shadow: 0 4px 12px var(--primary-glow);
}

.popup-user-info { display: flex; flex-direction: column; flex: 1; overflow: hidden; }
.popup-name  { font-size: 0.875rem; font-weight: 700; color: var(--text); }
.popup-email { font-size: 0.69rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.popup-status-pill {
    font-size: 0.62rem; font-weight: 700;
    color: var(--success);
    background: rgba(16, 185, 129, 0.12);
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
    flex-shrink: 0;
}

.popup-divider { height: 1px; background: var(--border); margin: 3px 0; }

.popup-item {
    width: 100%;
    display: flex; align-items: center; gap: 11px;
    padding: 11px 14px;
    background: transparent; border: none;
    color: var(--text-dim);
    cursor: pointer; transition: all 0.15s;
    text-align: left;
}
.popup-item:hover { background: var(--surface-3); color: var(--text); }

.popup-logout { color: var(--danger); }
.popup-logout:hover { background: rgba(239, 68, 68, 0.08); }

.popup-icon {
    width: 18px; height: 18px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; opacity: 0.85;
}
.popup-icon :deep(svg) { width: 16px; height: 16px; }

.popup-item-text { display: flex; flex-direction: column; flex: 1; }
.popup-item-label { font-size: 0.84rem; font-weight: 600; }
.popup-item-desc  { font-size: 0.69rem; color: var(--text-muted); margin-top: 1px; }

.popup-item-badge {
    font-size: 0.62rem; font-weight: 700;
    background: var(--danger); color: white;
    padding: 2px 7px; border-radius: 20px;
}

/* ── Transitions ── */
.popup-enter-active { transition: all 0.24s cubic-bezier(0.34, 1.56, 0.64, 1); }
.popup-leave-active { transition: all 0.15s ease; }
.popup-enter-from   { opacity: 0; transform: translateY(10px) scale(0.95); }
.popup-leave-to     { opacity: 0; transform: translateY(6px) scale(0.97); }
</style>
