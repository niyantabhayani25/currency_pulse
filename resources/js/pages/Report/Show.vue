<template>
  <div>
    <!-- Loading skeleton -->
    <div v-if="loading" class="animate-pulse space-y-6">
      <div class="h-8 bg-white/5 rounded-xl w-64" />
      <div class="grid grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="h-20 bg-white/5 rounded-xl" />
      </div>
      <div class="h-72 bg-white/5 rounded-xl" />
    </div>

    <template v-else-if="report">
      <!-- Back + Header -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-8">
        <a
          :href="dashboard.url()"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-white border border-white/8 hover:border-white/20 px-3 py-1.5 rounded-lg transition-all self-start"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
          Dashboard
        </a>

        <div>
          <h1 class="text-2xl font-display font-bold text-white tracking-tight">
            USD / <span class="text-teal-400">{{ report.currency.code }}</span>
            <span class="text-gray-500 font-normal text-base ml-2">Historical Report</span>
          </h1>
          <p class="text-gray-600 text-xs mt-0.5">
            {{ report.range_label }} · Completed {{ report.completed_at }}
            <span
              v-if="report.data_source === 'synthetic'"
              class="ml-2 text-amber-400/70"
              title="Rates estimated — Frankfurter does not cover this currency"
            >
              (estimated data)
            </span>
          </p>
        </div>
      </div>

      <!-- Stats Row -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-[#0d1117] border border-white/8 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Data Points</p>
          <p class="text-xl font-mono font-bold text-white">{{ report.results.length }}</p>
        </div>
        <div class="bg-[#0d1117] border border-white/8 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Latest Rate</p>
          <p class="text-xl font-mono font-bold text-teal-400">{{ formatRate(latestRate) }}</p>
        </div>
        <div class="bg-[#0d1117] border border-white/8 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Period High</p>
          <p class="text-xl font-mono font-bold text-emerald-400">{{ formatRate(periodHigh) }}</p>
        </div>
        <div class="bg-[#0d1117] border border-white/8 rounded-xl p-4">
          <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Period Low</p>
          <p class="text-xl font-mono font-bold text-red-400">{{ formatRate(periodLow) }}</p>
        </div>
      </div>

      <div class="space-y-6">

        <!-- ── Line Chart ──────────────────────────────────────────────────── -->
        <SectionCard title="Rate Over Time" :subtitle="`1 USD → ${report.currency.code}`">
          <div class="h-72">
            <Line :data="chartData" :options="chartOptions" />
          </div>
        </SectionCard>

        <!-- ── Data Table ──────────────────────────────────────────────────── -->
        <SectionCard title="Historical Data">
          <template #header>
            <span class="text-xs text-gray-600 font-mono">{{ report.results.length }} rows</span>
          </template>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-white/8">
                  <th class="text-left text-xs text-gray-500 font-medium pb-3 pr-8 uppercase tracking-wider">Date</th>
                  <th class="text-right text-xs text-gray-500 font-medium pb-3 pr-8 uppercase tracking-wider">
                    1 USD = {{ report.currency.code }}
                  </th>
                  <th class="text-right text-xs text-gray-500 font-medium pb-3 uppercase tracking-wider">Change</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-white/5">
                <tr
                  v-for="(row, idx) in report.results"
                  :key="row.date"
                  class="hover:bg-white/2 transition-colors"
                >
                  <td class="py-3 pr-8 font-mono text-gray-400 text-xs">{{ row.date }}</td>
                  <td class="py-3 pr-8 text-right font-mono text-white font-semibold">
                    <span v-if="row.rate != null">{{ formatRate(row.rate) }}</span>
                    <span v-else class="text-gray-700 text-xs">—</span>
                  </td>
                  <td class="py-3 text-right font-mono text-xs">
                    <template v-if="idx > 0 && report.results[idx - 1].rate != null && row.rate != null">
                      <span :class="pctClass(row.rate, report.results[idx - 1].rate!)">
                        {{ formatPct(row.rate, report.results[idx - 1].rate!) }}
                      </span>
                    </template>
                    <span v-else class="text-gray-700">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </SectionCard>

      </div>
    </template>

    <!-- Error state -->
    <div v-else class="text-center py-20">
      <p class="text-gray-500">Failed to load report. <a :href="dashboard.url()" class="text-teal-400 underline">Go back</a></p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler,
} from 'chart.js'
import SectionCard from '@/components/ui/SectionCard.vue'
import { useApi } from '@/composables/useApi'
import { useCurrencyFormat } from '@/composables/useCurrencyFormat'
import type { ReportDetail } from '@/types'
import { dashboard } from '@/routes'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

const props = defineProps<{ reportId: number }>()

const { request }                       = useApi()
const { formatRate, formatPct, pctClass } = useCurrencyFormat()

const report  = ref<ReportDetail | null>(null)
const loading = ref(true)

onMounted(async () => {
  const data = await request<{ report: ReportDetail }>(api => api.get(`/reports/${props.reportId}`))
  if (data) report.value = data.report
  loading.value = false
})

const validRates = computed(() =>
  (report.value?.results ?? []).map(d => d.rate).filter((r): r is number => r !== null)
)

const latestRate = computed(() => validRates.value.at(-1) ?? null)
const periodHigh = computed(() => validRates.value.length ? Math.max(...validRates.value) : null)
const periodLow  = computed(() => validRates.value.length ? Math.min(...validRates.value) : null)

const chartData = computed(() => ({
  labels: report.value?.results.map(r => r.date) ?? [],
  datasets: [
    {
      label:                `1 USD → ${report.value?.currency.code}`,
      data:                 report.value?.results.map(r => r.rate) ?? [],
      borderColor:          '#2dd4bf',
      backgroundColor:      'rgba(45,212,191,0.07)',
      borderWidth:          2,
      pointRadius:          (report.value?.results.length ?? 0) > 60 ? 0 : 3,
      pointHoverRadius:     5,
      pointBackgroundColor: '#2dd4bf',
      fill:                 true,
      tension:              0.35,
      spanGaps:             true,
    },
  ],
}))

const chartOptions = {
  responsive:          true,
  maintainAspectRatio: false,
  interaction:         { intersect: false, mode: 'index' as const },
  plugins: {
    legend:  { display: false },
    tooltip: {
      backgroundColor: '#0d1117',
      titleColor:      '#6b7280',
      bodyColor:       '#f9fafb',
      borderColor:     'rgba(255,255,255,0.08)',
      borderWidth:     1,
      padding:         10,
      callbacks: {
        label: (ctx: any) => ` ${Number(ctx.raw).toFixed(4)} ${report.value?.currency.code}`,
      },
    },
  },
  scales: {
    x: {
      ticks: { color: '#4b5563', font: { size: 10, family: 'monospace' }, maxTicksLimit: 14, maxRotation: 0 },
      grid:  { color: 'rgba(255,255,255,0.04)' },
    },
    y: {
      ticks: { color: '#4b5563', font: { size: 10, family: 'monospace' } },
      grid:  { color: 'rgba(255,255,255,0.04)' },
    },
  },
}
</script>
