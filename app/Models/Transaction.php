<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = ['asset_id', 'side', 'quantity', 'price', 'fee', 'transacted_at', 'note'];

    protected $casts = [
        'quantity' => 'float',
        'price' => 'float',
        'fee' => 'float',
        'transacted_at' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}