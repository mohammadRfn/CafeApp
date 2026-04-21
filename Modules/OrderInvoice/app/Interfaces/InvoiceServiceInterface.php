<?php

namespace Modules\OrderInvoice\app\Interfaces;

use Modules\OrderInvoice\app\Models\Invoice;
use Illuminate\Support\Collection;

/**
 * Invoice Service Interface
 * 
 * قرارداد سرویس مدیریت فاکتورها
 */
interface InvoiceServiceInterface
{
    // ═══════════════════════════════════════════════════════════
    // Invoice Management
    // ═══════════════════════════════════════════════════════════

    /**
     * ایجاد فاکتور از سفارش
     */
    public function generateInvoice(int $orderId): Invoice;

    /**
     * دریافت فاکتور با شماره
     */
    public function getByInvoiceNumber(string $invoiceNumber): Invoice;

    /**
     * دریافت فاکتور سفارش
     */
    public function getOrderInvoice(int $orderId): ?Invoice;

    // ═══════════════════════════════════════════════════════════
    // Payment Management
    // ═══════════════════════════════════════════════════════════

    /**
     * ثبت پرداخت
     * method: 'cash', 'card', 'online'
     */
    public function recordPayment(int $invoiceId, string $paymentMethod): Invoice;

    /**
     * برگشت پرداخت
     */
    public function refundPayment(int $invoiceId): Invoice;

    // ═══════════════════════════════════════════════════════════
    // Reports & Statistics
    // ═══════════════════════════════════════════════════════════

    /**
     * فاکتورهای پرداخت نشده
     */
    public function getUnpaidInvoices(): Collection;

    /**
     * درآمد روزانه
     */
    public function getDailyRevenue(?string $date = null): float;

    /**
     * درآمد بازه زمانی
     */
    public function getRevenueBetween(string $startDate, string $endDate): float;

    /**
     * آمار فاکتورها
     */
    public function getStatistics(array $filters = []): array;
    public function getTodayInvoices(): Collection;
    public function getTodayPaidInvoices(): Collection;
    public function deleteInvoice(int $invoiceId): array;

}
