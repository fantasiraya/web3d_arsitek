<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    X,
    MessageSquare,
    CheckCircle2,
    Clock,
    GripVertical,
    ArrowRight,
    ArrowLeft,
    Eye,
    Trash2,
    PinOff,
    Edit3,
    Sparkles,
} from '@lucide/vue';

interface CommentUser {
    id?: string;
    name: string;
    avatar?: string;
}

interface CommentItem {
    id: string;
    project_id?: string;
    user_id?: string;
    content: string;
    position_x: number;
    position_y: number;
    position_z: number;
    status?: string;
    user?: CommentUser;
    created_at?: string;
}

const props = defineProps<{
    isOpen: boolean;
    comments: CommentItem[];
    canEdit: (comment: CommentItem) => boolean;
    isUpdatingStatus?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updateStatus', commentId: string, newStatus: 'open' | 'resolved'): void;
    (e: 'focusComment', comment: CommentItem): void;
    (e: 'editComment', comment: CommentItem): void;
    (e: 'deleteComment', comment: CommentItem): void;
}>();

// Filtered lists for the two main statuses
const openComments = computed(() =>
    props.comments.filter((c) => c.status !== 'resolved')
);

const resolvedComments = computed(() =>
    props.comments.filter((c) => c.status === 'resolved')
);

// Drag & Drop State
const draggedComment = ref<CommentItem | null>(null);
const dragOverColumn = ref<'open' | 'resolved' | null>(null);

function handleDragStart(comment: CommentItem, event: DragEvent) {
    draggedComment.value = comment;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', comment.id);
    }
}

function handleDragEnd() {
    draggedComment.value = null;
    dragOverColumn.value = null;
}

function handleDragOver(column: 'open' | 'resolved', event: DragEvent) {
    event.preventDefault();
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move';
    }
    dragOverColumn.value = column;
}

function handleDragLeave(column: 'open' | 'resolved', event: DragEvent) {
    // Only clear if leaving to an outside target
    const currentTarget = event.currentTarget as HTMLElement | null;
    const relatedTarget = event.relatedTarget as HTMLElement | null;
    if (currentTarget && relatedTarget && currentTarget.contains(relatedTarget)) {
        return;
    }
    if (dragOverColumn.value === column) {
        dragOverColumn.value = null;
    }
}

function handleDrop(targetColumn: 'open' | 'resolved', event: DragEvent) {
    event.preventDefault();
    dragOverColumn.value = null;
    const comment = draggedComment.value;
    if (!comment) return;

    const currentStatus = comment.status === 'resolved' ? 'resolved' : 'open';
    if (currentStatus !== targetColumn) {
        emit('updateStatus', comment.id, targetColumn);
    }

    draggedComment.value = null;
}

function moveStatus(comment: CommentItem, targetColumn: 'open' | 'resolved') {
    emit('updateStatus', comment.id, targetColumn);
}

function formatDate(dateStr?: string): string {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
    >
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 lg:p-8 bg-black/85 backdrop-blur-md"
            @click.self="emit('close')"
        >
            <div
                class="relative flex flex-col w-full max-w-6xl h-[90vh] sm:h-[88vh] rounded-3xl border border-white/20 bg-[#0a0d14]/98 text-white shadow-2xl overflow-hidden"
            >
                <!-- ── Header ── -->
                <div class="flex items-center justify-between border-b border-white/10 px-6 py-5 shrink-0 bg-white/[0.03]">
                    <div class="flex items-center gap-3.5">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-500/20 border border-rose-500/40 text-rose-400 shadow-inner">
                            <MessageSquare class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h3 class="text-base sm:text-lg font-bold tracking-tight text-white">Daftar Komentar & Status Revisi</h3>
                                <span class="rounded-full bg-white/10 border border-white/15 px-2.5 py-0.5 text-xs font-mono font-medium text-neutral-200">
                                    {{ comments.length }} Total Pin
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-neutral-300 mt-0.5 hidden sm:block">
                                Geser <span class="text-white font-medium">(drag & drop)</span> kartu komentar antara kolom <strong>Belum Selesai</strong> dan <strong>Sudah Selesai</strong> untuk mengubah status.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-full p-2.5 text-neutral-400 hover:bg-white/10 hover:text-white transition"
                        title="Tutup Modal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- ── Board Columns ── -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 p-4 sm:p-6 overflow-y-auto flex-1 min-h-0 bg-black/50">
                    <!-- ── Kolom 1: Belum Selesai (Open) ── -->
                    <div
                        class="flex flex-col rounded-2xl border transition-all duration-200 min-h-[350px] shadow-sm overflow-hidden"
                        :class="[
                            dragOverColumn === 'open'
                                ? 'border-amber-400 bg-amber-500/10 ring-2 ring-amber-400/40'
                                : 'border-white/10 bg-white/[0.02]',
                        ]"
                        @dragover="handleDragOver('open', $event)"
                        @dragleave="handleDragLeave('open', $event)"
                        @drop="handleDrop('open', $event)"
                    >
                        <!-- Column Header -->
                        <div class="flex items-center justify-between border-b border-white/10 px-5 py-3.5 bg-white/[0.03]">
                            <div class="flex items-center gap-2.5">
                                <Clock class="h-5 w-5 text-amber-400" />
                                <span class="text-sm sm:text-base font-bold uppercase tracking-wider text-amber-300">Belum Selesai</span>
                            </div>
                            <span class="rounded-full bg-amber-500/20 px-3 py-1 text-xs font-bold text-amber-300 border border-amber-500/30">
                                {{ openComments.length }}
                            </span>
                        </div>

                        <!-- Column Content / Cards List -->
                        <div class="p-3.5 sm:p-4 space-y-3.5 overflow-y-auto flex-1">
                            <div
                                v-if="openComments.length === 0"
                                class="flex flex-col items-center justify-center py-16 text-center text-sm text-neutral-400 space-y-2"
                            >
                                <Sparkles class="h-8 w-8 text-emerald-400/80 mb-1 animate-pulse" />
                                <p class="font-semibold text-neutral-200 text-base">Tidak ada komentar belum selesai</p>
                                <p class="text-xs text-neutral-400">Semua catatan revisi telah diselesaikan!</p>
                            </div>

                            <div
                                v-for="(comment, idx) in openComments"
                                :key="comment.id"
                                draggable="true"
                                @dragstart="handleDragStart(comment, $event)"
                                @dragend="handleDragEnd"
                                class="group relative rounded-2xl border border-white/15 bg-black/70 p-4 transition-all duration-200 hover:border-amber-500/50 hover:bg-black/95 shadow-md cursor-grab active:cursor-grabbing hover:shadow-lg"
                                :class="{ 'opacity-50 ring-2 ring-amber-400': draggedComment?.id === comment.id }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <GripVertical class="h-5 w-5 text-neutral-500 shrink-0 group-hover:text-amber-400 transition" />
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-400 border border-amber-500/40">
                                            #{{ comments.indexOf(comment) + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-white truncate">
                                                {{ comment.user?.name ?? 'Reviewer' }}
                                            </p>
                                            <p v-if="comment.created_at" class="text-xs text-neutral-400">
                                                {{ formatDate(comment.created_at) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Quick Action to move to Resolved -->
                                    <button
                                        type="button"
                                        @click="moveStatus(comment, 'resolved')"
                                        class="flex items-center gap-1.5 rounded-full bg-emerald-500/15 hover:bg-emerald-500/30 border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 transition shrink-0 hover:scale-105 active:scale-95 shadow-xs"
                                        title="Pindahkan ke Sudah Selesai"
                                    >
                                        <span>Selesai</span>
                                        <ArrowRight class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <p class="mt-3 text-sm sm:text-[15px] font-normal text-neutral-100 leading-relaxed break-words whitespace-pre-wrap">
                                    {{ comment.content }}
                                </p>

                                <!-- Footer Card -->
                                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-2.5 text-xs text-neutral-400">
                                    <span class="font-mono text-[11px] text-neutral-400 bg-white/5 px-2 py-0.5 rounded-md">
                                        pos: ({{ comment.position_x.toFixed(1) }}, {{ comment.position_y.toFixed(1) }}, {{ comment.position_z.toFixed(1) }})
                                    </span>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="emit('focusComment', comment)"
                                            class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-indigo-300 bg-indigo-500/10 hover:bg-indigo-500/25 border border-indigo-500/20 transition"
                                            title="Fokus pandangan 3D ke pin ini"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            <span>Fokus 3D</span>
                                        </button>

                                        <button
                                            v-if="canEdit(comment)"
                                            type="button"
                                            @click="emit('editComment', comment)"
                                            class="rounded-lg p-1.5 text-neutral-400 hover:text-white hover:bg-white/10 transition"
                                            title="Edit Catatan"
                                        >
                                            <Edit3 class="h-4 w-4" />
                                        </button>

                                        <button
                                            v-if="canEdit(comment)"
                                            type="button"
                                            @click="emit('deleteComment', comment)"
                                            class="rounded-lg p-1.5 text-neutral-400 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                            title="Hapus Pin"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── Kolom 2: Sudah Selesai (Resolved) ── -->
                    <div
                        class="flex flex-col rounded-2xl border transition-all duration-200 min-h-[350px] shadow-sm overflow-hidden"
                        :class="[
                            dragOverColumn === 'resolved'
                                ? 'border-emerald-400 bg-emerald-500/10 ring-2 ring-emerald-400/40'
                                : 'border-white/10 bg-white/[0.02]',
                        ]"
                        @dragover="handleDragOver('resolved', $event)"
                        @dragleave="handleDragLeave('resolved', $event)"
                        @drop="handleDrop('resolved', $event)"
                    >
                        <!-- Column Header -->
                        <div class="flex items-center justify-between border-b border-white/10 px-5 py-3.5 bg-white/[0.03]">
                            <div class="flex items-center gap-2.5">
                                <CheckCircle2 class="h-5 w-5 text-emerald-400" />
                                <span class="text-sm sm:text-base font-bold uppercase tracking-wider text-emerald-300">Sudah Selesai</span>
                            </div>
                            <span class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-300 border border-emerald-500/30">
                                {{ resolvedComments.length }}
                            </span>
                        </div>

                        <!-- Column Content / Cards List -->
                        <div class="p-3.5 sm:p-4 space-y-3.5 overflow-y-auto flex-1">
                            <div
                                v-if="resolvedComments.length === 0"
                                class="flex flex-col items-center justify-center py-16 text-center text-sm text-neutral-400 space-y-2"
                            >
                                <PinOff class="h-8 w-8 text-neutral-500 mb-1" />
                                <p class="font-semibold text-neutral-200 text-base">Belum ada komentar selesai</p>
                                <p class="text-xs text-neutral-400">Tarik kartu komentar dari kolom sebelah ke sini saat revisi sudah beres.</p>
                            </div>

                            <div
                                v-for="(comment, idx) in resolvedComments"
                                :key="comment.id"
                                draggable="true"
                                @dragstart="handleDragStart(comment, $event)"
                                @dragend="handleDragEnd"
                                class="group relative rounded-2xl border border-white/15 bg-black/70 p-4 transition-all duration-200 hover:border-emerald-500/50 hover:bg-black/95 shadow-md cursor-grab active:cursor-grabbing hover:shadow-lg opacity-90"
                                :class="{ 'opacity-50 ring-2 ring-emerald-400': draggedComment?.id === comment.id }"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <GripVertical class="h-5 w-5 text-neutral-500 shrink-0 group-hover:text-emerald-400 transition" />
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-xs font-bold text-emerald-400 border border-emerald-500/40">
                                            #{{ comments.indexOf(comment) + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-white truncate">
                                                {{ comment.user?.name ?? 'Reviewer' }}
                                            </p>
                                            <p v-if="comment.created_at" class="text-xs text-neutral-400">
                                                {{ formatDate(comment.created_at) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Quick Action to move back to Open -->
                                    <button
                                        type="button"
                                        @click="moveStatus(comment, 'open')"
                                        class="flex items-center gap-1.5 rounded-full bg-amber-500/15 hover:bg-amber-500/30 border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-300 transition shrink-0 hover:scale-105 active:scale-95 shadow-xs"
                                        title="Buka kembali (kembalikan ke Belum Selesai)"
                                    >
                                        <ArrowLeft class="h-3.5 w-3.5" />
                                        <span>Buka Kembali</span>
                                    </button>
                                </div>

                                <p class="mt-3 text-sm sm:text-[15px] font-normal text-neutral-300 line-through leading-relaxed break-words whitespace-pre-wrap">
                                    {{ comment.content }}
                                </p>

                                <!-- Footer Card -->
                                <div class="mt-4 flex items-center justify-between border-t border-white/10 pt-2.5 text-xs text-neutral-400">
                                    <span class="font-mono text-[11px] text-neutral-400 bg-white/5 px-2 py-0.5 rounded-md">
                                        pos: ({{ comment.position_x.toFixed(1) }}, {{ comment.position_y.toFixed(1) }}, {{ comment.position_z.toFixed(1) }})
                                    </span>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="emit('focusComment', comment)"
                                            class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-indigo-300 bg-indigo-500/10 hover:bg-indigo-500/25 border border-indigo-500/20 transition"
                                            title="Fokus pandangan 3D ke pin ini"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                            <span>Fokus 3D</span>
                                        </button>

                                        <button
                                            v-if="canEdit(comment)"
                                            type="button"
                                            @click="emit('editComment', comment)"
                                            class="rounded-lg p-1.5 text-neutral-400 hover:text-white hover:bg-white/10 transition"
                                            title="Edit Catatan"
                                        >
                                            <Edit3 class="h-4 w-4" />
                                        </button>

                                        <button
                                            v-if="canEdit(comment)"
                                            type="button"
                                            @click="emit('deleteComment', comment)"
                                            class="rounded-lg p-1.5 text-neutral-400 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                            title="Hapus Pin"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Footer ── -->
                <div class="flex items-center justify-between border-t border-white/10 px-6 py-4 text-xs sm:text-sm bg-white/[0.03]">
                    <div class="flex items-center gap-2 text-neutral-300 font-medium">
                        <span>💡 Tip: Anda bisa drag & drop kartu atau gunakan tombol aksi cepat</span>
                    </div>
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-xl bg-white/10 hover:bg-white/20 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white transition shadow-sm"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
