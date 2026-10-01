<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
defineProps<{ count?: number; dark?: boolean }>();
</script>
<template>
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="i in (count ?? 3)"
            :key="i"
            class="flex flex-col overflow-hidden rounded-2xl border"
            :class="dark
                ? 'border-white/10 bg-white/[0.03]'
                : 'border-slate-200 dark:border-white/10 bg-white dark:bg-white/[0.03] shadow-sm'"
        >
            <!-- Thumbnail -->
            <Skeleton class="h-32 w-full rounded-none" />
            <!-- Content -->
            <div class="p-5 space-y-3 flex-1">
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-3 w-full" />
                <Skeleton class="h-3 w-2/3" />
                <!-- Progress block -->
                <div class="rounded-xl border p-3 space-y-2"
                     :class="dark ? 'border-white/10 bg-white/5' : 'border-slate-100 dark:border-white/10 bg-slate-50 dark:bg-white/5'">
                    <div class="flex justify-between">
                        <Skeleton class="h-3 w-20" />
                        <Skeleton class="h-3 w-10" />
                    </div>
                    <Skeleton class="h-1.5 w-full rounded-full" />
                </div>
                <div class="flex justify-between items-center">
                    <Skeleton class="h-3 w-20" />
                    <Skeleton class="h-3 w-14" />
                </div>
            </div>
            <!-- Footer -->
            <div class="border-t p-4 flex gap-2"
                 :class="dark ? 'border-white/10' : 'border-slate-100 dark:border-white/10'">
                <Skeleton class="h-8 flex-1 rounded-lg" />
                <Skeleton class="h-8 w-9 rounded-lg" />
                <Skeleton class="h-8 w-9 rounded-lg" />
            </div>
        </div>
    </div>
</template>
