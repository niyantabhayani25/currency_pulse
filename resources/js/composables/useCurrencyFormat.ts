// resources/js/Composables/useCurrencyFormat.ts

export function useCurrencyFormat() {
  function formatRate(rate: number | null | undefined, decimals = 4): string {
    if (rate === null || rate === undefined) return '—'
    return Number(rate).toLocaleString('en-US', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals + 2,
    })
  }

  function formatPct(current: number, previous: number): string {
    const pct = ((current - previous) / previous) * 100
    const sign = pct >= 0 ? '+' : ''
    return `${sign}${pct.toFixed(2)}%`
  }

  function pctClass(current: number, previous: number): string {
    const diff = current - previous
    if (diff > 0) return 'text-emerald-400'
    if (diff < 0) return 'text-red-400'
    return 'text-gray-500'
  }

  return { formatRate, formatPct, pctClass }
}
