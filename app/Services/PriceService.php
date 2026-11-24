<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PriceService
{
    public function fetch(Asset $asset): ?float
    {
        return Cache::remember(
            "price:{$asset->type}:{$asset->symbol}",
            now()->addMinutes(5),
            function () use ($asset) {
                try {
                    return $asset->type === 'crypto'
                        ? $this->indodax($asset->symbol)
                        : $this->yahoo($asset->symbol);
                } catch (\Throwable $e) {
                    report($e);

                    return null;
                }
            },
        );
    }

    // Saham IDX (tidak resmi, bisa berubah/diblokir sewaktu-waktu)
    private function yahoo(string $symbol): ?float
    {
        $res = Http::timeout(8)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}.JK", [
                'interval' => '1d',
                'range' => '1d',
            ]);

        if (! $res->successful()) {
            return null;
        }

        $price = data_get($res->json(), 'chart.result.0.meta.regularMarketPrice');

        return $price ? (float) $price : null;
    }

    // Crypto dalam Rupiah (BTC -> btcidr)
    private function indodax(string $symbol): ?float
    {
        $res = Http::timeout(8)->get('https://indodax.com/api/ticker/'.strtolower($symbol).'idr');

        if (! $res->successful()) {
            return null;
        }

        $price = (float) data_get($res->json(), 'ticker.last');

        return $price > 0 ? $price : null;
    }
}