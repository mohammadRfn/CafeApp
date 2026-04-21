<?php

namespace Modules\ItemMaker\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

/**
 * ItemCostHistory Model
 * 
 * 
 * @property int $id
 * @property int $item_id
 * @property float $ingredients_cost
 * @property float $boxes_cost
 * @property float $overhead_cost
 * @property float $total_cost (computed)
 * @property float|null $suggested_sell_price
 * @property float|null $profit_margin
 * @property string $calculation_method
 * @property array|null $breakdown_details
 * @property string|null $notes
 * @property \Carbon\Carbon $valid_from
 * @property \Carbon\Carbon|null $valid_until
 * @property int|null $calculated_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class ItemCostHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'item_cost_history';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'item_id',
        'ingredients_cost',
        'boxes_cost',
        'overhead_cost',
        'suggested_sell_price',
        'profit_margin',
        'calculation_method',
        'breakdown_details',
        'notes',
        'valid_from',
        'valid_until',
        'calculated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ingredients_cost' => 'decimal:2',
        'boxes_cost' => 'decimal:2',
        'overhead_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'suggested_sell_price' => 'decimal:2',
        'profit_margin' => 'decimal:4',
        'breakdown_details' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'is_current',
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
     * کاربر محاسبه کننده
     * 
     * @return BelongsTo
     */
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    // =====================================
    // Scopes
    // =====================================

    /**
     * هزینه‌های فعلی (معتبر)
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });
    }

    /**
     * هزینه‌های گذشته (منقضی شده)
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('valid_until')
            ->where('valid_until', '<', now());
    }

    /**
     * محاسبات خودکار
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeAutoCalculated(Builder $query): Builder
    {
        return $query->where('calculation_method', 'auto');
    }

    /**
     * محاسبات دستی
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeManualCalculated(Builder $query): Builder
    {
        return $query->where('calculation_method', 'manual');
    }

    /**
     * فیلتر بر اساس بازه زمانی
     * 
     * @param Builder $query
     * @param string $from
     * @param string|null $to
     * @return Builder
     */
    public function scopeDateRange(Builder $query, string $from, ?string $to = null): Builder
    {
        $query->where('valid_from', '>=', $from);
        
        if ($to) {
            $query->where(function ($q) use ($to) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '<=', $to);
            });
        }

        return $query;
    }

    // =====================================
    // Accessors & Mutators
    // =====================================

    /**
     * آیا این هزینه فعلاً معتبر است؟
     * 
     * @return bool
     */
    public function getIsCurrentAttribute(): bool
    {
        $now = now();
        
        if ($this->valid_from > $now) {
            return false;
        }

        if ($this->valid_until && $this->valid_until < $now) {
            return false;
        }

        return true;
    }

    /**
     * محاسبه دستی total_cost (چون computed column است)
     * 
     * @return float
     */
    public function getTotalCostManualAttribute(): float
    {
        return round(
            $this->ingredients_cost + $this->boxes_cost + $this->overhead_cost,
            2
        );
    }

    /**
     * محاسبه سود خالص
     * 
     * @return float
     */
    public function getNetProfitAttribute(): float
    {
        if (!$this->suggested_sell_price) {
            return 0;
        }

        $totalCost = $this->total_cost ?? $this->total_cost_manual;
        return round($this->suggested_sell_price - $totalCost, 2);
    }

    // =====================================
    // Business Logic Methods
    // =====================================

    /**
     * محاسبه حاشیه سود
     * 
     * @param float|null $sellPrice
     * @return float
     */
    public function calculateProfitMargin(?float $sellPrice = null): float
    {
        $sellPrice = $sellPrice ?? $this->suggested_sell_price;
        
        if (!$sellPrice || $sellPrice <= 0) {
            return 0;
        }

        $totalCost = $this->total_cost ?? $this->total_cost_manual;
        
        return round((($sellPrice - $totalCost) / $sellPrice) * 100, 2);
    }

    /**
     * اعتبارسنجی تاریخ‌ها
     * 
     * @return bool
     */
    public function validateDates(): bool
    {
        if ($this->valid_until && $this->valid_until <= $this->valid_from) {
            return false;
        }

        return true;
    }

    /**
     * نامعتبر کردن این رکورد
     * 
     * @return bool
     */
    public function invalidate(): bool
    {
        $this->valid_until = now();
        return $this->save();
    }

    /**
     * ادامه دادن اعتبار
     * 
     * @return bool
     */
    public function extendValidity(): bool
    {
        $this->valid_until = null;
        return $this->save();
    }
}
