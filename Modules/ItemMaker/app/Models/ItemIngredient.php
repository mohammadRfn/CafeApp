<?php

namespace Modules\ItemMaker\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\Ingredient;

/**
 * ItemIngredient Pivot Model
 * 
 * رابطه بین Item و Ingredient (Recipe Definition)
 * 
 * @property int $id
 * @property int $item_id
 * @property int $ingredient_id
 * @property float $required_grams
 * @property float $waste_factor
 * @property float $actual_grams (computed)
 * @property float $unit_cost
 * @property float $total_cost (computed)
 * @property bool $is_optional
 * @property bool $is_customizable
 * @property string|null $preparation_note
 * @property int $order
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ItemIngredient extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'item_ingredients';

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
        'ingredient_id',
        'required_grams',
        'waste_factor',
        'unit_cost',
        'is_optional',
        'is_customizable',
        'preparation_note',
        'order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'required_grams' => 'decimal:3',
        'waste_factor' => 'decimal:4',
        'actual_grams' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_optional' => 'boolean',
        'is_customizable' => 'boolean',
        'order' => 'integer',
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
     * ماده اولیه مربوطه
     * 
     * @return BelongsTo
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    // =====================================
    // Accessors & Mutators
    // =====================================

    /**
     * محاسبه دستی actual_grams (چون computed column است)
     * 
     * @return float
     */
    public function getActualGramsManualAttribute(): float
    {
        return round($this->required_grams * (1 + $this->waste_factor), 3);
    }

    /**
     * محاسبه دستی total_cost (چون computed column است)
     * 
     * @return float
     */
    public function getTotalCostManualAttribute(): float
    {
        $actualGrams = $this->actual_grams ?? $this->actual_grams_manual;
        return round($actualGrams * $this->unit_cost, 2);
    }

    // =====================================
    // Business Logic Methods
    // =====================================

    /**
     * محاسبه مقدار واقعی با احتساب ضایعات
     * 
     * @return float
     */
    public function calculateActualGrams(): float
    {
        return $this->required_grams * (1 + $this->waste_factor);
    }

    /**
     * محاسبه هزینه کل
     * 
     * @return float
     */
    public function calculateTotalCost(): float
    {
        return $this->calculateActualGrams() * $this->unit_cost;
    }

    /**
     * بروزرسانی قیمت واحد از آخرین قیمت ingredient
     * 
     * @return bool
     */
    public function updateUnitCostFromLatestPrice(): bool
    {
        $ingredient = $this->ingredient()->with('stock')->first();
        
        if (!$ingredient || !$ingredient->stock) {
            return false;
        }

        $this->unit_cost = $ingredient->stock->avg_cost_per_gram ?? 0;
        return $this->save();
    }
}
