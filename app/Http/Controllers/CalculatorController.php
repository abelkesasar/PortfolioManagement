<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\PositionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalculatorController extends Controller
{
    public function index(Request $request, PositionService $service)
    {
        $userId = $request->user()->id;

        $positions = Transaction::with('asset')
            ->whereHas('asset', fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($txs) => $service->forAsset($txs->first()->asset, $txs))
            ->filter(fn ($p) => $p['quantity'] > 0)
            ->values();

        return Inertia::render('Calculator/Index', ['positions' => $positions]);
    }
}