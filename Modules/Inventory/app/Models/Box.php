<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Box extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'barcode', 'total_weight_grams',
        'target_sell_price', 'expected_margin', 'is_active'
    ];

    protected $casts = [
        'total_weight_grams' => 'decimal:3',
        'target_sell_price' => 'decimal:2',
        'expected_margin' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'box_ingredients')
                    ->withPivot('required_quantity', 'waste_factor', 'unit_id');
    }

    public function stock()
    {
        return $this->hasOne(BoxStock::class)->withDefault(['quantity' => 0]);
    }

    protected function totalCost(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ingredients->sum(function ($ingredient) {
                $pivot = $ingredient->pivot;
                return $pivot->required_quantity * (1 + $pivot->waste_factor) *
                       ($ingredient->stock->avg_cost_per_gram ?? 0);
            })
        );
    }
    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class, 'box_id');
    }

    public function currentPrice()
    {
        return $this->hasOne(PriceHistory::class, 'box_id')
            ->current()
            ->latest('valid_from');
    }
    public function getLatestUnitPrice(): float
    {
        return $this->currentPrice?->sell_price ?? 0;
    }
    public function getMarginAttribute()
    {
        return $this->target_sell_price ?
            (($this->target_sell_price - $this->total_cost) / $this->target_sell_price) * 100 : 0;
    }
}
