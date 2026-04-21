<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class IngredientStock extends Model
{
    use HasFactory;
    protected $table = 'ingredient_stock';
    public $timestamps = false;
    protected $fillable = [
        'ingredient_id', 'quantity_grams', 'available_grams', 
        'reserved_grams', 'avg_cost_per_gram', 'last_updated'
    ];

    protected $casts = [
        'quantity_grams' => 'decimal:3',
        'available_grams' => 'decimal:3',
        'reserved_grams' => 'decimal:3',
        'avg_cost_per_gram' => 'decimal:6'
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    protected function displayQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ingredient->defaultUnit 
                ? $this->quantity_grams / $this->ingredient->defaultUnit->conversion_factor 
                : $this->quantity_grams
        );
    }

    public function getStatusAttribute()
    {
        $available = $this->available_grams;
        $reorder = $this->ingredient->reorder_point ?? 0;
        
        if ($available <= 0) return 'out_of_stock';
        if ($available < $reorder) return 'low_stock';
        return 'in_stock';
    }
}
