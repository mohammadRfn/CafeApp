<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'contact_person', 'phone', 'email', 
        'rating', 'avg_delivery_days', 'is_active'
    ];

    protected $casts = ['rating' => 'decimal:2'];

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
