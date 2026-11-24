<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            ['BBCA', 'Bank Central Asia', 'stock'],
            ['BBRI', 'Bank Rakyat Indonesia', 'stock'],
            ['BMRI', 'Bank Mandiri', 'stock'],
            ['TLKM', 'Telkom Indonesia', 'stock'],
            ['BTC', 'Bitcoin', 'crypto'],
            ['ETH', 'Ethereum', 'crypto'],
            ['SOL', 'Solana', 'crypto'],
        ];

        foreach ($assets as [$symbol, $name, $type]) {
            Asset::firstOrCreate(['symbol' => $symbol], ['name' => $name, 'type' => $type]);
        }
    }
}