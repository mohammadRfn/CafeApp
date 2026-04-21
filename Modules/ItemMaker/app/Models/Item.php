<?php

namespace Modules\ItemMaker\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Box;
use App\Models\User;

/**
 * Item Model
 * 
 * 
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property string|null $category
 * @property string|null $subcategory
 * @property float $target_cost
 * @property float $target_sell_price
 * @property float|null $actual_sell_price
 * @property int $preparation_time
 * @property float|null $serving_size
 * @property string|null $serving_unit
 * @property bool $is_active
 * @property bool $is_featured
 * @property bool $requires_preparation
 * @property int|null $daily_stock_limit
 * @property int $daily_sold_count
 * @property int|null $calories
 * @property array|null $allergens
 * @property string|null $image_url
 * @property int $display_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon|null $last_cost_calculated_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Item extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'subcategory',
        'target_cost',
        'target_sell_price',
        'actual_sell_price',
        'preparation_time',
        'serving_size',
        'serving_unit',
        'is_active',
        'is_featured',
        'requires_preparation',
        'daily_stock_limit',
        'daily_sold_count',
        'calories',
        'allergens',
        'image_url',
        'display_order',
        'created_by',
        'updated_by',
        'last_cost_calculated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'target_cost' => 'decimal:2',
        'target_sell_price' => 'decimal:2',
        'actual_sell_price' => 'decimal:2',
        'serving_size' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'requires_preparation' => 'boolean',
        'daily_stock_limit' => 'integer',
        'daily_sold_count' => 'integer',
        'preparation_time' => 'integer',
        'calories' => 'integer',
        'display_order' => 'integer',
        'allergens' => 'array',
        'last_cost_calculated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'profit_margin',
        'is_profitable',
        'can_be_ordered',
    ];

    // =====================================
    // Relationships
    // =====================================

    /**
     * 
     * @return BelongsToMany
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'item_ingredients')
            ->withPivot([
                'required_grams',
                'waste_factor',
                'actual_grams',
                'unit_cost',
                'total_cost',
                'is_optional',
                'is_customizable',
                'preparation_note',
                'order',
            ])
            ->withTimestamps()
            ->orderByPivot('order', 'asc');
    }

    /**
     * 
     * @return BelongsToMany
     */
    public function boxes(): BelongsToMany
    {
        return $this->belongsToMany(Box::class, 'item_boxes')
            ->withPivot([
                'required_quantity',
                'unit_cost',
                'total_cost',
                'is_default_packaging',
                'is_optional',
                'note',
            ])
            ->withTimestamps();
    }

    /**
     * 
     * @return HasMany
     */
    public function costHistory(): HasMany
    {
        return $this->hasMany(ItemCostHistory::class)->latest('valid_from');
    }

    /**
     * 
     * @return HasMany
     */
    public function currentCost(): HasMany
    {
        return $this->costHistory()
            ->where('valid_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->limit(1);
    }

    /**
     * 
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 
     * @return BelongsTo
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =====================================
    // Scopes
    // =====================================

    /**
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    /**
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * 
     * @param Builder $query
     * @param string $category
     * @return Builder
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithDailyLimit(Builder $query): Builder
    {
        return $query->whereNotNull('daily_stock_limit');
    }

    /**
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeSoldOut(Builder $query): Builder
    {
        return $query->whereNotNull('daily_stock_limit')
            ->whereColumn('daily_sold_count', '>=', 'daily_stock_limit');
    }

    /**
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeAvailableToday(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('daily_stock_limit')
                ->orWhereColumn('daily_sold_count', '<', 'daily_stock_limit');
        });
    }

    /**
     * 
     * @param Builder $query
     * @param string $direction
     * @return Builder
     */
    public function scopeOrdered(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('display_order', $direction)
            ->orderBy('name', 'asc');
    }

    /**
     * 
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // =====================================
    // Accessors & Mutators
    // =====================================

    /**
     * 
     * @return float
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->target_cost <= 0) {
            return 0;
        }

        $sellPrice = $this->actual_sell_price ?? $this->target_sell_price;
        
        if ($sellPrice <= 0) {
            return 0;
        }

        return round((($sellPrice - $this->target_cost) / $sellPrice) * 100, 2);
    }

    /**
     * 
     * @return bool
     */
    public function getIsProfitableAttribute(): bool
    {
        $sellPrice = $this->actual_sell_price ?? $this->target_sell_price;
        return $sellPrice > $this->target_cost;
    }

    /**
     * 
     * @return bool
     */
    public function getCanBeOrderedAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->daily_stock_limit && $this->daily_sold_count >= $this->daily_stock_limit) {
            return false;
        }

        return true;
    }

    /**
     * 
     * @return float
     */
    public function getFinalSellPriceAttribute(): float
    {
        return $this->actual_sell_price ?? $this->target_sell_price;
    }

    // =====================================
    // Business Logic Methods
    // =====================================

    /**
     * 
     * @param int $quantity
     * @return bool
     */
    public function incrementDailySold(int $quantity = 1): bool
    {
        return $this->increment('daily_sold_count', $quantity);
    }

    /**
     * 
     * @param int $quantity
     * @return bool
     */
    public function decrementDailySold(int $quantity = 1): bool
    {
        $newCount = max(0, $this->daily_sold_count - $quantity);
        return $this->update(['daily_sold_count' => $newCount]);
    }

    /**
     * 
     * @return bool
     */
    public function resetDailySoldCount(): bool
    {
        return $this->update(['daily_sold_count' => 0]);
    }

    /**
     * 
     * @param int $quantity
     * @return array
     */
    public function checkIngredientsAvailability(int $quantity = 1): array
    {
        $availability = [
            'available' => true,
            'shortages' => [],
        ];

        foreach ($this->ingredients as $ingredient) {
            $requiredGrams = $ingredient->pivot->actual_grams * $quantity;
            $stock = $ingredient->stock;

            if (!$stock || $stock->available_grams < $requiredGrams) {
                $availability['available'] = false;
                $availability['shortages'][] = [
                    'ingredient_id' => $ingredient->id,
                    'ingredient_name' => $ingredient->ingredient_name,
                    'required' => $requiredGrams,
                    'available' => $stock ? $stock->available_grams : 0,
                    'shortage' => $requiredGrams - ($stock ? $stock->available_grams : 0),
                ];
            }
        }

        return $availability;
    }

    /**
     * 
     * @param int $quantity
     * @return array
     */
    public function checkBoxesAvailability(int $quantity = 1): array
    {
        $availability = [
            'available' => true,
            'shortages' => [],
        ];

        foreach ($this->boxes as $box) {
            $requiredQuantity = $box->pivot->required_quantity * $quantity;
            $stock = $box->stock;

            if (!$stock || $stock->available_quantity < $requiredQuantity) {
                $availability['available'] = false;
                $availability['shortages'][] = [
                    'box_id' => $box->id,
                    'box_name' => $box->name,
                    'required' => $requiredQuantity,
                    'available' => $stock ? $stock->available_quantity : 0,
                    'shortage' => $requiredQuantity - ($stock ? $stock->available_quantity : 0),
                ];
            }
        }

        return $availability;
    }

    /**
     * محاسبه هزینه تمام شده
     * 
     * @return float
     */
    public function calculateTotalCost(): float
    {
        $ingredientsCost = $this->ingredients->sum('pivot.total_cost');
        $boxesCost = $this->boxes->sum('pivot.total_cost');

        return round($ingredientsCost + $boxesCost, 2);
    }
}
