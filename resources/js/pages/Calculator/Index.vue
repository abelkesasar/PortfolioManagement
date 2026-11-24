<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Field from '@/components/Field.vue'
import {
  type AssetType, unitMultiplier, costBasis, priceForProfit, breakEven,
  newAverage, unitsToReachAverage, positionSize, riskReward, scenarios,
  idxTick, roundToTick,
} from '@/composables/useCalculator'

const props = defineProps<{
  positions: { asset_id: number; symbol: string; type: AssetType; quantity: number; avg_price: number; current_price: number }[]
}>()

const fmt = (n: number, d = 2) =>
  Number.isFinite(n) ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(n) : '-'
const tone = (n: number) => (n >= 0 ? 'text-green-600' : 'text-red-600')
const inp = 'w-full rounded border p-2 bg-transparent'

// ---------- Tabs ----------
type Tab = 'target' | 'average' | 'risk' | 'scenario' | 'tick'
const tab = ref<Tab>('target')
const tabs: { key: Tab; label: string }[] = [
  { key: 'target', label: 'Target Jual / Cut Loss' },
  { key: 'average', label: 'Average' },
  { key: 'risk', label: 'Position Sizing' },
  { key: 'scenario', label: 'Skenario P/L' },
  { key: 'tick', label: 'Tick Size IDX' },
]

// ---------- Posisi (dipakai semua tab) ----------
const positionId = ref<number | ''>('')
const type = ref<AssetType>('stock')
const qty = ref(10)        // lot (saham) / koin (crypto)
const avg = ref(1000)
const current = ref(1000)
const feeBuyPct = ref(0.15)
const feeSellPct = ref(0.25)

const mult = computed(() => unitMultiplier(type.value))
const units = computed(() => qty.value * mult.value)
const feeBuy = computed(() => feeBuyPct.value / 100)
const feeSell = computed(() => feeSellPct.value / 100)
const cost = computed(() => costBasis(avg.value, units.value, feeBuy.value))
const unitName = computed(() => (type.value === 'stock' ? 'lot' : 'koin'))

function applyDefaultFees() {
  feeBuyPct.value = type.value === 'stock' ? 0.15 : 0.1
  feeSellPct.value = type.value === 'stock' ? 0.25 : 0.1
}

watch(positionId, (id) => {
  const p = props.positions.find((x) => x.asset_id === id)
  if (!p) return
  type.value = p.type
  qty.value = p.quantity
  avg.value = Math.round(p.avg_price * 100) / 100
  current.value = p.current_price || avg.value
  feeBuyPct.value = 0 // avg dari portofolio sudah termasuk fee beli
  feeSellPct.value = p.type === 'stock' ? 0.25 : 0.1
})

const tickUp = (p: number) => (type.value === 'stock' ? roundToTick(p, 'up') : p)

// ---------- Tab: Target ----------
const targetKind = ref<'nominal' | 'pct'>('nominal')
const profitValue = ref(500000)
const lossValue = ref(250000)

const profitAmount = computed(() => (targetKind.value === 'pct' ? (cost.value * profitValue.value) / 100 : profitValue.value))
const lossAmount = computed(() => (targetKind.value === 'pct' ? (cost.value * lossValue.value) / 100 : lossValue.value))
const sellProfit = computed(() => priceForProfit(cost.value, units.value, feeSell.value, profitAmount.value))
const sellLoss = computed(() => priceForProfit(cost.value, units.value, feeSell.value, -lossAmount.value))
const be = computed(() => breakEven(cost.value, units.value, feeSell.value))
const pctFromAvg = (p: number) => (avg.value > 0 ? (p / avg.value - 1) * 100 : 0)

// ---------- Tab: Average ----------
const addPrice = ref(800)
const addQty = ref(10)
const targetAvg = ref(900)
const budget = ref(5000000)

const resNewAvg = computed(() => newAverage(units.value, avg.value, addQty.value * mult.value, addPrice.value))
const needQty = computed(() => {
  const n = unitsToReachAverage(units.value, avg.value, addPrice.value, targetAvg.value)
  if (n === null) return null
  const q = n / mult.value
  return type.value === 'stock' ? Math.ceil(q) : q
})
const needCost = computed(() =>
  needQty.value === null ? 0 : needQty.value * mult.value * addPrice.value * (1 + feeBuy.value))
const budgetQty = computed(() => {
  const raw = budget.value / (addPrice.value * mult.value * (1 + feeBuy.value))
  return type.value === 'stock' ? Math.floor(raw) : raw
})
const budgetAvg = computed(() => newAverage(units.value, avg.value, budgetQty.value * mult.value, addPrice.value))

// ---------- Tab: Position sizing ----------
const capital = ref(100000000)
const riskPct = ref(2)
const entry = ref(1000)
const stop = ref(950)
const tp = ref(1150)

const sizeUnits = computed(() => positionSize(capital.value, riskPct.value, entry.value, stop.value))
const sizeQty = computed(() => (type.value === 'stock' ? Math.floor(sizeUnits.value / 100) : sizeUnits.value))
const sizeValue = computed(() => sizeQty.value * mult.value * entry.value)
const rr = computed(() => riskReward(entry.value, stop.value, tp.value))
const riskRp = computed(() => sizeQty.value * mult.value * Math.abs(entry.value - stop.value))
const rewardRp = computed(() => sizeQty.value * mult.value * Math.abs(tp.value - entry.value))

// ---------- Tab: Skenario ----------
const rows = computed(() => scenarios(cost.value, units.value, feeSell.value, current.value))

// ---------- Tab: Tick ----------
const tickPrice = ref(1234)
</script>

<template>
  <Head title="Kalkulator" />
  <div class="mx-auto max-w-5xl space-y-6 p-6">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Kalkulator Saham & Crypto</h1>
      <Link href="/portfolio" class="text-sm underline">← Portofolio</Link>
    </div>

    <!-- Input posisi -->
    <div class="space-y-3 rounded-xl border p-4">
      <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <Field label="Ambil dari portofolio">
          <select v-model="positionId" :class="inp">
            <option value="">Manual</option>
            <option v-for="p in positions" :key="p.asset_id" :value="p.asset_id">{{ p.symbol }}</option>
          </select>
        </Field>
        <Field label="Jenis">
          <select v-model="type" @change="applyDefaultFees" :class="inp">
            <option value="stock">Saham (IDX)</option>
            <option value="crypto">Crypto</option>
          </select>
        </Field>
        <Field :label="`Jumlah (${unitName})`">
          <input v-model.number="qty" type="number" step="any" :class="inp" />
        </Field>
        <Field label="Harga rata-rata">
          <input v-model.number="avg" type="number" step="any" :class="inp" />
        </Field>
        <Field label="Harga sekarang">
          <input v-model.number="current" type="number" step="any" :class="inp" />
        </Field>
        <Field label="Fee beli (%)">
          <input v-model.number="feeBuyPct" type="number" step="any" :class="inp" />
        </Field>
        <Field label="Fee jual (%)">
          <input v-model.number="feeSellPct" type="number" step="any" :class="inp" />
        </Field>
      </div>
      <p class="text-sm text-gray-500">
        Modal: <b>Rp {{ fmt(cost, 0) }}</b> · {{ fmt(units) }} {{ type === 'stock' ? 'lembar' : 'koin' }}
        <span v-if="positionId !== ''"> · avg dari portofolio sudah termasuk fee beli</span>
      </p>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="t in tabs" :key="t.key" @click="tab = t.key"
        :class="['rounded-full border px-3 py-1 text-sm', tab === t.key ? 'bg-black text-white dark:bg-white dark:text-black' : '']"
      >{{ t.label }}</button>
    </div>

    <!-- TARGET -->
    <section v-if="tab === 'target'" class="grid gap-4 md:grid-cols-2">
      <div class="space-y-3 rounded-xl border p-4">
        <div class="flex gap-2 text-sm">
          <button @click="targetKind = 'nominal'" :class="['rounded border px-2 py-1', targetKind === 'nominal' && 'font-semibold underline']">Nominal (Rp)</button>
          <button @click="targetKind = 'pct'" :class="['rounded border px-2 py-1', targetKind === 'pct' && 'font-semibold underline']">Persen (%)</button>
        </div>
        <Field :label="targetKind === 'pct' ? 'Target untung (%)' : 'Target untung (Rp)'">
          <input v-model.number="profitValue" type="number" step="any" :class="inp" />
        </Field>
        <Field :label="targetKind === 'pct' ? 'Batas rugi (%)' : 'Batas rugi (Rp)'">
          <input v-model.number="lossValue" type="number" step="any" :class="inp" />
        </Field>
      </div>
      <div class="space-y-2 rounded-xl border p-4 text-sm">
        <div class="flex justify-between"><span>Harga impas (break-even)</span><b>{{ fmt(tickUp(be)) }}</b></div>
        <div class="flex justify-between">
          <span>Jual di harga ini untuk untung Rp {{ fmt(profitAmount, 0) }}</span>
          <b class="text-green-600">{{ fmt(tickUp(sellProfit)) }} ({{ fmt(pctFromAvg(sellProfit)) }}%)</b>
        </div>
        <div class="flex justify-between">
          <span>Cut loss di harga ini (rugi maks Rp {{ fmt(lossAmount, 0) }})</span>
          <b class="text-red-600">{{ fmt(tickUp(sellLoss)) }} ({{ fmt(pctFromAvg(sellLoss)) }}%)</b>
        </div>
        <p class="pt-2 text-xs text-gray-500">Sudah termasuk fee jual. Harga saham dibulatkan ke atas sesuai tick size IDX.</p>
      </div>
    </section>

    <!-- AVERAGE -->
    <section v-if="tab === 'average'" class="grid gap-4 md:grid-cols-2">
      <div class="space-y-3 rounded-xl border p-4">
        <Field label="Harga beli tambahan"><input v-model.number="addPrice" type="number" step="any" :class="inp" /></Field>
        <Field :label="`Jumlah beli tambahan (${unitName})`"><input v-model.number="addQty" type="number" step="any" :class="inp" /></Field>
        <Field label="Target average"><input v-model.number="targetAvg" type="number" step="any" :class="inp" /></Field>
        <Field label="Dana tersedia (Rp)"><input v-model.number="budget" type="number" step="any" :class="inp" /></Field>
      </div>
      <div class="space-y-3 rounded-xl border p-4 text-sm">
        <div>
          <p class="text-gray-500">Kalau beli {{ fmt(addQty) }} {{ unitName }} di {{ fmt(addPrice) }}</p>
          <p>Average baru: <b>{{ fmt(resNewAvg) }}</b> ({{ fmt(((resNewAvg / avg) - 1) * 100) }}%)</p>
        </div>
        <div>
          <p class="text-gray-500">Supaya average jadi {{ fmt(targetAvg) }}</p>
          <p v-if="needQty === null" class="text-red-600">Tidak bisa dicapai di harga beli tersebut.</p>
          <p v-else>Beli <b>{{ fmt(needQty) }} {{ unitName }}</b> di {{ fmt(addPrice) }} (modal tambahan Rp {{ fmt(needCost, 0) }})</p>
        </div>
        <div>
          <p class="text-gray-500">Dengan dana Rp {{ fmt(budget, 0) }} di harga {{ fmt(addPrice) }}</p>
          <p>Dapat <b>{{ fmt(budgetQty) }} {{ unitName }}</b> → average jadi <b>{{ fmt(budgetAvg) }}</b></p>
        </div>
        <p class="text-xs text-gray-500">Hitungan average belum memasukkan fee beli tambahan.</p>
      </div>
    </section>

    <!-- RISK -->
    <section v-if="tab === 'risk'" class="grid gap-4 md:grid-cols-2">
      <div class="space-y-3 rounded-xl border p-4">
        <Field label="Total modal (Rp)"><input v-model.number="capital" type="number" step="any" :class="inp" /></Field>
        <Field label="Risiko maksimal (% modal)"><input v-model.number="riskPct" type="number" step="any" :class="inp" /></Field>
        <div class="grid grid-cols-3 gap-2">
          <Field label="Entry"><input v-model.number="entry" type="number" step="any" :class="inp" /></Field>
          <Field label="Stop loss"><input v-model.number="stop" type="number" step="any" :class="inp" /></Field>
          <Field label="Take profit"><input v-model.number="tp" type="number" step="any" :class="inp" /></Field>
        </div>
      </div>
      <div class="space-y-2 rounded-xl border p-4 text-sm">
        <div class="flex justify-between"><span>Boleh beli maksimal</span><b>{{ fmt(sizeQty) }} {{ unitName }}</b></div>
        <div class="flex justify-between"><span>Nilai posisi</span><b>Rp {{ fmt(sizeValue, 0) }} ({{ fmt((sizeValue / capital) * 100) }}% modal)</b></div>
        <div class="flex justify-between"><span>Risiko jika kena SL</span><b class="text-red-600">Rp {{ fmt(riskRp, 0) }}</b></div>
        <div class="flex justify-between"><span>Potensi untung di TP</span><b class="text-green-600">Rp {{ fmt(rewardRp, 0) }}</b></div>
        <div class="flex justify-between"><span>Risk : Reward</span><b>1 : {{ fmt(rr) }}</b></div>
        <p class="pt-2 text-xs text-gray-500">Belum memasukkan fee.</p>
      </div>
    </section>

    <!-- SCENARIO -->
    <section v-if="tab === 'scenario'" class="overflow-x-auto rounded-xl border">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left dark:bg-white/5">
          <tr><th class="p-3">Perubahan</th><th>Harga</th><th>P/L bersih</th><th>Return</th></tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.pct" class="border-t">
            <td class="p-3">{{ r.pct > 0 ? '+' : '' }}{{ r.pct }}%</td>
            <td>{{ fmt(r.price) }}</td>
            <td :class="tone(r.pl)">Rp {{ fmt(r.pl, 0) }}</td>
            <td :class="tone(r.ret)">{{ fmt(r.ret) }}%</td>
          </tr>
        </tbody>
      </table>
      <p class="p-3 text-xs text-gray-500">Dihitung dari harga sekarang, sudah termasuk fee jual.</p>
    </section>

    <!-- TICK -->
    <section v-if="tab === 'tick'" class="grid gap-4 md:grid-cols-2">
      <div class="rounded-xl border p-4">
        <Field label="Harga saham"><input v-model.number="tickPrice" type="number" step="any" :class="inp" /></Field>
      </div>
      <div class="space-y-2 rounded-xl border p-4 text-sm">
        <div class="flex justify-between"><span>Fraksi harga</span><b>{{ idxTick(tickPrice) }}</b></div>
        <div class="flex justify-between"><span>Dibulatkan ke bawah</span><b>{{ roundToTick(tickPrice, 'down') }}</b></div>
        <div class="flex justify-between"><span>Terdekat</span><b>{{ roundToTick(tickPrice, 'nearest') }}</b></div>
        <div class="flex justify-between"><span>Dibulatkan ke atas</span><b>{{ roundToTick(tickPrice, 'up') }}</b></div>
      </div>
    </section>
  </div>
</template>