<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'symbol', 'conversion_factor', 'precision_digits'
    ];

    protected $casts = ['conversion_factor' => 'decimal:8'];

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_units')
                    ->withPivot('conversion_rate', 'is_default_display');
    }

    public function ingredientUnits()
    {
        return $this->hasMany(IngredientUnit::class);
    }

    public function transactions()
    {
        return $this->hasMany(IngredientTransaction::class, 'input_unit_id');
    }

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function boxIngredients()
    {
        return $this->hasMany(BoxIngredient::class);
    }
}
