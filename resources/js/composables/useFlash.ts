// resources/js/Composables/useFlash.ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/Types'

export function useFlash() {
  const page = usePage<PageProps>()

  const successMessage = computed(() => page.props.flash?.success ?? null)
  const infoMessage    = computed(() => page.props.flash?.info ?? null)
  const errorMessage   = computed(() => page.props.flash?.error ?? null)

  return { successMessage, infoMessage, errorMessage }
}
