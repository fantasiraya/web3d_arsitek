<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowUpRight,
    Calendar,
    CheckCircle2,
    Crown,
    Filter,
    FolderKanban,
    Layers,
    Sparkles,
    TrendingUp,
    UserCheck,
    UserMinus,
    UserPlus,
    Users,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface Stats {
    total_users: number;
    active_users: number;
    free_users: number;
    pro_users: number;
    enterprise_users: number;
    total_projects: number;
    projects_created_this_month: number;
    new_users_this_month: number;
}

interface Charts {
    labels: string[];
    user_growth: number[];
    project_growth: number[];
    subscription_distribution: {
        free: number;
        pro: number;
        enterprise: number;
    };
    project_distribution: {
        free: number;
        pro: number;
        enterprise: number;
    };
    user_activity: {
        active_recent: number;
        active_total: number;
        suspended: number;
    };
}

interface Filters {
    filter: string;
    from: string;
    to: string;
}

const props = defineProps<{
    stats: Stats;
    charts: Charts;
    filters: Filters;
}>();

const selectedFilter = ref(props.filters.filter || '30days');
const customFrom = ref(props.filters.from || '');
const customTo = ref(props.filters.to || '');
const showCustomRange = ref(props.filters.filter === 'custom');

function applyFilter(filterName: string) {
    selectedFilter.value = filterName;
    if (filterName === 'custom') {
        showCustomRange.value = true;
        return;
    }
    showCustomRange.value = false;
    router.get(
        '/admin/dashboard',
        { filter: filterName },
        { preserveState: true, preserveScroll: true }
    );
}

function submitCustomRange() {
    if (!customFrom.value || !customTo.value) return;
    router.get(
        '/admin/dashboard',
        {
            filter: 'custom',
            from: customFrom.value,
            to: customTo.value,
        },
        { preserveState: true, preserveScroll: true }
    );
}

// Compute SVG chart coordinates for growth
function getMaxVal(arr: number[]): number {
    const max = Math.max(...arr, 5);
    return Math.ceil(max * 1.2);
}

function getPolylinePoints(data: number[], height: number, width: number): string {
    if (!data.length) return '';
    const max = getMaxVal(data);
    const stepX = width / Math.max(1, data.length - 1);
    return data
        .map((val, index) => {
            const x = index * stepX;
            const y = height - (val / max) * (height - 20) - 10;
            return `${x},${y}`;
        })
        .join(' ');
}
</script>

<template>
    <AdminLayout title="Admin Dashboard">
        <div class="space-y-8">
            <!-- Header with Title & Date Filters -->
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl text-foreground">
                        SaaS Executive Dashboard
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Ringkasan metrik pengguna, pertumbuhan proyek, dan distribusi paket subscription secara real-time.
                    </p>
                </div>

                <!-- Date Range Selector -->
                <div class="flex flex-wrap items-center gap-1.5 bg-muted/60 p-1.5 rounded-xl border border-border">
                    <button
                        v-for="btn in [
                            { id: 'today', label: 'Today' },
                            { id: '7days', label: '7 Days' },
                            { id: '30days', label: '30 Days' },
                            { id: 'this_month', label: 'This Month' },
                            { id: 'last_month', label: 'Last Month' },
                            { id: 'custom', label: 'Custom' },
                        ]"
                        :key="btn.id"
                        @click="applyFilter(btn.id)"
                        :class="[
                            'px-3 py-1.5 text-xs font-medium rounded-lg transition-all',
                            selectedFilter === btn.id
                                ? 'bg-background text-foreground shadow-xs font-semibold'
                                : 'text-muted-foreground hover:text-foreground hover:bg-background/50'
                        ]"
                    >
                        {{ btn.label }}
                    </button>
                </div>
            </div>

            <!-- Custom Date Range Form if selected -->
            <div v-if="showCustomRange" class="flex flex-wrap items-center gap-3 p-4 rounded-xl bg-card border border-border shadow-xs">
                <Calendar class="h-4 w-4 text-primary" />
                <span class="text-xs font-medium">Custom Range:</span>
                <Input type="date" v-model="customFrom" class="w-40 h-8 text-xs" />
                <span class="text-xs text-muted-foreground">sampai</span>
                <Input type="date" v-model="customTo" class="w-40 h-8 text-xs" />
                <Button size="sm" class="h-8 text-xs" @click="submitCustomRange">
                    Terapkan Rentang
                </Button>
            </div>

            <!-- STATISTIC CARDS (8 Cards) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Total Users -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Total Users</span>
                            <Users class="h-4 w-4 text-primary" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_users }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Terdaftar di platform</p>
                    </CardContent>
                </Card>

                <!-- Active Users -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Active Users</span>
                            <UserCheck class="h-4 w-4 text-emerald-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.active_users }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Akun aktif (tidak suspend)</p>
                    </CardContent>
                </Card>

                <!-- Free Users -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Free Users</span>
                            <Badge variant="secondary" class="text-[10px] font-mono">1 Project</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.free_users }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Tier Free eksplorasi</p>
                    </CardContent>
                </Card>

                <!-- Pro Users -->
                <Card class="border-border shadow-xs bg-linear-to-br from-card to-amber-500/5">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Pro Users</span>
                            <Sparkles class="h-4 w-4 text-amber-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pro_users }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Tier Pro (20 projects)</p>
                    </CardContent>
                </Card>

                <!-- Enterprise Users -->
                <Card class="border-border shadow-xs bg-linear-to-br from-card to-primary/5">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Enterprise Users</span>
                            <Crown class="h-4 w-4 text-primary" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-primary">{{ stats.enterprise_users }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Unlimited scale tier</p>
                    </CardContent>
                </Card>

                <!-- Total Projects -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Total Projects</span>
                            <FolderKanban class="h-4 w-4 text-blue-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total_projects }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Model 3D arsitektur</p>
                    </CardContent>
                </Card>

                <!-- Projects Created This Month -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">Projects This Month</span>
                            <TrendingUp class="h-4 w-4 text-indigo-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.projects_created_this_month }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Pertumbuhan proyek bulan ini</p>
                    </CardContent>
                </Card>

                <!-- New Users This Month -->
                <Card class="border-border shadow-xs">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between text-muted-foreground">
                            <span class="text-xs font-medium uppercase tracking-wider">New Users This Month</span>
                            <UserPlus class="h-4 w-4 text-teal-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-teal-600 dark:text-teal-400">{{ stats.new_users_this_month }}</div>
                        <p class="text-[11px] text-muted-foreground mt-1">Akuisisi pengguna baru</p>
                    </CardContent>
                </Card>
            </div>

            <!-- CHARTS SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- User Growth Chart -->
                <Card class="border-border shadow-xs">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-semibold">User Growth Timeline</CardTitle>
                                <CardDescription class="text-xs">Pertambahan registrasi pengguna dalam periode ini</CardDescription>
                            </div>
                            <Badge variant="outline" class="font-mono text-xs">Users</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="h-48 w-full relative flex flex-col justify-end">
                            <svg class="w-full h-40 overflow-visible" viewBox="0 0 500 160" preserveAspectRatio="none">
                                <polyline
                                    fill="none"
                                    stroke="var(--color-primary, #6366f1)"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :points="getPolylinePoints(charts.user_growth, 160, 500)"
                                />
                            </svg>
                            <!-- X Axis labels -->
                            <div class="flex justify-between text-[10px] text-muted-foreground mt-2 border-t pt-1">
                                <span>{{ charts.labels[0] ?? '' }}</span>
                                <span>{{ charts.labels[Math.floor(charts.labels.length / 2)] ?? '' }}</span>
                                <span>{{ charts.labels[charts.labels.length - 1] ?? '' }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Project Growth Chart -->
                <Card class="border-border shadow-xs">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-semibold">Project Growth Timeline</CardTitle>
                                <CardDescription class="text-xs">Jumlah pembuatan proyek 3D baru</CardDescription>
                            </div>
                            <Badge variant="outline" class="font-mono text-xs">Projects</Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="h-48 w-full relative flex flex-col justify-end">
                            <svg class="w-full h-40 overflow-visible" viewBox="0 0 500 160" preserveAspectRatio="none">
                                <polyline
                                    fill="none"
                                    stroke="#10b981"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    :points="getPolylinePoints(charts.project_growth, 160, 500)"
                                />
                            </svg>
                            <div class="flex justify-between text-[10px] text-muted-foreground mt-2 border-t pt-1">
                                <span>{{ charts.labels[0] ?? '' }}</span>
                                <span>{{ charts.labels[Math.floor(charts.labels.length / 2)] ?? '' }}</span>
                                <span>{{ charts.labels[charts.labels.length - 1] ?? '' }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Subscription Distribution -->
                <Card class="border-border shadow-xs">
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">Subscription Distribution</CardTitle>
                        <CardDescription class="text-xs">Proporsi pengguna berdasarkan tingkatan paket</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full bg-slate-400 inline-block"></span> Free Tier
                                    </span>
                                    <span>{{ charts.subscription_distribution.free }} user</span>
                                </div>
                                <div class="w-full h-2.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full bg-slate-400 rounded-full transition-all"
                                        :style="{ width: `${stats.total_users > 0 ? (charts.subscription_distribution.free / stats.total_users) * 100 : 0}%` }"
                                    ></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full bg-amber-500 inline-block"></span> Pro Tier
                                    </span>
                                    <span>{{ charts.subscription_distribution.pro }} user</span>
                                </div>
                                <div class="w-full h-2.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full bg-amber-500 rounded-full transition-all"
                                        :style="{ width: `${stats.total_users > 0 ? (charts.subscription_distribution.pro / stats.total_users) * 100 : 0}%` }"
                                    ></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-medium mb-1">
                                    <span class="flex items-center gap-1.5">
                                        <span class="h-2 w-2 rounded-full bg-primary inline-block"></span> Enterprise Tier
                                    </span>
                                    <span>{{ charts.subscription_distribution.enterprise }} user</span>
                                </div>
                                <div class="w-full h-2.5 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full bg-primary rounded-full transition-all"
                                        :style="{ width: `${stats.total_users > 0 ? (charts.subscription_distribution.enterprise / stats.total_users) * 100 : 0}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Project Distribution per Plan & User Activity -->
                <Card class="border-border shadow-xs">
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">Project Distribution per Plan</CardTitle>
                        <CardDescription class="text-xs">Distribusi kepemilikan proyek 3D pada setiap tier</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="p-3 rounded-xl bg-muted/40 border border-border">
                                <div class="text-xs text-muted-foreground font-medium">Free Tier</div>
                                <div class="text-xl font-bold mt-1">{{ charts.project_distribution.free }}</div>
                                <div class="text-[10px] text-muted-foreground mt-0.5">Projects</div>
                            </div>

                            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                                <div class="text-xs text-amber-700 dark:text-amber-400 font-medium">Pro Tier</div>
                                <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ charts.project_distribution.pro }}</div>
                                <div class="text-[10px] text-muted-foreground mt-0.5">Projects</div>
                            </div>

                            <div class="p-3 rounded-xl bg-primary/10 border border-primary/20">
                                <div class="text-xs text-primary font-medium">Enterprise</div>
                                <div class="text-xl font-bold text-primary mt-1">{{ charts.project_distribution.enterprise }}</div>
                                <div class="text-[10px] text-muted-foreground mt-0.5">Projects</div>
                            </div>
                        </div>

                        <!-- User Activity Status Strip -->
                        <div class="pt-4 border-t border-border flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <Activity class="h-4 w-4 text-emerald-500" />
                                <span>Aktif 7 hari terakhir: <strong>{{ charts.user_activity.active_recent }}</strong></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <UserMinus class="h-4 w-4 text-destructive" />
                                <span>Suspended: <strong>{{ charts.user_activity.suspended }}</strong></span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>

