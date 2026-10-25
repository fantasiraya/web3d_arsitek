<script setup lang="ts">
import { watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
    Sparkles,
    Crown,
    CheckCircle2,
    ArrowRight,
    Lock,
} from '@lucide/vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useUpgradeModal } from '@/composables/useUpgradeModal';

const { state, closeUpgradeModal, openUpgradeModal } = useUpgradeModal();

function handleUpgrade() {
    closeUpgradeModal();
    router.visit('/plans');
}

// Watch Inertia flash props for server-side redirects
const page = usePage();
watch(
    () => (page.props as any).flash?.upgrade_modal,
    (val) => {
        if (val) {
            openUpgradeModal({
                feature: val.feature,
                title: val.title,
                description: val.description || val.message,
                requiredPlan: val.required_plan || val.requiredPlan || 'Pro',
            });
        }
    },
    { immediate: true }
);
</script>

<template>
    <Dialog :open="state.isOpen" @update:open="(val) => { if (!val) closeUpgradeModal(); }">
        <DialogContent class="sm:max-w-md gap-0 p-0 overflow-hidden border border-slate-200 dark:border-white/10 bg-white dark:bg-[#12131a] shadow-2xl">
            <!-- Header visual with gradient banner -->
            <div class="relative px-6 pt-6 pb-5 bg-gradient-to-b from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-400/10 dark:via-transparent border-b border-slate-100 dark:border-white/[0.06]">
                <div class="flex items-center justify-between mb-3.5">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-tr from-amber-500 via-amber-500 to-orange-400 text-white shadow-lg shadow-amber-500/25 ring-4 ring-amber-400/15">
                            <Crown v-if="state.requiredPlan === 'Enterprise'" class="h-5 w-5" :stroke-width="2.5" />
                            <Sparkles v-else class="h-5 w-5" :stroke-width="2.5" />
                        </div>
                        <Badge
                            variant="outline"
                            class="text-[11px] font-mono font-bold tracking-wider uppercase border-amber-300 dark:border-amber-400/40 bg-amber-50 dark:bg-amber-400/15 text-amber-700 dark:text-amber-300 px-2.5 py-0.5 rounded-lg"
                        >
                            Paket {{ state.requiredPlan }}
                        </Badge>
                    </div>
                </div>

                <DialogHeader class="text-left space-y-1.5">
                    <DialogTitle class="text-lg font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                        {{ state.title }}
                    </DialogTitle>
                    <DialogDescription class="text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                        {{ state.description }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Features list container -->
            <div class="p-6 space-y-4">
                <div class="rounded-2xl border border-slate-200/80 dark:border-white/10 bg-slate-50/70 dark:bg-white/[0.03] p-4">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1.5">
                        <Sparkles class="h-3.5 w-3.5 text-amber-500" />
                        Akses Fitur Termasuk:
                    </div>
                    <ul class="space-y-2.5">
                        <li
                            v-for="(item, idx) in state.featuresList"
                            :key="idx"
                            class="flex items-start gap-2.5 text-xs text-slate-700 dark:text-slate-200"
                        >
                            <CheckCircle2 class="h-4 w-4 text-emerald-500 shrink-0 mt-0.5" :stroke-width="2.2" />
                            <span class="leading-snug">{{ item }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="px-6 py-4 bg-slate-50/50 dark:bg-white/[0.02] border-t border-slate-100 dark:border-white/[0.06] flex items-center justify-end gap-2.5">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white"
                    @click="closeUpgradeModal"
                >
                    Nanti Saja
                </Button>
                <Button
                    type="button"
                    size="sm"
                    class="bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold text-xs shadow-md shadow-amber-500/20 gap-1.5 px-4 h-9"
                    @click="handleUpgrade"
                >
                    <span>Lihat Paket Langganan</span>
                    <ArrowRight class="h-3.5 w-3.5" />
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
