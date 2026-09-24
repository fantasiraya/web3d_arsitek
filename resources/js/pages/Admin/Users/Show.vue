<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeft,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    CreditCard,
    Crown,
    ExternalLink,
    FolderKanban,
    HardDrive,
    Layers,
    Pencil,
    RefreshCw,
    Shield,
    ShieldAlert,
    Sliders,
    Sparkles,
    UserCheck,
    UserMinus,
    Users,
    X,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface UserDetails {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    status: 'active' | 'suspended';
    subscription_status: string;
    created_at: string;
    last_active_at: string;
}

interface PlanDetails {
    current_plan: any;
    default_limit: number | null;
    custom_limit: number | 'unlimited' | null;
    effective_limit: number | null;
    is_unlimited: boolean;
    has_override: boolean;
    override_reason: string | null;
}

interface ProjectUsage {
    count: number;
    limit: number | null;
    remaining: number | null;
    usage_percentage: number;
    is_exceeded: boolean;
}

interface ProjectItem {
    id: string;
    title: string;
    slug: string;
    file_size_bytes: number;
    current_revision_count: number;
    max_revisions_allowed: number;
    versions_count: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    user: UserDetails;
    plan_details: PlanDetails;
    project_usage: ProjectUsage;
    projects: ProjectItem[];
    active_subscription: any;
    subscriptions: any[];
    audit_logs: any[];
    available_plans: any[];
}>();

// MODAL: Set Custom Project Limit Override
const isLimitModalOpen = ref(false);
const limitForm = useForm({
    is_unlimited: props.plan_details.custom_limit === 'unlimited',
    custom_limit: typeof props.plan_details.custom_limit === 'number'
        ? props.plan_details.custom_limit
        : (props.plan_details.default_limit || 20),
    reason: props.plan_details.override_reason || '',
});

function openLimitModal() {
    limitForm.is_unlimited = props.plan_details.custom_limit === 'unlimited';
    limitForm.custom_limit = typeof props.plan_details.custom_limit === 'number'
        ? props.plan_details.custom_limit
        : (props.plan_details.default_limit || 20);
    limitForm.reason = props.plan_details.override_reason || '';
    isLimitModalOpen.value = true;
}

function submitLimitOverride() {
    limitForm.post(`/admin/users/${props.user.id}/limit`, {
        preserveScroll: true,
        onSuccess: () => {
            isLimitModalOpen.value = false;
        },
    });
}

function removeLimitOverride() {
    if (confirm(`Hapus custom override untuk ${props.user.name}? Batas proyek akan kembali ke default paket.`)) {
        router.delete(`/admin/users/${props.user.id}/limit`, { preserveScroll: true });
    }
}

// MODAL: Change Plan
const isPlanModalOpen = ref(false);
const planForm = useForm({
    plan_slug: props.user.subscription_status,
    billing_type: 'monthly',
    reason: '',
});

function openPlanModal() {
    planForm.plan_slug = props.user.subscription_status;
    planForm.billing_type = 'monthly';
    planForm.reason = '';
    isPlanModalOpen.value = true;
}

function submitPlanChange() {
    planForm.post(`/admin/users/${props.user.id}/plan`, {
        preserveScroll: true,
        onSuccess: () => {
            isPlanModalOpen.value = false;
        },
    });
}

// Toggle Status
function toggleUserStatus() {
    const newStatus = props.user.status === 'suspended' ? 'active' : 'suspended';
    const actionLabel = newStatus === 'suspended' ? 'MENANGGUHKAN (Suspend)' : 'MENGAKTIFKAN KEMBALI';
    if (confirm(`Yakin ingin ${actionLabel} akun pengguna ${props.user.name}?`)) {
        router.patch(`/admin/users/${props.user.id}/status`, { status: newStatus }, { preserveScroll: true });
    }
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<template>
    <AdminLayout :title="`User: ${user.name}`">
        <div class="space-y-6">
            <!-- Navigation back & Actions -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <Link href="/admin/users" class="p-2 rounded-lg border border-border bg-card hover:bg-muted text-muted-foreground hover:text-foreground transition-colors">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold md:text-2xl text-foreground">{{ user.name }}</h1>
                            <Badge
                                v-if="user.status === 'active'"
                                variant="outline"
                                class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px]"
                            >
                                Active
                            </Badge>
                            <Badge v-else variant="destructive" class="text-[10px]">Suspended</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">User ID: {{ user.id }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="sm" class="text-xs h-8 gap-1.5" @click="openLimitModal">
                        <Sliders class="h-3.5 w-3.5 text-primary" />
                        Set Custom Limit
                    </Button>

                    <Button
                        v-if="plan_details.has_override"
                        variant="outline"
                        size="sm"
                        class="text-xs h-8 gap-1.5 text-amber-600 dark:text-amber-400"
                        @click="removeLimitOverride"
                    >
                        <RefreshCw class="h-3.5 w-3.5" />
                        Restore Default Limit
                    </Button>

                    <Button variant="outline" size="sm" class="text-xs h-8 gap-1.5" @click="openPlanModal">
                        <Sparkles class="h-3.5 w-3.5 text-amber-500" />
                        Change Subscription
                    </Button>

                    <Button
                        :variant="user.status === 'active' ? 'destructive' : 'outline'"
                        size="sm"
                        class="text-xs h-8 gap-1.5"
                        @click="toggleUserStatus"
                    >
                        <component :is="user.status === 'active' ? UserMinus : UserCheck" class="h-3.5 w-3.5" />
                        {{ user.status === 'active' ? 'Suspend User' : 'Activate User' }}
                    </Button>
                </div>
            </div>

            <!-- Over Limit Warning Banner if downgraded with excess projects -->
            <div
                v-if="project_usage.is_exceeded"
                class="flex items-start gap-3 p-4 rounded-xl border border-amber-500/30 bg-amber-50/50 dark:bg-amber-950/20 text-amber-900 dark:text-amber-200"
            >
                <AlertTriangle class="h-5 w-5 shrink-0 text-amber-600 mt-0.5" />
                <div class="text-xs space-y-1">
                    <div class="font-semibold text-sm">Batas Proyek Terlampaui (Project Limit Exceeded)</div>
                    <p>
                        Pengguna saat ini memiliki <strong>{{ project_usage.count }} proyek</strong>, sementara batas efektif paket adalah <strong>{{ project_usage.limit }} proyek</strong>.
                        Proyek lama tetap aman dan tidak dihapus, namun pengguna diblokir dari membuat proyek baru hingga kuota mencukupi atau admin menaikkan limit kustom.
                    </p>
                </div>
            </div>

            <!-- 3 Columns Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Card 1: User Profile -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold flex items-center justify-between">
                            <span>Informasi Akun</span>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Email:</span>
                            <span class="font-medium">{{ user.email }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Terdaftar:</span>
                            <span>{{ user.created_at }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Terakhir Aktif:</span>
                            <span>{{ user.last_active_at }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Status Akun:</span>
                            <span class="font-semibold" :class="user.status === 'active' ? 'text-emerald-600' : 'text-destructive'">
                                {{ user.status.toUpperCase() }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Card 2: Subscription Details -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold flex items-center justify-between">
                            <span>Subscription Plan</span>
                            <Crown class="h-4 w-4 text-primary" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div class="flex justify-between border-b pb-2 items-center">
                            <span class="text-muted-foreground">Current Plan:</span>
                            <Badge
                                v-if="user.subscription_status === 'enterprise'"
                                class="bg-primary text-primary-foreground font-semibold uppercase text-[10px]"
                            >
                                ENTERPRISE
                            </Badge>
                            <Badge
                                v-else-if="user.subscription_status === 'pro'"
                                class="bg-amber-500 text-white font-semibold uppercase text-[10px]"
                            >
                                PRO TIER
                            </Badge>
                            <Badge v-else variant="secondary" class="uppercase text-[10px] font-mono border">
                                FREE TIER
                            </Badge>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Status Langganan:</span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400 capitalize">
                                {{ active_subscription?.status ?? 'Active' }}
                            </span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Mulai:</span>
                            <span>{{ active_subscription?.started_at ? new Date(active_subscription.started_at).toLocaleDateString('id-ID') : user.created_at }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Billing Type:</span>
                            <span class="font-medium capitalize">{{ active_subscription?.billing_type ?? 'Monthly' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Card 3: Project Limit Breakdown -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold flex items-center justify-between">
                            <span>Project Quota & Limits</span>
                            <FolderKanban class="h-4 w-4 text-blue-500" />
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-xs">
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground">Default Plan Limit:</span>
                            <span class="font-medium">{{ plan_details.default_limit ?? 'Unlimited' }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2 items-center">
                            <span class="text-muted-foreground">Custom Admin Override:</span>
                            <Badge v-if="plan_details.has_override" variant="outline" class="text-[10px] bg-blue-500/10 text-blue-600 border-blue-500/30">
                                {{ plan_details.custom_limit }}
                            </Badge>
                            <span v-else class="text-muted-foreground italic">None (Default)</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="text-muted-foreground font-semibold">Effective Limit:</span>
                            <span class="font-bold text-primary">
                                {{ plan_details.is_unlimited ? 'Unlimited' : `${plan_details.effective_limit} projects` }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Sisa Kuota:</span>
                            <span class="font-semibold">
                                {{ plan_details.is_unlimited ? 'Unlimited' : `${project_usage.remaining} projects` }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Usage Progress Bar Box -->
            <Card class="border-border shadow-xs">
                <CardContent class="p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2 text-xs">
                        <div>
                            <span class="font-semibold text-sm">Project Usage: </span>
                            <span class="font-mono text-sm font-bold text-primary">{{ project_usage.count }}</span>
                            <span class="text-muted-foreground"> / {{ plan_details.is_unlimited ? 'Unlimited' : plan_details.effective_limit }} projects</span>
                        </div>
                        <div class="text-muted-foreground">
                            {{ plan_details.is_unlimited ? '0% (Unlimited)' : `${project_usage.usage_percentage}% terpakai` }}
                        </div>
                    </div>

                    <div class="w-full h-3 rounded-full bg-muted overflow-hidden">
                        <div
                            v-if="!plan_details.is_unlimited && plan_details.effective_limit"
                            class="h-full rounded-full transition-all"
                            :class="[
                                project_usage.is_exceeded
                                    ? 'bg-destructive'
                                    : project_usage.usage_percentage >= 80
                                      ? 'bg-amber-500'
                                      : 'bg-primary'
                            ]"
                            :style="{ width: `${project_usage.usage_percentage}%` }"
                        ></div>
                        <div v-else class="h-full bg-primary/70 rounded-full w-full"></div>
                    </div>
                </CardContent>
            </Card>

            <!-- Projects Table -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold tracking-tight">Daftar Proyek Milik Pengguna ({{ projects.length }})</h2>
                </div>

                <div class="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-muted/50 border-b border-border text-muted-foreground font-medium uppercase tracking-wider">
                                <tr>
                                    <th class="py-3 px-4">Nama Proyek</th>
                                    <th class="py-3 px-4">Ukuran Model</th>
                                    <th class="py-3 px-4">Revisi Digunakan</th>
                                    <th class="py-3 px-4">Versi</th>
                                    <th class="py-3 px-4">Dibuat Pada</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="p in projects" :key="p.id" class="hover:bg-muted/30 transition-colors">
                                    <td class="py-3.5 px-4 font-medium text-foreground">
                                        <div class="font-semibold">{{ p.title }}</div>
                                        <div class="text-[10px] text-muted-foreground font-mono">{{ p.slug }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-muted-foreground">
                                        {{ formatBytes(p.file_size_bytes) }}
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-medium">{{ p.current_revision_count }}</span>
                                        <span class="text-muted-foreground"> / {{ p.max_revisions_allowed }} revisi</span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <Badge variant="outline" class="text-[10px] font-mono">
                                            v{{ p.versions_count }}
                                        </Badge>
                                    </td>
                                    <td class="py-3.5 px-4 text-muted-foreground text-[11px]">
                                        {{ p.created_at }}
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <Link :href="`/projects/${p.id}/viewer`" target="_blank">
                                            <Button variant="outline" size="sm" class="h-7 text-xs gap-1">
                                                <ExternalLink class="h-3 w-3" />
                                                Buka 3D Viewer
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>

                                <tr v-if="projects.length === 0">
                                    <td colspan="6" class="py-8 text-center text-muted-foreground">
                                        Pengguna ini belum membuat proyek 3D apa pun.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Audit Trail for this User -->
            <div class="space-y-3">
                <h2 class="text-base font-bold tracking-tight">Riwayat Audit Perubahan Admin</h2>
                <div class="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-muted/50 border-b border-border text-muted-foreground font-medium uppercase tracking-wider">
                                <tr>
                                    <th class="py-3 px-4">Timestamp</th>
                                    <th class="py-3 px-4">Admin Pelaku</th>
                                    <th class="py-3 px-4">Aksi</th>
                                    <th class="py-3 px-4">Ringkasan Perubahan</th>
                                    <th class="py-3 px-4">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="log in audit_logs" :key="log.id" class="hover:bg-muted/30">
                                    <td class="py-3 px-4 text-muted-foreground text-[11px] whitespace-nowrap">{{ log.created_at }}</td>
                                    <td class="py-3 px-4 font-medium">{{ log.admin_name }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px]">{{ log.action }}</td>
                                    <td class="py-3 px-4">{{ log.metadata?.summary ?? '-' }}</td>
                                    <td class="py-3 px-4 text-muted-foreground font-mono text-[11px]">{{ log.ip_address ?? '-' }}</td>
                                </tr>
                                <tr v-if="audit_logs.length === 0">
                                    <td colspan="5" class="py-6 text-center text-muted-foreground">
                                        Belum ada riwayat audit administratif untuk pengguna ini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Set Custom Project Limit -->
        <Dialog :open="isLimitModalOpen" @update:open="isLimitModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Set Custom Project Limit</DialogTitle>
                    <DialogDescription class="text-xs">
                        Admin override untuk pengguna <strong>{{ user.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitLimitOverride" class="space-y-4 py-2">
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border border-border">
                        <input
                            type="checkbox"
                            id="unlimited_checkbox_show"
                            v-model="limitForm.is_unlimited"
                            class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <Label for="unlimited_checkbox_show" class="text-xs font-semibold cursor-pointer">
                            Berikan Kuota Unlimited (Tanpa Batas Proyek)
                        </Label>
                    </div>

                    <div v-if="!limitForm.is_unlimited" class="space-y-1.5">
                        <Label for="custom_limit_val_show" class="text-xs font-medium">Batas Maksimal Proyek Baru</Label>
                        <Input
                            id="custom_limit_val_show"
                            type="number"
                            v-model.number="limitForm.custom_limit"
                            min="1"
                            max="10000"
                            required
                            class="h-9 text-xs"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="limit_reason_show" class="text-xs font-medium">Alasan / Catatan Override</Label>
                        <Input
                            id="limit_reason_show"
                            v-model="limitForm.reason"
                            class="h-9 text-xs"
                            placeholder="Alasan perubahan limit"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" size="sm" @click="isLimitModalOpen = false">
                            Batal
                        </Button>
                        <Button type="submit" size="sm" :disabled="limitForm.processing">
                            Simpan Override Limit
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- MODAL: Change Plan -->
        <Dialog :open="isPlanModalOpen" @update:open="isPlanModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ubah Subscription Pengguna</DialogTitle>
                    <DialogDescription class="text-xs">
                        Pilih paket baru untuk <strong>{{ user.name }}</strong>.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitPlanChange" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-medium">Pilih Paket Subscription</Label>
                        <select
                            v-model="planForm.plan_slug"
                            class="w-full h-9 px-3 rounded-lg border border-input bg-background text-xs font-medium text-foreground focus:outline-hidden focus:ring-2 focus:ring-primary"
                        >
                            <option v-for="p in available_plans" :key="p.slug" :value="p.slug">
                                {{ p.name }} (Limit: {{ p.project_limit ?? 'Unlimited' }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-medium">Tipe Billing</Label>
                        <select
                            v-model="planForm.billing_type"
                            class="w-full h-9 px-3 rounded-lg border border-input bg-background text-xs font-medium text-foreground focus:outline-hidden focus:ring-2 focus:ring-primary"
                        >
                            <option value="monthly">Monthly</option>
                            <option value="annual">Annual</option>
                            <option value="lifetime">Lifetime</option>
                            <option value="custom">Custom Enterprise Contract</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-medium">Alasan Perubahan</Label>
                        <Input
                            v-model="planForm.reason"
                            class="h-9 text-xs"
                            placeholder="Alasan perubahan paket"
                        />
                    </div>

                    <DialogFooter class="pt-3">
                        <Button type="button" variant="outline" size="sm" @click="isPlanModalOpen = false">
                            Batal
                        </Button>
                        <Button type="submit" size="sm" :disabled="planForm.processing">
                            Terapkan Paket Baru
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
