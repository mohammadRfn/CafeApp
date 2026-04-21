<?php

namespace Modules\ItemMaker\app\Interfaces;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ItemMaker\Models\Item;

/**
 * Item Service Interface
 * 
 */
interface ItemServiceInterface
{
    /**
     * 
     * @param array $filters
     * @param int|null $perPage 
     * @return Collection|LengthAwarePaginator
     */
    public function list(array $filters = [], ?int $perPage = null): Collection|LengthAwarePaginator;

    /**
     * دریافت جزئیات کامل محصول
     * 
     * @param int $id
     * @return Item
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getDetails(int $id): Item;

    /**
     * دریافت محصول با کد
     * 
     * @param string $code
     * @return Item
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getByCode(string $code): Item;

    /**
     * ایجاد محصول جدید با recipe
     * 
     * @param array $itemData
     * @param array $ingredients
     * @param array $boxes
     * @return Item
     */
    public function create(array $itemData, array $ingredients = [], array $boxes = []): Item;

    /**
     * بروزرسانی محصول
     * 
     * @param int $id
     * @param array $itemData
     * @param array|null $ingredients
     * @param array|null $boxes
     * @return Item
     */
    public function update(int $id, array $itemData, ?array $ingredients = null, ?array $boxes = null): Item;

    /**
     * حذف محصول
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * بازگردانی محصول حذف شده
     * 
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * بروزرسانی recipe (ingredients)
     * 
     * @param int $itemId
     * @param array $ingredients
     * @return bool
     */
    public function updateRecipe(int $itemId, array $ingredients): bool;

    /**
     * بروزرسانی packaging (boxes)
     * 
     * @param int $itemId
     * @param array $boxes
     * @return bool
     */
    public function updatePackaging(int $itemId, array $boxes): bool;

    /**
     * محاسبه و بروزرسانی هزینه تمام شده
     * 
     * @param int $itemId
     * @param bool $saveToHistory
     * @return array ['total_cost', 'ingredients_cost', 'boxes_cost', 'breakdown']
     */
    public function calculateCost(int $itemId, bool $saveToHistory = true): array;

    /**
     * بررسی موجودی برای تولید
     * 
     * @param int $itemId
     * @param int $quantity
     * @return array ['available' => bool, 'shortages' => array]
     */
    public function checkAvailability(int $itemId, int $quantity = 1): array;

    /**
     * فعال/غیرفعال کردن محصول
     * 
     * @param int $itemId
     * @param bool $isActive
     * @return bool
     */
    public function toggleActive(int $itemId, bool $isActive): bool;

    /**
     * تنظیم قیمت فروش
     * 
     * @param int $itemId
     * @param float $price
     * @return bool
     */
    public function setSellPrice(int $itemId, float $price): bool;

    /**
     * افزودن به شمارنده فروش
     * 
     * @param int $itemId
     * @param int $quantity
     * @return bool
     */
    public function recordSale(int $itemId, int $quantity = 1): bool;

    /**
     * لغو فروش (کاهش شمارنده)
     * 
     * @param int $itemId
     * @param int $quantity
     * @return bool
     */
    public function cancelSale(int $itemId, int $quantity = 1): bool;

    /**
     * ریست شمارنده روزانه (برای Job)
     * 
     * @return int تعداد محصولات ریست شده
     */
    public function resetDailyCounts(): int;

    /**
     * دریافت محصولات فعال
     * 
     * @param string|null $category
     * @return Collection
     */
    public function getActiveItems(?string $category = null): Collection;

    /**
     * دریافت محصولات ویژه
     * 
     * @return Collection
     */
    public function getFeaturedItems(): Collection;

    /**
     * دریافت محصولات موجود (محدودیت روزانه تمام نشده)
     * 
     * @return Collection
     */
    public function getAvailableItems(): Collection;

    /**
     * جستجوی محصولات
     * 
     * @param string $query
     * @param array $filters
     * @return Collection
     */
    public function search(string $query, array $filters = []): Collection;

    /**
     * دریافت دسته‌بندی‌های موجود
     * 
     * @return Collection
     */
    public function getCategories(): Collection;

    /**
     * دریافت آمار محصولات
     * 
     * @return array
     */
    public function getStatistics(): array;

    /**
     * کلون کردن محصول (ایجاد کپی)
     * 
     * @param int $itemId
     * @param string $newCode
     * @param string|null $newName
     * @return Item
     */
    public function duplicate(int $itemId, string $newCode, ?string $newName = null): Item;

    /**
     * اعتبارسنجی قبل از ایجاد/بروزرسانی
     * 
     * @param array $data
     * @param int|null $excludeId
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validate(array $data, ?int $excludeId = null): array;

    /**
     * بروزرسانی دسته‌ای وضعیت
     * 
     * @param array $itemIds
     * @param bool $isActive
     * @return int تعداد بروزرسانی شده
     */
    public function bulkToggleActive(array $itemIds, bool $isActive): int;

    /**
     * حذف دسته‌ای
     * 
     * @param array $itemIds
     * @return int تعداد حذف شده
     */
    public function bulkDelete(array $itemIds): int;
}
