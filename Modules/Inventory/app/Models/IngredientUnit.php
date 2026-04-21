<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class IngredientUnit extends Model
{
    use HasFactory;
    public $timestamps = false;  
    protected $fillable = [
        'ingredient_id', 'unit_id', 'is_default_display', 'conversion_rate'
    ];

    protected $casts = ['conversion_rate' => 'decimal:8', 'is_default_display' => 'boolean'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
