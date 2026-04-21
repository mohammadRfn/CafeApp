<?php

namespace Modules\OrderInvoice\app\Interfaces;

/**
 * Inventory Integration Service Interface
 * 
 * قرارداد سرویس ارتباط با Inventory Module
 */
interface InventoryIntegrationServiceInterface
{
    // ═══════════════════════════════════════════════════════════
    // Availability Checks
    // ═══════════════════════════════════════════════════════════

    /**
     * بررسی موجودی برای سفارش
     * 
     * @return array [
     *   'available' => bool,
     *   'ingredients' => [...],
     *   'boxes' => [...]
     * ]
     */
    public function checkOrderAvailability(int $orderId): array;

    /**
     * بررسی موجودی برای یک آیتم با تعداد مشخص
     */
    public function checkItemAvailability(int $itemId, int $quantity): array;

    // ═══════════════════════════════════════════════════════════
    // Reserve Operations (Confirm Order)
    // ═══════════════════════════════════════════════════════════

    /**
     * رزرو موجودی برای سفارش
     * وقتی Order → confirmed
     */
    public function reserveForOrder(int $orderId): bool;

    /**
     * آزادسازی رزرو (Cancel Order)
     * وقتی Order → cancelled
     */
    public function releaseReservation(int $orderId): bool;

    // ═══════════════════════════════════════════════════════════
    // Commit Operations (Payment Confirmed)
    // ═══════════════════════════════════════════════════════════

    /**
     * کم کردن واقعی موجودی (Commit)
     * وقتی Invoice → paid
     */
    public function commitInventory(int $orderId): bool;

    // ═══════════════════════════════════════════════════════════
    // Rollback Operations (Refund)
    // ═══════════════════════════════════════════════════════════

    /**
     * برگشت موجودی - مصرف شده
     * type: 'consumed' - فقط transaction ثبت میشه
     */
    public function rollbackConsumed(int $orderId): bool;

    /**
     * برگشت موجودی - سالم
     * type: 'returned' - موجودی برمیگرده
     */
    public function rollbackReturned(int $orderId): bool;

    // ═══════════════════════════════════════════════════════════
    // Tracking & Reporting
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت تاریخچه مصرف موجودی سفارش
     */
    public function getOrderUsageHistory(int $orderId): array;

    /**
     * جمع مصرف ingredient امروز
     */
    public function getIngredientUsageToday(int $ingredientId): float;

    /**
     * جمع مصرف box امروز
     */
    public function getBoxUsageToday(int $boxId): int;
}