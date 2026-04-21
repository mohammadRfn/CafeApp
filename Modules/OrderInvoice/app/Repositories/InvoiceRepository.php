<?php

namespace Modules\OrderInvoice\app\Repositories;

use Modules\OrderInvoice\app\Interfaces\InvoiceRepositoryInterface;
use Modules\OrderInvoice\app\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Invoice Repository
 * 
 * مدیریت دسترسی داده‌های فاکتور
 */
class InvoiceRepository implements InvoiceRepositoryInterface
{
    protected int $cacheTTL = 1800; // 30 minutes

    // ═══════════════════════════════════════════════════════════
    // CRUD Operations
    // ═══════════════════════════════════════════════════════════

    public function getAll(array $filters = []): Collection
    {
        $query = Invoice::query();
        $this->applyFilters($query, $filters);
        return $query->latest()->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query();
        $this->applyFilters($query, $filters);
        return $query->latest()->paginate($perPage);
    }

    public function findById(int $id): ?Invoice
    {
        $cacheKey = "invoice:{$id}";

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($id) {
            return Invoice::with(['order', 'creator'])->find($id);
        });
    }

    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        $cacheKey = "invoice:number:{$invoiceNumber}";

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($invoiceNumber) {
            return Invoice::where('invoice_number', $invoiceNumber)
                ->with(['order', 'creator'])
                ->first();
        });
    }

    public function findByOrderId(int $orderId): ?Invoice
    {

            return Invoice::where('order_id', $orderId)
                ->with(['order', 'creator'])
                ->first();
    }

    public function create(array $data): Invoice
    {
        $invoice = Invoice::create($data);

        $this->clearCache($invoice->id);

        return $invoice->fresh(['order', 'creator']);
    }

    public function update(int $id, array $data): Invoice
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update($data);

        $this->clearCache($id);

        return $invoice->fresh(['order', 'creator']);
    }

    // ═══════════════════════════════════════════════════════════
    // Query Methods
    // ═══════════════════════════════════════════════════════════

    public function getUnpaid(): Collection
    {
        return Cache::remember('invoices:unpaid', $this->cacheTTL, function () {
            return Invoice::unpaid()->with(['order', 'creator'])->get();
        });
    }

    public function getPaid(array $filters = []): Collection
    {
        $query = Invoice::paid();
        $this->applyFilters($query, $filters);
        return $query->with(['order', 'creator'])->get();
    }

    public function getToday(): Collection
    {
        return Invoice::today()->with(['order', 'creator'])->get();
    }

    public function getBetweenDates(string $startDate, string $endDate): Collection
    {
        return Invoice::betweenDates($startDate, $endDate)
            ->with(['order', 'creator'])
            ->get();
    }

    // ═══════════════════════════════════════════════════════════
    // Payment Operations
    // ═══════════════════════════════════════════════════════════

    public function recordPayment(int $id, string $paymentMethod): Invoice
    {
        return DB::transaction(function () use ($id, $paymentMethod) {
            $invoice = Invoice::lockForUpdate()->findOrFail($id);

            $invoice->payment_method = $paymentMethod;
            $invoice->payment_status = 'paid';
            $invoice->paid_at = now();
            $invoice->save();

            $this->clearCache($id);

            return $invoice->fresh(['order', 'creator']);
        });
    }

    public function refundPayment(int $id): Invoice
    {
        return DB::transaction(function () use ($id) {
            $invoice = Invoice::lockForUpdate()->findOrFail($id);

            $invoice->payment_status = 'refunded';
            $invoice->refunded_at = now();
            $invoice->save();

            $this->clearCache($id);

            return $invoice->fresh(['order', 'creator']);
        });
    }

    // ═══════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════

    public function getTotalRevenue(array $filters = []): float
    {
        $query = Invoice::paid();
        $this->applyFilters($query, $filters);
        return $query->sum('total_amount');
    }

    public function getStatistics(array $filters = []): array
    {
        $cacheKey = 'invoices:statistics:' . md5(json_encode($filters));

        return Cache::remember($cacheKey, 600, function () use ($filters) {
            $query = Invoice::query();
            $this->applyFilters($query, $filters);

            return [
                'total' => (clone $query)->count(),
                'unpaid' => (clone $query)->unpaid()->count(),
                'paid' => (clone $query)->paid()->count(),
                'refunded' => (clone $query)->refunded()->count(),
                'total_revenue' => (clone $query)->paid()->sum('total_amount'),
                'today_revenue' => Invoice::paidToday()->sum('total_amount'),
            ];
        });
    }

    public function invoiceNumberExists(string $invoiceNumber): bool
    {
        return Invoice::where('invoice_number', $invoiceNumber)->exists();
    }

    // ═══════════════════════════════════════════════════════════
    // Helper Methods
    // ═══════════════════════════════════════════════════════════

    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }
    }

    protected function clearCache(int $id): void
    {
        $invoice = Invoice::find($id);

        if ($invoice) {
            Cache::forget("invoice:{$id}");
            Cache::forget("invoice:number:{$invoice->invoice_number}");
            Cache::forget("invoice:order:{$invoice->order_id}");
            Cache::forget('invoices:unpaid');
            Cache::forget('invoices:statistics');
        }
    }
    // ═══════════════════════════════════════════════════════════
    // Query Builder Methods
    // ═══════════════════════════════════════════════════════════

    public function findWhere(array $conditions, array $with = []): Collection
    {
        $query = Invoice::query();

        foreach ($conditions as $condition) {
            if (count($condition) === 3) {
                [$field, $operator, $value] = $condition;
                $query->where($field, $operator, $value);
            } elseif (count($condition) === 2) {
                [$field, $value] = $condition;
                $query->where($field, $value);
            }
        }

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->latest()->get();
    }
}
