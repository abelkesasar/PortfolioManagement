<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
protected $fillable = ['user_id', 'symbol', 'name', 'type', 'current_price', 'price_updated_at'];

public function user()
{
    return $this->belongsTo(User::class);
}
    protected $casts = [
        'current_price' => 'float',
        'price_updated_at' => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // 1 lot saham = 100 lembar, crypto = 1
    public function getUnitMultiplierAttribute(): int
    {
        return $this->type === 'stock' ? 100 : 1;
    }
}