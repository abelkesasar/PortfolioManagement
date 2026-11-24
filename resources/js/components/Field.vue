<script setup lang="ts">
defineProps<{ label: string }>()
</script>

<template>
  <label class="block text-sm">
    <span class="mb-1 block text-gray-500">{{ label }}</span>
    <slot />
  </label>
</template>