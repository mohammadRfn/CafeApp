<?php

namespace Modules\ItemMaker\app\Interfaces;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ItemMaker\Models\Item;

/**
 * Item Repository Interface
 * 
 * قرارداد لایه دسترسی به داده برای Items
 * تمام عملیات دیتابیس مربوط به Items از طریق این interface انجام می‌شود
 */
interface ItemRepositoryInterface
{
    /**
     * دریافت تمام محصولات با فیلترها
     * 
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function getAll(array $filters = [], array $with = []): Collection;

    /**
     * دریافت محصولات با صفحه‌بندی
     * 
     * @param int $perPage
     * @param array $filters
     * @param array $with
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator;

    /**
     * پیدا کردن محصول با ID
     * 
     * @param int $id
     * @param array $with
     * @return Item|null
     */
    public function findById(int $id, array $with = []): ?Item;

    /**
     * پیدا کردن محصول با کد
     * 
     * @param string $code
     * @param array $with
     * @return Item|null
     */
    public function findByCode(string $code, array $with = []): ?Item;

    /**
     * ایجاد محصول جدید
     * 
     * @param array $data
     * @return Item
     */
    public function create(array $data): Item;

    /**
     * بروزرسانی محصول
     * 
     * @param int $id
     * @param array $data
     * @return Item
     */
    public function update(int $id, array $data): Item;

    /**
     * حذف محصول (soft delete)
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
     * حذف دائمی محصول
     * 
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * دریافت محصولات فعال
     * 
     * @param array $with
     * @return Collection
     */
    public function getActive(array $with = []): Collection;

    /**
     * دریافت محصولات ویژه
     * 
     * @param array $with
     * @return Collection
     */
    public function getFeatured(array $with = []): Collection;

    /**
     * دریافت محصولات بر اساس دسته‌بندی
     * 
     * @param string $category
     * @param array $with
     * @return Collection
     */
    public function getByCategory(string $category, array $with = []): Collection;

    /**
     * جستجوی محصولات
     * 
     * @param string $search
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function search(string $search, array $filters = [], array $with = []): Collection;

    /**
     * دریافت محصولات موجود امروز (محدودیت روزانه تمام نشده)
     * 
     * @param array $with
     * @return Collection
     */
    public function getAvailableToday(array $with = []): Collection;

    /**
     * دریافت محصولاتی که موجودیشان تمام شده
     * 
     * @param array $with
     * @return Collection
     */
    public function getSoldOut(array $with = []): Collection;

    /**
     * افزودن ingredient به recipe
     * 
     * @param int $itemId
     * @param int $ingredientId
     * @param array $pivotData
     * @return bool
     */
    public function attachIngredient(int $itemId, int $ingredientId, array $pivotData): bool;

    /**
     * حذف ingredient از recipe
     * 
     * @param int $itemId
     * @param int $ingredientId
     * @return bool
     */
    public function detachIngredient(int $itemId, int $ingredientId): bool;

    /**
     * بروزرسانی ingredient در recipe
     * 
     * @param int $itemId
     * @param int $ingredientId
     * @param array $pivotData
     * @return bool
     */
    public function updateIngredient(int $itemId, int $ingredientId, array $pivotData): bool;

    /**
     * همگام‌سازی ingredients (sync)
     * 
     * @param int $itemId
     * @param array $ingredients
     * @return array
     */
    public function syncIngredients(int $itemId, array $ingredients): array;

    /**
     * افزودن box به item
     * 
     * @param int $itemId
     * @param int $boxId
     * @param array $pivotData
     * @return bool
     */
    public function attachBox(int $itemId, int $boxId, array $pivotData): bool;

    /**
     * حذف box از item
     * 
     * @param int $itemId
     * @param int $boxId
     * @return bool
     */
    public function detachBox(int $itemId, int $boxId): bool;

    /**
     * بروزرسانی box در item
     * 
     * @param int $itemId
     * @param int $boxId
     * @param array $pivotData
     * @return bool
     */
    public function updateBox(int $itemId, int $boxId, array $pivotData): bool;

    /**
     * همگام‌سازی boxes (sync)
     * 
     * @param int $itemId
     * @param array $boxes
     * @return array
     */
    public function syncBoxes(int $itemId, array $boxes): array;

    /**
     * افزایش شمارنده فروش روزانه
     * 
     * @param int $itemId
     * @param int $quantity
     * @return bool
     */
    public function incrementDailySold(int $itemId, int $quantity = 1): bool;

    /**
     * کاهش شمارنده فروش روزانه
     * 
     * @param int $itemId
     * @param int $quantity
     * @return bool
     */
    public function decrementDailySold(int $itemId, int $quantity = 1): bool;

    /**
     * ریست شمارنده فروش روزانه
     * 
     * @param int|null $itemId اگر null باشد، همه محصولات ریست می‌شوند
     * @return int تعداد محصولات ریست شده
     */
    public function resetDailySoldCount(?int $itemId = null): int;

    /**
     * بروزرسانی وضعیت فعال/غیرفعال
     * 
     * @param int $itemId
     * @param bool $isActive
     * @return bool
     */
    public function updateActiveStatus(int $itemId, bool $isActive): bool;

    /**
     * بروزرسانی قیمت فروش
     * 
     * @param int $itemId
     * @param float $price
     * @return bool
     */
    public function updateSellPrice(int $itemId, float $price): bool;

    /**
     * دریافت آمار محصولات
     * 
     * @return array
     */
    public function getStatistics(): array;

    /**
     * بررسی وجود کد محصول
     * 
     * @param string $code
     * @param int|null $excludeId
     * @return bool
     */
    public function codeExists(string $code, ?int $excludeId = null): bool;
}
