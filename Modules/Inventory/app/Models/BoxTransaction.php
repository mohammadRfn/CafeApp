<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BoxTransaction extends Model
{
    use HasFactory;
    
    public $timestamps = false;  

    protected $table = 'box_transactions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'box_id', 'batch_number', 'transaction_type', 
        'input_quantity', 'input_unit_id', 'quantity_effect', 
        'unit_cost', 'total_cost', 'reference_type', 
        'reference_id', 'invoice_number', 'notes', 
        'created_by', 'status', 'entity_name', 'entity_code',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'input_quantity' => 'decimal:3',
        'quantity_effect' => 'decimal:3',  
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4'
    ];

    /**
     * Relationships
     */
    public function box()
    {
        return $this->belongsTo(Box::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'input_unit_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Scopes
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc')->limit(100);
    }
}
