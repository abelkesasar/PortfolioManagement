<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\PriceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $data = $request->validate([
            'symbol' => [
                'required', 'string', 'max:20',
                Rule::unique('assets', 'symbol')->where('user_id', $userId),
            ],
            'name' => 'required|string|max:100',
            'type' => 'required|in:stock,crypto',
            'current_price' => 'nullable|numeric|min:0',
        ]);

        $request->user()->assets()->create([
            'symbol' => strtoupper($data['symbol']),
            'name' => $data['name'],
            'type' => $data['type'],
            'current_price' => $data['current_price'] ?? 0,
            'price_updated_at' => now(),
        ]);

        return back();
    }

    // Update harga terkini manual
    public function updatePrice(Request $request, Asset $asset)
    {
        abort_unless($asset->user_id === $request->user()->id, 403);

        $data = $request->validate(['current_price' => 'required|numeric|min:0']);

        $asset->update($data + ['price_updated_at' => now()]);

        return back();
    }

    // Ambil harga otomatis untuk semua aset milik user
    public function refreshPrices(Request $request, PriceService $prices)
    {
        foreach ($request->user()->assets as $asset) {
            $price = $prices->fetch($asset);

            if ($price) {
                $asset->update([
                    'current_price' => $price,
                    'price_updated_at' => now(),
                ]);
            }
        }

        return back();
    }
}