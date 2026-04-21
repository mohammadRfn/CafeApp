<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceHistory extends Model
{
    use HasFactory;
    protected $table = 'price_history';
    public $timestamps = false;
    protected $fillable = [
        'ingredient_id', 'unit_id', 'box_id','buy_price', 'sell_price',
        'valid_from', 'valid_until'
    ];

    protected $casts = [
        'buy_price' => 'decimal:4',
        'sell_price' => 'decimal:4',
        'valid_from' => 'date',
        'valid_until' => 'date'
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('valid_from', '<=', now()->startOfDay())
                     ->where(function ($q) {
                         $q->whereNull('valid_until')
                           ->orWhere('valid_until', '>=', now()->startOfDay());
                     });
    }
}
