<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PortfolioLayout from '@/layouts/PortfolioLayout.vue'
import TransactionModal from '@/components/TransactionModal.vue'

type Tx = {
  id: number; date: string; symbol: string; name: string; type: 'stock' | 'crypto'
  side: 'buy' | 'sell'; quantity: number; price: number; fee: number; total: number; note: string | null
  avg_price: number | null; pl: number | null; pl_pct: number | null
}
type Asset = { id: number; symbol: string; name: string; type: 'stock' | 'crypto'; current_price: number }

const props = defineProps<{ transactions: Tx[]; assets: Asset[]; holdings: any[] }>()

const fmt = (n: number, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(n)
const fmtDate = (d: string) =>
  new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
const tone = (n: number) => (n >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')
const card = 'rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-neutral-900'
const inp =
  'rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-gray-400 dark:border-white/10 dark:bg-neutral-900 dark:focus:border-white/30'

const showModal = ref(false)
const q = ref('')
const side = ref<'all' | 'buy' | 'sell'>('all')

const filtered = computed(() => {
  const s = q.value.trim().toLowerCase()
  return props.transactions.filter(
    (t) =>
      (side.value === 'all' || t.side === side.value) &&
      (!s || t.symbol.toLowerCase().includes(s) || t.name.toLowerCase().includes(s)),
  )
})

const totalBuy = computed(() =>
  filtered.value.filter((t) => t.side === 'buy').reduce((s, t) => s + t.total + t.fee, 0),
)
const totalSell = computed(() =>
  filtered.value.filter((t) => t.side === 'sell').reduce((s, t) => s + t.total - t.fee, 0),
)

// ---------- Trade jual: profit / loss / impas ----------
const sells = computed(() => filtered.value.filter((t) => t.side === 'sell' && t.pl !== null))
const realizedTotal = computed(() => sells.value.reduce((s, t) => s + (t.pl ?? 0), 0))
const wins = computed(() => sells.value.filter((t) => (t.pl ?? 0) > 0.5).length)
const losses = computed(() => sells.value.filter((t) => (t.pl ?? 0) < -0.5).length)
const flats = computed(() => sells.value.length - wins.value - losses.value)
const winRate = computed(() => (sells.value.length ? (wins.value / sells.value.length) * 100 : 0))

const R = 40
const C = 2 * Math.PI * R

const segments = computed(() => {
  const total = sells.value.length
  if (!total) return []
  const parts = [
    { label: 'Profit', n: wins.value, color: '#10b981' },
    { label: 'Loss', n: losses.value, color: '#f43f5e' },
    { label: 'Impas', n: flats.value, color: '#9ca3af' },
  ].filter((p) => p.n > 0)

  let acc = 0
  return parts.map((p) => {
    const len = (p.n / total) * C
    const seg = { ...p, pct: (p.n / total) * 100, dash: `${len} ${C - len}`, offset: -acc }
    acc += len
    return seg
  })
})

const remove = (id: number) => {
  if (!confirm('Hapus transaksi ini? Posisi dan P/L akan dihitung ulang.')) return
  router.delete(`/transactions/${id}`, { preserveScroll: true })
}
</script>

<template>
  <Head title="Riwayat" />

  <PortfolioLayout>
    <div class="space-y-6">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">Riwayat transaksi</h1>
          <p class="text-sm text-gray-500">Semua pembelian dan penjualan, dari yang terbaru.</p>
        </div>
        <button
          @click="showModal = true"
          class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
        >
          + Transaksi
        </button>
      </div>

      <!-- Ringkasan + donut -->
      <div class="grid gap-4 lg:grid-cols-3">
        <div class="grid grid-cols-2 gap-4 lg:col-span-2">
          <div :class="card">
            <p class="text-xs text-gray-500">Jumlah transaksi</p>
            <p class="mt-1 text-xl font-semibold">{{ filtered.length }}</p>
          </div>
          <div :class="card">
            <p class="text-xs text-gray-500">Realized P/L</p>
            <p class="mt-1 text-xl font-semibold" :class="tone(realizedTotal)">
              {{ realizedTotal >= 0 ? '+' : '' }}Rp {{ fmt(realizedTotal) }}
            </p>
          </div>
          <div :class="card">
            <p class="text-xs text-gray-500">Total beli (termasuk fee)</p>
            <p class="mt-1 text-xl font-semibold">Rp {{ fmt(totalBuy) }}</p>
          </div>
          <div :class="card">
            <p class="text-xs text-gray-500">Total jual (setelah fee)</p>
            <p class="mt-1 text-xl font-semibold">Rp {{ fmt(totalSell) }}</p>
          </div>
        </div>

        <div :class="[card, 'flex items-center gap-5']">
          <div class="relative h-32 w-32 shrink-0">
            <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
              <circle cx="50" cy="50" :r="R" fill="none" stroke-width="14" class="stroke-gray-100 dark:stroke-white/10" />
              <circle
                v-for="s in segments"
                :key="s.label"
                cx="50"
                cy="50"
                :r="R"
                fill="none"
                stroke-width="14"
                :stroke="s.color"
                :stroke-dasharray="s.dash"
                :stroke-dashoffset="s.offset"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-xl font-semibold">{{ sells.length ? fmt(winRate) + '%' : '–' }}</span>
              <span class="text-[10px] text-gray-500">win rate</span>
            </div>
          </div>

          <div class="min-w-0 flex-1 space-y-1.5 text-sm">
            <p class="text-xs font-medium text-gray-500">Hasil trade (per transaksi jual)</p>
            <p v-if="!segments.length" class="text-xs text-gray-500">Belum ada transaksi jual.</p>
            <div v-for="s in segments" :key="s.label" class="flex items-center justify-between gap-2">
              <span class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: s.color }" />
                {{ s.label }}
              </span>
              <span class="text-gray-500">{{ s.n }} · {{ fmt(s.pct, 1) }}%</span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <input v-model="q" placeholder="Cari kode atau nama aset" :class="[inp, 'w-64']" />
        <select v-model="side" :class="inp">
          <option value="all">Semua</option>
          <option value="buy">Beli</option>
          <option value="sell">Jual</option>
        </select>
      </div>

      <div :class="[card, 'overflow-hidden p-0']">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-white/10">
              <tr>
                <th class="px-5 py-3 font-medium">Tanggal</th>
                <th class="px-3 font-medium">Aset</th>
                <th class="px-3 font-medium">Jenis</th>
                <th class="px-3 text-right font-medium">Jumlah</th>
                <th class="px-3 text-right font-medium">Harga</th>
                <th class="px-3 text-right font-medium">Total</th>
                <th class="px-3 text-right font-medium">Fee</th>
                <th class="px-3 text-right font-medium">Profit / Loss</th>
                <th class="px-3 font-medium">Catatan</th>
                <th class="px-5"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!filtered.length">
                <td colspan="10" class="px-5 py-12 text-center text-gray-500">Belum ada transaksi yang cocok.</td>
              </tr>
              <tr
                v-for="t in filtered"
                :key="t.id"
                class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
              >
                <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ fmtDate(t.date) }}</td>
                <td class="px-3">
                  <p class="font-medium leading-tight">{{ t.symbol }}</p>
                  <p class="text-xs text-gray-500">{{ t.name }}</p>
                </td>
                <td class="px-3">
                  <span
                    :class="[
                      'rounded-md px-2 py-0.5 text-xs font-medium',
                      t.side === 'buy'
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400'
                        : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
                    ]"
                  >
                    {{ t.side === 'buy' ? 'Beli' : 'Jual' }}
                  </span>
                </td>
                <td class="px-3 text-right">{{ fmt(t.quantity, 8) }} <span class="text-xs text-gray-400">{{ t.type === 'stock' ? 'lot' : '' }}</span></td>
                <td class="px-3 text-right">{{ fmt(t.price, 2) }}</td>
                <td class="px-3 text-right font-medium">Rp {{ fmt(t.total) }}</td>
                <td class="px-3 text-right text-gray-500">{{ fmt(t.fee) }}</td>
                <td class="whitespace-nowrap px-3 text-right">
                  <template v-if="t.pl !== null">
                    <p class="font-medium" :class="tone(t.pl)">
                      {{ t.pl >= 0 ? '+' : '' }}Rp {{ fmt(t.pl) }}
                      <span class="text-xs">({{ (t.pl_pct ?? 0) >= 0 ? '+' : '' }}{{ (t.pl_pct ?? 0).toFixed(2) }}%)</span>
                    </p>
                    <p class="text-xs text-gray-500">avg beli {{ fmt(t.avg_price ?? 0, 2) }}</p>
                  </template>
                  <span v-else class="text-gray-300 dark:text-white/20">–</span>
                </td>
                <td class="max-w-[12rem] truncate px-3 text-gray-500">{{ t.note }}</td>
                <td class="px-5 text-right">
                  <button @click="remove(t.id)" class="text-xs text-gray-400 transition hover:text-rose-500">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <TransactionModal :open="showModal" :assets="assets" :holdings="holdings" @close="showModal = false" />
  </PortfolioLayout>
</template>