<?php

namespace Modules\OrderInvoice\app\Interfaces;

use Modules\OrderInvoice\app\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * OrderItem Repository Interface
 * 
 * قرارداد Repository مدیریت آیتم‌های سفارش
 */
interface OrderItemRepositoryInterface
{
    // ═══════════════════════════════════════════════════════════
    // CRUD Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * دریافت آیتم‌های یک سفارش
     */
    public function getOrderItems(int $orderId): Collection;

    /**
     * دریافت آیتم با ID
     */
    public function findById(int $id): ?OrderItem;

    /**
     * ایجاد آیتم
     */
    public function create(array $data): OrderItem;

    /**
     * بروزرسانی آیتم
     */
    public function update(int $id, array $data): OrderItem;

    /**
     * حذف آیتم
     */
    public function delete(int $id): bool;

    // ═══════════════════════════════════════════════════════════
    // Business Operations
    // ═══════════════════════════════════════════════════════════

    /**
     * بروزرسانی تعداد
     */
    public function updateQuantity(int $id, int $quantity): OrderItem;

    /**
     * بررسی وجود آیتم در سفارش
     */
    public function itemExistsInOrder(int $orderId, int $itemId): bool;

    /**
     * دریافت آیتم خاص از سفارش
     */
    public function getOrderItem(int $orderId, int $itemId): ?OrderItem;
}