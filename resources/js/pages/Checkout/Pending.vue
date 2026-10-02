<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Clock, Copy } from '@lucide/vue';
import { ref } from 'vue';
import LandingLayout from '@/layouts/LandingLayout.vue';

defineOptions({ layout: LandingLayout });

interface Tx {
    id: string; order_id: string; amount: string; status: string;
    created_at: string; snap_response: Record<string, any> | null;
}
interface BankInfo { bank_name: string; account: string; holder: string; whatsapp: string; whatsapp_template: string; }

const props = defineProps<{ transaction: Tx; bankInfo: BankInfo }>();

const copied = ref('');
function copyText(text: string, key: string) {
    navigator.clipboard.writeText(text).then(() => {
        copied.value = key;
        setTimeout(() => { copied.value = ''; }, 2000);
    });
}

// Replace variabel di template WA
function buildWaMessage(): string {
    const planName = props.transaction.snap_response?.plan_slug ?? 'Pro';
    const template = props.bankInfo.whatsapp_template
        || 'Halo Admin AETHER 3D,\n\nSaya sudah melakukan transfer pembayaran.\n\nOrder ID: {order_id}\nPaket: {plan_name}\nJumlah: {amount}\n\nMohon dikonfirmasi. Terima kasih!';
    return template
        .replaceAll('{order_id}', props.transaction.order_id)
        .replaceAll('{plan_name}', planName)
        .replaceAll('{amount}', props.transaction.amount);
}
</script>

<template>
    <Head title="Menunggu Konfirmasi Pembayaran" />

    <div class="relative z-10 max-w-lg mx-auto px-4 py-16 sm:py-24">
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] backdrop-blur-xl p-7 sm:p-8 space-y-6 text-center">

            <!-- Icon -->
            <div class="flex justify-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/15 border border-amber-500/30">
                    <Clock class="h-8 w-8 text-amber-400" />
                </div>
            </div>

            <div>
                <h1 class="text-xl font-bold text-white mb-2">Menunggu Konfirmasi Transfer</h1>
                <p class="text-sm text-neutral-400 leading-relaxed">
                    Order <span class="font-mono text-white">{{ transaction.order_id }}</span> telah dibuat.
                    Silakan transfer sesuai jumlah di bawah, lalu admin akan mengaktifkan akun Anda.
                </p>
            </div>

            <!-- Amount highlight -->
            <div class="rounded-xl bg-amber-500/10 border border-amber-500/20 px-5 py-4">
                <p class="text-xs text-neutral-400 mb-1">Jumlah Transfer</p>
                <p class="text-3xl font-extrabold font-mono text-white">{{ transaction.amount }}</p>
                <p class="text-[11px] text-amber-400 mt-1">Transfer tepat sesuai nominal ini untuk memudahkan verifikasi</p>
            </div>

            <!-- Bank info -->
            <div class="rounded-xl border border-white/10 bg-white/5 p-4 space-y-3 text-left">
                <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Informasi Rekening</p>
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] text-neutral-500">Bank</p>
                            <p class="text-sm font-semibold text-white">{{ bankInfo.bank_name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] text-neutral-500">Nomor Rekening</p>
                            <p class="text-sm font-mono font-semibold text-white">{{ bankInfo.account }}</p>
                        </div>
                        <button
                            type="button"
                            @click="copyText(bankInfo.account, 'account')"
                            class="rounded-lg p-1.5 text-neutral-500 hover:bg-white/10 hover:text-white transition-all"
                        >
                            <CheckCircle2 v-if="copied === 'account'" class="h-4 w-4 text-emerald-400" />
                            <Copy v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] text-neutral-500">Atas Nama</p>
                            <p class="text-sm font-semibold text-white">{{ bankInfo.holder }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Steps -->
            <div class="rounded-xl border border-white/8 bg-white/5 p-4 space-y-2.5 text-left text-xs text-neutral-400">
                <p class="font-semibold text-white text-sm mb-1">Langkah selanjutnya:</p>
                <p>1. Transfer <span class="font-mono text-white">{{ transaction.amount }}</span> ke rekening di atas.</p>
                <p>2. Simpan bukti transfer Anda.</p>
                <p>3. Konfirmasi ke admin via WhatsApp dengan menyebutkan Order ID Anda.</p>
                <p>4. Akun Anda akan diaktifkan dalam <span class="text-white font-medium">1×24 jam</span> setelah konfirmasi diterima.</p>
            </div>

            <!-- WA Konfirmasi button -->
            <a
                v-if="bankInfo.whatsapp"
                :href="`https://wa.me/${bankInfo.whatsapp}?text=${encodeURIComponent(buildWaMessage())}`"
                target="_blank"
                class="flex w-full items-center justify-center gap-2.5 rounded-full bg-emerald-500 text-white font-semibold py-3 text-sm hover:bg-emerald-600 transition-all"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Konfirmasi Transfer via WhatsApp
            </a>

            <!-- Order ID -->
            <div class="flex items-center justify-between text-xs text-neutral-500 font-mono">
                <span>Order: {{ transaction.order_id }}</span>
                <span>{{ transaction.created_at }}</span>
            </div>

            <!-- CTA -->
            <Link
                href="/dashboard"
                class="flex w-full items-center justify-center gap-2 rounded-full bg-white text-[#050608] font-semibold py-3 text-sm hover:bg-neutral-100 transition-all"
            >
                Kembali ke Dashboard <ArrowRight class="h-4 w-4" />
            </Link>
        </div>
    </div>
</template>
