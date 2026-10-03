<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Check, CreditCard, Pencil, Plus,
    Save, ShieldCheck, Sparkles,
    ToggleLeft, ToggleRight, Trash2, X,
} from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Dialog, DialogContent, DialogDescription,
    DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';

defineOptions({ layout: AdminLayout });

interface Plan {
    id: string;
    name: string;
    slug: string;
    display_name: string | null;
    tagline: string | null;
    badge_text: string | null;
    cta_text: string;
    cta_url: string;
    is_featured: boolean;
    price_monthly: string;
    price_annual: string;
    period_label: string;
    benefits: string[] | null;
    sort_order: number;
    status: string;
    subscriptions_count: number;
}

interface PaymentSettings {
    payment_gateway_enabled: string;
    bank_name: string;
    bank_account_number: string;
    bank_account_holder: string;
    midtrans_is_production: string;
    midtrans_server_key: string;
    midtrans_client_key: string;
    admin_whatsapp: string;
    whatsapp_template: string;
}

const props = defineProps<{
    plans: Plan[];
    paymentSettings: PaymentSettings;
}>();

// ─── Edit Pricing Modal ───────────────────────────────────
const editingPlan = ref<Plan | null>(null);
const isEditOpen  = ref(false);

const pricingForm = useForm({
    display_name:  '',
    tagline:       '',
    badge_text:    '',
    cta_text:      '',
    cta_url:       '',
    is_featured:   false,
    price_monthly: '',
    price_annual:  '',
    period_label:  '',
    benefits:      [] as string[],
    sort_order:    0,
});

function openEdit(plan: Plan) {
    editingPlan.value        = plan;
    pricingForm.display_name = plan.display_name ?? plan.name;
    pricingForm.tagline      = plan.tagline ?? '';
    pricingForm.badge_text   = plan.badge_text ?? '';
    pricingForm.cta_text     = plan.cta_text;
    pricingForm.cta_url      = plan.cta_url;
    pricingForm.is_featured  = plan.is_featured;
    pricingForm.price_monthly = plan.price_monthly;
    pricingForm.price_annual  = plan.price_annual;
    pricingForm.period_label  = plan.period_label;
    pricingForm.benefits      = plan.benefits ? [...plan.benefits] : [];
    pricingForm.sort_order    = plan.sort_order;
    isEditOpen.value          = true;
}

function closeEdit() {
    isEditOpen.value  = false;
    editingPlan.value = null;
    pricingForm.reset();
}

function submitPricing() {
    if (!editingPlan.value) return;
    pricingForm.patch(`/admin/plans/${editingPlan.value.id}/pricing`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`Paket ${pricingForm.display_name} berhasil diperbarui.`);
            closeEdit();
        },
    });
}

// ─── Benefits dynamic list ────────────────────────────────
function addBenefit() {
    pricingForm.benefits.push('');
}

function removeBenefit(idx: number) {
    pricingForm.benefits.splice(idx, 1);
}

// ─── Payment Settings ─────────────────────────────────────
const paymentForm = useForm({
    payment_gateway_enabled: props.paymentSettings.payment_gateway_enabled === '1',
    bank_name:              props.paymentSettings.bank_name ?? '',
    bank_account_number:    props.paymentSettings.bank_account_number ?? '',
    bank_account_holder:    props.paymentSettings.bank_account_holder ?? '',
    midtrans_is_production: props.paymentSettings.midtrans_is_production === '1',
    midtrans_server_key:    '',
    midtrans_client_key:    '',
    admin_whatsapp:         props.paymentSettings.admin_whatsapp ?? '',
    whatsapp_template:      props.paymentSettings.whatsapp_template ?? '',
});

// Apakah key sudah tersimpan sebelumnya (untuk placeholder informatif)
const serverKeySaved = !!props.paymentSettings.midtrans_server_key;
const clientKeySaved = !!props.paymentSettings.midtrans_client_key;

function submitPaymentSettings() {
    paymentForm.patch('/admin/payment-settings', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Pengaturan pembayaran berhasil disimpan.');
        },
    });
}
</script>

<template>
    <Head title="Kelola Paket Harga" />

    <div class="space-y-8">

        <!-- Page header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">Kelola Paket Harga</h1>
                <p class="text-sm text-muted-foreground mt-1">Atur tampilan, harga, dan benefit setiap paket di landing page.</p>
            </div>
        </div>

        <!-- Plans grid -->
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="rounded-2xl border bg-card p-5 space-y-4 relative overflow-hidden"
                :class="plan.is_featured ? 'border-indigo-500/50 bg-indigo-500/5' : 'border-border'"
            >
                <!-- Featured badge -->
                <div v-if="plan.is_featured" class="absolute top-3 right-3">
                    <Badge class="bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border-indigo-500/30 text-[10px]">
                        <Sparkles class="h-2.5 w-2.5 mr-1" /> Featured
                    </Badge>
                </div>

                <!-- Plan header -->
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-bold text-base text-foreground">{{ plan.display_name ?? plan.name }}</h3>
                        <Badge v-if="plan.status !== 'active'" variant="destructive" class="text-[10px]">Inactive</Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ plan.tagline ?? '—' }}</p>
                </div>

                <!-- Price -->
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-extrabold font-mono text-foreground">{{ plan.price_monthly }}</span>
                    <span class="text-xs text-muted-foreground">/ {{ plan.period_label }}</span>
                </div>

                <!-- Benefits preview (max 3) -->
                <ul class="space-y-1.5 text-xs text-muted-foreground">
                    <li v-for="(b, i) in (plan.benefits ?? []).slice(0, 3)" :key="i" class="flex items-start gap-1.5">
                        <Check class="h-3.5 w-3.5 shrink-0 text-emerald-500 mt-0.5" />
                        <span class="line-clamp-1">{{ b }}</span>
                    </li>
                    <li v-if="(plan.benefits ?? []).length > 3" class="text-[11px] text-muted-foreground pl-5">
                        + {{ (plan.benefits ?? []).length - 3 }} benefit lainnya
                    </li>
                </ul>

                <!-- Subscriptions count -->
                <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground border-t border-border pt-3">
                    <CreditCard class="h-3.5 w-3.5" />
                    <span>{{ plan.subscriptions_count }} subscriber aktif</span>
                </div>

                <!-- Edit button -->
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full text-xs"
                    @click="openEdit(plan)"
                >
                    <Pencil class="h-3.5 w-3.5 mr-1.5" /> Edit Tampilan & Harga
                </Button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════
             PAYMENT SETTINGS
        ════════════════════════════════════════════════ -->
        <div class="rounded-2xl border border-border bg-card p-6 space-y-6">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 border border-primary/20">
                    <ShieldCheck class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h2 class="font-semibold text-foreground">Pengaturan Pembayaran</h2>
                    <p class="text-xs text-muted-foreground">Pilih mode pembayaran dan konfigurasikan detail rekening / payment gateway.</p>
                </div>
            </div>

            <form @submit.prevent="submitPaymentSettings" class="space-y-6">

                <!-- Toggle payment gateway -->
                <div class="flex items-center justify-between p-4 rounded-xl border"
                     :class="paymentForm.payment_gateway_enabled
                         ? 'border-emerald-500/30 bg-emerald-500/5'
                         : 'border-border bg-muted/30'">
                    <div>
                        <p class="font-semibold text-sm text-foreground">Payment Gateway (Midtrans)</p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ paymentForm.payment_gateway_enabled
                                ? 'Aktif — Pelanggan membayar otomatis via QRIS, VA, Kartu.'
                                : 'Nonaktif — Pelanggan membayar via transfer bank manual.' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="paymentForm.payment_gateway_enabled = !paymentForm.payment_gateway_enabled"
                        class="shrink-0"
                    >
                        <ToggleRight v-if="paymentForm.payment_gateway_enabled" class="h-9 w-9 text-emerald-500" />
                        <ToggleLeft v-else class="h-9 w-9 text-muted-foreground" />
                    </button>
                </div>

                <!-- Transfer bank fields (hanya jika gateway nonaktif) -->
                <div v-if="!paymentForm.payment_gateway_enabled" class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label>Nama Bank</Label>
                        <Input v-model="paymentForm.bank_name" placeholder="BCA" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nomor Rekening</Label>
                        <Input v-model="paymentForm.bank_account_number" placeholder="1234567890" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Atas Nama</Label>
                        <Input v-model="paymentForm.bank_account_holder" placeholder="PT Aether Studio" />
                    </div>
                </div>

                <!-- WhatsApp admin — selalu tampil -->
                <div class="space-y-1.5">
                    <Label>Nomor WhatsApp Admin</Label>
                    <div class="flex gap-2 items-center">
                        <span class="text-sm text-muted-foreground bg-muted px-3 h-9 flex items-center rounded-l-lg border border-r-0 border-input">+</span>
                        <Input
                            v-model="paymentForm.admin_whatsapp"
                            placeholder="628123456789"
                            class="rounded-l-none"
                        />
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                        Format internasional tanpa + (contoh: 628123456789). Ditampilkan sebagai tombol WA di halaman pembayaran.
                    </p>
                </div>

                <!-- Template pesan WA -->
                <div class="space-y-1.5">
                    <Label>Template Pesan WhatsApp</Label>
                    <textarea
                        v-model="paymentForm.whatsapp_template"
                        rows="6"
                        class="w-full resize-none rounded-xl border border-input bg-background px-3 py-2.5 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:border-ring focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 font-mono"
                        placeholder="Halo Admin,&#10;&#10;Order ID: {order_id}&#10;Paket: {plan_name}&#10;Jumlah: {amount}"
                    ></textarea>
                    <div class="flex flex-wrap gap-2 text-[11px]">
                        <span class="text-muted-foreground">Variabel yang tersedia:</span>
                        <code class="rounded bg-muted px-1.5 py-0.5 text-primary">{order_id}</code>
                        <code class="rounded bg-muted px-1.5 py-0.5 text-primary">{amount}</code>
                        <code class="rounded bg-muted px-1.5 py-0.5 text-primary">{plan_name}</code>
                    </div>
                </div>

                <!-- Midtrans fields (hanya jika gateway aktif) -->
                <div v-if="paymentForm.payment_gateway_enabled" class="space-y-4">
                    <div class="flex items-center justify-between p-3 rounded-xl border border-border bg-muted/20">
                        <div>
                            <p class="text-sm font-medium text-foreground">Mode Midtrans</p>
                            <p class="text-xs text-muted-foreground">
                                {{ paymentForm.midtrans_is_production ? 'Produksi (Live)' : 'Sandbox (Testing)' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="paymentForm.midtrans_is_production = !paymentForm.midtrans_is_production"
                        >
                            <ToggleRight v-if="paymentForm.midtrans_is_production" class="h-8 w-8 text-emerald-500" />
                            <ToggleLeft v-else class="h-8 w-8 text-muted-foreground" />
                        </button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label>Server Key</Label>
                            <div class="relative">
                                <Input
                                    v-model="paymentForm.midtrans_server_key"
                                    type="password"
                                    :placeholder="serverKeySaved ? '••••••• (tersimpan, kosongkan jika tidak diubah)' : 'SB-Mid-server-xxxx'"
                                />
                                <span v-if="serverKeySaved && !paymentForm.midtrans_server_key"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-emerald-500 pointer-events-none">
                                    ✓ Tersimpan
                                </span>
                            </div>
                            <p class="text-[11px] text-muted-foreground">Kosongkan jika tidak ingin mengubah key yang tersimpan.</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Client Key</Label>
                            <div class="relative">
                                <Input
                                    v-model="paymentForm.midtrans_client_key"
                                    type="password"
                                    :placeholder="clientKeySaved ? '••••••• (tersimpan, kosongkan jika tidak diubah)' : 'SB-Mid-client-xxxx'"
                                />
                                <span v-if="clientKeySaved && !paymentForm.midtrans_client_key"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-emerald-500 pointer-events-none">
                                    ✓ Tersimpan
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <Button type="submit" :disabled="paymentForm.processing" class="gap-2">
                    <Save class="h-4 w-4" />
                    {{ paymentForm.processing ? 'Menyimpan...' : 'Simpan Pengaturan Pembayaran' }}
                </Button>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         MODAL: Edit Pricing Plan
    ════════════════════════════════════════════════ -->
    <Dialog :open="isEditOpen" @update:open="(v) => { if (!v) closeEdit() }">
        <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Pencil class="h-5 w-5 text-primary" />
                    Edit Paket: {{ editingPlan?.display_name ?? editingPlan?.name }}
                </DialogTitle>
                <DialogDescription>
                    Ubah tampilan, harga, dan benefit yang muncul di landing page.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitPricing" class="space-y-5 py-2">

                <!-- Row 1: display name + badge -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>Nama Tampilan <span class="text-rose-500">*</span></Label>
                        <Input v-model="pricingForm.display_name" placeholder="Free Tier" required />
                        <p v-if="pricingForm.errors.display_name" class="text-xs text-rose-500">{{ pricingForm.errors.display_name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Teks Badge</Label>
                        <Input v-model="pricingForm.badge_text" placeholder="Coba Gratis / Paling Populer" />
                    </div>
                </div>

                <!-- Tagline -->
                <div class="space-y-1.5">
                    <Label>Subtitle / Tagline</Label>
                    <Input v-model="pricingForm.tagline" placeholder="Ideal untuk arsitek individual..." />
                </div>

                <!-- Row 2: harga bulanan + tahunan + periode -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label>Harga Bulanan <span class="text-rose-500">*</span></Label>
                        <Input v-model="pricingForm.price_monthly" placeholder="Rp 149.000" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Harga Tahunan <span class="text-rose-500">*</span></Label>
                        <Input v-model="pricingForm.price_annual" placeholder="Rp 119.000" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Label Periode <span class="text-rose-500">*</span></Label>
                        <Input v-model="pricingForm.period_label" placeholder="per bulan / selamanya" required />
                    </div>
                </div>

                <!-- Row 3: CTA text + URL + sort order -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label>Teks Tombol CTA <span class="text-rose-500">*</span></Label>
                        <Input v-model="pricingForm.cta_text" placeholder="Mulai Sekarang" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>URL Tombol CTA <span class="text-rose-500">*</span></Label>
                        <Input v-model="pricingForm.cta_url" placeholder="/register atau /checkout/pro" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Urutan Tampil</Label>
                        <Input v-model.number="pricingForm.sort_order" type="number" min="0" placeholder="1" />
                    </div>
                </div>

                <!-- Featured toggle -->
                <div class="flex items-center justify-between rounded-xl border border-border p-3 bg-muted/20">
                    <div>
                        <p class="text-sm font-medium text-foreground">Tampilkan sebagai Featured</p>
                        <p class="text-xs text-muted-foreground">Card ini akan ditonjolkan di tengah dengan border indigo.</p>
                    </div>
                    <button
                        type="button"
                        @click="pricingForm.is_featured = !pricingForm.is_featured"
                    >
                        <ToggleRight v-if="pricingForm.is_featured" class="h-8 w-8 text-indigo-500" />
                        <ToggleLeft v-else class="h-8 w-8 text-muted-foreground" />
                    </button>
                </div>

                <!-- Benefits dynamic list -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Benefits / Fitur yang Didapat <span class="text-rose-500">*</span></Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs gap-1"
                            @click="addBenefit"
                        >
                            <Plus class="h-3.5 w-3.5" /> Tambah
                        </Button>
                    </div>

                    <div v-if="pricingForm.benefits.length === 0" class="rounded-xl border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                        Belum ada benefit. Klik "+ Tambah" untuk menambahkan.
                    </div>

                    <TransitionGroup name="benefit-list" tag="div" class="space-y-2">
                        <div
                            v-for="(benefit, idx) in pricingForm.benefits"
                            :key="idx"
                            class="flex items-center gap-2"
                        >
                            <Check class="h-4 w-4 shrink-0 text-emerald-500" />
                            <Input
                                v-model="pricingForm.benefits[idx]"
                                :placeholder="`Benefit ${idx + 1}...`"
                                class="flex-1 text-sm"
                            />
                            <button
                                type="button"
                                class="shrink-0 rounded-lg p-1.5 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-500 transition-colors"
                                title="Hapus benefit"
                                @click="removeBenefit(idx)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </TransitionGroup>

                    <p v-if="pricingForm.errors.benefits" class="text-xs text-rose-500">{{ pricingForm.errors.benefits }}</p>
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="closeEdit">Batal</Button>
                    <Button
                        type="submit"
                        :disabled="pricingForm.processing || pricingForm.benefits.length === 0"
                        class="gap-2"
                    >
                        <Save class="h-4 w-4" />
                        {{ pricingForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Toaster position="top-right" :duration="4000" rich-colors close-button />
</template>

<style scoped>
.benefit-list-enter-active,
.benefit-list-leave-active { transition: all 0.2s ease; }
.benefit-list-enter-from,
.benefit-list-leave-to { opacity: 0; transform: translateX(-10px); }
</style>
