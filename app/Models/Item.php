<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Item extends Model
{
    use HasFactory;

    public function autoBids()
    {
        return $this->hasMany('App\Models\AutoBid');
    }

    public function scopeHasAutoBid($query, $item)
    {
        return $query->with(['autoBids' => function ($query) use ($item) {
            $query->where('user_id', Auth::id())->where('item_id', $item->id);
        }])->find($item->id);
    }
}
