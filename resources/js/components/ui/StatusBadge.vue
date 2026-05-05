<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wider"
    :class="cfg.badge"
  >
    <span class="w-1.5 h-1.5 rounded-full" :class="cfg.dot" />
    {{ cfg.label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ReportStatus } from '@/types'

const props = defineProps<{ status: ReportStatus }>()

const map: Record<ReportStatus, { label: string; badge: string; dot: string }> = {
  pending:    { label: 'Pending',    badge: 'bg-amber-500/15 text-amber-400',     dot: 'bg-amber-400' },
  processing: { label: 'Processing', badge: 'bg-blue-500/15 text-blue-400',       dot: 'bg-blue-400 animate-pulse' },
  completed:  { label: 'Completed',  badge: 'bg-emerald-500/15 text-emerald-400', dot: 'bg-emerald-400' },
  failed:     { label: 'Failed',     badge: 'bg-red-500/15 text-red-400',         dot: 'bg-red-400' },
}

const cfg = computed(() => map[props.status] ?? map.pending)
</script>
