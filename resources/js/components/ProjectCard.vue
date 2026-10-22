<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Box,
    ClipboardList,
    Pencil,
    Trash2,
    UserCheck,
    UserPlus,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Project3DPreview from '@/components/Project3DPreview.vue';
import { enqueueAutoCapture, releaseAutoCapture, removeFromCaptureQueue } from '@/lib/snapshotQueue';

export interface ProjectClientItem {
    id: string;
    email: string;
    status: 'pending' | 'accepted' | 'revoked';
    invited_at: string;
    accepted_at: string | null;
}

export interface ProjectItem {
    id: string;
    title: string;
    slug?: string;
    description: string | null;
    file_path?: string;
    thumbnail_url?: string | null;
    file_size_bytes: number;
    is_draco_compressed: boolean;
    max_revisions_allowed: number;
    current_revision_count: number;
    has_reached_revision_limit: boolean;
    created_at: string;
    invited_clients: ProjectClientItem[];
}

const props = defineProps<{
    project: ProjectItem;
}>();

const emit = defineEmits<{
    (e: 'edit', project: ProjectItem): void;
    (e: 'delete', project: ProjectItem): void;
    (e: 'manageClients', project: ProjectItem): void;
    (e: 'preview', project: ProjectItem): void;
}>();

const is3DActive = ref(false);
const isAutoCapturing = ref(false);
let hoverTimer: ReturnType<typeof setTimeout> | null = null;
let captureTimeout: ReturnType<typeof setTimeout> | null = null;
const localThumbnail = ref<string | null>(null);

const isSkp = computed(() => !!props.project.file_path?.toLowerCase().endsWith('.skp'));

const isPlaceholderThumb = computed(() => {
    const url = props.project.thumbnail_url;
    return !url || url.includes('skp_thumb_');
});

function getCacheKey(projectId: string, filePath?: string): string {
    if (!filePath) return `project_thumb_${projectId}`;
    const cleanPath = filePath.replace(/[^a-zA-Z0-9_-]/g, '_');
    return `project_thumb_${projectId}_${cleanPath}`;
}

const cacheKey = computed(() => getCacheKey(props.project.id, props.project.file_path));

function loadCachedThumbnail(): boolean {
    if (!props.project.file_path) {
        localThumbnail.value = null;
        return false;
    }
    try {
        const cached = localStorage.getItem(cacheKey.value);
        if (cached) {
            localThumbnail.value = cached;
            return true;
        }
    } catch (e) {}
    localThumbnail.value = null;
    return false;
}

function finishAutoCapture() {
    if (captureTimeout) {
        clearTimeout(captureTimeout);
        captureTimeout = null;
    }
    isAutoCapturing.value = false;
    releaseAutoCapture(props.project.id);
}

function checkAndTriggerAutoSnapshot() {
    if (!props.project.file_path) return;

    // 1. Cek apakah ada snapshot tersimpan di browser untuk file_path saat ini
    const hasCached = loadCachedThumbnail();
    if (hasCached) return;

    // 2. Jika di database sudah ada real snapshot (bukan placeholder skp_thumb), tidak perlu auto capture
    if (!isPlaceholderThumb.value) return;

    // 3. Masukkan ke antrean auto-capture latar belakang
    enqueueAutoCapture(props.project.id, () => {
        isAutoCapturing.value = true;

        if (captureTimeout) clearTimeout(captureTimeout);
        captureTimeout = setTimeout(() => {
            finishAutoCapture();
        }, 8000);
    });
}

onMounted(() => {
    checkAndTriggerAutoSnapshot();
});

onBeforeUnmount(() => {
    if (hoverTimer) {
        clearTimeout(hoverTimer);
        hoverTimer = null;
    }
    if (captureTimeout) {
        clearTimeout(captureTimeout);
        captureTimeout = null;
    }
    removeFromCaptureQueue(props.project.id);
});

// Pantau perubahan file_path (misal saat edit file proyek atau re-upload)
watch(
    () => props.project.file_path,
    (newPath, oldPath) => {
        if (newPath !== oldPath) {
            localThumbnail.value = null;
            checkAndTriggerAutoSnapshot();
        }
    }
);

// Pantau perubahan thumbnail_url di props
watch(
    () => props.project.thumbnail_url,
    () => {
        checkAndTriggerAutoSnapshot();
    }
);

async function uploadSnapshotToServer(dataUrl: string) {
    try {
        await fetch(`/projects/${props.project.id}/thumbnail`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ image: dataUrl }),
        });
    } catch (e) {
        // Silent fail, client already has localThumbnail
    }
}

function onSnapshot(dataUrl: string) {
    if (!dataUrl) return;
    localThumbnail.value = dataUrl;
    try {
        localStorage.setItem(cacheKey.value, dataUrl);
    } catch (e) {}

    finishAutoCapture();

    // Jika thumbnail di database belum ada snapshot riil, simpan ke server
    if (isPlaceholderThumb.value) {
        uploadSnapshotToServer(dataUrl);
    }
}

function onPreviewError() {
    finishAutoCapture();
}

const effectiveThumbnail = computed(() => localThumbnail.value || props.project.thumbnail_url);

function onBannerMouseEnter() {
    if (!props.project.file_path) return;
    hoverTimer = setTimeout(() => {
        is3DActive.value = true;
    }, 220);
}

function onBannerMouseLeave() {
    if (hoverTimer) {
        clearTimeout(hoverTimer);
        hoverTimer = null;
    }
    is3DActive.value = false;
}

const iconButtonClass =
    'border-slate-200 bg-white text-xs text-slate-500 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 dark:border-white/15 dark:bg-white/5 dark:text-neutral-300 dark:hover:border-white/30 dark:hover:bg-white/10 dark:hover:text-white';

function formatBytes(bytes: number): string {
    if (!bytes) return '—';
    const mb = bytes / (1024 * 1024);
    return mb >= 1 ? `${mb.toFixed(1)} MB` : `${(bytes / 1024).toFixed(0)} KB`;
}
</script>

<template>
    <div
        class="flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 dark:border-white/15 bg-white dark:bg-white/[0.04] shadow-sm dark:shadow-lg transition-all duration-300 hover:shadow-md dark:hover:shadow-[0_0_45px_rgba(99,102,241,0.25)] hover:border-slate-300 dark:hover:border-white/30 hover:scale-[1.02] group"
    >
        <!-- Card Header & 3D Thumbnail Banner -->
        <div>
            <div
                class="relative flex h-40 w-full items-center justify-center bg-gradient-to-br from-slate-800 to-slate-950 text-white overflow-hidden transition-colors"
                @mouseenter="onBannerMouseEnter"
                @mouseleave="onBannerMouseLeave"
            >
                <!-- Initial Static Snapshot Image (Shown when NOT hovering or behind 3D during loading) -->
                <img
                    v-if="effectiveThumbnail"
                    :src="effectiveThumbnail"
                    :alt="project.title"
                    class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                />

                <!-- Subtle gradient overlay on snapshot image for readability of badges & actions -->
                <div
                    v-if="effectiveThumbnail"
                    class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-black/40 pointer-events-none"
                ></div>

                <!-- 3D WebGL Preview (mounted when hovering OR during background auto-capture) -->
                <Project3DPreview
                    v-if="(is3DActive || isAutoCapturing) && project.file_path"
                    :key="project.file_path"
                    :file-path="project.file_path"
                    :is-draco="project.is_draco_compressed"
                    class="absolute inset-0 z-10 w-full h-full transition-opacity duration-300"
                    :class="[is3DActive ? 'opacity-100' : 'opacity-0 pointer-events-none']"
                    @snapshot="onSnapshot"
                    @error="onPreviewError"
                />

                <!-- Default Static Icon when no snapshot and not active -->
                <Box
                    v-if="!effectiveThumbnail && !is3DActive"
                    class="h-16 w-16 opacity-20 transition-transform duration-500 group-hover:scale-110 group-hover:opacity-30"
                />

                <!-- Hover hint when 3D available -->
                <div
                    v-if="!is3DActive && project.file_path"
                    class="absolute bottom-2 left-3 z-20 text-[10px] text-white/90 bg-black/60 backdrop-blur-md px-2 py-0.5 rounded-full border border-white/20 opacity-80 group-hover:opacity-100 transition-opacity pointer-events-none flex items-center gap-1.5"
                >
                    <span v-if="isSkp" class="inline-block w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    <Box v-else class="h-3 w-3 text-indigo-400" />
                    <span>Arahkan mouse untuk 3D</span>
                </div>

                <!-- Badges -->
                <div class="absolute top-3 left-3 z-20 flex flex-wrap gap-1.5 pointer-events-none">
                    <Badge v-if="isSkp" class="bg-red-600/90 text-white backdrop-blur-xl text-[11px] border border-red-400/40 font-medium">
                        SketchUp .SKP
                    </Badge>
                    <Badge v-else class="bg-black/60 text-white backdrop-blur-xl text-[11px] border border-white/20">
                        3D GLB
                    </Badge>
                    <Badge
                        v-if="project.is_draco_compressed"
                        class="bg-emerald-500/80 text-white text-[10px] border border-emerald-400/50"
                    >
                        Draco
                    </Badge>
                </div>

                <!-- Actions Top Right -->
                <div class="absolute top-3 right-3 z-20 flex items-center gap-1.5">
                    <button
                        type="button"
                        @click.stop="emit('edit', project)"
                        title="Edit Data Proyek"
                        class="rounded-full bg-black/50 border border-white/20 backdrop-blur-xl p-1.5 text-slate-300 hover:bg-indigo-500 hover:text-white hover:border-indigo-400 transition-all duration-300"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        @click.stop="emit('delete', project)"
                        title="Hapus Proyek"
                        class="rounded-full bg-black/50 border border-white/20 backdrop-blur-xl p-1.5 text-slate-300 hover:bg-rose-600 hover:text-white hover:border-rose-400 transition-all duration-300"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>

                <!-- File Size -->
                <div class="absolute bottom-2 right-3 z-20 text-[11px] text-slate-300 font-mono pointer-events-none">
                    {{ formatBytes(project.file_size_bytes) }}
                </div>
            </div>

            <!-- Card Content -->
            <div class="p-5">
                <h3 class="font-bold text-base text-slate-900 dark:text-neutral-100 break-words leading-snug">
                    {{ project.title }}
                </h3>
                <p class="mt-1.5 text-xs text-slate-400 dark:text-neutral-400 line-clamp-2 min-h-[32px]">
                    {{ project.description || 'Tidak ada deskripsi proyek.' }}
                </p>

                <!-- Revision Limit Tracker -->
                <div class="mt-4 rounded-xl bg-slate-50 dark:bg-white/5 border border-slate-200 dark:border-white/10 p-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-500 dark:text-neutral-400">Kuota Revisi Klien:</span>
                        <span class="font-bold text-slate-900 dark:text-neutral-100">
                            {{ project.current_revision_count }} / {{ project.max_revisions_allowed }}
                        </span>
                    </div>
                    <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 dark:bg-white/10 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="[
                                project.has_reached_revision_limit
                                    ? 'bg-gradient-to-r from-rose-500 to-rose-600'
                                    : project.current_revision_count > 0
                                    ? 'bg-gradient-to-r from-amber-500 to-orange-500'
                                    : 'bg-gradient-to-r from-emerald-500 to-green-500',
                            ]"
                            :style="{
                                width: `${Math.min(100, (project.current_revision_count / project.max_revisions_allowed) * 100)}%`,
                            }"
                        ></div>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between text-[11px]">
                        <span
                            v-if="project.has_reached_revision_limit"
                            class="font-semibold text-rose-500 dark:text-rose-400 flex items-center gap-1"
                        >
                            <AlertTriangle class="h-3 w-3" /> Batas revisi habis
                        </span>
                        <span v-else class="text-emerald-600 dark:text-emerald-400">
                            Revisi terbuka
                        </span>
                        <span class="text-slate-400 dark:text-neutral-400">{{ project.created_at }}</span>
                    </div>
                </div>

                <!-- Collaborators pill -->
                <div class="mt-3 flex items-center justify-between text-xs">
                    <span class="text-slate-400 dark:text-neutral-400">Klien Kolaborator:</span>
                    <button
                        type="button"
                        @click="emit('manageClients', project)"
                        class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 hover:underline flex items-center gap-1 transition-colors"
                    >
                        <UserCheck class="h-3.5 w-3.5" />
                        {{ project.invited_clients.length }} Klien
                    </button>
                </div>
            </div>
        </div>

        <!-- Card Footer Actions -->
        <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.02]">
            <!-- Baris 1: aksi utama -->
            <Link :href="`/projects/${project.id}/viewer`" class="block">
                <Button
                    size="sm"
                    class="w-full whitespace-nowrap bg-indigo-500 text-xs font-semibold text-white shadow-[0_0_20px_rgba(99,102,241,0.3)] transition-all duration-300 hover:bg-indigo-600"
                >
                    <Box class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                    Buka 3D Viewer
                </Button>
            </Link>

            <!-- Baris 2: aksi sekunder -->
            <div class="grid grid-cols-[1fr_auto_auto] gap-2">
                <!-- Menuju daftar dokumen RAB project ini -->
                <Link :href="`/projects/${project.id}/rab`" class="block">
                    <Button
                        variant="outline"
                        size="sm"
                        title="Rencana Anggaran Biaya"
                        class="w-full whitespace-nowrap border-emerald-200 bg-emerald-50 text-xs text-emerald-700 hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400 dark:hover:border-emerald-500/50 dark:hover:bg-emerald-500/20"
                    >
                        <ClipboardList class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                        RAB
                    </Button>
                </Link>

                <Button
                    variant="outline"
                    size="sm"
                    title="Edit Data Proyek"
                    :class="iconButtonClass"
                    @click="emit('edit', project)"
                >
                    <Pencil class="h-3.5 w-3.5" />
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    title="Undang / Kelola Klien"
                    :class="iconButtonClass"
                    @click="emit('manageClients', project)"
                >
                    <UserPlus class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
