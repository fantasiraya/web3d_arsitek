<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    Crown,
    ExternalLink,
    Filter,
    MoreHorizontal,
    Pencil,
    RefreshCw,
    Search,
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface UserItem {
    id: string;
    name: string;
    email: string;
    avatar: string | null;
    subscription_status: string;
    status: 'active' | 'suspended';
    project_count: number;
    default_limit: number | null;
    custom_limit: number | 'unlimited' | null;
    effective_limit: number | null;
    is_unlimited: boolean;
    has_override: boolean;
    created_at: string;
    last_active_at: string;
}

interface PlanItem {
    id: string;
    name: string;
    slug: string;
    project_limit: number | null;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    users: Pagination<UserItem>;
    plans: PlanItem[];
    filters: {
        search: string;
        plan: string;
        status: string;
    };
}>();

const searchInput = ref(props.filters.search || '');
const planFilter = ref(props.filters.plan || '');
const statusFilter = ref(props.filters.status || '');

function handleSearch() {
    router.get(
        '/admin/users',
        {
            search: searchInput.value,
            plan: planFilter.value,
            status: statusFilter.value,
        },
        { preserveState: true, preserveScroll: true }
    );
}

function resetFilters() {
    searchInput.value = '';
    planFilter.value = '';
    statusFilter.value = '';
    router.get('/admin/users');
}

// MODAL: Set Custom Project Limit Override
const isLimitModalOpen = ref(false);
const selectedUserForLimit = ref<UserItem | null>(null);
const limitForm = useForm({
    is_unlimited: false,
    custom_limit: 20,
    reason: '',
});

function openLimitModal(user: UserItem) {
    selectedUserForLimit.value = user;
    limitForm.is_unlimited = user.custom_limit === 'unlimited';
    limitForm.custom_limit = typeof user.custom_limit === 'number' ? user.custom_limit : (user.default_limit || 20);
    limitForm.reason = '';
    isLimitModalOpen.value = true;
}

function submitLimitOverride() {
    if (!selectedUserForLimit.value) return;
    limitForm.post(`/admin/users/${selectedUserForLimit.value.id}/limit`, {
        preserveScroll: true,
        onSuccess: () => {
            isLimitModalOpen.value = false;
        },
    });
}

function removeLimitOverride(user: UserItem) {
    if (confirm(`Hapus custom override untuk ${user.name}? Batas proyek akan kembali ke default paket ${user.subscription_status.toUpperCase()}.`)) {
        router.delete(`/admin/users/${user.id}/limit`, { preserveScroll: true });
    }
}

// MODAL: Change Subscription Plan
const isPlanModalOpen = ref(false);
const selectedUserForPlan = ref<UserItem | null>(null);
const planForm = useForm({
    plan_slug: 'pro',
    billing_type: 'monthly',
    reason: '',
});

function openPlanModal(user: UserItem) {
    selectedUserForPlan.value = user;
    planForm.plan_slug = user.subscription_status;
    planForm.billing_type = 'monthly';
    planForm.reason = '';
    isPlanModalOpen.value = true;
}

function submitPlanChange() {
    if (!selectedUserForPlan.value) return;
    planForm.post(`/admin/users/${selectedUserForPlan.value.id}/plan`, {
        preserveScroll: true,
        onSuccess: () => {
            isPlanModalOpen.value = false;
        },
    });
}

// TOGGLE STATUS: Suspend / Activate
function toggleUserStatus(user: UserItem) {
    const newStatus = user.status === 'suspended' ? 'active' : 'suspended';
    const actionLabel = newStatus === 'suspended' ? 'MENANGGUHKAN (Suspend)' : 'MENGAKTIFKAN KEMBALI';
    if (confirm(`Yakin ingin ${actionLabel} akun pengguna ${user.name}?`)) {
        router.patch(`/admin/users/${user.id}/status`, { status: newStatus }, { preserveScroll: true });
    }
}
</script>

<template>
    <AdminLayout title="Manajemen Pengguna">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl text-foreground">
                        User Management
                    </h1>
                    <p class="text-sm text-muted-foreground mt-0.5">
                        Kelola akun pengguna, kuota proyek kustom, serta status paket subscription. Total: {{ users.total }} pengguna.
                    </p>
                </div>
            </div>

            <!-- Filters Bar -->
            <Card class="border-border shadow-xs">
                <CardContent class="p-4">
                    <form @submit.prevent="handleSearch" class="flex flex-col md:flex-row gap-3 items-center">
                        <div class="relative flex-1 w-full">
                            <Search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                v-model="searchInput"
                                placeholder="Cari nama, email, atau User ID..."
                                class="pl-9 h-9 text-xs"
                            />
                        </div>

                        <!-- Filter Plan -->
                        <select
                            v-model="planFilter"
                            @change="handleSearch"
                            class="h-9 px-3 rounded-lg border border-input bg-background text-xs font-medium text-foreground focus:outline-hidden focus:ring-2 focus:ring-primary w-full md:w-40"
                        >
                            <option value="">Semua Plan</option>
                            <option value="free">Free</option>
                            <option value="pro">Pro</option>
                            <option value="enterprise">Enterprise</option>
                        </select>

                        <!-- Filter Status -->
                        <select
                            v-model="statusFilter"
                            @change="handleSearch"
                            class="h-9 px-3 rounded-lg border border-input bg-background text-xs font-medium text-foreground focus:outline-hidden focus:ring-2 focus:ring-primary w-full md:w-36"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                        </select>

                        <Button type="submit" size="sm" class="h-9 text-xs px-4">
                            Filter
                        </Button>

                        <Button
                            v-if="searchInput || planFilter || statusFilter"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-9 text-xs"
                            @click="resetFilters"
                        >
                            Reset
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Users Data Table -->
            <div class="rounded-xl border border-border bg-card shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-muted/50 border-b border-border text-muted-foreground font-medium uppercase tracking-wider">
                            <tr>
                                <th class="py-3 px-4">User</th>
                                <th class="py-3 px-4">Subscription</th>
                                <th class="py-3 px-4">Project Usage</th>
                                <th class="py-3 px-4">Effective Limit</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Created At</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-muted/30 transition-colors">
                                <!-- User Name & Email -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-8 w-8 rounded-full bg-primary/10 text-primary font-semibold flex items-center justify-center shrink-0">
                                            {{ user.name.charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <Link :href="`/admin/users/${user.id}`" class="font-medium hover:underline text-foreground block truncate">
                                                {{ user.name }}
                                            </Link>
                                            <div class="text-[11px] text-muted-foreground truncate">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Subscription Badge -->
                                <td class="py-3.5 px-4">
                                    <Badge
                                        v-if="user.subscription_status === 'enterprise'"
                                        class="bg-primary text-primary-foreground font-semibold uppercase text-[10px] gap-1"
                                    >
                                        <Crown class="h-3 w-3" /> ENTERPRISE
                                    </Badge>
                                    <Badge
                                        v-else-if="user.subscription_status === 'pro'"
                                        class="bg-amber-500 text-white font-semibold uppercase text-[10px] gap-1"
                                    >
                                        <Sparkles class="h-3 w-3" /> PRO
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="secondary"
                                        class="uppercase text-[10px] font-mono border"
                                    >
                                        FREE
                                    </Badge>
                                </td>

                                <!-- Project Usage (e.g. 5 / 20 or 5 / Unlimited) + Progress Bar -->
                                <td class="py-3.5 px-4 min-w-36">
                                    <div class="flex items-center justify-between text-xs font-semibold mb-1">
                                        <span>{{ user.project_count }}</span>
                                        <span class="text-muted-foreground font-normal">
                                            / {{ user.is_unlimited ? 'Unlimited' : user.effective_limit }}
                                        </span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-muted overflow-hidden">
                                        <div
                                            v-if="!user.is_unlimited && user.effective_limit"
                                            class="h-full rounded-full transition-all"
                                            :class="[
                                                user.project_count > user.effective_limit
                                                    ? 'bg-destructive'
                                                    : (user.project_count / user.effective_limit) >= 0.8
                                                      ? 'bg-amber-500'
                                                      : 'bg-primary'
                                            ]"
                                            :style="{ width: `${Math.min(100, (user.project_count / user.effective_limit) * 100)}%` }"
                                        ></div>
                                        <div
                                            v-else
                                            class="h-full bg-primary/70 rounded-full w-full"
                                        ></div>
                                    </div>
                                </td>

                                <!-- Effective Limit breakdown & Custom Override badge -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium text-xs">
                                            {{ user.is_unlimited ? 'Unlimited' : `${user.effective_limit} projects` }}
                                        </span>
                                        <Badge
                                            v-if="user.has_override"
                                            variant="outline"
                                            class="text-[9px] px-1 py-0 bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/30 font-medium"
                                        >
                                            Custom
                                        </Badge>
                                    </div>
                                    <div class="text-[10px] text-muted-foreground mt-0.5">
                                        Default: {{ user.default_limit ?? 'Unlimited' }}
                                    </div>
                                </td>

                                <!-- Account Status -->
                                <td class="py-3.5 px-4">
                                    <Badge
                                        v-if="user.status === 'active'"
                                        variant="outline"
                                        class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 text-[10px]"
                                    >
                                        Active
                                    </Badge>
                                    <Badge
                                        v-else
                                        variant="destructive"
                                        class="text-[10px]"
                                    >
                                        Suspended
                                    </Badge>
                                </td>

                                <!-- Created At -->
                                <td class="py-3.5 px-4 text-muted-foreground text-[11px]">
                                    {{ user.created_at }}
                                </td>

                                <!-- Actions Dropdown -->
                                <td class="py-3.5 px-4 text-right">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="h-8 w-8">
                                                <MoreHorizontal class="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" class="w-48 text-xs">
                                            <DropdownMenuLabel>Tindakan Pengguna</DropdownMenuLabel>
                                            <DropdownMenuItem :as-child="true">
                                                <Link :href="`/admin/users/${user.id}`">
                                                    <ExternalLink class="mr-2 h-3.5 w-3.5" /> Detail Lengkap
                                                </Link>
                                            </DropdownMenuItem>

                                            <DropdownMenuItem @click="openLimitModal(user)">
                                                <Sliders class="mr-2 h-3.5 w-3.5" /> Set Custom Limit
                                            </DropdownMenuItem>

                                            <DropdownMenuItem
                                                v-if="user.has_override"
                                                @click="removeLimitOverride(user)"
                                                class="text-amber-600 dark:text-amber-400"
                                            >
                                                <RefreshCw class="mr-2 h-3.5 w-3.5" /> Restore Default Limit
                                            </DropdownMenuItem>

                                            <DropdownMenuItem @click="openPlanModal(user)">
                                                <Sparkles class="mr-2 h-3.5 w-3.5" /> Ubah Subscription
                                            </DropdownMenuItem>

                                            <DropdownMenuSeparator />

                                            <DropdownMenuItem
                                                @click="toggleUserStatus(user)"
                                                :class="user.status === 'active' ? 'text-destructive font-medium' : 'text-emerald-600'"
                                            >
                                                <component :is="user.status === 'active' ? UserMinus : UserCheck" class="mr-2 h-3.5 w-3.5" />
                                                {{ user.status === 'active' ? 'Suspend User' : 'Activate User' }}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>

                            <tr v-if="users.data.length === 0">
                                <td colspan="7" class="py-8 text-center text-muted-foreground">
                                    Tidak ada pengguna yang cocok dengan kriteria filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div v-if="users.last_page > 1" class="p-4 border-t border-border flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">
                        Halaman {{ users.current_page }} dari {{ users.last_page }}
                    </span>
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            class="px-3 py-1.5 rounded-lg border border-border bg-background hover:bg-muted font-medium"
                        >
                            Sebelumnya
                        </Link>
                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            class="px-3 py-1.5 rounded-lg border border-border bg-background hover:bg-muted font-medium"
                        >
                            Berikutnya
                        </Link>
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
                        Admin override untuk pengguna <strong>{{ selectedUserForLimit?.name }}</strong>.
                        Batas kustom memiliki prioritas di atas batas bawaan subscription plan.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitLimitOverride" class="space-y-4 py-2">
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-muted/40 border border-border">
                        <input
                            type="checkbox"
                            id="unlimited_checkbox"
                            v-model="limitForm.is_unlimited"
                            class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                        />
                        <Label for="unlimited_checkbox" class="text-xs font-semibold cursor-pointer">
                            Berikan Kuota Unlimited (Tanpa Batas Proyek)
                        </Label>
                    </div>

                    <div v-if="!limitForm.is_unlimited" class="space-y-1.5">
                        <Label for="custom_limit_val" class="text-xs font-medium">Batas Maksimal Proyek Baru</Label>
                        <Input
                            id="custom_limit_val"
                            type="number"
                            v-model.number="limitForm.custom_limit"
                            min="1"
                            max="10000"
                            required
                            class="h-9 text-xs"
                            placeholder="Contoh: 30 atau 50"
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Default paket {{ selectedUserForLimit?.subscription_status.toUpperCase() }}: {{ selectedUserForLimit?.default_limit ?? 'Unlimited' }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="limit_reason" class="text-xs font-medium">Alasan / Catatan Override (Opsional)</Label>
                        <Input
                            id="limit_reason"
                            v-model="limitForm.reason"
                            class="h-9 text-xs"
                            placeholder="Contoh: Permintaan ekspansi proyek klien Q3"
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

        <!-- MODAL: Change User Subscription -->
        <Dialog :open="isPlanModalOpen" @update:open="isPlanModalOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ubah Subscription Pengguna</DialogTitle>
                    <DialogDescription class="text-xs">
                        Pilih paket baru untuk <strong>{{ selectedUserForPlan?.name }}</strong>.
                        Proyek pengguna yang sudah ada tidak akan dihapus.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitPlanChange" class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-medium">Pilih Paket Subscription</Label>
                        <select
                            v-model="planForm.plan_slug"
                            class="w-full h-9 px-3 rounded-lg border border-input bg-background text-xs font-medium text-foreground focus:outline-hidden focus:ring-2 focus:ring-primary"
                        >
                            <option v-for="p in plans" :key="p.slug" :value="p.slug">
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
                            placeholder="Contoh: Upgrade paket sponsorship / manual invoice"
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

