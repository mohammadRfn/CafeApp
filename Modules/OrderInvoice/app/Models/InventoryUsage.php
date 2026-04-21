<?php

namespace Modules\OrderInvoice\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Box;

/**
 * InventoryUsage Model
 * 
 * ردیابی مصرف موجودی برای هر سفارش (Polymorphic)
 */
class InventoryUsage extends Model
{
    use HasFactory;

    public $timestamps = false; // فقط created_at

    protected $fillable = [
        'order_id',
        'order_item_id',
        'entity_type',
        'entity_id',
        'quantity_used',
        'unit',
        'transaction_id',
        'usage_type',
        'created_at',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:3',
        'created_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // Relationships
    // ═══════════════════════════════════════════════════════════

    /**
     * سفارش مربوطه
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * آیتم سفارش
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Entity polymorphic (Ingredient یا Box)
     */
    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id')
            ->morphWith([
                'ingredient' => Ingredient::class,
                'box' => Box::class,
            ]);
    }

    /**
     * Ingredient (اگه entity_type = ingredient)
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'entity_id')
            ->where('entity_type', 'ingredient');
    }

    /**
     * Box (اگه entity_type = box)
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'entity_id')
            ->where('entity_type', 'box');
    }

    // ═══════════════════════════════════════════════════════════
    // Scopes
    // ═══════════════════════════════════════════════════════════

    /**
     * فقط ingredients
     */
    public function scopeIngredients($query)
    {
        return $query->where('entity_type', 'ingredient');
    }

    /**
     * فقط boxes
     */
    public function scopeBoxes($query)
    {
        return $query->where('entity_type', 'box');
    }

    /**
     * فقط commit
     */
    public function scopeCommits($query)
    {
        return $query->where('usage_type', 'commit');
    }

    /**
     * فقط rollback
     */
    public function scopeRollbacks($query)
    {
        return $query->where('usage_type', 'rollback');
    }

    /**
     * برای یک entity خاص
     */
    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('entity_type', $type)
            ->where('entity_id', $id);
    }

    /**
     * در بازه زمانی
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * امروز
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    // ═══════════════════════════════════════════════════════════
    // Accessors
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا ingredient است؟
     */
    protected function isIngredient(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->entity_type === 'ingredient'
        );
    }

    /**
     * آیا box است؟
     */
    protected function isBox(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->entity_type === 'box'
        );
    }

    /**
     * آیا commit است؟
     */
    protected function isCommit(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->usage_type === 'commit'
        );
    }

    /**
     * آیا rollback است؟
     */
    protected function isRollback(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->usage_type === 'rollback'
        );
    }

    /**
     * نوع entity به فارسی
     */
    protected function entityTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->entity_type === 'ingredient' ? 'ماده اولیه' : 'بسته‌بندی'
        );
    }

    /**
     * نوع usage به فارسی
     */
    protected function usageTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->usage_type === 'commit' ? 'مصرف' : 'برگشت'
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Business Logic
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت مصرف ingredient
     */
    public static function recordIngredientUsage(
        int $orderId,
        int $orderItemId,
        int $ingredientId,
        float $quantityGrams,
        ?int $transactionId = null,
        string $usageType = 'commit'
    ): self {
        return static::create([
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'entity_type' => 'ingredient',
            'entity_id' => $ingredientId,
            'quantity_used' => $quantityGrams,
            'unit' => 'gram',
            'transaction_id' => $transactionId,
            'usage_type' => $usageType,
            'created_at' => now(),
        ]);
    }

    /**
     * ثبت مصرف box
     */
    public static function recordBoxUsage(
        int $orderId,
        int $orderItemId,
        int $boxId,
        int $quantity,
        ?int $transactionId = null,
        string $usageType = 'commit'
    ): self {
        return static::create([
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'entity_type' => 'box',
            'entity_id' => $boxId,
            'quantity_used' => $quantity,
            'unit' => 'piece',
            'transaction_id' => $transactionId,
            'usage_type' => $usageType,
            'created_at' => now(),
        ]);
    }

    /**
     * جمع مصرف یک entity در بازه زمانی
     */
    public static function totalUsageFor(string $type, int $id, $startDate = null, $endDate = null): float
    {
        $query = static::forEntity($type, $id)->commits();

        if ($startDate && $endDate) {
            $query->betweenDates($startDate, $endDate);
        }

        return $query->sum('quantity_used');
    }

    /**
     * جمع مصرف ingredient امروز
     */
    public static function ingredientUsageToday(int $ingredientId): float
    {
        return static::ingredients()
            ->commits()
            ->where('entity_id', $ingredientId)
            ->today()
            ->sum('quantity_used');
    }

    /**
     * جمع مصرف box امروز
     */
    public static function boxUsageToday(int $boxId): int
    {
        return (int) static::boxes()
            ->commits()
            ->where('entity_id', $boxId)
            ->today()
            ->sum('quantity_used');
    }

    // ═══════════════════════════════════════════════════════════
    // Events
    // ═══════════════════════════════════════════════════════════

    protected static function booted()
    {
        // Set created_at on creation
        static::creating(function ($usage) {
            if (!$usage->created_at) {
                $usage->created_at = now();
            }
        });
    }
}