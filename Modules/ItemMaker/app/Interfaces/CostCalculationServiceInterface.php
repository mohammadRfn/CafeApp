<?php

namespace Modules\ItemMaker\app\Interfaces;

use Modules\ItemMaker\Models\Item;
use Modules\ItemMaker\Models\ItemCostHistory;
use Illuminate\Support\Collection;

/**
 * 
 */
interface CostCalculationServiceInterface
{
    /**
     * محاسبه هزینه تمام شده محصول
     * 
     * @param int $itemId
     * @return array ['total_cost', 'ingredients_cost', 'boxes_cost', 'breakdown']
     */
    public function calculateItemCost(int $itemId): array;

    /**
     * محاسبه هزینه مواد اولیه
     * 
     * @param Item $item
     * @return array ['cost' => float, 'breakdown' => array]
     */
    public function calculateIngredientsCost(Item $item): array;

    /**
     * محاسبه هزینه بسته‌بندی
     * 
     * @param Item $item
     * @return array ['cost' => float, 'breakdown' => array]
     */
    public function calculateBoxesCost(Item $item): array;

    /**
     * محاسبه قیمت پیشنهادی فروش
     * 
     * @param float $totalCost
     * @param float $profitMarginPercent
     * @return float
     */
    public function calculateSuggestedPrice(float $totalCost, float $profitMarginPercent): float;

    /**
     * محاسبه حاشیه سود
     * 
     * @param float $sellPrice
     * @param float $cost
     * @return float درصد
     */
    public function calculateProfitMargin(float $sellPrice, float $cost): float;

    /**
     * ذخیره محاسبات در تاریخچه
     * 
     * @param int $itemId
     * @param array $costData
     * @param string $calculationMethod 'auto' | 'manual'
     * @return ItemCostHistory
     */
    public function saveCostHistory(int $itemId, array $costData, string $calculationMethod = 'auto'): ItemCostHistory;

    /**
     * دریافت آخرین هزینه محاسبه شده
     * 
     * @param int $itemId
     * @return ItemCostHistory|null
     */
    public function getCurrentCost(int $itemId): ?ItemCostHistory;

    /**
     * دریافت تاریخچه هزینه‌ها
     * 
     * @param int $itemId
     * @param int $limit
     * @return Collection
     */
    public function getCostHistory(int $itemId, int $limit = 10): Collection;

    /**
     * بروزرسانی هزینه‌های واحد از قیمت‌های جدید Inventory
     * 
     * @param int $itemId
     * @return bool
     */
    public function refreshUnitCosts(int $itemId): bool;

    /**
     * نامعتبر کردن هزینه‌های قبلی
     * 
     * @param int $itemId
     * @return int تعداد نامعتبر شده
     */
    public function invalidatePreviousCosts(int $itemId): int;

    /**
     * محاسبه مجدد هزینه تمام محصولات
     * 
     * @param bool $saveToHistory
     * @return array ['success_count', 'failed_count', 'errors']
     */
    public function recalculateAllItems(bool $saveToHistory = true): array;

    /**
     * محاسبه هزینه دسته‌ای محصولات
     * 
     * @param array $itemIds
     * @param bool $saveToHistory
     * @return array ['success_count', 'failed_count', 'results']
     */
    public function bulkCalculateCosts(array $itemIds, bool $saveToHistory = true): array;

    /**
     * تحلیل سودآوری محصول
     * 
     * @param int $itemId
     * @return array ['is_profitable', 'profit_margin', 'net_profit', 'cost_breakdown']
     */
    public function analyzeProfitability(int $itemId): array;

    /**
     * مقایسه هزینه فعلی با گذشته
     * 
     * @param int $itemId
     * @return array ['current', 'previous', 'change_percent', 'change_amount']
     */
    public function compareCosts(int $itemId): array;

    /**
     * پیش‌بینی هزینه با تغییر قیمت مواد اولیه
     * 
     * @param int $itemId
     * @param array $priceChanges ['ingredient_id' => new_price]
     * @return array ['projected_cost', 'current_cost', 'difference']
     */
    public function projectCostWithPriceChanges(int $itemId, array $priceChanges): array;

    /**
     * تعیین قیمت بهینه بر اساس هزینه و حاشیه سود هدف
     * 
     * @param int $itemId
     * @param float $targetProfitMargin
     * @return array ['suggested_price', 'current_cost', 'profit_margin']
     */
    public function suggestOptimalPrice(int $itemId, float $targetProfitMargin): array;

    /**
     * دریافت گزارش هزینه‌ها
     * 
     * @param array $filters
     * @return array
     */
    public function getCostReport(array $filters = []): array;
}
