<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

class Ingredient extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'supplier_id', 'ingredient_name', 'ingredient_code', 'barcode',
        'description', 'min_stock', 'reorder_point', 'safety_stock',
        'abc_class', 'is_perishable', 'shelf_life_days', 'is_active'
    ];

    protected $casts = [
        'min_stock' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'safety_stock' => 'decimal:3',
        'is_perishable' => 'boolean',
        'is_active' => 'boolean'
    ];

    protected static function booted()
    {
        static::deleting(function ($ingredient) {
            $ingredient->transactions()->delete();
        });
    }

    public function category()
    {
        return $this->belongsTo(IngredientCategory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock()
    {
        return $this->hasOne(IngredientStock::class)->withDefault([
            'available_grams' => 0,
            'quantity_grams' => 0
        ]);
    }

    public function units()
    {
        return $this->belongsToMany(Unit::class, 'ingredient_units')
                    ->withPivot('conversion_rate', 'is_default_display');
    }

    public function transactions()
    {
        return $this->hasMany(IngredientTransaction::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class);
    }
    public function currentPrice()
    {
        return $this->hasOne(PriceHistory::class, 'ingredient_id')
            ->current()
            ->latest('valid_from');
    }
    public function getLatestPricePerGram(): float
    {
        return $this->currentPrice?->sell_price ?? 0;
    }
    public function boxIngredients()
    {
        return $this->belongsToMany(Box::class, 'box_ingredients')
                    ->withPivot('required_quantity', 'waste_factor', 'unit_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('stock', function ($q) {
            $q->whereColumn('available_grams', '<', DB::raw('reorder_point'));
        });
    }

    protected function defaultUnit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->units()->wherePivot('is_default_display', true)->first()
        );
    }

    public function getDisplayStockAttribute()
    {
        $unit = $this->defaultUnit;
        return $unit ? $this->stock->quantity_grams / $unit->conversion_factor : 0;
    }

    public function getStockStatusAttribute()
    {
        $stock = $this->stock->available_grams;
        if ($stock <= 0) {
            return 'out_of_stock';
        }
        if ($stock < $this->reorder_point) {
            return 'low_stock';
        }
        return 'in_stock';
    }
}
