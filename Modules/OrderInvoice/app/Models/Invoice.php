<?php

namespace Modules\OrderInvoice\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\User;

/**
 * Invoice Model
 * 
 * فاکتور مالی (1-to-1 با Order)
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'delivery_fee',
        'total_amount',
        'payment_method',
        'payment_status',
        'paid_at',
        'refunded_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // ═══════════════════════════════════════════════════════════
    // Relationships
    // ═══════════════════════════════════════════════════════════

    /**
     * سفارش مربوطه (1-to-1)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * کاربر صادرکننده
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ═══════════════════════════════════════════════════════════
    // Scopes
    // ═══════════════════════════════════════════════════════════

    /**
     * فاکتورهای پرداخت نشده
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    /**
     * فاکتورهای پرداخت شده
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    /**
     * فاکتورهای برگشتی
     */
    public function scopeRefunded($query)
    {
        return $query->where('payment_status', 'refunded');
    }

    /**
     * فاکتورهای امروز
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * فاکتورهای پرداخت شده امروز
     */
    public function scopePaidToday($query)
    {
        return $query->paid()->whereDate('paid_at', today());
    }

    /**
     * فاکتورهای بازه زمانی
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * فاکتورهای پرداخت شده در بازه
     */
    public function scopePaidBetween($query, $startDate, $endDate)
    {
        return $query->paid()->whereBetween('paid_at', [$startDate, $endDate]);
    }

    /**
     * بر اساس روش پرداخت
     */
    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    // ═══════════════════════════════════════════════════════════
    // Accessors
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا پرداخت شده؟
     */
    protected function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_status === 'paid'
        );
    }

    /**
     * آیا برگشت خورده؟
     */
    protected function isRefunded(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_status === 'refunded'
        );
    }

    /**
     * وضعیت فارسی
     */
    protected function paymentStatusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->payment_status) {
                'unpaid' => 'پرداخت نشده',
                'paid' => 'پرداخت شده',
                'refunded' => 'برگشت داده شده',
                default => 'نامشخص',
            }
        );
    }

    /**
     * روش پرداخت فارسی
     */
    protected function paymentMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->payment_method) {
                'cash' => 'نقدی',
                'card' => 'کارت',
                'online' => 'آنلاین',
                default => 'نامشخص',
            }
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Business Logic
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت پرداخت
     */
    public function recordPayment(string $method): bool
    {
        if ($this->payment_status !== 'unpaid') {
            return false;
        }

        if (!in_array($method, ['cash', 'card', 'online'])) {
            return false;
        }

        $this->payment_method = $method;
        $this->payment_status = 'paid';
        $this->paid_at = now();
        $this->save();

        return true;
    }

    /**
     * برگشت پرداخت
     */
    public function refund(): bool
    {
        if ($this->payment_status !== 'paid') {
            return false;
        }

        $this->payment_status = 'refunded';
        $this->refunded_at = now();
        $this->save();

        return true;
    }

    /**
     * تولید شماره فاکتور یکتا
     */
    public static function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $lastInvoice = static::whereDate('created_at', today())->latest('id')->first();
        $sequence = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -3) + 1 : 1;

        return sprintf('INV-%s-%03d', $date, $sequence);
    }

    /**
     * ساخت Invoice از Order
     */
    public static function createFromOrder(Order $order, int $createdBy): self
    {
        return static::create([
            'order_id' => $order->id,
            'invoice_number' => static::generateInvoiceNumber(),
            'subtotal' => $order->subtotal,
            'discount_amount' => $order->discount_amount,
            'tax_amount' => $order->tax_amount,
            'delivery_fee' => $order->delivery_fee,
            'total_amount' => $order->total_amount,
            'payment_status' => 'unpaid',
            'created_by' => $createdBy,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // Events
    // ═══════════════════════════════════════════════════════════

    protected static function booted()
    {
        // Auto-generate invoice number
        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }
        });
    }
}