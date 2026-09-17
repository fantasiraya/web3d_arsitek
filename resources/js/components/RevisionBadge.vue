<template>
  <div :class="badgeClass" class="inline-block px-3 py-1 text-sm font-medium rounded-full">
    Revisi {{ project.current_revision_count }} dari {{ project.max_revisions_allowed }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Project {
  current_revision_count: number;
  max_revisions_allowed: number;
}

const props = defineProps<{ project: Project }>();

const badgeClass = computed(() => {
  return props.project.current_revision_count >= props.project.max_revisions_allowed
    ? 'bg-red-200 text-red-800'
    : 'bg-green-200 text-green-800';
});
</script>

<style scoped>
/* No additional styles needed; Tailwind handles colors */
</style>

