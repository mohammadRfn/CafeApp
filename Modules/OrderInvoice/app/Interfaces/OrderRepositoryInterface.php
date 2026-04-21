<?php

namespace Modules\OrderInvoice\app\Interfaces;

use Modules\OrderInvoice\app\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Order Repository Interface
 * 
 * قرارداد Repository مدیریت داده‌های سفارش
 */
interface OrderRepositoryInterface
{
    // ═══════════════════════════════════════════════════════════
    // CRUD Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * لیست همه سفارشات
     */
    public function getAll(array $filters = [], array $with = []): Collection;

    /**
     * لیست با pagination
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator;

    /**
     * دریافت سفارش با ID
     */
    public function findById(int $id, array $with = []): ?Order;

    /**
     * دریافت سفارش با order_number
     */
    public function findByOrderNumber(string $orderNumber, array $with = []): ?Order;

    /**
     * ایجاد سفارش
     */
    public function create(array $data): Order;

    /**
     * بروزرسانی سفارش
     */
    public function update(int $id, array $data): Order;

    /**
     * حذف سفارش (soft delete)
     */
    public function delete(int $id): bool;

    /**
     * بازگردانی سفارش
     */
    public function restore(int $id): bool;

    // ═══════════════════════════════════════════════════════════
    // Query Methods
    // ═══════════════════════════════════════════════════════════

    /**
     * سفارشات بر اساس وضعیت
     */
    public function getByStatus(string $status, array $with = []): Collection;

    /**
     * سفارشات یک کاربر
     */
    public function getUserOrders(int $userId, array $filters = []): Collection;

    /**
     * سفارشات امروز
     */
    public function getToday(array $filters = []): Collection;

    /**
     * سفارشات بازه زمانی
     */
    public function getBetweenDates(string $startDate, string $endDate, array $filters = []): Collection;

    /**
     * جستجو
     */
    public function search(string $query, array $filters = []): Collection;

    // ═══════════════════════════════════════════════════════════
    // Status Updates
    // ═══════════════════════════════════════════════════════════

    /**
     * تغییر وضعیت
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * تنظیم confirmed_at
     */
    public function markAsConfirmed(int $id): bool;

    /**
     * تنظیم paid_at
     */
    public function markAsPaid(int $id): bool;

    /**
     * تنظیم completed_at
     */
    public function markAsCompleted(int $id): bool;

    /**
     * تنظیم cancelled_at
     */
    public function markAsCancelled(int $id): bool;

    /**
     * تنظیم refunded_at
     */
    public function markAsRefunded(int $id, string $refundType, ?string $reason = null): bool;

    // ═══════════════════════════════════════════════════════════
    // Pricing Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * بروزرسانی قیمت‌ها
     */
    public function updatePricing(int $id, array $pricingData): bool;

    // ═══════════════════════════════════════════════════════════
    // Statistics
    // ═══════════════════════════════════════════════════════════

    /**
     * آمار سفارشات
     */
    public function getStatistics(array $filters = []): array;

    /**
     * بررسی وجود order_number
     */
    public function orderNumberExists(string $orderNumber): bool;

    public function addItem(int $orderId, array $itemData): \Modules\OrderInvoice\app\Models\OrderItem;
    public function removeItem(int $orderId, int $orderItemId): bool;
    public function updateItem(int $orderItemId, array $data): \Modules\OrderInvoice\app\Models\OrderItem;
    public function getTodayOrders(array $with = []): Collection;
    public function getPaidOrders(array $with = []): Collection;
}