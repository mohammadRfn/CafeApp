<?php

namespace Modules\OrderInvoice\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\ItemMaker\Models\Item;

/**
 * OrderItem Model
 * 
 * آیتم سفارش با snapshot قیمت و recipe
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'item_id',
        'item_snapshot',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'item_snapshot' => 'array',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
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
     * محصول از ItemMaker
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * ردیابی مصرف موجودی
     */
    public function inventoryUsages(): HasMany
    {
        return $this->hasMany(InventoryUsage::class);
    }

    // ═══════════════════════════════════════════════════════════
    // Accessors
    // ═══════════════════════════════════════════════════════════

    /**
     * نام محصول از snapshot
     */
    protected function itemName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->item_snapshot['name'] ?? 'نامشخص'
        );
    }

    /**
     * کد محصول از snapshot
     */
    protected function itemCode(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->item_snapshot['code'] ?? null
        );
    }

    /**
     * recipe از snapshot
     */
    protected function recipe(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->item_snapshot['recipe'] ?? []
        );
    }

    /**
     * ingredients از recipe
     */
    protected function recipeIngredients(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->recipe['ingredients'] ?? []
        );
    }

    /**
     * boxes از recipe
     */
    protected function recipeBoxes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->recipe['boxes'] ?? []
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Business Logic
    // ═══════════════════════════════════════════════════════════

    /**
     * محاسبه total_price
     */
    public function calculateTotal(): void
    {
        $this->total_price = $this->unit_price * $this->quantity;
    }

    /**
     * بروزرسانی quantity
     */
    public function updateQuantity(int $newQuantity): bool
    {
        if ($newQuantity < 1) {
            return false;
        }

        $this->quantity = $newQuantity;
        $this->calculateTotal();
        $this->save();

        // Recalculate order totals
        $this->order->recalculatePricing();
        $this->order->save();

        return true;
    }

    /**
     * ساخت snapshot از Item
     */
    public static function createSnapshot(Item $item): array
    {
        // Load relationships
        $item->load(['ingredients', 'boxes']);

        return [
            'name' => $item->name,
            'code' => $item->code,
            'final_sell_price' => (float) $item->final_sell_price,
            'target_cost' => (float) $item->target_cost,
            'recipe' => [
                'ingredients' => $item->ingredients->map(function ($ingredient) {
                    return [
                        'id' => $ingredient->id,
                        'name' => $ingredient->ingredient_name,
                        'code' => $ingredient->ingredient_code,
                        'required_grams' => (float) $ingredient->pivot->required_grams,
                        'waste_factor' => (float) $ingredient->pivot->waste_factor,
                        'actual_grams' => (float) $ingredient->pivot->actual_grams,
                        'unit_cost' => (float) $ingredient->pivot->unit_cost,
                        'total_cost' => (float) $ingredient->pivot->total_cost,
                    ];
                })->toArray(),
                'boxes' => $item->boxes->map(function ($box) {
                    return [
                        'id' => $box->id,
                        'name' => $box->name,
                        'code' => $box->code,
                        'required_quantity' => (int) $box->pivot->required_quantity,
                        'unit_cost' => (float) $box->pivot->unit_cost,
                        'total_cost' => (float) $box->pivot->total_cost,
                    ];
                })->toArray(),
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Events
    // ═══════════════════════════════════════════════════════════

    protected static function booted()
    {
        // Auto-calculate total on creation
        static::creating(function ($orderItem) {
            $orderItem->calculateTotal();
        });

        // Recalculate order totals when item changes
        // static::saved(function ($orderItem) {
        //     $orderItem->order->recalculatePricing();
        //     $orderItem->order->save();
     //    });

        // // Recalculate order totals when item deleted
        // static::deleted(function ($orderItem) {
        //     $orderItem->order->recalculatePricing();
        //     $orderItem->order->save();
        // });
    }
}