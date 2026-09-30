<script setup lang="ts">
/**
 * ChatPanel — Realtime chat antara Arsitek & Klien per project
 *
 * Cara kerja:
 * 1. Mount → fetch histori 50 pesan terakhir
 * 2. Subscribe private channel via Laravel Echo (Reverb)
 * 3. Listen event 'message.sent' → append ke list secara realtime
 * 4. Kirim pesan → POST ke /projects/{id}/chat
 * 5. Unmount → leave channel
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Send, X, MessageCircle, Loader2 } from '@lucide/vue';
import { echo } from '@/lib/echo';

// ─── Props ───────────────────────────────────────────────
const props = defineProps<{
    projectId: string;
    visible: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

// ─── Types ───────────────────────────────────────────────
interface ChatMsg {
    id: string;
    sender_id: string;
    message: string;
    created_at: string | null;
    read_at: string | null;
    sender: { id: string; name: string };
}

// ─── State ───────────────────────────────────────────────
const page = usePage();
const currentUserId = computed(() => (page.props.auth as any)?.user?.id as string);
const currentUserName = computed(() => (page.props.auth as any)?.user?.name as string);

const messages = ref<ChatMsg[]>([]);
const newMessage = ref('');
const isLoading = ref(false);
const isSending = ref(false);
const sendError = ref('');
const messagesEndRef = ref<HTMLDivElement | null>(null);
const inputRef = ref<HTMLTextAreaElement | null>(null);
const unreadCount = ref(0);
const isConnected = ref(false);

// ─── Scroll to bottom ────────────────────────────────────
async function scrollToBottom() {
    await nextTick();
    messagesEndRef.value?.scrollIntoView({ behavior: 'smooth' });
}

// ─── Fetch history ───────────────────────────────────────
async function fetchMessages() {
    isLoading.value = true;
    try {
        const res = await fetch(`/projects/${props.projectId}/chat`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            const data = await res.json();
            messages.value = data.data ?? [];
            await scrollToBottom();
        }
    } catch (e) {
        console.error('[ChatPanel] fetch error:', e);
    } finally {
        isLoading.value = false;
    }
}

// ─── Mark read ───────────────────────────────────────────
async function markRead() {
    if (unreadCount.value === 0) return;
    unreadCount.value = 0;
    try {
        await fetch(`/projects/${props.projectId}/chat/read`, {
            method: 'PATCH',
            headers: {
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch {}
}

// ─── Subscribe Reverb channel ────────────────────────────
let channel: ReturnType<typeof echo.private> | null = null;

function subscribeChannel() {
    channel = echo.private(`project.${props.projectId}.chat`);

    channel
        .subscribed(() => {
            isConnected.value = true;
        })
        .listen('.message.sent', (data: ChatMsg) => {
            // Hindari duplikat jika pesan sudah ada (sent by self via POST)
            if (messages.value.some(m => m.id === data.id)) return;

            messages.value.push(data);

            // Kalau panel tertutup, tambah unread count
            if (!props.visible) {
                unreadCount.value++;
            } else {
                scrollToBottom();
                if (data.sender_id !== currentUserId.value) {
                    markRead();
                }
            }
        })
        .error((err: any) => {
            console.error('[ChatPanel] channel error:', err);
            isConnected.value = false;
        });
}

// ─── Send message ─────────────────────────────────────────
async function sendMessage() {
    const text = newMessage.value.trim();
    if (!text || isSending.value) return;

    isSending.value = true;
    sendError.value = '';

    // Optimistic UI — tambah ke list sebelum respons server
    const tempId = `temp-${Date.now()}`;
    const optimistic: ChatMsg = {
        id: tempId,
        sender_id: currentUserId.value,
        message: text,
        created_at: new Date().toISOString(),
        read_at: null,
        sender: { id: currentUserId.value, name: currentUserName.value },
    };
    messages.value.push(optimistic);
    newMessage.value = '';
    await scrollToBottom();

    try {
        const res = await fetch(`/projects/${props.projectId}/chat`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ message: text }),
        });

        if (res.ok) {
            const data = await res.json();
            // Ganti optimistic message dengan data server yang sesungguhnya
            const idx = messages.value.findIndex(m => m.id === tempId);
            if (idx !== -1) {
                messages.value[idx] = data.data;
            }
        } else {
            // Rollback optimistic jika gagal
            messages.value = messages.value.filter(m => m.id !== tempId);
            sendError.value = 'Gagal mengirim pesan. Coba lagi.';
            newMessage.value = text; // kembalikan teks
        }
    } catch {
        messages.value = messages.value.filter(m => m.id !== tempId);
        sendError.value = 'Koneksi error. Pastikan Reverb server berjalan.';
        newMessage.value = text;
    } finally {
        isSending.value = false;
    }
}

// ─── Keyboard submit (Ctrl+Enter atau Enter) ─────────────
function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// ─── Format time ─────────────────────────────────────────
function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function formatDate(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    const today = new Date();
    if (d.toDateString() === today.toDateString()) return 'Hari ini';
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Kemarin';
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

// Grouping: tampilkan tanggal divider
interface GroupedMessages {
    date: string;
    messages: ChatMsg[];
}
const groupedMessages = computed<GroupedMessages[]>(() => {
    const groups: GroupedMessages[] = [];
    let currentDate = '';

    for (const msg of messages.value) {
        const date = formatDate(msg.created_at);
        if (date !== currentDate) {
            currentDate = date;
            groups.push({ date, messages: [] });
        }
        groups[groups.length - 1].messages.push(msg);
    }
    return groups;
});

// ─── Lifecycle ───────────────────────────────────────────
watch(() => props.visible, async (visible) => {
    if (visible) {
        unreadCount.value = 0;
        await scrollToBottom();
        await markRead();
        nextTick(() => inputRef.value?.focus());
    }
});

onMounted(() => {
    fetchMessages();
    subscribeChannel();
});

onBeforeUnmount(() => {
    channel?.stopListening('.message.sent');
    echo.leave(`project.${props.projectId}.chat`);
});

// Expose unread count ke parent
defineExpose({ unreadCount });
</script>

<template>
    <!-- Panel overlay -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-x-8 scale-95"
        enter-to-class="opacity-100 translate-x-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-x-0 scale-100"
        leave-to-class="opacity-0 translate-x-8 scale-95"
    >
        <div
            v-if="visible"
            class="absolute top-4 right-4 bottom-20 z-30 w-80 max-w-[90vw] flex flex-col rounded-3xl border border-white/15 bg-black/90 shadow-2xl backdrop-blur-2xl overflow-hidden"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-3 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <MessageCircle class="h-4 w-4 text-indigo-400" />
                    <span class="text-xs font-bold uppercase tracking-wider text-white">Chat Proyek</span>
                    <!-- Connection indicator -->
                    <span
                        class="flex h-2 w-2 rounded-full"
                        :class="isConnected ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse'"
                        :title="isConnected ? 'Terhubung' : 'Menghubungkan...'"
                    ></span>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="text-neutral-400 hover:text-white transition-colors"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Messages area -->
            <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1 min-h-0">
                <!-- Loading skeleton -->
                <div v-if="isLoading" class="flex flex-col items-center justify-center h-full gap-2 text-neutral-500 text-xs">
                    <Loader2 class="h-5 w-5 animate-spin text-indigo-400" />
                    <span>Memuat pesan...</span>
                </div>

                <!-- Empty state -->
                <div v-else-if="messages.length === 0" class="flex flex-col items-center justify-center h-full gap-2 text-center px-4">
                    <MessageCircle class="h-8 w-8 text-neutral-600" />
                    <p class="text-xs text-neutral-500 leading-relaxed">
                        Belum ada pesan. Mulai diskusi dengan mengetik di bawah.
                    </p>
                </div>

                <!-- Grouped messages -->
                <template v-else>
                    <div v-for="group in groupedMessages" :key="group.date">
                        <!-- Date divider -->
                        <div class="flex items-center gap-2 my-3">
                            <div class="flex-1 h-px bg-white/10"></div>
                            <span class="text-[10px] font-mono text-neutral-500 px-2">{{ group.date }}</span>
                            <div class="flex-1 h-px bg-white/10"></div>
                        </div>

                        <!-- Messages in group -->
                        <div
                            v-for="msg in group.messages"
                            :key="msg.id"
                            class="flex flex-col mb-2"
                            :class="msg.sender_id === currentUserId ? 'items-end' : 'items-start'"
                        >
                            <!-- Sender name (only for incoming) -->
                            <span
                                v-if="msg.sender_id !== currentUserId"
                                class="text-[10px] font-medium text-neutral-400 mb-0.5 ml-1"
                            >
                                {{ msg.sender.name }}
                            </span>

                            <!-- Bubble -->
                            <div
                                class="max-w-[85%] rounded-2xl px-3 py-2 text-xs leading-relaxed break-words"
                                :class="[
                                    msg.sender_id === currentUserId
                                        ? 'bg-indigo-600 text-white rounded-tr-sm'
                                        : 'bg-white/10 text-neutral-200 rounded-tl-sm',
                                    msg.id.startsWith('temp-') ? 'opacity-70' : 'opacity-100',
                                ]"
                            >
                                {{ msg.message }}
                            </div>

                            <!-- Time + read receipt -->
                            <div class="flex items-center gap-1 mt-0.5 mx-1">
                                <span class="text-[9px] font-mono text-neutral-600">
                                    {{ formatTime(msg.created_at) }}
                                </span>
                                <!-- Read receipt (hanya untuk pesan sendiri) -->
                                <span
                                    v-if="msg.sender_id === currentUserId"
                                    class="text-[9px]"
                                    :class="msg.read_at ? 'text-indigo-400' : 'text-neutral-600'"
                                    :title="msg.read_at ? 'Dibaca' : 'Terkirim'"
                                >
                                    {{ msg.read_at ? '✓✓' : '✓' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Scroll anchor -->
                <div ref="messagesEndRef"></div>
            </div>

            <!-- Error -->
            <div v-if="sendError" class="px-3 pb-1">
                <p class="text-[10px] text-rose-400 bg-rose-500/10 border border-rose-500/20 rounded-lg px-2 py-1">
                    {{ sendError }}
                </p>
            </div>

            <!-- Input area -->
            <div class="border-t border-white/10 px-3 py-2.5 flex-shrink-0">
                <div class="flex items-end gap-2">
                    <textarea
                        ref="inputRef"
                        v-model="newMessage"
                        placeholder="Tulis pesan..."
                        rows="1"
                        class="flex-1 resize-none rounded-xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/30 transition-all max-h-24 overflow-y-auto"
                        style="field-sizing: content;"
                        @keydown="onKeydown"
                    ></textarea>
                    <button
                        type="button"
                        @click="sendMessage"
                        :disabled="isSending || !newMessage.trim()"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white transition-all hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed"
                        title="Kirim (Enter)"
                    >
                        <Loader2 v-if="isSending" class="h-3.5 w-3.5 animate-spin" />
                        <Send v-else class="h-3.5 w-3.5" />
                    </button>
                </div>
                <p class="mt-1 text-[9px] text-neutral-600">Enter untuk kirim · Shift+Enter untuk baris baru</p>
            </div>
        </div>
    </Transition>
</template>
