<script setup lang="ts">
/**
 * ConfirmDialog — Global confirm dialog yang dipasang satu kali di layout.
 * Dikendalikan via useConfirm() composable.
 * Support dark mode otomatis lewat CSS variables shadcn.
 */
import { AlertTriangle, Trash2, Info, AlertCircle } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

const { state, handleConfirm, handleCancel } = useConfirm();

const iconMap = {
    trash:   { component: Trash2,        class: 'text-rose-500' },
    warning: { component: AlertTriangle, class: 'text-amber-500' },
    info:    { component: Info,          class: 'text-blue-500' },
};
</script>

<template>
    <Dialog :open="state.open" @update:open="(v) => { if (!v) handleCancel() }">
        <DialogContent class="sm:max-w-sm gap-0 p-0 overflow-hidden">

            <!-- Icon strip -->
            <div
                class="flex items-center justify-center pt-6 pb-3"
                :class="{
                    'bg-rose-500/5 dark:bg-rose-500/10':  state.variant === 'destructive',
                    'bg-muted/50':                        state.variant !== 'destructive',
                }"
            >
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full"
                    :class="{
                        'bg-rose-500/15 ring-4 ring-rose-500/10': state.variant === 'destructive',
                        'bg-amber-500/15 ring-4 ring-amber-500/10': state.variant !== 'destructive',
                    }"
                >
                    <component
                        :is="iconMap[state.icon ?? 'warning'].component"
                        class="h-6 w-6"
                        :class="state.variant === 'destructive' ? 'text-rose-500' : iconMap[state.icon ?? 'warning'].class"
                        :stroke-width="2"
                    />
                </div>
            </div>

            <!-- Content -->
            <div class="px-6 pb-2 pt-1 text-center">
                <DialogHeader class="gap-1.5">
                    <DialogTitle class="text-base font-semibold">
                        {{ state.title }}
                    </DialogTitle>
                    <DialogDescription v-if="state.description" class="text-sm leading-relaxed">
                        {{ state.description }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <!-- Actions -->
            <DialogFooter class="flex-row gap-2 px-6 pb-5 pt-3 sm:justify-center">
                <Button
                    variant="outline"
                    class="flex-1 sm:flex-none sm:min-w-24"
                    @click="handleCancel"
                >
                    {{ state.cancelText }}
                </Button>
                <Button
                    :variant="state.variant === 'destructive' ? 'destructive' : 'default'"
                    class="flex-1 sm:flex-none sm:min-w-24"
                    @click="handleConfirm"
                >
                    {{ state.confirmText }}
                </Button>
            </DialogFooter>

        </DialogContent>
    </Dialog>
</template>
