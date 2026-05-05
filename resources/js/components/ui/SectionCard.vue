<template>
  <div class="bg-[#0d1117] border border-white/8 rounded-2xl p-6">
    <div v-if="title || $slots.header" class="flex items-start justify-between mb-5">
      <div>
        <h2 class="text-base font-display font-semibold text-white">{{ title }}</h2>
        <p v-if="subtitle" class="text-xs text-gray-500 mt-0.5">{{ subtitle }}</p>
      </div>
      <slot name="header" />
    </div>
    <slot />
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  subtitle?: string
}>()
</script>
