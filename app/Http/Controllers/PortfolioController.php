<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Transaction;
use App\Services\PositionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PortfolioController extends Controller
{
    public function index(Request $request, PositionService $service)
    {
        $userId = $request->user()->id;

        $transactions = Transaction::with('asset')
            ->whereHas('asset', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $byAsset = $transactions->groupBy('asset_id')
            ->map(fn ($txs) => $service->forAsset($txs->first()->asset, $txs));

        $positions = $byAsset->filter(fn ($p) => $p['quantity'] > 0)->values();

        return Inertia::render('Portfolio/Index', [
            'positions' => $positions,
            'summary' => [
                'total_cost' => $positions->sum('total_cost'),
                'market_value' => $positions->sum('market_value'),
                'unrealized_pl' => $positions->sum('unrealized_pl'),
                'realized_pl' => $byAsset->sum('realized_pl'),
            ],
            'assets' => Asset::where('user_id', $userId)
                ->orderBy('symbol')
                ->get(['id', 'symbol', 'name', 'type', 'current_price']),
        ]);
    }

    public function history(Request $request, PositionService $service)
    {
        $userId = $request->user()->id;

        $all = Transaction::with('asset')
            ->whereHas('asset', fn ($q) => $q->where('user_id', $userId))
            ->get();

        // P/L tiap transaksi jual
        $sales = [];
        foreach ($all->groupBy('asset_id') as $txs) {
            $sales += $service->realizedBySale($txs->first()->asset, $txs);
        }

        // Aset yang masih dimiliki (untuk form jual)
        $holdings = $all->groupBy('asset_id')
            ->map(fn ($txs) => $service->forAsset($txs->first()->asset, $txs))
            ->filter(fn ($p) => $p['quantity'] > 0)
            ->values();

        $transactions = $all
            ->sortBy([['transacted_at', 'desc'], ['id', 'desc']])
            ->values()
            ->map(function ($t) use ($sales) {
                $s = $sales[$t->id] ?? null;

                return [
                    'id' => $t->id,
                    'date' => $t->transacted_at->format('Y-m-d'),
                    'symbol' => $t->asset->symbol,
                    'name' => $t->asset->name,
                    'type' => $t->asset->type,
                    'side' => $t->side,
                    'quantity' => $t->quantity,
                    'price' => $t->price,
                    'fee' => $t->fee,
                    'total' => $t->quantity * $t->asset->unit_multiplier * $t->price,
                    'note' => $t->note,
                    'avg_price' => $s['avg_price'] ?? null,
                    'pl' => $s['pl'] ?? null,
                    'pl_pct' => $s['pl_pct'] ?? null,
                ];
            });

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'holdings' => $holdings,
            'assets' => Asset::where('user_id', $userId)
                ->orderBy('symbol')
                ->get(['id', 'symbol', 'name', 'type', 'current_price']),
        ]);
    }

    public function storeTransaction(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'symbol' => 'required|string|max:20',
            'name' => 'nullable|string|max:100',
            'type' => 'required|in:stock,crypto',
            'side' => 'required|in:buy,sell',
            'quantity' => 'required|numeric|gt:0',
            'price' => 'required|numeric|gt:0',
            'fee' => 'nullable|numeric|min:0',
            'transacted_at' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        $symbol = strtoupper(trim($data['symbol']));

        $asset = $user->assets()->where('symbol', $symbol)->first();

        if (! $asset) {
            if ($data['side'] === 'sell') {
                throw ValidationException::withMessages([
                    'symbol' => 'Kamu belum punya aset ini, jadi tidak bisa dijual.',
                ]);
            }

            $asset = $user->assets()->create([
                'symbol' => $symbol,
                'name' => $data['name'] ?: $symbol,
                'type' => $data['type'],
                'current_price' => $data['price'],
                'price_updated_at' => now(),
            ]);
        }

        if ($data['side'] === 'sell') {
            $held = $asset->transactions()->get()
                ->sum(fn ($t) => $t->side === 'buy' ? $t->quantity : -$t->quantity);

            if ($data['quantity'] > $held + 1e-9) {
                $heldText = rtrim(rtrim(number_format($held, 8, '.', ''), '0'), '.');

                throw ValidationException::withMessages([
                    'quantity' => "Jumlah jual melebihi kepemilikan ({$heldText}).",
                ]);
            }
        }

        Transaction::create([
            'asset_id' => $asset->id,
            'side' => $data['side'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
            'fee' => $data['fee'] ?? 0,
            'transacted_at' => $data['transacted_at'],
            'note' => $data['note'] ?? null,
        ]);

        return back();
    }

    public function destroyTransaction(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->asset->user_id === $request->user()->id, 403);

        $transaction->delete();

        return back();
    }
}