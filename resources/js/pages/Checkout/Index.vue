<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft, ArrowRight, Check, CreditCard,
    Lock, ShieldCheck, Sparkles, Zap,
} from '@lucide/vue';
import LandingLayout from '@/layouts/LandingLayout.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({ layout: LandingLayout });

interface Plan {
    id: string; slug: string; display_name: string; tagline: string;
    price_monthly: string; price_annual: string; period_label: string;
    benefits: string[]; is_featured: boolean;
}
interface BankInfo { bank_name: string; account: string; holder: string; whatsapp: string; whatsapp_template: string; }

// ─── Helper: replace variabel di template WA ─────────────
function buildWaMessage(template: string, vars: Record<string, string>): string {
    let msg = template || 'Halo Admin,\n\nOrder ID: {order_id}\nPaket: {plan_name}\nJumlah: {amount}\n\nMohon konfirmasi. Terima kasih!';
    Object.entries(vars).forEach(([k, v]) => {
        msg = msg.replaceAll(`{${k}}`, v);
    });
    return msg;
}

const props = defineProps<{
    plan: Plan;
    gatewayEnabled: boolean;
    bankInfo: BankInfo;
    midtransClientKey: string | null;
    isProduction: boolean;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

// ─── Form ─────────────────────────────────────────────────
const billingType  = ref<'monthly' | 'annual'>('monthly');
const name         = ref(user.value?.name ?? '');
const email        = ref(user.value?.email ?? '');
const isSubmitting = ref(false);
const errorMsg     = ref('');

const selectedPrice = computed(() =>
    billingType.value === 'annual' ? props.plan.price_annual : props.plan.price_monthly
);

// ─── Submit ───────────────────────────────────────────────
function submit() {
    if (!name.value.trim() || !email.value.trim()) {
        errorMsg.value = 'Nama dan email wajib diisi.';
        return;
    }
    isSubmitting.value = true;
    errorMsg.value     = '';

    // ── Mode: Transfer Manual → Inertia router.post (handles CSRF + redirect) ──
    if (!props.gatewayEnabled) {
        router.post(`/checkout/${props.plan.slug}/order`, {
            billing_type: billingType.value,
            name: name.value,
            email: email.value,
        }, {
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                errorMsg.value = (firstError as string) ?? 'Terjadi kesalahan. Coba lagi.';
                isSubmitting.value = false;
            },
            onFinish: () => {
                // hanya reset spinner jika tidak di-redirect
                // (onError sudah handle, onSuccess akan redirect)
            },
        });
        return;
    }

    // ── Mode: Midtrans Gateway → fetch JSON untuk snap_token ──
    submitWithGateway();
}

async function submitWithGateway() {
    try {
        const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
        const res = await fetch(`/checkout/${props.plan.slug}/order`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
            body: JSON.stringify({ billing_type: billingType.value, name: name.value, email: email.value }),
        });

        const data = await res.json();

        if (!res.ok) {
            errorMsg.value = data.message ?? 'Terjadi kesalahan. Coba lagi.';
            return;
        }

        if (data.snap_token) {
            openMidtransSnap(data.snap_token, data.order_id);
        }
    } catch (err) {
        console.error('[Checkout] Gateway error:', err);
        errorMsg.value = 'Koneksi error. Periksa jaringan Anda.';
    } finally {
        isSubmitting.value = false;
    }
}

function openMidtransSnap(token: string, orderId: string) {
    // Load Midtrans Snap.js jika belum ada
    const snapUrl = props.isProduction
        ? 'https://app.midtrans.com/snap/snap.js'
        : 'https://app.sandbox.midtrans.com/snap/snap.js';

    const loadSnap = () => {
        (window as any).snap.pay(token, {
            onSuccess: (result: any) => {
                // Tandai transaksi sebagai paid di backend, lalu redirect
                fetch(`/checkout/payment-callback`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'include',
                    body: JSON.stringify({ order_id: orderId, status: 'settlement', result }),
                }).finally(() => {
                    window.location.href = `/dashboard?payment=success&order=${orderId}`;
                });
            },
            onPending: () => { window.location.href = `/dashboard?payment=pending&order=${orderId}`; },
            onError:   () => { errorMsg.value = 'Pembayaran gagal. Silakan coba lagi.'; },
            onClose:   () => { /* user tutup popup */ },
        });
    };

    if ((window as any).snap) {
        loadSnap();
    } else {
        const script = document.createElement('script');
        script.src = snapUrl;
        script.setAttribute('data-client-key', props.midtransClientKey ?? '');
        script.onload = loadSnap;
        document.head.appendChild(script);
    }
}
</script>

<template>
    <Head :title="`Checkout — ${plan.display_name}`" />

    <!-- Ambient glow -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden z-0">
        <div class="absolute -top-[20%] left-1/2 -translate-x-1/2 w-[700px] h-[500px] bg-gradient-to-b from-indigo-600/10 via-blue-600/5 to-transparent blur-[120px] rounded-full"></div>
    </div>

    <div class="relative z-10 max-w-5xl mx-auto px-4 py-12 sm:py-16">

        <!-- Back link -->
        <Link href="/#pricing" class="inline-flex items-center gap-1.5 text-sm text-neutral-400 hover:text-white transition-colors mb-8">
            <ArrowLeft class="h-4 w-4" /> Kembali ke Paket
        </Link>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

            <!-- ── KIRI: Order summary ── -->
            <div class="space-y-5">
                <!-- Plan card -->
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] backdrop-blur-xl p-6 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h2 class="text-lg font-bold text-white">{{ plan.display_name }}</h2>
                                <span v-if="plan.is_featured" class="inline-flex items-center gap-1 rounded-full bg-indigo-500/20 border border-indigo-500/30 px-2 py-0.5 text-[10px] font-bold text-indigo-400">
                                    <Sparkles class="h-2.5 w-2.5" /> Featured
                                </span>
                            </div>
                            <p class="text-sm text-neutral-400">{{ plan.tagline }}</p>
                        </div>
                    </div>

                    <!-- Billing toggle -->
                    <div class="flex gap-2 p-1 rounded-xl bg-white/5 border border-white/10">
                        <button
                            type="button"
                            @click="billingType = 'monthly'"
                            class="flex-1 rounded-lg py-2 text-xs font-semibold transition-all"
                            :class="billingType === 'monthly' ? 'bg-white text-black shadow' : 'text-neutral-400 hover:text-white'"
                        >
                            Bulanan
                        </button>
                        <button
                            type="button"
                            @click="billingType = 'annual'"
                            class="flex-1 rounded-lg py-2 text-xs font-semibold transition-all flex items-center justify-center gap-1.5"
                            :class="billingType === 'annual' ? 'bg-white text-black shadow' : 'text-neutral-400 hover:text-white'"
                        >
                            Tahunan
                            <span class="rounded-full bg-emerald-500/20 px-1.5 py-0.5 text-[9px] font-bold text-emerald-400">Hemat 20%</span>
                        </button>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-2">
                        <span class="text-4xl font-extrabold font-mono text-white">{{ selectedPrice }}</span>
                        <span class="text-sm text-neutral-400">/ {{ plan.period_label }}</span>
                    </div>

                    <div class="h-px bg-white/10"></div>

                    <!-- Benefits -->
                    <ul class="space-y-2.5">
                        <li v-for="benefit in plan.benefits" :key="benefit" class="flex items-start gap-2.5 text-sm text-neutral-300">
                            <Check class="h-4 w-4 shrink-0 text-indigo-400 mt-0.5" />
                            {{ benefit }}
                        </li>
                    </ul>
                </div>

                <!-- Payment method info -->
                <div
                    class="rounded-2xl border p-4 space-y-2"
                    :class="gatewayEnabled
                        ? 'border-indigo-500/30 bg-indigo-500/5'
                        : 'border-amber-500/30 bg-amber-500/5'"
                >
                    <div class="flex items-center gap-2">
                        <component :is="gatewayEnabled ? Zap : CreditCard" class="h-4 w-4" :class="gatewayEnabled ? 'text-indigo-400' : 'text-amber-400'" />
                        <p class="text-sm font-semibold text-white">
                            {{ gatewayEnabled ? 'Payment Gateway (Midtrans)' : 'Transfer Bank Manual' }}
                        </p>
                    </div>
                    <p v-if="gatewayEnabled" class="text-xs text-neutral-400">
                        Bayar aman via QRIS, Virtual Account, atau Kartu Kredit. Akun aktif otomatis setelah pembayaran berhasil.
                    </p>
                    <div v-else class="text-xs text-neutral-400 space-y-2">
                        <p>Transfer ke rekening berikut:</p>
                        <p class="font-mono text-white font-semibold">{{ bankInfo.bank_name }} · {{ bankInfo.account }}</p>
                        <p class="text-neutral-500">a.n. {{ bankInfo.holder }}</p>
                        <div class="pt-1 space-y-1.5 border-t border-white/10 mt-2">
                            <p class="text-neutral-300 font-medium">Setelah transfer, konfirmasi ke admin via:</p>
                            <a
                                v-if="bankInfo.whatsapp"
                                :href="`https://wa.me/${bankInfo.whatsapp}?text=${encodeURIComponent(buildWaMessage(bankInfo.whatsapp_template, {
                                    order_id: '(isi setelah submit)',
                                    plan_name: plan.display_name,
                                    amount: selectedPrice,
                                }))}`"
                                target="_blank"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-500/20 border border-emerald-500/30 px-3 py-2 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/30 transition-all"
                            >
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                Konfirmasi via WhatsApp
                            </a>
                            <p v-else class="text-amber-400/80 text-[11px]">
                                ⚠ Simpan bukti transfer dan hubungi admin untuk mengaktifkan akun.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Trust badges -->
                <div class="flex flex-wrap items-center gap-4 text-[11px] font-mono text-neutral-600">
                    <span class="flex items-center gap-1.5"><Lock class="h-3 w-3" /> SSL Secured</span>
                    <span class="flex items-center gap-1.5"><ShieldCheck class="h-3 w-3" /> SOC2 Type II</span>
                    <span class="flex items-center gap-1.5"><Check class="h-3 w-3" /> No Hidden Fees</span>
                </div>
            </div>

            <!-- ── KANAN: Form checkout ── -->
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] backdrop-blur-xl p-6 sm:p-7 space-y-5">
                <div>
                    <h3 class="text-base font-semibold text-white mb-0.5">Detail Pembayaran</h3>
                    <p class="text-xs text-neutral-400">Isi data di bawah untuk melanjutkan.</p>
                </div>

                <div class="space-y-4">
                    <div class="space-y-1.5">
                        <Label for="name" class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Nama Lengkap</Label>
                        <Input
                            id="name"
                            v-model="name"
                            placeholder="Nama Anda"
                            required
                            class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="email" class="text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="email"
                            placeholder="email@studio.id"
                            required
                            class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600"
                        />
                    </div>
                </div>

                <!-- Error -->
                <div v-if="errorMsg" class="rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                    {{ errorMsg }}
                </div>

                <!-- Login prompt jika belum login -->
                <div v-if="!user" class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-xs text-amber-300 space-y-1">
                    <p class="font-semibold">Anda belum login.</p>
                    <p>
                        <Link href="/login" class="underline hover:text-white">Login</Link> atau
                        <Link href="/register" class="underline hover:text-white">daftar</Link> terlebih dahulu agar akun Anda bisa diaktifkan setelah pembayaran.
                    </p>
                </div>

                <!-- Summary -->
                <div class="rounded-xl border border-white/8 bg-white/5 px-4 py-3 flex items-center justify-between text-sm">
                    <span class="text-neutral-400">Total</span>
                    <span class="font-bold text-white font-mono">{{ selectedPrice }}</span>
                </div>

                <!-- Submit button -->
                <button
                    type="button"
                    @click="submit"
                    :disabled="isSubmitting || !name || !email"
                    class="w-full h-12 rounded-xl bg-white text-[#050608] font-bold text-sm flex items-center justify-center gap-2 transition-all hover:bg-neutral-100 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed shadow-md"
                >
                    <Spinner v-if="isSubmitting" class="h-4 w-4 text-[#050608]" />
                    <span v-else-if="gatewayEnabled">
                        Bayar {{ selectedPrice }} <ArrowRight class="inline h-4 w-4 ml-1" />
                    </span>
                    <span v-else>
                        Lanjut ke Instruksi Transfer <ArrowRight class="inline h-4 w-4 ml-1" />
                    </span>
                </button>

                <p class="text-center text-[11px] text-neutral-600">
                    Dengan melanjutkan, Anda menyetujui
                    <Link href="#" class="underline hover:text-white">Syarat & Ketentuan</Link> kami.
                </p>
            </div>
        </div>
    </div>
</template>
