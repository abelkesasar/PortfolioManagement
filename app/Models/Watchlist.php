<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watchlist extends Model
{
    protected $fillable = ['user_id', 'asset_id'];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}