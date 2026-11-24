<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { assetList, type AssetOption } from '@/data/assetList'
import MoneyInput from '@/components/MoneyInput.vue'

type Asset = { id: number; symbol: string; name: string; type: 'stock' | 'crypto'; current_price: number }
type Holding = {
  asset_id: number; symbol: string; name: string; type: 'stock' | 'crypto'
  quantity: number; avg_price: number; total_cost: number; current_price: number
}

const props = defineProps<{ open: boolean; assets: Asset[]; holdings: Holding[] }>()
const emit = defineEmits<{ (e: 'close'): void }>()

const inp =
  'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-gray-400 dark:border-white/10 dark:bg-neutral-900 dark:text-white dark:focus:border-white/30'
const label = 'mb-1 block text-xs font-medium text-gray-500'

const fmt = (n: number, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(n)

const form = useForm({
  symbol: '',
  name: '',
  type: 'stock' as 'stock' | 'crypto',
  side: 'buy' as 'buy' | 'sell',
  quantity: '' as string | number,
  price: '' as string | number,
  fee: '' as string | number,
  transacted_at: new Date().toISOString().slice(0, 10),
  note: '',
})

const isSell = computed(() => form.side === 'sell')
const mult = computed(() => (form.type === 'stock' ? 100 : 1))
const unitName = computed(() => (form.type === 'stock' ? 'lot' : 'koin'))

// Ganti Beli/Jual -> kosongkan pilihan aset
watch(
  () => form.side,
  () => {
    form.symbol = ''
    form.name = ''
    form.quantity = ''
    form.price = ''
    form.clearErrors()
    showSuggest.value = false
  },
)

// ---------- BELI: autocomplete ----------
const showSuggest = ref(false)

const options = computed<AssetOption[]>(() => {
  const own: AssetOption[] = props.assets.map((a) => ({ symbol: a.symbol, name: a.name, type: a.type }))
  const seen = new Set(own.map((a) => a.symbol))
  return [...own, ...assetList.filter((a) => !seen.has(a.symbol))]
})

const suggestions = computed(() => {
  const q = form.symbol.trim().toLowerCase()
  if (!q) return []
  return options.value
    .filter((a) => a.symbol.toLowerCase().includes(q) || a.name.toLowerCase().includes(q))
    .sort(
      (a, b) =>
        Number(b.symbol.toLowerCase().startsWith(q)) - Number(a.symbol.toLowerCase().startsWith(q)),
    )
    .slice(0, 8)
})

const pickAsset = (a: AssetOption) => {
  form.symbol = a.symbol
  form.name = a.name
  form.type = a.type
  showSuggest.value = false
}

// ---------- JUAL: hanya aset yang dimiliki ----------
const holding = computed(() => props.holdings.find((h) => h.symbol === form.symbol.trim().toUpperCase()))

const onPickHolding = () => {
  const h = props.holdings.find((x) => x.symbol === form.symbol)
  if (!h) return
  form.name = h.name
  form.type = h.type
  if (!form.price && h.current_price) form.price = String(h.current_price)
}

const sellAll = () => {
  if (holding.value) form.quantity = String(holding.value.quantity)
}

const overHeld = computed(
  () => isSell.value && !!holding.value && Number(form.quantity) > holding.value.quantity + 1e-9,
)

// Estimasi profit/loss vs average
const plPreview = computed(() => {
  const h = holding.value
  const q = Number(form.quantity)
  const p = Number(form.price)
  if (!isSell.value || !h || !q || !p || h.quantity <= 0) return null
  const proceeds = q * mult.value * p - Number(form.fee || 0)
  const basis = (h.total_cost / h.quantity) * q
  const pl = proceeds - basis
  return { pl, pct: basis > 0 ? (pl / basis) * 100 : 0, avg: h.avg_price }
})

const total = computed(() => {
  const q = Number(form.quantity)
  const p = Number(form.price)
  if (!q || !p) return 0
  return q * mult.value * p
})

// ---------- Submit / close ----------
const close = () => {
  showSuggest.value = false
  form.clearErrors()
  emit('close')
}

const submit = () =>
  form.post('/transactions', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('symbol', 'name', 'quantity', 'price', 'fee', 'note')
      close()
    },
  })

const onKey = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.open) close()
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div v-if="open" class="fixed inset-0 z-30 overflow-y-auto bg-black/40 p-4" @mousedown.self="close">
    <div class="mx-auto mt-10 w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-neutral-900">
      <div class="mb-5 flex items-center justify-between">
        <h2 class="text-lg font-semibold">Tambah transaksi</h2>
        <button @click="close" class="rounded-lg px-2 py-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/10">✕</button>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <!-- Beli / Jual -->
        <div class="grid grid-cols-2 gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5">
          <button
            type="button"
            @click="form.side = 'buy'"
            :class="['rounded-lg py-2 text-sm font-medium transition', form.side === 'buy' ? 'bg-emerald-500 text-white shadow' : 'text-gray-500']"
          >
            Beli
          </button>
          <button
            type="button"
            @click="form.side = 'sell'"
            :class="['rounded-lg py-2 text-sm font-medium transition', form.side === 'sell' ? 'bg-rose-500 text-white shadow' : 'text-gray-500']"
          >
            Jual
          </button>
        </div>

        <!-- JUAL: pilih dari aset yang dimiliki -->
        <div v-if="isSell">
          <label :class="label">Aset yang kamu punya</label>
          <select v-model="form.symbol" @change="onPickHolding" :class="inp" required :disabled="!holdings.length">
            <option value="" disabled>{{ holdings.length ? 'Pilih aset yang mau dijual' : 'Belum ada aset yang bisa dijual' }}</option>
            <option v-for="h in holdings" :key="h.asset_id" :value="h.symbol">
              {{ h.symbol }} · {{ h.name }} (punya {{ fmt(h.quantity, 8) }} {{ h.type === 'stock' ? 'lot' : 'koin' }})
            </option>
          </select>
          <p v-if="holding" class="mt-1.5 text-xs text-gray-500">
            Avg beli Rp {{ fmt(holding.avg_price, 2) }} · Harga kini Rp {{ fmt(holding.current_price, 2) }}
          </p>
          <p v-if="form.errors.symbol" class="mt-1 text-xs text-rose-500">{{ form.errors.symbol }}</p>
        </div>

        <!-- BELI: autocomplete -->
        <template v-else>
          <div class="relative">
            <label :class="label">Aset</label>
            <input
              v-model="form.symbol"
              placeholder="Ketik kode atau nama (BBCA, bitcoin)"
              autocomplete="off"
              :class="inp"
              required
              @focus="showSuggest = true"
              @input="showSuggest = true"
              @blur="showSuggest = false"
            />
            <ul
              v-if="showSuggest && suggestions.length"
              class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-neutral-900"
            >
              <li
                v-for="a in suggestions"
                :key="a.type + a.symbol"
                class="flex cursor-pointer items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-white/10"
                @mousedown.prevent="pickAsset(a)"
              >
                <span><b>{{ a.symbol }}</b> <span class="text-gray-500">{{ a.name }}</span></span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] uppercase text-gray-500 dark:bg-white/10">
                  {{ a.type === 'stock' ? 'saham' : 'crypto' }}
                </span>
              </li>
            </ul>
            <p v-if="form.errors.symbol" class="mt-1 text-xs text-rose-500">{{ form.errors.symbol }}</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Nama</label>
              <input v-model="form.name" placeholder="Otomatis dari pilihan" :class="inp" />
            </div>
            <div>
              <label :class="label">Jenis</label>
              <select v-model="form.type" :class="inp">
                <option value="stock">Saham</option>
                <option value="crypto">Crypto</option>
              </select>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <div class="mb-1 flex items-center justify-between">
              <label class="block text-xs font-medium text-gray-500">Jumlah ({{ unitName }})</label>
              <button
                v-if="isSell && holding"
                type="button"
                @click="sellAll"
                class="text-xs font-medium text-rose-500 hover:underline"
              >
                Jual semua
              </button>
            </div>
            <MoneyInput
              v-model="form.quantity"
              :decimals="form.type === 'crypto'"
              placeholder="0"
              :class="inp"
              required
            />
            <p v-if="overHeld" class="mt-1 text-xs text-rose-500">Melebihi kepemilikan ({{ fmt(holding!.quantity, 8) }}).</p>
            <p v-else-if="form.errors.quantity" class="mt-1 text-xs text-rose-500">{{ form.errors.quantity }}</p>
          </div>
          <div>
            <label :class="label">Harga per {{ form.type === 'stock' ? 'lembar' : 'koin' }}</label>
            <MoneyInput v-model="form.price" decimals placeholder="0" :class="inp" required />
            <p v-if="form.errors.price" class="mt-1 text-xs text-rose-500">{{ form.errors.price }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label :class="label">Fee (Rp)</label>
            <MoneyInput v-model="form.fee" placeholder="0" :class="inp" />
          </div>
          <div>
            <label :class="label">Tanggal</label>
            <input v-model="form.transacted_at" type="date" :class="inp" required />
          </div>
        </div>

        <div>
          <label :class="label">Catatan (opsional)</label>
          <input v-model="form.note" :class="inp" />
        </div>

        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">
          <span class="text-gray-500">
            Nilai transaksi
            <span v-if="form.type === 'stock'" class="text-xs">(1 lot = 100 lembar)</span>
          </span>
          <b>Rp {{ fmt(total) }}</b>
        </div>

        <!-- Estimasi profit / loss saat jual -->
        <div
          v-if="plPreview"
          :class="[
            'flex items-center justify-between rounded-xl px-4 py-3 text-sm',
            plPreview.pl >= 0
              ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
              : 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
          ]"
        >
          <span>Estimasi {{ plPreview.pl >= 0 ? 'profit' : 'loss' }} <span class="text-xs opacity-75">(vs avg {{ fmt(plPreview.avg, 2) }})</span></span>
          <b>{{ plPreview.pl >= 0 ? '+' : '' }}Rp {{ fmt(plPreview.pl) }} ({{ plPreview.pct >= 0 ? '+' : '' }}{{ plPreview.pct.toFixed(2) }}%)</b>
        </div>

        <button
          :disabled="form.processing || (isSell && (!holding || overHeld))"
          class="w-full rounded-xl bg-gray-900 py-2.5 text-sm font-medium text-white transition hover:bg-gray-700 disabled:opacity-50 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200"
        >
          {{ form.processing ? 'Menyimpan...' : 'Simpan transaksi' }}
        </button>
      </form>
    </div>
  </div>
</template>