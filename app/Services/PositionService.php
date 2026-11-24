<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Collection;

class PositionService
{
    /** Hitung posisi satu aset dari transaksi (metode average cost). */
    public function forAsset(Asset $asset, Collection $transactions): array
    {
        $mult = $asset->unit_multiplier;
        $qty = 0.0;        // dalam lot / unit
        $cost = 0.0;       // total modal posisi tersisa
        $realized = 0.0;

        foreach ($this->ordered($transactions) as $t) {
            $value = $t->quantity * $mult * $t->price;

            if ($t->side === 'buy') {
                $qty += $t->quantity;
                $cost += $value + $t->fee;
            } else {
                $avgPerUnit = $qty > 0 ? $cost / $qty : 0;
                $realized += ($value - $t->fee) - ($avgPerUnit * $t->quantity);
                $cost -= $avgPerUnit * $t->quantity;
                $qty -= $t->quantity;
            }
        }

        $avgPrice = $qty > 0 ? $cost / ($qty * $mult) : 0;
        $marketValue = $qty * $mult * $asset->current_price;

        return [
            'asset_id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'type' => $asset->type,
            'quantity' => $qty,
            'avg_price' => $avgPrice,
            'total_cost' => $cost,
            'current_price' => $asset->current_price,
            'market_value' => $marketValue,
            'unrealized_pl' => $marketValue - $cost,
            'unrealized_pl_pct' => $cost > 0 ? ($marketValue - $cost) / $cost * 100 : 0,
            'realized_pl' => $realized,
        ];
    }

    /**
     * Profit/loss tiap transaksi JUAL, dibanding average beli saat itu.
     * Hasil: [id_transaksi => ['avg_price' => ..., 'pl' => ..., 'pl_pct' => ...]]
     */
    public function realizedBySale(Asset $asset, Collection $transactions): array
    {
        $mult = $asset->unit_multiplier;
        $qty = 0.0;
        $cost = 0.0;
        $result = [];

        foreach ($this->ordered($transactions) as $t) {
            $value = $t->quantity * $mult * $t->price;

            if ($t->side === 'buy') {
                $qty += $t->quantity;
                $cost += $value + $t->fee;
                continue;
            }

            $avgPerLot = $qty > 0 ? $cost / $qty : 0;
            $basis = $avgPerLot * $t->quantity;
            $pl = ($value - $t->fee) - $basis;

            $result[$t->id] = [
                'avg_price' => $avgPerLot / $mult,   // per lembar / per koin
                'pl' => $pl,
                'pl_pct' => $basis > 0 ? $pl / $basis * 100 : 0,
            ];

            $cost -= $basis;
            $qty -= $t->quantity;
        }

        return $result;
    }

    private function ordered(Collection $transactions): Collection
    {
        return $transactions->sortBy([['transacted_at', 'asc'], ['id', 'asc']]);
    }
}