<template>
  <div>
    <div v-if="selectedCurrencies.length === 0" class="text-center py-12">
      <div class="text-3xl mb-3 opacity-30">💱</div>
      <p class="text-gray-600 text-sm">Select currencies above to see live rates</p>
    </div>

    <div v-else class="overflow-x-auto">
      <div v-if="rates === null" class="text-center py-6 text-sm text-amber-400/80">
        Live rates temporarily unavailable. Try again shortly.
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-white/8">
            <th class="text-left text-gray-500 text-xs font-medium pb-3 pr-8 uppercase tracking-wider">Currency</th>
            <th class="text-left text-gray-500 text-xs font-medium pb-3 pr-8 uppercase tracking-wider">Code</th>
            <th class="text-right text-gray-500 text-xs font-medium pb-3 uppercase tracking-wider">1 USD =</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr v-for="currency in selectedCurrencies" :key="currency.id" class="group hover:bg-white/2 transition-colors">
            <td class="py-3.5 pr-8 text-gray-300 text-sm">{{ currency.name }}</td>
            <td class="py-3.5 pr-8">
              <span class="font-mono text-xs bg-teal-500/10 text-teal-400 border border-teal-500/20 px-2 py-1 rounded-lg">
                {{ currency.code }}
              </span>
            </td>
            <td class="py-3.5 text-right">
              <span v-if="rates[currency.code] != null" class="font-mono text-white font-semibold">
                {{ formatRate(rates[currency.code]) }}
              </span>
              <span v-else class="text-gray-700 font-mono text-xs">unavailable</span>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="text-xs text-gray-700 mt-3 text-right">Rates cached for 10 minutes · Source: CurrencyLayer</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useCurrencyFormat } from '@/composables/useCurrencyFormat'
import type { Currency } from '@/types'

defineProps<{
  selectedCurrencies: Currency[]
  rates: Record<string, number | null> | null
}>()

const { formatRate } = useCurrencyFormat()
</script>
