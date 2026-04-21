<?php

namespace Modules\OrderInvoice\app\Interfaces;

use Modules\OrderInvoice\app\Models\Order;
use Modules\OrderInvoice\app\Models\OrderItem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Order Service Interface
 * 
 * قرارداد سرویس مدیریت سفارشات
 */
interface OrderServiceInterface
{
    // ═══════════════════════════════════════════════════════════
    // Query & Retrieval
    // ═══════════════════════════════════════════════════════════

    /**
     * لیست سفارشات با فیلتر و pagination
     */
    public function list(array $filters = [], ?int $perPage = null): Collection|LengthAwarePaginator;

    /**
     * دریافت جزئیات سفارش
     */
    public function getDetails(int $orderId): Order;

    /**
     * دریافت سفارش با order_number
     */
    public function getByOrderNumber(string $orderNumber): Order;

    /**
     * دریافت سفارشات یک کاربر
     */
    public function getUserOrders(int $userId, array $filters = []): Collection;

    // ═══════════════════════════════════════════════════════════
    // Order Management
    // ═══════════════════════════════════════════════════════════

    /**
     * ایجاد سفارش جدید (draft)
     */
    public function createOrder(array $orderData): Order;

    /**
     * بروزرسانی سفارش (فقط draft)
     */
    public function updateOrder(int $orderId, array $orderData): Order;

    /**
     * حذف سفارش (soft delete)
     */
    public function deleteOrder(int $orderId): bool;

    // ═══════════════════════════════════════════════════════════
    // Order Items Management
    // ═══════════════════════════════════════════════════════════

    /**
     * افزودن آیتم به سفارش
     */
    public function addItem(int $orderId, int $itemId, int $quantity, ?string $notes = null): OrderItem;

    /**
     * حذف آیتم از سفارش
     */
    public function removeItem(int $orderId, int $orderItemId): bool;

    /**
     * بروزرسانی تعداد آیتم
     */
    public function updateItemQuantity(int $orderId, int $orderItemId, int $newQuantity): OrderItem;

    // ═══════════════════════════════════════════════════════════
    // Pricing Management
    // ═══════════════════════════════════════════════════════════

    /**
     * اعمال تخفیف
     */
    public function applyDiscount(int $orderId, float $discountPercent): Order;

    /**
     * اعمال مالیات
     */
    public function applyTax(int $orderId, float $taxPercent): Order;

    /**
     * تنظیم هزینه ارسال
     */
    public function setDeliveryFee(int $orderId, float $fee): Order;

    /**
     * محاسبه مجدد قیمت‌ها
     */
    public function recalculatePricing(int $orderId): Order;

    // ═══════════════════════════════════════════════════════════
    // Order Workflow
    // ═══════════════════════════════════════════════════════════

    /**
     * تایید سفارش (draft → confirmed)
     * موجودی رزرو میشه
     */
    public function confirmOrder(int $orderId): Order;

    /**
     * لغو سفارش (قبل پرداخت)
     * رزرو آزاد میشه
     */
    public function cancelOrder(int $orderId, ?string $reason = null): Order;

    /**
     * تکمیل سفارش (paid → completed)
     */
    public function completeOrder(int $orderId): Order;

    /**
     * برگشت سفارش (refund)
     * type: 'consumed' یا 'returned'
     */
    public function refundOrder(int $orderId, string $refundType, ?string $reason = null): Order;

    // ═══════════════════════════════════════════════════════════
    // Validation & Checks
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی موجودی برای سفارش
     */
    public function checkAvailability(int $orderId): array;

    /**
     * اعتبارسنجی سفارش
     */
    public function validateOrder(int $orderId): array;

    // ═══════════════════════════════════════════════════════════
    // Statistics & Reports
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت آمار سفارشات
     */
    public function getStatistics(array $filters = []): array;

    /**
     * سفارشات امروز
     */
    public function getTodayOrders(): Collection;

    /**
     * سفارشات پرداخت شده امروز
     */
    public function getTodayPaidOrders(): Collection;
}