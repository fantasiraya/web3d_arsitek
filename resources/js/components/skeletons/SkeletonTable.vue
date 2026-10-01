<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
defineProps<{ rows?: number; cols?: number; dark?: boolean }>();
</script>
<template>
    <div class="overflow-hidden rounded-2xl border"
         :class="dark ? 'border-white/10 bg-white/[0.02]' : 'border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.02] shadow-sm'">
        <!-- Header row -->
        <div class="flex items-center gap-4 px-4 py-3 border-b"
             :class="dark ? 'border-white/10 bg-white/5' : 'border-slate-100 dark:border-white/10 bg-slate-50 dark:bg-white/5'">
            <Skeleton v-for="c in (cols ?? 4)" :key="c" class="h-3 flex-1" />
        </div>
        <!-- Data rows -->
        <div
            v-for="r in (rows ?? 5)"
            :key="r"
            class="flex items-center gap-4 px-4 py-3.5 border-b last:border-0"
            :class="dark ? 'border-white/8' : 'border-slate-100 dark:border-white/8'"
        >
            <Skeleton class="h-9 w-9 rounded-full shrink-0" />
            <Skeleton class="h-3 flex-1" />
            <Skeleton class="h-3 w-20" />
            <Skeleton class="h-5 w-16 rounded-full" />
            <Skeleton class="h-7 w-16 rounded-lg" />
        </div>
    </div>
</template>
