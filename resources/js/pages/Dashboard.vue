<template>
  <div>
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-display font-bold text-white tracking-tight">
        Good {{ timeOfDay }}, <span class="text-teal-400">{{ auth.user.name }}</span>
      </h1>
      <p class="text-gray-500 text-sm mt-1">
        Live exchange rates against USD · Updated every 10 minutes
      </p>
    </div>

    <div class="space-y-6">

      <!-- ── 1. Currency Selector ────────────────────────────────────────────── -->
      <SectionCard title="Select Currencies" subtitle="Choose up to 5 currencies to track">
        <template #header>
          <span
            class="text-xs font-mono px-2.5 py-1 rounded-full"
            :class="localSelectedIds.length >= MAX_CURRENCIES
              ? 'bg-amber-500/15 text-amber-400 border border-amber-500/25'
              : 'bg-teal-500/15 text-teal-400 border border-teal-500/25'"
          >
            {{ localSelectedIds.length }} / {{ MAX_CURRENCIES }}
          </span>
        </template>

        <CurrencySelector
          v-model="localSelectedIds"
          :all-currencies="currencies"
          :max="MAX_CURRENCIES"
        />

        <div class="mt-5 flex items-center justify-end gap-3">
          <span v-if="selectionDirty" class="text-xs text-gray-600 italic">Unsaved changes</span>
          <button
            @click="saveCurrencies"
            :disabled="localSelectedIds.length === 0 || saving || !selectionDirty"
            class="inline-flex items-center gap-2 bg-teal-500 hover:bg-teal-400 disabled:opacity-40 disabled:cursor-not-allowed text-gray-950 font-semibold text-sm px-5 py-2.5 rounded-xl transition-all duration-150"
          >
            <svg v-if="saving" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            {{ saving ? 'Saving…' : 'Save Selection' }}
          </button>
        </div>
      </SectionCard>

      <!-- ── 2. Live Rates Table ─────────────────────────────────────────────── -->
      <SectionCard title="Live Rates" subtitle="Base currency: USD">
        <RatesTable
          :selected-currencies="selectedCurrencies"
          :rates="rates"
        />
      </SectionCard>

      <!-- ── 3. Request Historical Report ──────────────────────────────────── -->
      <SectionCard
        title="Request Historical Report"
        subtitle="Reports run in the background and are ready within 15 minutes"
      >
        <div class="grid sm:grid-cols-3 gap-4">
          <!-- Currency -->
          <div>
            <label class="block text-xs text-gray-500 mb-2 uppercase tracking-wider">Currency</label>
            <select
              v-model="reportForm.currencyId"
              class="w-full bg-white/5 border border-white/10 text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-teal-500/50 transition-colors appearance-none cursor-pointer"
            >
              <option :value="0" disabled>Select currency…</option>
              <option
                v-for="currency in currencies"
                :key="currency.id"
                :value="currency.id"
              >
                {{ currency.code }} — {{ currency.name }}
              </option>
            </select>
          </div>

          <!-- Range -->
          <div>
            <label class="block text-xs text-gray-500 mb-2 uppercase tracking-wider">Range &amp; Interval</label>
            <select
              v-model="reportForm.range"
              class="w-full bg-white/5 border border-white/10 text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-teal-500/50 transition-colors appearance-none cursor-pointer"
            >
              <option value="" disabled>Select range…</option>
              <option
                v-for="pair in rangePairs"
                :key="pair.range"
                :value="pair.range"
              >
                {{ pair.label }}
              </option>
            </select>
          </div>

          <!-- Submit -->
          <div class="flex items-end">
            <button
              @click="submitReport"
              :disabled="!reportForm.currencyId || !reportForm.range || submitting"
              class="w-full inline-flex items-center justify-center gap-2 bg-indigo-500 hover:bg-indigo-400 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition-all duration-150"
            >
              <svg v-if="submitting" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              {{ submitting ? 'Submitting…' : 'Request Report' }}
            </button>
          </div>
        </div>
      </SectionCard>

      <!-- ── 4. Report Jobs List ──────────────────────────────────────────────  -->
      <SectionCard title="Report Jobs">
        <template #header>
          <span class="text-xs text-gray-600 font-mono">{{ reports.length }} job{{ reports.length !== 1 ? 's' : '' }}</span>
        </template>

        <div v-if="reports.length === 0" class="text-center py-10">
          <div class="text-3xl mb-3 opacity-20">📋</div>
          <p class="text-gray-600 text-sm">No reports yet. Submit a request above.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/8">
                <th class="text-left text-xs text-gray-500 font-medium pb-3 pr-6 uppercase tracking-wider">#</th>
                <th class="text-left text-xs text-gray-500 font-medium pb-3 pr-6 uppercase tracking-wider">Currency</th>
                <th class="text-left text-xs text-gray-500 font-medium pb-3 pr-6 uppercase tracking-wider">Range</th>
                <th class="text-left text-xs text-gray-500 font-medium pb-3 pr-6 uppercase tracking-wider">Status</th>
                <th class="text-left text-xs text-gray-500 font-medium pb-3 pr-6 uppercase tracking-wider">Submitted</th>
                <th class="text-left text-xs text-gray-500 font-medium pb-3 uppercase tracking-wider">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr
                v-for="report in reports"
                :key="report.id"
                class="group hover:bg-white/2 transition-colors"
              >
                <td class="py-3.5 pr-6 text-gray-600 font-mono text-xs">{{ report.id }}</td>
                <td class="py-3.5 pr-6">
                  <span class="font-mono text-xs bg-teal-500/10 text-teal-400 border border-teal-500/20 px-2 py-1 rounded-lg">
                    {{ report.currency.code }}
                  </span>
                </td>
                <td class="py-3.5 pr-6 text-gray-300 text-xs">{{ report.range_label }}</td>
                <td class="py-3.5 pr-6">
                  <StatusBadge :status="report.status" />
                </td>
                <td class="py-3.5 pr-6 text-gray-600 text-xs">{{ report.created_at }}</td>
                <td class="py-3.5">
                  <div v-if="report.status === 'completed'" class="flex items-center gap-3">
                    <a
                      :href="showReport.url(report.id)"
                      class="text-xs text-indigo-400 hover:text-indigo-300 underline underline-offset-2 transition-colors"
                    >
                      View →
                    </a>
                    <button
                      @click="deleteReport(report.id)"
                      class="text-xs text-red-500/60 hover:text-red-400 transition-colors"
                    >
                      Delete
                    </button>
                  </div>
                  <button
                    v-else-if="report.status === 'failed'"
                    @click="deleteReport(report.id)"
                    class="text-xs text-red-500 hover:text-red-400 transition-colors"
                  >
                    Delete
                  </button>
                  <span v-else class="text-gray-700 text-xs">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </SectionCard>

    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import SectionCard from '@/components/ui/SectionCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import CurrencySelector from '@/components/CurrencySelector.vue'
import RatesTable from '@/components/RatesTable.vue'
import { useApi } from '@/composables/useApi'
import type { Currency, PageProps, RangePair, ReportListItem } from '@/types'
import { show as showReport } from '@/routes/reports'
import { toast } from 'vue-sonner'

const MAX_CURRENCIES = 5

// ── Props (from DashboardController via Inertia) ──────────────────────────────
const props = defineProps<{ rangePairs: RangePair[] }>()

// ── Auth ──────────────────────────────────────────────────────────────────────
const page = usePage<PageProps>()
const auth = computed(() => page.props.auth)

// ── API ───────────────────────────────────────────────────────────────────────
const { request } = useApi()

// ── Remote state ──────────────────────────────────────────────────────────────
const currencies     = ref<Currency[]>([])
const savedIds       = ref<number[]>([])
const rates          = ref<Record<string, number | null> | null>(null)
const reports        = ref<ReportListItem[]>([])

// ── Local selection (dirty-tracked) ──────────────────────────────────────────
const localSelectedIds = ref<number[]>([])
const saving           = ref(false)

const selectionDirty = computed(
  () => JSON.stringify([...localSelectedIds.value].sort()) !== JSON.stringify([...savedIds.value].sort())
)

const selectedCurrencies = computed(() =>
  currencies.value.filter(c => localSelectedIds.value.includes(c.id))
)

// ── On mount: load currencies + reports in parallel ──────────────────────────
onMounted(async () => {
  const [currData, repData] = await Promise.all([
    request<{ currencies: Currency[]; selected_ids: number[]; rates: Record<string, number | null> | null }>(
      api => api.get('/currencies')
    ),
    request<{ reports: ReportListItem[] }>(
      api => api.get('/reports')
    ),
  ])

  if (currData) {
    currencies.value      = currData.currencies
    savedIds.value        = currData.selected_ids
    localSelectedIds.value = [...currData.selected_ids]
    rates.value           = currData.rates
  }

  if (repData) {
    reports.value = repData.reports
  }
})

// ── Save currency selection ───────────────────────────────────────────────────
async function saveCurrencies(): Promise<void> {
  saving.value = true
  const data = await request<{ selected_ids: number[] }>(
    api => api.put('/currencies', { currency_ids: localSelectedIds.value })
  )
  if (data) {
    savedIds.value = data.selected_ids
    const currData = await request<{ currencies: Currency[]; selected_ids: number[]; rates: Record<string, number | null> | null }>(
      api => api.get('/currencies')
    )
    if (currData) rates.value = currData.rates
    toast.success('Currency selection saved.')
  } else {
    toast.error('Failed to save currencies. Please try again.')
  }
  saving.value = false
}

// ── Report form ───────────────────────────────────────────────────────────────
const reportForm  = ref({ currencyId: 0, range: '' })
const submitting  = ref(false)

async function submitReport(): Promise<void> {
  if (!reportForm.value.currencyId || !reportForm.value.range) return
  submitting.value = true

  const data = await request(
    api => api.post('/reports', {
      currency_id: reportForm.value.currencyId,
      range:       reportForm.value.range,
    })
  )

  if (data) {
    reportForm.value = { currencyId: 0, range: '' }
    const repData = await request<{ reports: ReportListItem[] }>(api => api.get('/reports'))
    if (repData) reports.value = repData.reports
    toast.success('Report queued. It will be ready shortly.')
  } else {
    toast.error('Failed to submit report. Please try again.')
  }
  submitting.value = false
}

// ── Delete report ─────────────────────────────────────────────────────────────
async function deleteReport(id: number): Promise<void> {
  if (!confirm('Delete this report?')) return
  const ok = await request(api => api.delete(`/reports/${id}`))
  if (ok !== null) {
    reports.value = reports.value.filter(r => r.id !== id)
    toast.success('Report deleted.')
  } else {
    toast.error('Failed to delete report. Please try again.')
  }
}

// ── Time greeting ─────────────────────────────────────────────────────────────
const timeOfDay = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'morning'
  if (h < 17) return 'afternoon'
  return 'evening'
})
</script>
