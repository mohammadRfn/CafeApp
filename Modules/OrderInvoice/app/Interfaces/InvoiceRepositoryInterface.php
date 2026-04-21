<?php

namespace Modules\OrderInvoice\app\Interfaces;

use Modules\OrderInvoice\app\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Invoice Repository Interface
 * 
 * قرارداد Repository مدیریت فاکتورها
 */
interface InvoiceRepositoryInterface
{
    // ═══════════════════════════════════════════════════════════
    // CRUD Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * لیست همه فاکتورها
     */
    public function getAll(array $filters = []): Collection;

    /**
     * لیست با pagination
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * دریافت فاکتور با ID
     */
    public function findById(int $id): ?Invoice;

    /**
     * دریافت فاکتور با invoice_number
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice;

    /**
     * دریافت فاکتور سفارش
     */
    public function findByOrderId(int $orderId): ?Invoice;

    /**
     * ایجاد فاکتور
     */
    public function create(array $data): Invoice;

    /**
     * بروزرسانی فاکتور
     */
    public function update(int $id, array $data): Invoice;

    // ═══════════════════════════════════════════════════════════
    // Query Methods
    // ═══════════════════════════════════════════════════════════

    /**
     * فاکتورهای پرداخت نشده
     */
    public function getUnpaid(): Collection;

    /**
     * فاکتورهای پرداخت شده
     */
    public function getPaid(array $filters = []): Collection;

    /**
     * فاکتورهای امروز
     */
    public function getToday(): Collection;

    /**
     * فاکتورهای بازه زمانی
     */
    public function getBetweenDates(string $startDate, string $endDate): Collection;

    // ═══════════════════════════════════════════════════════════
    // Payment Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت پرداخت
     */
    public function recordPayment(int $id, string $paymentMethod): Invoice;

    /**
     * برگشت پرداخت
     */
    public function refundPayment(int $id): Invoice;

    // ═══════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════

    /**
     * جمع درآمد
     */
    public function getTotalRevenue(array $filters = []): float;

    /**
     * آمار فاکتورها
     */
    public function getStatistics(array $filters = []): array;

    /**
     * بررسی وجود invoice_number
     */
    public function invoiceNumberExists(string $invoiceNumber): bool;

    public function findWhere(array $conditions, array $with = []): Collection;
}