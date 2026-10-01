<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
defineProps<{ count?: number; dark?: boolean }>();
</script>
<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
            v-for="i in (count ?? 4)"
            :key="i"
            class="rounded-2xl border p-5 space-y-3"
            :class="dark
                ? 'border-white/10 bg-white/[0.03]'
                : 'border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.03] shadow-sm'"
        >
            <div class="flex items-center justify-between">
                <Skeleton class="h-3 w-24" />
                <Skeleton class="h-9 w-9 rounded-lg" />
            </div>
            <Skeleton class="h-8 w-16" />
            <Skeleton class="h-2 w-full rounded-full" />
        </div>
    </div>
</template>
