<?php

namespace Modules\OrderInvoice\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;
use App\Models\User;

/**
 * Order Model
 * 
 * مدل سفارش - مدیریت کامل lifecycle سفارش
 */
class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'status',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'tax_percent',
        'tax_amount',
        'delivery_fee',
        'total_amount',
        'notes',
        'refund_reason',
        'refund_type',
        'created_by',
        'confirmed_at',
        'paid_at',
        'completed_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // Relationships
    // ═══════════════════════════════════════════════════════════

    /**
     * آیتم‌های سفارش
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * فاکتور (1-to-1)
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * ردیابی مصرف موجودی
     */
    public function inventoryUsages(): HasMany
    {
        return $this->hasMany(InventoryUsage::class);
    }

    /**
     * کاربر ایجادکننده
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ═══════════════════════════════════════════════════════════
    // Scopes
    // ═══════════════════════════════════════════════════════════

    /**
     * سفارشات draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * سفارشات confirmed
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * سفارشات پرداخت شده
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * سفارشات تکمیل شده
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * سفارشات لغو شده
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * سفارشات برگشتی
     */
    public function scopeRefunded($query)
    {
        return $query->whereIn('status', ['refunded_consumed', 'refunded_returned']);
    }

    /**
     * سفارشات امروز
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * سفارشات بازه زمانی
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * سفارشات یک کاربر خاص
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * مرتب‌سازی بر اساس جدیدترین
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ═══════════════════════════════════════════════════════════
    // Accessors & Mutators
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا سفارش قابل ویرایش است؟
     */
    protected function isEditable(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->status === 'draft'
        );
    }

    /**
     * آیا سفارش قابل تایید است؟
     */
    protected function isConfirmable(): Attribute
    {
        return Attribute::make(
            get: fn() => in_array($this->status, ['draft', 'pending'])
        );
    }

    /**
     * آیا سفارش قابل لغو است؟
     */
    protected function isCancellable(): Attribute
    {
        return Attribute::make(
            get: fn() => in_array($this->status, ['draft', 'pending', 'confirmed'])
        );
    }

    /**
     * آیا سفارش قابل refund است؟
     */
    protected function isRefundable(): Attribute
    {
        return Attribute::make(
            get: fn() => in_array($this->status, ['paid', 'completed'])
        );
    }

    /**
     * وضعیت فارسی
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn() => match ($this->status) {
                'draft' => 'پیش‌نویس',
                'pending' => 'در انتظار',
                'confirmed' => 'تایید شده',
                'paid' => 'پرداخت شده',
                'completed' => 'تکمیل شده',
                'cancelled' => 'لغو شده',
                'refunded_consumed' => 'برگشتی (مصرف شده)',
                'refunded_returned' => 'برگشتی (سالم)',
                default => 'نامشخص',
            }
        );
    }

    /**
     * تعداد آیتم‌ها
     */
    protected function itemsCount(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->items->count()
        );
    }

    /**
     * تعداد کل محصولات (جمع quantity)
     */
    protected function totalQuantity(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->items->sum('quantity')
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Business Logic Methods
    // ═══════════════════════════════════════════════════════════

    /**
     * محاسبه مجدد قیمت‌ها
     */
    public function recalculatePricing(): void
    {
        // 🔧 CRITICAL: Force reload items هر بار
        $this->loadMissing('items');

        \Log::info("recalculatePricing STARTED", [
            'order_id' => $this->id,
            'items_count' => $this->items->count(),
            'items_sum_raw' => $this->items->sum('total_price'),
            'discount_percent' => $this->discount_percent,
            'tax_percent' => $this->tax_percent,
            'delivery_fee' => $this->delivery_fee,
        ]);

        // محاسبه subtotal
        $this->subtotal = $this->items->sum('total_price');

        // تخفیف
        $this->discount_amount = $this->discount_percent
            ? $this->subtotal * ($this->discount_percent / 100)
            : 0;

        // بعد تخفیف
        $afterDiscount = $this->subtotal - $this->discount_amount;

        // مالیات
        $this->tax_amount = $this->tax_percent
            ? $afterDiscount * ($this->tax_percent / 100)
            : 0;

        // ✅ FINAL TOTAL
        $this->total_amount = $afterDiscount + $this->tax_amount + ($this->delivery_fee ?? 0);

        \Log::info("recalculatePricing FINISHED", [
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'after_discount' => $afterDiscount,
            'tax_amount' => $this->tax_amount,
            'delivery_fee' => $this->delivery_fee,
            'total_amount' => $this->total_amount,
            'formula' => "{$afterDiscount} + {$this->tax_amount} + " . ($this->delivery_fee ?? 0) . " = {$this->total_amount}"
        ]);
    }

   
    /**
     * اعمال تخفیف
     */
    public function applyDiscount(float $discountPercent): bool
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            return false;
        }

        $this->discount_percent = $discountPercent;
        $this->recalculatePricing();
        $this->save();

        return true;
    }

    /**
     * اعمال مالیات
     */
    public function applyTax(float $taxPercent): bool
    {
        if ($taxPercent < 0 || $taxPercent > 100) {
            return false;
        }

        $this->tax_percent = $taxPercent;
        $this->recalculatePricing();
        $this->save();

        return true;
    }

    /**
     * تنظیم هزینه ارسال
     */
    public function setDeliveryFee(float $fee): bool
    {
        if ($fee < 0) {
            return false;
        }

        $this->delivery_fee = $fee;
        $this->recalculatePricing();
        $this->save();

        return true;
    }

    /**
     * تایید سفارش
     */
    public function confirm(): bool
    {
        if (!$this->is_confirmable) {
            return false;
        }

        $this->status = 'confirmed';
        $this->confirmed_at = now();
        $this->save();

        return true;
    }

    /**
     * پرداخت سفارش
     */
    public function markAsPaid(): bool
    {
        if ($this->status !== 'confirmed') {
            return false;
        }

        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();

        return true;
    }

    /**
     * تکمیل سفارش
     */
    public function complete(): bool
    {
        if ($this->status !== 'paid') {
            return false;
        }

        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();

        return true;
    }

    /**
     * لغو سفارش
     */
    public function cancel(?string $reason = null): bool
    {
        if (!$this->is_cancellable) {
            return false;
        }

        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->notes = $reason ? "لغو شده: {$reason}" : $this->notes;
        $this->save();

        return true;
    }

    /**
     * برگشت سفارش (refund)
     */
    public function refund(string $type, ?string $reason = null): bool
    {
        if (!$this->is_refundable) {
            return false;
        }

        if (!in_array($type, ['consumed', 'returned'])) {
            return false;
        }

        $this->status = $type === 'consumed' ? 'refunded_consumed' : 'refunded_returned';
        $this->refund_type = $type;
        $this->refund_reason = $reason;
        $this->refunded_at = now();
        $this->save();

        return true;
    }

    /**
     * تولید شماره سفارش یکتا
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $lastOrder = static::whereDate('created_at', today())->latest('id')->first();
        $sequence = $lastOrder ? (int) substr($lastOrder->order_number, -3) + 1 : 1;

        return sprintf('ORD-%s-%03d', $date, $sequence);
    }

    // ═══════════════════════════════════════════════════════════
    // Events
    // ═══════════════════════════════════════════════════════════

    protected static function booted()
    {
        // Auto-generate order number
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = static::generateOrderNumber();
            }
        });

        // Recalculate pricing when items change
        static::saved(function ($order) {
            if ($order->isDirty('items')) {
                $order->recalculatePricing();
            }
        });
    }
}
