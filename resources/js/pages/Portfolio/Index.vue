<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PortfolioLayout from '@/layouts/PortfolioLayout.vue'
import TransactionModal from '@/components/TransactionModal.vue'

type Asset = { id: number; symbol: string; name: string; type: 'stock' | 'crypto'; current_price: number }

const props = defineProps<{
  positions: any[]
  summary: { total_cost: number; market_value: number; unrealized_pl: number; realized_pl: number }
  assets: Asset[]
}>()

const fmt = (n: number, d = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(n)
const tone = (n: number) => (n >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')
const card = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900'

const showModal = ref(false)

// ---------- Ringkasan ----------
const plPct = computed(() =>
  props.summary.total_cost > 0 ? (props.summary.unrealized_pl / props.summary.total_cost) * 100 : 0,
)

// ---------- Alokasi ----------
const palette = [
  'bg-emerald-500', 'bg-sky-500', 'bg-violet-500', 'bg-amber-500',
  'bg-rose-500', 'bg-teal-500', 'bg-indigo-500', 'bg-orange-500',
]

const allocation = computed(() => {
  const total = props.summary.market_value
  return [...props.positions]
    .sort((a, b) => b.market_value - a.market_value)
    .map((p, i) => ({
      symbol: p.symbol,
      pct: total > 0 ? (p.market_value / total) * 100 : 0,
      color: palette[i % palette.length],
    }))
})

const shareOf = (symbol: string) => allocation.value.find((a) => a.symbol === symbol)?.pct ?? 0

const byType = computed(() => {
  const sum = (t: string) => props.positions.filter((p) => p.type === t).reduce((s, p) => s + p.market_value, 0)
  const total = props.summary.market_value
  return {
    stock: total > 0 ? (sum('stock') / total) * 100 : 0,
    crypto: total > 0 ? (sum('crypto') / total) * 100 : 0,
  }
})

// ---------- Harga terkini ----------
const editing = ref<Record<number, string>>({})
const savePrice = (id: number) => {
  const v = editing.value[id]
  if (v === undefined || v === '') return
  router.patch(`/assets/${id}/price`, { current_price: v }, {
    preserveScroll: true,
    onSuccess: () => delete editing.value[id],
  })
}

const refreshing = ref(false)
const refreshPrices = () =>
  router.post('/assets/refresh-prices', {}, {
    preserveScroll: true,
    onStart: () => (refreshing.value = true),
    onFinish: () => (refreshing.value = false),
  })
</script>

<template>
  <Head title="Aset" />

  <PortfolioLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Aset</h1>
          <p class="text-sm text-gray-500">Posisi saham dan crypto yang sedang kamu pegang.</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="refreshPrices"
            :disabled="refreshing"
            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm transition hover:bg-gray-100 disabled:opacity-50 dark:border-white/10 dark:bg-neutral-900 dark:hover:bg-white/10"
          >
            {{ refreshing ? 'Mengambil...' : '↻ Ambil harga' }}
          </button>
          <button
            @click="showModal = true"
            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
          >
            + Transaksi
          </button>
        </div>
      </div>

      <!-- Ringkasan -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div :class="card">
          <p class="text-xs text-gray-500">Total modal</p>
          <p class="mt-1 text-xl font-semibold">Rp {{ fmt(summary.total_cost, 0) }}</p>
        </div>
        <div :class="card">
          <p class="text-xs text-gray-500">Nilai sekarang</p>
          <p class="mt-1 text-xl font-semibold">Rp {{ fmt(summary.market_value, 0) }}</p>
        </div>
        <div :class="card">
          <p class="text-xs text-gray-500">Unrealized P/L</p>
          <p class="mt-1 text-xl font-semibold" :class="tone(summary.unrealized_pl)">
            {{ summary.unrealized_pl >= 0 ? '+' : '' }}Rp {{ fmt(summary.unrealized_pl, 0) }}
          </p>
          <p class="text-xs" :class="tone(plPct)">{{ plPct >= 0 ? '+' : '' }}{{ fmt(plPct) }}%</p>
        </div>
        <div :class="card">
          <p class="text-xs text-gray-500">Realized P/L</p>
          <p class="mt-1 text-xl font-semibold" :class="tone(summary.realized_pl)">
            {{ summary.realized_pl >= 0 ? '+' : '' }}Rp {{ fmt(summary.realized_pl, 0) }}
          </p>
        </div>
      </div>

      <!-- Alokasi -->
      <div v-if="allocation.length" :class="card">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-medium">Alokasi</h2>
          <p class="text-xs text-gray-500">
            Saham {{ fmt(byType.stock, 1) }}% · Crypto {{ fmt(byType.crypto, 1) }}%
          </p>
        </div>
        <div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
          <div
            v-for="a in allocation"
            :key="a.symbol"
            :class="a.color"
            :style="{ width: a.pct + '%' }"
            :title="a.symbol + ' ' + fmt(a.pct, 1) + '%'"
          />
        </div>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
          <span v-for="a in allocation" :key="a.symbol" class="flex items-center gap-1.5">
            <span class="h-2 w-2 rounded-full" :class="a.color" />
            {{ a.symbol }} {{ fmt(a.pct, 1) }}%
          </span>
        </div>
      </div>

      <!-- Tabel posisi -->
      <div :class="[card, 'overflow-hidden p-0']">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-white/10">
              <tr>
                <th class="px-5 py-3 font-medium">Aset</th>
                <th class="px-3 font-medium">Jumlah</th>
                <th class="px-3 font-medium">Avg</th>
                <th class="px-3 font-medium">Harga kini</th>
                <th class="px-3 font-medium">Nilai</th>
                <th class="px-3 font-medium">P/L</th>
                <th class="px-5 text-right font-medium">Porsi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!positions.length">
                <td colspan="7" class="px-5 py-12 text-center text-gray-500">
                  Belum ada posisi.
                  <button @click="showModal = true" class="font-medium text-emerald-600 underline">Tambah transaksi pertama</button>
                </td>
              </tr>
              <tr
                v-for="p in positions"
                :key="p.asset_id"
                class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
              >
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-xs font-semibold dark:bg-white/10">
                      {{ p.symbol.slice(0, 2) }}
                    </span>
                    <div>
                      <p class="font-medium leading-tight">{{ p.symbol }}</p>
                      <p class="text-xs text-gray-500">{{ p.name }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-3">{{ fmt(p.quantity, 8) }} <span class="text-xs text-gray-400">{{ p.type === 'stock' ? 'lot' : '' }}</span></td>
                <td class="px-3">{{ fmt(p.avg_price) }}</td>
                <td class="px-3">
                  <input
                    v-model="editing[p.asset_id]"
                    type="number"
                    step="any"
                    :placeholder="fmt(p.current_price)"
                    @keyup.enter="savePrice(p.asset_id)"
                    @blur="savePrice(p.asset_id)"
                    class="w-28 rounded-lg border border-transparent bg-gray-50 px-2 py-1 text-sm outline-none transition hover:border-gray-200 focus:border-gray-400 dark:bg-white/5 dark:hover:border-white/10"
                  />
                </td>
                <td class="px-3">Rp {{ fmt(p.market_value, 0) }}</td>
                <td class="px-3" :class="tone(p.unrealized_pl)">
                  {{ p.unrealized_pl >= 0 ? '+' : '' }}Rp {{ fmt(p.unrealized_pl, 0) }}
                  <span class="block text-xs">{{ p.unrealized_pl_pct >= 0 ? '+' : '' }}{{ p.unrealized_pl_pct.toFixed(2) }}%</span>
                </td>
                <td class="px-5 text-right text-gray-500">{{ fmt(shareOf(p.symbol), 1) }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="positions.length" class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-white/5">
          Klik kolom "Harga kini" untuk edit manual, lalu Enter atau klik di luar untuk menyimpan.
        </p>
      </div>
    </div>

    <TransactionModal :open="showModal" :assets="assets" :holdings="positions" @close="showModal = false" />
  </PortfolioLayout>
</template>