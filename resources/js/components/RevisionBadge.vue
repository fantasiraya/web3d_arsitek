<template>
  <div
    :class="badgeClass"
    class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-full shadow-xs transition"
  >
    <span
      class="h-2 w-2 rounded-full shrink-0"
      :class="isLimitReached ? 'bg-rose-500 animate-pulse' : count > 0 ? 'bg-amber-400' : 'bg-emerald-400'"
    ></span>
    <span>Revisi {{ count }} dari {{ max }}</span>
    <span v-if="isLimitReached" class="text-[10px] font-normal opacity-90 hidden sm:inline">(Maksimal)</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Project {
  current_revision_count: number;
  max_revisions_allowed: number;
}

const props = withDefaults(
  defineProps<{
    project: Project;
    currentCount?: number;
  }>(),
  {
    currentCount: undefined,
  }
);

const count = computed(() => {
  return props.currentCount !== undefined
    ? props.currentCount
    : (props.project?.current_revision_count ?? 0);
});

const max = computed(() => props.project?.max_revisions_allowed || 3);

const isLimitReached = computed(() => count.value >= max.value);

const badgeClass = computed(() => {
  if (isLimitReached.value) {
    return 'bg-rose-950/80 border border-rose-500/50 text-rose-300';
  }
  if (count.value > 0) {
    return 'bg-amber-950/80 border border-amber-500/50 text-amber-300';
  }
  return 'bg-emerald-950/80 border border-emerald-500/50 text-emerald-300';
});
</script>

