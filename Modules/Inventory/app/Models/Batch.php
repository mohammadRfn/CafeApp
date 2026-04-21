<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Inventory\Enums\BatchStatus;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_id', 'batch_number', 'quantity_received', 
        'quantity_used', 'expiry_date', 'supplier_id', 'status'
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'quantity_used' => 'decimal:3',
        'expiry_date' => 'date',
        'status' => BatchStatus::class
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                     ->where('expiry_date', '>', now());
    }

    public function scopeExpiringSoon($query)
    {
        return $query->whereBetween('expiry_date', [now(), now()->addDays(7)]);
    }
}
