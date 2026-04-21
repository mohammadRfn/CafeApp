<?php

namespace Modules\OrderInvoice\app\Services;

use Modules\OrderInvoice\app\Interfaces\InvoiceServiceInterface;
use Modules\OrderInvoice\app\Interfaces\InvoiceRepositoryInterface;
use Modules\OrderInvoice\app\Interfaces\OrderRepositoryInterface;
use Modules\OrderInvoice\app\Models\Invoice;
use Modules\OrderInvoice\app\Events\InvoicePaid;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Modules\OrderInvoice\app\Models\Order;

/**
 * Invoice Service
 * 
 * سرویس مدیریت فاکتورها
 */
class InvoiceService implements InvoiceServiceInterface
{
    protected int $cacheTTL = 1800; // 30 minutes

    public function __construct(
        protected InvoiceRepositoryInterface $repository,
        protected OrderRepositoryInterface $orderRepository
    ) {}

    // ═══════════════════════════════════════════════════════════
    // Invoice Management
    // ═══════════════════════════════════════════════════════════

    public function generateInvoice(int $orderId): Invoice
    {
        return RateLimiter::attempt("generate-invoice:{$orderId}", 1, function () use ($orderId) {
            return DB::transaction(function () use ($orderId) {
                // 🔥 FIXX: Cache پاک کن + fresh بخون
                Cache::forget("order:{$orderId}");
                Cache::forget("order:{$orderId}:*");

                // 🔥 یا مستقیم از DB:
                $order = Order::with('items')->findOrFail($orderId);

                if (!$order) {
                    throw new \Illuminate\Database\Eloquent\ModelNotFoundException("سفارش یافت نشد");
                }

                $existingInvoice = $this->repository->findByOrderId($orderId);
                if ($existingInvoice) {
                    throw new \Exception("فاکتور برای این سفارش قبلاً ایجاد شده است");
                }

                if ($order->status !== 'confirmed') {
                    throw new \Exception("فقط سفارشات تایید شده می‌توانند فاکتور داشته باشند");
                }

                $order->recalculatePricing();
                $order->save();

                $invoice = Invoice::createFromOrder($order, auth()->id());

                Cache::forget("invoice:order:{$orderId}");
                Cache::forget('invoices:unpaid');
                Cache::forget("order:{$orderId}");  // ← order cache هم پاک کن

                Log::info("Invoice generated: {$invoice->invoice_number}", [
                    'invoice_id' => $invoice->id,
                    'order_id' => $orderId,
                    'order_subtotal' => $order->subtotal,  // ← log بزن
                    'invoice_total' => $invoice->total_amount,
                ]);

                return $invoice->fresh(['order']);
            });
        }, 60);
    }
    public function deleteInvoice(int $invoiceId): array
    {
        return DB::transaction(function () use ($invoiceId) {
            $invoice = $this->repository->findById($invoiceId, ['order']);

            if (!$invoice) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException("فاکتور یافت نشد");
            }

            $invoiceData = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $invoice->order_id,
                'payment_status' => $invoice->payment_status,
                'total_amount' => $invoice->total_amount,
                'payment_method' => $invoice->payment_method,
            ];

            $invoice->delete();

            Cache::forget("invoice:{$invoiceId}");
            Cache::forget("invoice:order:{$invoice->order_id}");
            Cache::forget('invoices:unpaid');
            Cache::forget('invoices:today:' . today()->toDateString());
            Cache::forget('invoices:today:paid:' . today()->toDateString());

            Cache::forget('revenue:daily:' . today()->toDateString());

            Log::warning("Invoice deleted", [
                'invoice_id' => $invoiceData['id'],
                'invoice_number' => $invoiceData['invoice_number'],
                'order_id' => $invoiceData['order_id'],
                'payment_status' => $invoiceData['payment_status'],
                'total_amount' => $invoiceData['total_amount'],
                'deleted_by' => auth()->id(),
                'deleted_at' => now()->toDateTimeString(),
            ]);

            return $invoiceData;
        });
    }


    public function getByInvoiceNumber(string $invoiceNumber): Invoice
    {
        $invoice = $this->repository->findByInvoiceNumber($invoiceNumber);

        if (!$invoice) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("فاکتور با شماره {$invoiceNumber} یافت نشد");
        }

        return $invoice;
    }

    public function getOrderInvoice(int $orderId): ?Invoice
    {
        return $this->repository->findByOrderId($orderId);
    }

    // ═══════════════════════════════════════════════════════════
    // Payment Management
    // ═══════════════════════════════════════════════════════════

    public function recordPayment(int $invoiceId, string $paymentMethod): Invoice
    {
        return RateLimiter::attempt("record-payment:{$invoiceId}", 100, function () use ($invoiceId, $paymentMethod) {
            return DB::transaction(function () use ($invoiceId, $paymentMethod) {
                $invoice = $this->repository->findById($invoiceId, ['order']); // ✅ با order

                if (!$invoice) {
                    throw new \Illuminate\Database\Eloquent\ModelNotFoundException("فاکتور یافت نشد");
                }

                if ($invoice->payment_status !== 'unpaid') {
                    throw new \Exception("فاکتور قبلاً پرداخت شده است");
                }

                if (!in_array($paymentMethod, ['cash', 'card', 'online'])) {
                    throw new \InvalidArgumentException("روش پرداخت نامعتبر است");
                }

                // ✅ Record payment
                $invoice = $this->repository->recordPayment($invoiceId, $paymentMethod);

                // ✅ Fire event INSIDE transaction
                event(new InvoicePaid($invoice, $invoice->order, $paymentMethod));

                // ✅ Clear cache
                Cache::forget("invoice:{$invoiceId}");
                Cache::forget("invoice:order:{$invoice->order_id}");
                Cache::forget('invoices:unpaid');

                Log::info("Payment recorded for invoice: {$invoice->invoice_number}", [
                    'invoice_id' => $invoice->id,
                    'payment_method' => $paymentMethod,
                    'amount' => $invoice->total_amount,
                ]);

                return $invoice->fresh(['order']);
            });
        }, 60);
    }

    public function refundPayment(int $invoiceId): Invoice
    {
        $invoice = $this->repository->findById($invoiceId);

        if (!$invoice) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("فاکتور یافت نشد");
        }

        if ($invoice->payment_status !== 'paid') {
            throw new \Exception("فقط فاکتورهای پرداخت شده قابل برگشت هستند");
        }

        $invoice = $this->repository->refundPayment($invoiceId);

        Cache::forget("invoice:{$invoiceId}");
        Cache::forget("invoice:order:{$invoice->order_id}");
        Log::info("Payment refunded for invoice: {$invoice->invoice_number}", [
            'invoice_id' => $invoice->id,
        ]);

        return $invoice->fresh(['order']);
    }

    // ═══════════════════════════════════════════════════════════
    // Reports & Statistics
    // ═══════════════════════════════════════════════════════════

    public function getUnpaidInvoices(): Collection
    {
        return Cache::remember('invoices:unpaid', $this->cacheTTL, function () {
            return $this->repository->getUnpaid();
        });
    }

    public function getDailyRevenue(?string $date = null): float
    {
        $date = $date ?? today()->toDateString();
        $cacheKey = "revenue:daily:{$date}";

        return Cache::remember($cacheKey, 60, function () use ($date) {
            $invoices = $this->repository->getPaid([
                'start_date' => $date,
                'end_date' => $date,
            ]);

            return $invoices->sum('total_amount');
        });
    }

    public function getRevenueBetween(string $startDate, string $endDate): float
    {
        $cacheKey = "revenue:between:{$startDate}:{$endDate}";

        return Cache::remember($cacheKey, 60, function () use ($startDate, $endDate) {
            return $this->repository->getTotalRevenue([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        });
    }

    public function getStatistics(array $filters = []): array
    {
        return $this->repository->getStatistics($filters);
    }
    // ═══════════════════════════════════════════════════════════
    // Daily Reports
    // ═══════════════════════════════════════════════════════════

    public function getTodayInvoices(): Collection
    {
        $cacheKey = 'invoices:today:' . today()->toDateString();

        return Cache::remember($cacheKey, 3, function () {
            return $this->repository->findWhere([
                ['created_at', '>=', today()->startOfDay()],
                ['created_at', '<=', today()->endOfDay()],
            ], ['order']);
        });
    }

    public function getTodayPaidInvoices(): Collection
    {
        $cacheKey = 'invoices:today:paid:' . today()->toDateString();

        return Cache::remember($cacheKey, 3, function () {
            return $this->repository->findWhere([
                ['payment_status', '=', 'paid'],
                ['paid_at', '>=', today()->startOfDay()],
                ['paid_at', '<=', today()->endOfDay()],
            ], ['order']);
        });
    }
}
