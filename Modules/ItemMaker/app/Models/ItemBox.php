<?php

namespace Modules\ItemMaker\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Box;

/**
 * ItemBox Pivot Model
 * 
 * رابطه بین Item و Box (Packaging/Components)
 * 
 * @property int $id
 * @property int $item_id
 * @property int $box_id
 * @property int $required_quantity
 * @property float $unit_cost
 * @property float $total_cost (computed)
 * @property bool $is_default_packaging
 * @property bool $is_optional
 * @property string|null $note
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ItemBox extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'item_boxes';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'item_id',
        'box_id',
        'required_quantity',
        'unit_cost',
        'is_default_packaging',
        'is_optional',
        'note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'required_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_default_packaging' => 'boolean',
        'is_optional' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'total_cost_manual',
    ];

    // =====================================
    // Relationships
    // =====================================

    /**
     * محصول مربوطه
     * 
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * باکس مربوطه
     * 
     * @return BelongsTo
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    // =====================================
    // Accessors & Mutators
    // =====================================

    /**
     * محاسبه دستی total_cost (چون computed column است)
     * 
     * @return float
     */
    public function getTotalCostManualAttribute(): float
    {
        return round($this->required_quantity * $this->unit_cost, 2);
    }

    // =====================================
    // Business Logic Methods
    // =====================================

    /**
     * محاسبه هزینه کل
     * 
     * @return float
     */
    public function calculateTotalCost(): float
    {
        return $this->required_quantity * $this->unit_cost;
    }

    /**
     * بروزرسانی قیمت واحد از آخرین قیمت box
     * 
     * @return bool
     */
    public function updateUnitCostFromLatestPrice(): bool
    {
        $box = $this->box()->first();
        
        if (!$box) {
            return false;
        }

        // اگر box قیمت داشته باشد
        $latestPrice = $box->prices()->latest('valid_from')->first();
        
        if ($latestPrice) {
            $this->unit_cost = $latestPrice->buy_price ?? 0;
            return $this->save();
        }

        return false;
    }
}
