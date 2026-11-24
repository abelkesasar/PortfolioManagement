export type AssetType = 'stock' | 'crypto'

// Semua fungsi bekerja dalam "unit" (lembar / koin), bukan lot.
export const unitMultiplier = (type: AssetType) => (type === 'stock' ? 100 : 1)

export const costBasis = (avg: number, units: number, feeBuy: number) =>
  avg * units * (1 + feeBuy)

// Harga jual supaya hasil bersih = profit (profit negatif = rugi)
export const priceForProfit = (cost: number, units: number, feeSell: number, profit: number) =>
  units > 0 ? (cost + profit) / (units * (1 - feeSell)) : 0

export const breakEven = (cost: number, units: number, feeSell: number) =>
  priceForProfit(cost, units, feeSell, 0)

export const netPL = (price: number, cost: number, units: number, feeSell: number) =>
  price * units * (1 - feeSell) - cost

export const newAverage = (curUnits: number, curAvg: number, buyUnits: number, buyPrice: number) =>
  curUnits + buyUnits > 0 ? (curUnits * curAvg + buyUnits * buyPrice) / (curUnits + buyUnits) : 0

// Unit yang harus dibeli di buyPrice supaya average jadi targetAvg (null = mustahil)
export function unitsToReachAverage(curUnits: number, curAvg: number, buyPrice: number, targetAvg: number) {
  const d = targetAvg - buyPrice
  if (d === 0) return null
  const n = (curUnits * (curAvg - targetAvg)) / d
  return n > 0 && Number.isFinite(n) ? n : null
}

export const positionSize = (capital: number, riskPct: number, entry: number, stop: number) => {
  const riskPerUnit = Math.abs(entry - stop)
  return riskPerUnit > 0 ? (capital * (riskPct / 100)) / riskPerUnit : 0
}

export const riskReward = (entry: number, stop: number, tp: number) => {
  const risk = Math.abs(entry - stop)
  return risk > 0 ? Math.abs(tp - entry) / risk : 0
}

export function scenarios(
  cost: number, units: number, feeSell: number, base: number,
  steps = [-30, -20, -10, -5, 0, 5, 10, 20, 30, 50, 100],
) {
  return steps.map((pct) => {
    const price = base * (1 + pct / 100)
    const pl = netPL(price, cost, units, feeSell)
    return { pct, price, pl, ret: cost > 0 ? (pl / cost) * 100 : 0 }
  })
}

// Fraksi harga IDX
export function idxTick(price: number) {
  if (price < 200) return 1
  if (price < 500) return 2
  if (price < 2000) return 5
  if (price < 5000) return 10
  return 25
}

export function roundToTick(price: number, mode: 'up' | 'down' | 'nearest' = 'nearest') {
  const t = idxTick(price)
  const fn = mode === 'up' ? Math.ceil : mode === 'down' ? Math.floor : Math.round
  return fn(price / t) * t
}   