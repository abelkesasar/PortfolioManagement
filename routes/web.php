<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/portfolio')->name('home');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/portfolio')->name('dashboard');

    Route::get('/portfolio', [PortfolioController::class, 'index'])->name('portfolio');
    Route::post('/transactions', [PortfolioController::class, 'storeTransaction'])->name('transactions.store');
    Route::delete('/transactions/{transaction}', [PortfolioController::class, 'destroyTransaction'])->name('transactions.destroy');

    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::patch('/assets/{asset}/price', [AssetController::class, 'updatePrice'])->name('assets.price');

    Route::get('/calculator', [CalculatorController::class, 'index'])->name('calculator');

    Route::get('/transactions', [PortfolioController::class, 'history'])->name('transactions.index');

    Route::post('/assets/refresh-prices', [AssetController::class, 'refreshPrices'])->name('assets.refresh');
});

// Hapus baris di bawah kalau file-nya tidak ada
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';