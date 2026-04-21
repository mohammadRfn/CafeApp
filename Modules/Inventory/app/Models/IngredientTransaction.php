<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IngredientTransaction extends Model
{
    use HasFactory;
    public $timestamps = false;  

    protected $fillable = [
        'ingredient_id', 'batch_number', 'transaction_type', 
        'input_quantity', 'input_unit_id', 'grams_effect', 
        'unit_cost', 'total_cost', 'reference_type', 
        'reference_id', 'invoice_number', 'notes', 'created_by', 'status', 'ingredient_name',
        'ingredient_code',
    ];

    protected $casts = [
        'input_quantity' => 'decimal:3',
        'grams_effect' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4'
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'input_unit_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc')->limit(100);
    }
}
