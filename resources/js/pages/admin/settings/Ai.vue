<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Sparkles, Check, Server, Eye, EyeOff,
    RotateCcw, Activity, ShieldCheck, AlertCircle,
    CheckCircle2, Loader2, ArrowRight, Bot, Cpu, Zap, Key
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { toast } from 'vue-sonner';

defineOptions({ layout: AdminLayout });

interface ProviderDetail {
    name: string;
    description: string;
    has_db_key: boolean;
    has_env_key: boolean;
    masked_key: string;
    model: string;
    is_model_from_db: boolean;
    base_url?: string;
    default_model: string;
    recommended_models: string[];
}

interface Props {
    activeProvider: 'gemini' | 'openai' | 'claude' | 'ollama';
    isProviderFromDb: boolean;
    providers: Record<string, ProviderDetail>;
}

const props = defineProps<Props>();

// Selected tab in UI
const activeTab = ref<'gemini' | 'openai' | 'claude' | 'ollama'>(props.activeProvider);

// Show/Hide password toggles for keys
const showKeys = ref<Record<string, boolean>>({
    gemini: false,
    openai: false,
    claude: false,
});

function toggleShowKey(provider: string) {
    showKeys.value[provider] = !showKeys.value[provider];
}

// Keys marked to be reset/cleared from DB (so system falls back to .env)
const resetKeys = ref<string[]>([]);

function markResetToEnv(keyName: string) {
    if (!resetKeys.value.includes(keyName)) {
        resetKeys.value.push(keyName);
        toast.info(`Kunci '${keyName}' akan dihapus dari Database dan kembali memakai nilai di .env setelah disimpan.`);
    }
}

function cancelReset(keyName: string) {
    resetKeys.value = resetKeys.value.filter(k => k !== keyName);
}

// Inertia Form
const form = useForm({
    vision_ai_provider: props.activeProvider,
    gemini_api_key: '',
    gemini_model: props.providers.gemini?.model || 'gemini-2.5-flash',
    openai_api_key: '',
    openai_model: props.providers.openai?.model || 'gpt-4o-mini',
    openai_base_url: props.providers.openai?.base_url || 'https://api.openai.com/v1',
    claude_api_key: '',
    claude_model: props.providers.claude?.model || 'claude-3-5-sonnet-20241022',
    ollama_model: props.providers.ollama?.model || 'llava',
    ollama_base_url: props.providers.ollama?.base_url || 'http://localhost:11434',
    reset_keys: [] as string[],
});

// Test Connection State
const testingConnection = ref(false);
const testResult = ref<{
    provider: string;
    success: boolean;
    message: string;
} | null>(null);

async function runTestConnection(providerKey: 'gemini' | 'openai' | 'claude' | 'ollama') {
    testingConnection.value = true;
    testResult.value = null;

    let apiKey = '';
    let model = '';
    let baseUrl = '';

    if (providerKey === 'gemini') {
        apiKey = form.gemini_api_key;
        model = form.gemini_model;
    } else if (providerKey === 'openai') {
        apiKey = form.openai_api_key;
        model = form.openai_model;
        baseUrl = form.openai_base_url;
    } else if (providerKey === 'claude') {
        apiKey = form.claude_api_key;
        model = form.claude_model;
    } else if (providerKey === 'ollama') {
        model = form.ollama_model;
        baseUrl = form.ollama_base_url;
    }

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        const res = await fetch('/admin/settings/ai/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                provider: providerKey,
                api_key: apiKey,
                model: model,
                base_url: baseUrl,
            }),
        });

        const data = await res.json().catch(() => ({}));

        if (res.ok && data.success) {
            testResult.value = {
                provider: providerKey,
                success: true,
                message: data.message || 'Koneksi AI berhasil diverifikasi.',
            };
            toast.success(testResult.value.message);
        } else {
            testResult.value = {
                provider: providerKey,
                success: false,
                message: data.message || `Gagal menghubungi penyedia AI (${res.status}).`,
            };
            toast.error(testResult.value.message);
        }
    } catch (err: any) {
        testResult.value = {
            provider: providerKey,
            success: false,
            message: err.message || 'Gagal menghubungi penyedia AI.',
        };
        toast.error(testResult.value.message);
    } finally {
        testingConnection.value = false;
    }
}

function submitSettings() {
    form.reset_keys = resetKeys.value;
    form.patch('/admin/settings/ai', {
        preserveScroll: true,
        onSuccess: () => {
            resetKeys.value = [];
            toast.success('Pengaturan Vision AI berhasil disimpan.');
        },
        onError: (errs) => {
            const firstErr = Object.values(errs)[0];
            toast.error(firstErr ? String(firstErr) : 'Gagal menyimpan pengaturan.');
        },
    });
}
</script>

<template>
    <Head title="Pengaturan AI - Admin Panel" />

    <div class="space-y-8 max-w-5xl pb-16">
        <!-- Top Heading -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-neutral-200 dark:border-neutral-800 pb-6">
            <div>
                <div class="flex items-center gap-2 text-amber-500 font-semibold text-sm mb-1">
                    <Sparkles class="w-4 h-4" />
                    <span>Vision AI Engine</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-50">
                    Pengaturan Model & API Key AI
                </h1>
                <p class="text-sm text-neutral-500 dark:text-neutral-400 mt-1 max-w-2xl">
                    Kelola penyedia AI Vision untuk menganalisa screenshot 3D viewport dan menghasilkan prompt rendering arsitektural.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 text-xs"
                    :disabled="testingConnection"
                    @click="runTestConnection(activeTab)"
                >
                    <Loader2 v-if="testingConnection" class="w-3.5 h-3.5 animate-spin text-neutral-400" />
                    <Activity v-else class="w-3.5 h-3.5 text-blue-500" />
                    <span>Uji Koneksi ({{ providers[activeTab]?.name }})</span>
                </Button>

                <Button
                    type="button"
                    class="gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-sm"
                    :disabled="form.processing"
                    @click="submitSettings"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                    <Check v-else class="w-4 h-4" />
                    <span>Simpan Pengaturan</span>
                </Button>
            </div>
        </div>

        <!-- Strategy Info Alert -->
        <div class="rounded-xl border border-blue-200/60 bg-blue-50/50 p-4 dark:border-blue-900/40 dark:bg-blue-950/20 text-sm flex items-start gap-3.5 text-blue-900 dark:text-blue-200">
            <ShieldCheck class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <div class="font-semibold text-blue-950 dark:text-blue-100">
                    Sistem Penyimpanan Kunci Hibrida (Database &amp; Fallback .env)
                </div>
                <p class="text-xs text-blue-800/80 dark:text-blue-300/80 leading-relaxed">
                    Kunci dan model yang Anda masukkan di halaman ini akan disimpan di <strong>Database</strong> (<code class="bg-blue-100/70 dark:bg-blue-900/60 px-1 py-0.5 rounded text-[11px]">system_settings</code>) dan diprioritaskan. Jika suatu nilai di database kosong, sistem akan otomatis mengambil nilai konfigurasi dari file <strong>.env</strong> sebagai cadangan tanpa memutus layanan rendering.
                </p>
            </div>
        </div>

        <!-- Test Connection Result Notification -->
        <div
            v-if="testResult"
            class="rounded-xl p-4 border text-sm flex items-start gap-3 transition-all"
            :class="testResult.success 
                ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200' 
                : 'border-red-300 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200'"
        >
            <CheckCircle2 v-if="testResult.success" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
            <AlertCircle v-else class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" />
            <div class="flex-1">
                <div class="font-semibold">
                    {{ testResult.success ? 'Uji Koneksi Berhasil' : 'Uji Koneksi Gagal' }}
                </div>
                <div class="text-xs mt-0.5 opacity-90">{{ testResult.message }}</div>
            </div>
            <button
                class="text-xs opacity-60 hover:opacity-100 font-bold ml-2"
                @click="testResult = null"
            >
                ✕
            </button>
        </div>

        <!-- Provider Selection Tiles -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <label class="text-sm font-semibold text-neutral-800 dark:text-neutral-200 flex items-center gap-2">
                    <span>Penyedia AI Aktif (Active Engine)</span>
                    <Badge variant="outline" class="text-[10px] uppercase font-mono tracking-wider">
                        Dipakai Saat Ini: {{ form.vision_ai_provider }}
                    </Badge>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-3.5">
                <!-- Gemini -->
                <div
                    class="relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between"
                    :class="[
                        form.vision_ai_provider === 'gemini'
                            ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/20 ring-2 ring-blue-600/30 shadow-sm'
                            : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:border-neutral-300 dark:hover:border-neutral-700',
                        activeTab === 'gemini' ? 'ring-1 ring-neutral-400 dark:ring-neutral-600' : ''
                    ]"
                    @click="activeTab = 'gemini'"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="p-2 rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                <Sparkles class="w-5 h-5" />
                            </div>
                            <span
                                v-if="form.vision_ai_provider === 'gemini'"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-blue-600 text-white"
                            >
                                <Check class="w-3 h-3" /> Aktif
                            </span>
                        </div>
                        <div>
                            <div class="font-semibold text-sm text-neutral-900 dark:text-neutral-100">Google Gemini</div>
                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">
                                Gemini 2.5 Flash / 2.0 Flash
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                        <span class="text-neutral-400">Status Kunci:</span>
                        <span v-if="providers.gemini?.has_db_key" class="font-medium text-indigo-600 dark:text-indigo-400">Database</span>
                        <span v-else-if="providers.gemini?.has_env_key" class="font-medium text-emerald-600 dark:text-emerald-400">.env File</span>
                        <span v-else class="font-medium text-amber-600 dark:text-amber-400">Belum Ada</span>
                    </div>

                    <button
                        v-if="form.vision_ai_provider !== 'gemini'"
                        type="button"
                        class="mt-3 w-full py-1 text-[11px] font-medium rounded border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                        @click.stop="form.vision_ai_provider = 'gemini'; activeTab = 'gemini'"
                    >
                        Jadikan Aktif
                    </button>
                </div>

                <!-- OpenAI -->
                <div
                    class="relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between"
                    :class="[
                        form.vision_ai_provider === 'openai'
                            ? 'border-emerald-600 bg-emerald-50/40 dark:bg-emerald-950/20 ring-2 ring-emerald-600/30 shadow-sm'
                            : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:border-neutral-300 dark:hover:border-neutral-700',
                        activeTab === 'openai' ? 'ring-1 ring-neutral-400 dark:ring-neutral-600' : ''
                    ]"
                    @click="activeTab = 'openai'"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <Zap class="w-5 h-5" />
                            </div>
                            <span
                                v-if="form.vision_ai_provider === 'openai'"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-600 text-white"
                            >
                                <Check class="w-3 h-3" /> Aktif
                            </span>
                        </div>
                        <div>
                            <div class="font-semibold text-sm text-neutral-900 dark:text-neutral-100">OpenAI (ChatGPT)</div>
                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">
                                GPT-4o Mini / GPT-4o Vision
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                        <span class="text-neutral-400">Status Kunci:</span>
                        <span v-if="providers.openai?.has_db_key" class="font-medium text-indigo-600 dark:text-indigo-400">Database</span>
                        <span v-else-if="providers.openai?.has_env_key" class="font-medium text-emerald-600 dark:text-emerald-400">.env File</span>
                        <span v-else class="font-medium text-amber-600 dark:text-amber-400">Belum Ada</span>
                    </div>

                    <button
                        v-if="form.vision_ai_provider !== 'openai'"
                        type="button"
                        class="mt-3 w-full py-1 text-[11px] font-medium rounded border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                        @click.stop="form.vision_ai_provider = 'openai'; activeTab = 'openai'"
                    >
                        Jadikan Aktif
                    </button>
                </div>

                <!-- Claude -->
                <div
                    class="relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between"
                    :class="[
                        form.vision_ai_provider === 'claude'
                            ? 'border-purple-600 bg-purple-50/40 dark:bg-purple-950/20 ring-2 ring-purple-600/30 shadow-sm'
                            : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:border-neutral-300 dark:hover:border-neutral-700',
                        activeTab === 'claude' ? 'ring-1 ring-neutral-400 dark:ring-neutral-600' : ''
                    ]"
                    @click="activeTab = 'claude'"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="p-2 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                <Bot class="w-5 h-5" />
                            </div>
                            <span
                                v-if="form.vision_ai_provider === 'claude'"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-purple-600 text-white"
                            >
                                <Check class="w-3 h-3" /> Aktif
                            </span>
                        </div>
                        <div>
                            <div class="font-semibold text-sm text-neutral-900 dark:text-neutral-100">Anthropic Claude</div>
                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">
                                Claude 3.5 Sonnet
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                        <span class="text-neutral-400">Status Kunci:</span>
                        <span v-if="providers.claude?.has_db_key" class="font-medium text-indigo-600 dark:text-indigo-400">Database</span>
                        <span v-else-if="providers.claude?.has_env_key" class="font-medium text-emerald-600 dark:text-emerald-400">.env File</span>
                        <span v-else class="font-medium text-amber-600 dark:text-amber-400">Belum Ada</span>
                    </div>

                    <button
                        v-if="form.vision_ai_provider !== 'claude'"
                        type="button"
                        class="mt-3 w-full py-1 text-[11px] font-medium rounded border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                        @click.stop="form.vision_ai_provider = 'claude'; activeTab = 'claude'"
                    >
                        Jadikan Aktif
                    </button>
                </div>

                <!-- Ollama -->
                <div
                    class="relative rounded-xl border p-4 cursor-pointer transition-all duration-150 flex flex-col justify-between"
                    :class="[
                        form.vision_ai_provider === 'ollama'
                            ? 'border-orange-600 bg-orange-50/40 dark:bg-orange-950/20 ring-2 ring-orange-600/30 shadow-sm'
                            : 'border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 hover:border-neutral-300 dark:hover:border-neutral-700',
                        activeTab === 'ollama' ? 'ring-1 ring-neutral-400 dark:ring-neutral-600' : ''
                    ]"
                    @click="activeTab = 'ollama'"
                >
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="p-2 rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400">
                                <Cpu class="w-5 h-5" />
                            </div>
                            <span
                                v-if="form.vision_ai_provider === 'ollama'"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-orange-600 text-white"
                            >
                                <Check class="w-3 h-3" /> Aktif
                            </span>
                        </div>
                        <div>
                            <div class="font-semibold text-sm text-neutral-900 dark:text-neutral-100">Ollama (Lokal)</div>
                            <div class="text-[11px] text-neutral-500 dark:text-neutral-400 mt-0.5 line-clamp-2">
                                LLaVA / Self-hosted Vision
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-neutral-100 dark:border-neutral-800/80 flex items-center justify-between text-[11px]">
                        <span class="text-neutral-400">Server:</span>
                        <span class="font-medium text-neutral-600 dark:text-neutral-300">Lokal / Intranet</span>
                    </div>

                    <button
                        v-if="form.vision_ai_provider !== 'ollama'"
                        type="button"
                        class="mt-3 w-full py-1 text-[11px] font-medium rounded border border-neutral-200 dark:border-neutral-700 text-neutral-600 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-800 transition"
                        @click.stop="form.vision_ai_provider = 'ollama'; activeTab = 'ollama'"
                    >
                        Jadikan Aktif
                    </button>
                </div>
            </div>
        </div>

        <!-- Detail Form Section with Tabs -->
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 overflow-hidden shadow-sm">
            <!-- Tabs Bar -->
            <div class="flex items-center border-b border-neutral-200 dark:border-neutral-800 px-4 bg-neutral-50/50 dark:bg-neutral-950/40">
                <button
                    type="button"
                    class="px-4 py-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-2"
                    :class="activeTab === 'gemini'
                        ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                    @click="activeTab = 'gemini'"
                >
                    <Sparkles class="w-3.5 h-3.5" />
                    <span>Google Gemini</span>
                </button>

                <button
                    type="button"
                    class="px-4 py-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-2"
                    :class="activeTab === 'openai'
                        ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                    @click="activeTab = 'openai'"
                >
                    <Zap class="w-3.5 h-3.5" />
                    <span>OpenAI</span>
                </button>

                <button
                    type="button"
                    class="px-4 py-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-2"
                    :class="activeTab === 'claude'
                        ? 'border-purple-600 text-purple-600 dark:text-purple-400'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                    @click="activeTab = 'claude'"
                >
                    <Bot class="w-3.5 h-3.5" />
                    <span>Anthropic Claude</span>
                </button>

                <button
                    type="button"
                    class="px-4 py-3 text-xs font-semibold border-b-2 transition-all flex items-center gap-2"
                    :class="activeTab === 'ollama'
                        ? 'border-orange-600 text-orange-600 dark:text-orange-400'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 dark:hover:text-neutral-200'"
                    @click="activeTab = 'ollama'"
                >
                    <Cpu class="w-3.5 h-3.5" />
                    <span>Ollama (Lokal)</span>
                </button>
            </div>

            <!-- TAB CONTENT: Google Gemini -->
            <div v-show="activeTab === 'gemini'" class="p-6 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                            <span>Konfigurasi Google Gemini</span>
                            <span v-if="form.vision_ai_provider === 'gemini'" class="text-[11px] px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-medium">Provider Aktif Saat Ini</span>
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {{ providers.gemini?.description }}
                        </p>
                    </div>

                    <Button
                        v-if="form.vision_ai_provider !== 'gemini'"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="text-xs"
                        @click="form.vision_ai_provider = 'gemini'"
                    >
                        Pilih Sebagai Provider Aktif
                    </Button>
                </div>

                <!-- API Key Gemini -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="gemini_api_key" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                            Gemini API Key
                        </Label>
                        <div class="flex items-center gap-2">
                            <span v-if="providers.gemini?.has_db_key && !resetKeys.includes('gemini_api_key')" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1">
                                <Key class="w-3 h-3" /> Tersimpan di Database
                            </span>
                            <span v-else-if="providers.gemini?.has_env_key" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                                <ShieldCheck class="w-3 h-3" /> Menggunakan .env File
                            </span>
                            <span v-else class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">
                                Belum dikonfigurasi
                            </span>

                            <button
                                v-if="providers.gemini?.has_db_key && !resetKeys.includes('gemini_api_key')"
                                type="button"
                                class="text-[11px] text-red-500 hover:text-red-700 underline ml-2"
                                @click="markResetToEnv('gemini_api_key')"
                            >
                                Hapus dari DB (Kembali ke .env)
                            </button>
                            <button
                                v-else-if="resetKeys.includes('gemini_api_key')"
                                type="button"
                                class="text-[11px] text-blue-500 underline ml-2"
                                @click="cancelReset('gemini_api_key')"
                            >
                                Batalkan Reset
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <Input
                            id="gemini_api_key"
                            v-model="form.gemini_api_key"
                            :type="showKeys.gemini ? 'text' : 'password'"
                            class="pr-10 font-mono text-xs"
                            :placeholder="providers.gemini?.masked_key ? `Tersimpan: ${providers.gemini.masked_key} (Kosongkan jika tidak diubah)` : 'Contoh: AIzaSyD-xxxxxxxxxxxxxxxxxxxxxxxx'"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                            @click="toggleShowKey('gemini')"
                        >
                            <EyeOff v-if="showKeys.gemini" class="w-4 h-4" />
                            <Eye v-else class="w-4 h-4" />
                        </button>
                    </div>
                    <p class="text-[11px] text-neutral-500">
                        Kosongkan bidang ini jika Anda tidak ingin mengubah kunci yang sudah tersimpan. Dapatkan API Key gratis di Google AI Studio.
                    </p>
                </div>

                <!-- Model Gemini -->
                <div class="space-y-2">
                    <Label for="gemini_model" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Model Vision Gemini
                    </Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="gemini_model"
                            v-model="form.gemini_model"
                            type="text"
                            class="font-mono text-xs max-w-md"
                            placeholder="gemini-2.5-flash"
                        />
                        <button
                            v-if="form.gemini_model !== 'gemini-2.5-flash'"
                            type="button"
                            class="text-xs text-neutral-500 hover:text-neutral-800 flex items-center gap-1"
                            @click="form.gemini_model = 'gemini-2.5-flash'"
                        >
                            <RotateCcw class="w-3 h-3" /> Reset Default
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <span class="text-[11px] text-neutral-400 mr-1 py-0.5">Rekomendasi:</span>
                        <button
                            v-for="m in providers.gemini?.recommended_models"
                            :key="m"
                            type="button"
                            class="text-[11px] font-mono px-2 py-0.5 rounded border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            :class="form.gemini_model === m ? 'bg-blue-50 dark:bg-blue-950 border-blue-400 text-blue-600 dark:text-blue-400 font-semibold' : 'text-neutral-600 dark:text-neutral-400'"
                            @click="form.gemini_model = m"
                        >
                            {{ m }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: OpenAI -->
            <div v-show="activeTab === 'openai'" class="p-6 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                            <span>Konfigurasi OpenAI (ChatGPT)</span>
                            <span v-if="form.vision_ai_provider === 'openai'" class="text-[11px] px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-medium">Provider Aktif Saat Ini</span>
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {{ providers.openai?.description }}
                        </p>
                    </div>

                    <Button
                        v-if="form.vision_ai_provider !== 'openai'"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="text-xs"
                        @click="form.vision_ai_provider = 'openai'"
                    >
                        Pilih Sebagai Provider Aktif
                    </Button>
                </div>

                <!-- API Key OpenAI -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="openai_api_key" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                            OpenAI API Key
                        </Label>
                        <div class="flex items-center gap-2">
                            <span v-if="providers.openai?.has_db_key && !resetKeys.includes('openai_api_key')" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1">
                                <Key class="w-3 h-3" /> Tersimpan di Database
                            </span>
                            <span v-else-if="providers.openai?.has_env_key" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                                <ShieldCheck class="w-3 h-3" /> Menggunakan .env File
                            </span>
                            <span v-else class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">
                                Belum dikonfigurasi
                            </span>

                            <button
                                v-if="providers.openai?.has_db_key && !resetKeys.includes('openai_api_key')"
                                type="button"
                                class="text-[11px] text-red-500 hover:text-red-700 underline ml-2"
                                @click="markResetToEnv('openai_api_key')"
                            >
                                Hapus dari DB (Kembali ke .env)
                            </button>
                            <button
                                v-else-if="resetKeys.includes('openai_api_key')"
                                type="button"
                                class="text-[11px] text-blue-500 underline ml-2"
                                @click="cancelReset('openai_api_key')"
                            >
                                Batalkan Reset
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <Input
                            id="openai_api_key"
                            v-model="form.openai_api_key"
                            :type="showKeys.openai ? 'text' : 'password'"
                            class="pr-10 font-mono text-xs"
                            :placeholder="providers.openai?.masked_key ? `Tersimpan: ${providers.openai.masked_key} (Kosongkan jika tidak diubah)` : 'Contoh: sk-proj-xxxxxxxxxxxxxxxxxxxxxxxx'"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                            @click="toggleShowKey('openai')"
                        >
                            <EyeOff v-if="showKeys.openai" class="w-4 h-4" />
                            <Eye v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Model OpenAI -->
                <div class="space-y-2">
                    <Label for="openai_model" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Model Vision OpenAI
                    </Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="openai_model"
                            v-model="form.openai_model"
                            type="text"
                            class="font-mono text-xs max-w-md"
                            placeholder="gpt-4o-mini"
                        />
                        <button
                            v-if="form.openai_model !== 'gpt-4o-mini'"
                            type="button"
                            class="text-xs text-neutral-500 hover:text-neutral-800 flex items-center gap-1"
                            @click="form.openai_model = 'gpt-4o-mini'"
                        >
                            <RotateCcw class="w-3 h-3" /> Reset Default
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <span class="text-[11px] text-neutral-400 mr-1 py-0.5">Rekomendasi:</span>
                        <button
                            v-for="m in providers.openai?.recommended_models"
                            :key="m"
                            type="button"
                            class="text-[11px] font-mono px-2 py-0.5 rounded border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            :class="form.openai_model === m ? 'bg-emerald-50 dark:bg-emerald-950 border-emerald-400 text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-neutral-600 dark:text-neutral-400'"
                            @click="form.openai_model = m"
                        >
                            {{ m }}
                        </button>
                    </div>
                </div>

                <!-- Base URL (Optional / Compatible Endpoint) -->
                <div class="space-y-2">
                    <Label for="openai_base_url" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Custom Base URL (Opsional / OpenAI Compatible Proxy)
                    </Label>
                    <Input
                        id="openai_base_url"
                        v-model="form.openai_base_url"
                        type="text"
                        class="font-mono text-xs max-w-md"
                        placeholder="https://api.openai.com/v1"
                    />
                    <p class="text-[11px] text-neutral-500">
                        Dapat diarahkan ke layanan proxy pihak ketiga yang kompatibel dengan protokol OpenAI (e.g. OpenRouter, Azure OpenAI).
                    </p>
                </div>
            </div>

            <!-- TAB CONTENT: Anthropic Claude -->
            <div v-show="activeTab === 'claude'" class="p-6 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                            <span>Konfigurasi Anthropic Claude</span>
                            <span v-if="form.vision_ai_provider === 'claude'" class="text-[11px] px-2 py-0.5 rounded bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 font-medium">Provider Aktif Saat Ini</span>
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {{ providers.claude?.description }}
                        </p>
                    </div>

                    <Button
                        v-if="form.vision_ai_provider !== 'claude'"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="text-xs"
                        @click="form.vision_ai_provider = 'claude'"
                    >
                        Pilih Sebagai Provider Aktif
                    </Button>
                </div>

                <!-- API Key Claude -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="claude_api_key" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                            Anthropic API Key
                        </Label>
                        <div class="flex items-center gap-2">
                            <span v-if="providers.claude?.has_db_key && !resetKeys.includes('claude_api_key')" class="text-[11px] text-indigo-600 dark:text-indigo-400 font-medium flex items-center gap-1">
                                <Key class="w-3 h-3" /> Tersimpan di Database
                            </span>
                            <span v-else-if="providers.claude?.has_env_key" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium flex items-center gap-1">
                                <ShieldCheck class="w-3 h-3" /> Menggunakan .env File
                            </span>
                            <span v-else class="text-[11px] text-amber-600 dark:text-amber-400 font-medium">
                                Belum dikonfigurasi
                            </span>

                            <button
                                v-if="providers.claude?.has_db_key && !resetKeys.includes('claude_api_key')"
                                type="button"
                                class="text-[11px] text-red-500 hover:text-red-700 underline ml-2"
                                @click="markResetToEnv('claude_api_key')"
                            >
                                Hapus dari DB (Kembali ke .env)
                            </button>
                            <button
                                v-else-if="resetKeys.includes('claude_api_key')"
                                type="button"
                                class="text-[11px] text-blue-500 underline ml-2"
                                @click="cancelReset('claude_api_key')"
                            >
                                Batalkan Reset
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <Input
                            id="claude_api_key"
                            v-model="form.claude_api_key"
                            :type="showKeys.claude ? 'text' : 'password'"
                            class="pr-10 font-mono text-xs"
                            :placeholder="providers.claude?.masked_key ? `Tersimpan: ${providers.claude.masked_key} (Kosongkan jika tidak diubah)` : 'Contoh: sk-ant-api03-xxxxxxxxxxxxxxxxxxxxxxxx'"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-2.5 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200"
                            @click="toggleShowKey('claude')"
                        >
                            <EyeOff v-if="showKeys.claude" class="w-4 h-4" />
                            <Eye v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <!-- Model Claude -->
                <div class="space-y-2">
                    <Label for="claude_model" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Model Vision Claude
                    </Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="claude_model"
                            v-model="form.claude_model"
                            type="text"
                            class="font-mono text-xs max-w-md"
                            placeholder="claude-3-5-sonnet-20241022"
                        />
                        <button
                            v-if="form.claude_model !== 'claude-3-5-sonnet-20241022'"
                            type="button"
                            class="text-xs text-neutral-500 hover:text-neutral-800 flex items-center gap-1"
                            @click="form.claude_model = 'claude-3-5-sonnet-20241022'"
                        >
                            <RotateCcw class="w-3 h-3" /> Reset Default
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <span class="text-[11px] text-neutral-400 mr-1 py-0.5">Rekomendasi:</span>
                        <button
                            v-for="m in providers.claude?.recommended_models"
                            :key="m"
                            type="button"
                            class="text-[11px] font-mono px-2 py-0.5 rounded border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            :class="form.claude_model === m ? 'bg-purple-50 dark:bg-purple-950 border-purple-400 text-purple-600 dark:text-purple-400 font-semibold' : 'text-neutral-600 dark:text-neutral-400'"
                            @click="form.claude_model = m"
                        >
                            {{ m }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: Ollama (Local) -->
            <div v-show="activeTab === 'ollama'" class="p-6 space-y-6">
                <div class="flex items-center justify-between pb-3 border-b border-neutral-100 dark:border-neutral-800">
                    <div>
                        <h3 class="font-semibold text-neutral-900 dark:text-neutral-100 flex items-center gap-2">
                            <span>Konfigurasi Ollama (Self-Hosted / Local)</span>
                            <span v-if="form.vision_ai_provider === 'ollama'" class="text-[11px] px-2 py-0.5 rounded bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 font-medium">Provider Aktif Saat Ini</span>
                        </h3>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
                            {{ providers.ollama?.description }}
                        </p>
                    </div>

                    <Button
                        v-if="form.vision_ai_provider !== 'ollama'"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="text-xs"
                        @click="form.vision_ai_provider = 'ollama'"
                    >
                        Pilih Sebagai Provider Aktif
                    </Button>
                </div>

                <!-- Ollama Base URL -->
                <div class="space-y-2">
                    <Label for="ollama_base_url" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Ollama Base URL Endpoint
                    </Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="ollama_base_url"
                            v-model="form.ollama_base_url"
                            type="text"
                            class="font-mono text-xs max-w-md"
                            placeholder="http://localhost:11434"
                        />
                        <button
                            v-if="form.ollama_base_url !== 'http://localhost:11434'"
                            type="button"
                            class="text-xs text-neutral-500 hover:text-neutral-800 flex items-center gap-1"
                            @click="form.ollama_base_url = 'http://localhost:11434'"
                        >
                            <RotateCcw class="w-3 h-3" /> Reset Default
                        </button>
                    </div>
                    <p class="text-[11px] text-neutral-500">
                        Alamat host server Ollama yang menjalankan model Vision di mesin lokal atau jaringan lokal Anda.
                    </p>
                </div>

                <!-- Model Ollama -->
                <div class="space-y-2">
                    <Label for="ollama_model" class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                        Model Vision Ollama
                    </Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="ollama_model"
                            v-model="form.ollama_model"
                            type="text"
                            class="font-mono text-xs max-w-md"
                            placeholder="llava"
                        />
                        <button
                            v-if="form.ollama_model !== 'llava'"
                            type="button"
                            class="text-xs text-neutral-500 hover:text-neutral-800 flex items-center gap-1"
                            @click="form.ollama_model = 'llava'"
                        >
                            <RotateCcw class="w-3 h-3" /> Reset Default
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <span class="text-[11px] text-neutral-400 mr-1 py-0.5">Rekomendasi:</span>
                        <button
                            v-for="m in providers.ollama?.recommended_models"
                            :key="m"
                            type="button"
                            class="text-[11px] font-mono px-2 py-0.5 rounded border border-neutral-200 dark:border-neutral-700 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition"
                            :class="form.ollama_model === m ? 'bg-orange-50 dark:bg-orange-950 border-orange-400 text-orange-600 dark:text-orange-400 font-semibold' : 'text-neutral-600 dark:text-neutral-400'"
                            @click="form.ollama_model = m"
                        >
                            {{ m }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bottom Action Footer -->
            <div class="p-4 bg-neutral-50 dark:bg-neutral-950/60 border-t border-neutral-200 dark:border-neutral-800 flex items-center justify-between">
                <div class="text-xs text-neutral-500 dark:text-neutral-400 flex items-center gap-1.5">
                    <Server class="w-3.5 h-3.5 text-neutral-400" />
                    <span>Perubahan disimpan langsung ke tabel database <code class="text-[11px] font-mono bg-neutral-200/60 dark:bg-neutral-800 px-1 py-0.5 rounded">system_settings</code>.</span>
                </div>

                <div class="flex items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        :disabled="testingConnection"
                        @click="runTestConnection(activeTab)"
                    >
                        <Loader2 v-if="testingConnection" class="w-3.5 h-3.5 animate-spin" />
                        <Activity v-else class="w-3.5 h-3.5 text-blue-500" />
                        <span>Uji Koneksi {{ providers[activeTab]?.name }}</span>
                    </Button>

                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5 bg-blue-600 hover:bg-blue-700 text-white"
                        :disabled="form.processing"
                        @click="submitSettings"
                    >
                        <Loader2 v-if="form.processing" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else class="w-3.5 h-3.5" />
                        <span>Simpan Pengaturan</span>
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>

