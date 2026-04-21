<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoxIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'box_id', 'ingredient_id', 'unit_id', 
        'required_quantity', 'waste_factor'
    ];

    protected $casts = [
        'required_quantity' => 'decimal:6',
        'waste_factor' => 'decimal:3'
    ];

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
