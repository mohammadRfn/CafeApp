<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'address', 'phone', 
        'capacity_kg', 'current_utilization', 'is_active'
    ];

    protected $casts = [
        'capacity_kg' => 'decimal:3',
        'current_utilization' => 'decimal:3'
    ];

    public function ingredientStocks()
    {
        return $this->hasMany(IngredientStock::class);
    }
}
