<template>
  <div>
    <!-- Search -->
    <div class="relative mb-4">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
      </svg>
      <input
        v-model="search"
        type="text"
        placeholder="Search currencies…"
        class="w-full bg-white/5 border border-white/10 text-white placeholder-gray-600 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-teal-500/50 transition-colors"
      />
    </div>

    <!-- Selected count pill -->
    <div class="flex items-center justify-between mb-3">
      <span class="text-xs text-gray-500">
        Click to toggle · <span class="text-white">{{ modelValue.length }}/{{ max }}</span> selected
      </span>
      <button
        v-if="modelValue.length > 0"
        @click="$emit('update:modelValue', [])"
        class="text-xs text-gray-500 hover:text-red-400 transition-colors"
      >
        Clear all
      </button>
    </div>

    <!-- Currency grid -->
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-7 gap-1.5 max-h-60 overflow-y-auto pr-1 scrollbar">
      <button
        v-for="currency in visibleCurrencies"
        :key="currency.id"
        :title="currency.name"
        :disabled="!isSelected(currency.id) && modelValue.length >= max"
        @click="toggle(currency.id)"
        class="relative flex flex-col items-center py-2.5 px-1 rounded-xl border text-xs font-mono transition-all duration-150 focus:outline-none focus:ring-1 focus:ring-teal-500/50"
        :class="isSelected(currency.id)
          ? 'bg-teal-500/15 border-teal-500/40 text-teal-300 shadow-sm shadow-teal-500/10'
          : modelValue.length >= max
            ? 'bg-white/2 border-white/5 text-gray-700 cursor-not-allowed'
            : 'bg-white/4 border-white/8 text-gray-300 hover:border-white/20 hover:text-white hover:bg-white/8'"
      >
        <span class="font-bold tracking-wide">{{ currency.code }}</span>
        <span class="text-[9px] text-current opacity-50 truncate w-full text-center mt-0.5 px-0.5">
          {{ currency.name.length > 10 ? currency.name.slice(0, 10) + '…' : currency.name }}
        </span>
        <span v-if="isSelected(currency.id)" class="absolute top-1 right-1 text-teal-400 text-[9px]">✓</span>
      </button>
    </div>

    <!-- No results -->
    <p v-if="visibleCurrencies.length === 0" class="text-center text-sm text-gray-600 py-6">
      No currencies match "{{ search }}"
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Currency } from '@/types'

const props = defineProps<{
  allCurrencies: Currency[]
  modelValue: number[]
  max: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
}>()

const search = ref('')

const visibleCurrencies = computed(() => {
  const q = search.value.toLowerCase().trim()

  if (!q) {
return props.allCurrencies
}

  return props.allCurrencies.filter(
    c => c.code.toLowerCase().includes(q) || c.name.toLowerCase().includes(q)
  )
})

function isSelected(id: number): boolean {
  return props.modelValue.includes(id)
}

function toggle(id: number): void {
  if (isSelected(id)) {
    emit('update:modelValue', props.modelValue.filter(i => i !== id))
  } else if (props.modelValue.length < props.max) {
    emit('update:modelValue', [...props.modelValue, id])
  }
}
</script>
