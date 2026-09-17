<template>
  <TresMesh :position="position" :castShadow="true">
    <TresSphereGeometry :args="[0.05, 16, 16]" />
    <TresMeshStandardMaterial color="#ef4444" />
  </TresMesh>
</template>

<script setup lang="ts">

interface Props {
  position: [number, number, number];
}

const props = defineProps<Props>();
</script>

<style scoped>
/* No extra styles */
</style>

