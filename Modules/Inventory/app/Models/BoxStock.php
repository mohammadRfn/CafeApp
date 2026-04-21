<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoxStock extends Model
{
    use HasFactory;
    protected $table = 'box_stock';
    public $timestamps = false;
    protected $fillable = ['box_id', 'quantity', 'reserved_quantity'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'reserved_quantity' => 'decimal:3'
    ];

    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function getAvailableAttribute()
    {
        return $this->quantity - $this->reserved_quantity;
    }
}
