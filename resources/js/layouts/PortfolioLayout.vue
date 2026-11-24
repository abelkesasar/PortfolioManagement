<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const userName = computed(() => (page.props as any).auth?.user?.name ?? '')

const links = [
  { href: '/portfolio', label: 'Aset' },
  { href: '/transactions', label: 'Riwayat' },
  { href: '/calculator', label: 'Kalkulator' },
]

const isActive = (href: string) => page.url.startsWith(href)
const logout = () => router.post('/logout')
</script>

<template>
  <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-neutral-950 dark:text-gray-100">
    <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/80 backdrop-blur dark:border-white/10 dark:bg-neutral-950/80">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-8">
          <Link href="/portfolio" class="text-base font-semibold tracking-tight">
            Portofolio<span class="text-emerald-500">.</span>
          </Link>
          <nav class="flex gap-1">
            <Link
              v-for="l in links"
              :key="l.href"
              :href="l.href"
              :class="[
                'rounded-lg px-3 py-1.5 text-sm transition',
                isActive(l.href)
                  ? 'bg-gray-100 font-medium text-gray-900 dark:bg-white/10 dark:text-white'
                  : 'text-gray-500 hover:text-gray-900 dark:hover:text-white',
              ]"
            >
              {{ l.label }}
            </Link>
          </nav>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <span class="hidden text-gray-500 sm:inline">{{ userName }}</span>
          <button
            @click="logout"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-600 transition hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10"
          >
            Keluar
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6">
      <slot />
    </main>
  </div>
</template>