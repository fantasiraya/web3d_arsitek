<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>
<template>
    <!-- Landing page hero skeleton -->
    <div class="flex flex-col items-center text-center space-y-6 py-20 px-4">
        <Skeleton class="h-6 w-40 rounded-full" />
        <Skeleton class="h-14 w-3/4 max-w-2xl" />
        <Skeleton class="h-14 w-2/4 max-w-xl" />
        <Skeleton class="h-5 w-1/2 max-w-lg" />
        <Skeleton class="h-5 w-2/5 max-w-md" />
        <div class="flex items-center gap-4 pt-2">
            <Skeleton class="h-12 w-40 rounded-full" />
            <Skeleton class="h-12 w-36 rounded-full" />
        </div>
        <!-- Feature pills -->
        <div class="flex items-center gap-3 pt-4 flex-wrap justify-center">
            <Skeleton v-for="i in 4" :key="i" class="h-8 w-28 rounded-full" />
        </div>
    </div>
</template>
