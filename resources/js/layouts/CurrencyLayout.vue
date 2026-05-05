<template>
  <div class="min-h-screen bg-[#0a0c10] text-gray-100 font-body">
    <Head :title="title ? `${title} — CurrencyPulse` : 'CurrencyPulse'" />

    <!-- Top Nav -->
    <nav class="border-b border-white/5 bg-[#0d1117]/80 backdrop-blur-md sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <Link :href="dashboard.url()" class="flex items-center gap-2 group">
          <span class="text-lg font-display font-bold tracking-tight">
            <span class="text-white">Currency</span><span class="text-teal-400">Pulse</span>
          </span>
        </Link>

        <div class="flex items-center gap-3">
          <span class="text-xs text-gray-500 hidden sm:inline">{{ auth.user.name }}</span>
          <Link
            :href="logout.url()"
            method="post"
            as="button"
            class="text-xs text-gray-400 hover:text-white border border-white/10 hover:border-white/25 px-3 py-1.5 rounded-lg transition-all duration-150"
          >
            Sign out
          </Link>
        </div>
      </div>
    </nav>

    <!-- Flash Toasts -->
    <Transition name="toast">
      <div
        v-if="activeFlash"
        :class="[
          'fixed top-16 right-4 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-2xl text-sm font-medium border',
          toastClass,
        ]"
      >
        <span>{{ activeFlash }}</span>
        <button @click="dismissFlash" class="opacity-60 hover:opacity-100 ml-1 text-xs">✕</button>
      </div>
    </Transition>

    <!-- Main -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <slot />
    </main>

    <Toaster position="top-right" :duration="3500" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'
import { dashboard, logout } from '@/routes'
import { Toaster } from '@/components/ui/sonner'

defineProps<{ title?: string }>()

const page  = usePage<PageProps>()
const auth  = computed(() => page.props.auth)
const flash = computed(() => page.props.flash)

const dismissed = ref(false)

const activeFlash = computed(() => {
  if (dismissed.value) return null
  return flash.value?.success ?? flash.value?.info ?? flash.value?.error ?? null
})

const toastClass = computed(() => {
  if (flash.value?.success) return 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
  if (flash.value?.info)    return 'bg-blue-500/15 border-blue-500/30 text-blue-300'
  if (flash.value?.error)   return 'bg-red-500/15 border-red-500/30 text-red-300'
  return ''
})

watch(flash, () => { dismissed.value = false })

function dismissFlash() {
  dismissed.value = true
}
</script>
