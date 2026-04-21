<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IngredientCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'sort_order'];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order');
    }
}
