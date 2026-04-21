<?php

namespace Modules\ItemMaker\app\Services;

use Modules\ItemMaker\app\Interfaces\CostCalculationServiceInterface;
use Modules\ItemMaker\Models\Item;
use Modules\ItemMaker\Models\ItemCostHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost Calculation Service
 *
 * سرویس محاسبه و مدیریت هزینه‌های محصولات
 */
class CostCalculationService implements CostCalculationServiceInterface
{
    /**
     * محاسبه هزینه تمام شده محصول
     */
    public function calculateItemCost(int $itemId): array
    {
        $item = Item::with(['ingredients.currentPrice', 'boxes.currentPrice'])->findOrFail($itemId);

        $ingredientsCost = $this->calculateIngredientsCost($item);
        $boxesCost = $this->calculateBoxesCost($item);

        $totalCost = $ingredientsCost['cost'] + $boxesCost['cost'];

        return [
            'total_cost' => round($totalCost, 2),
            'ingredients_cost' => $ingredientsCost['cost'],
            'boxes_cost' => $boxesCost['cost'],
            'breakdown' => [
                'ingredients' => $ingredientsCost['breakdown'],
                'boxes' => $boxesCost['breakdown'],
            ],
        ];
    }

    /**
     * محاسبه هزینه مواد اولیه
     */
    public function calculateIngredientsCost(Item $item): array
    {
        $totalCost = 0;
        $breakdown = [];
        $item->load('ingredients.currentPrice');
        foreach ($item->ingredients as $ingredient) {
            $actualGrams = $ingredient->pivot->actual_grams;
            $unitCost = $ingredient->getLatestPricePerGram();

            $cost = $actualGrams * $unitCost;
            $totalCost += $cost;

            $breakdown[] = [
                'ingredient_id' => $ingredient->id,
                'ingredient_name' => $ingredient->ingredient_name,
                'required_grams' => (float) $ingredient->pivot->required_grams,
                'waste_factor' => (float) $ingredient->pivot->waste_factor,
                'actual_grams' => (float) $actualGrams,
                'unit_cost' => round($unitCost, 2),
                'total_cost' => round($cost, 2),
            ];
        }

        return [
            'cost' => round($totalCost, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * محاسبه هزینه بسته‌بندی
     */
    public function calculateBoxesCost(Item $item): array
    {
        $totalCost = 0;
        $breakdown = [];
        $item->load('boxes.currentPrice');
        foreach ($item->boxes as $box) {
            $requiredQuantity = $box->pivot->required_quantity;
            $unitCost = $box->getLatestUnitPrice();

            $cost = $requiredQuantity * $unitCost;
            $totalCost += $cost;

            $breakdown[] = [
                'box_id' => $box->id,
                'box_name' => $box->name,
                'required_quantity' => (int) $requiredQuantity,
                'unit_cost' => round($unitCost, 2),
                'total_cost' => round($cost, 2),
            ];
        }

        return [
            'cost' => round($totalCost, 2),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * محاسبه قیمت پیشنهادی فروش
     */
    public function calculateSuggestedPrice(float $totalCost, float $profitMarginPercent): float
    {
        if ($profitMarginPercent <= 0 || $profitMarginPercent >= 100) {
            return $totalCost;
        }

        // Formula: SellPrice = Cost / (1 - ProfitMargin/100)
        $suggestedPrice = $totalCost / (1 - ($profitMarginPercent / 100));

        return round($suggestedPrice, 2);
    }

    /**
     * محاسبه حاشیه سود
     */
    public function calculateProfitMargin(float $sellPrice, float $cost): float
    {
        if ($sellPrice <= 0) {
            return 0;
        }

        // Formula: ProfitMargin = ((SellPrice - Cost) / SellPrice) * 100
        $margin = (($sellPrice - $cost) / $sellPrice) * 100;

        return round($margin, 2);
    }

    /**
     * ذخیره محاسبات در تاریخچه
     */
    public function saveCostHistory(int $itemId, array $costData, string $calculationMethod = 'auto'): ItemCostHistory
    {
        return DB::transaction(function () use ($itemId, $costData, $calculationMethod) {
            $this->invalidatePreviousCosts($itemId);
            $item = Item::findOrFail($itemId);
            $totalCost = $costData['total_cost'];

            $sellPrice = $item->actual_sell_price ?? $item->target_sell_price;
            $profitMargin = null;
            $suggestedPrice = null;

            if ($sellPrice > 0) {
                $profitMargin = $this->calculateProfitMargin($sellPrice, $totalCost);
                $suggestedPrice = $sellPrice; 
            }
            return ItemCostHistory::create([
                'item_id' => $itemId,
                'ingredients_cost' => $costData['ingredients_cost'] ?? 0,
                'boxes_cost' => $costData['boxes_cost'] ?? 0,
                'overhead_cost' => $costData['overhead_cost'] ?? 0,
                'suggested_sell_price' => $suggestedPrice,
                'profit_margin' => $profitMargin,
                'calculation_method' => $calculationMethod,
                'breakdown_details' => $costData['breakdown'] ?? null,
                'notes' => $costData['notes'] ?? null,
                'valid_from' => now(),
                'valid_until' => null,
                'calculated_by' => auth()->id(),
            ]);
        });
    }

    /**
     * دریافت آخرین هزینه محاسبه شده
     */
    public function getCurrentCost(int $itemId): ?ItemCostHistory
    {
        return ItemCostHistory::where('item_id', $itemId)
            ->current()
            ->first();
    }

    /**
     * دریافت تاریخچه هزینه‌ها
     */
    public function getCostHistory(int $itemId, int $limit = 10): Collection
    {
        return ItemCostHistory::where('item_id', $itemId)
            ->with('calculator')
            ->latest('valid_from')
            ->limit($limit)
            ->get();
    }

    /**
     * بروزرسانی هزینه‌های واحد از قیمت‌های جدید
     */
    public function refreshUnitCosts(int $itemId): bool
    {
        $item = Item::with(['ingredients.stock', 'boxes'])->findOrFail($itemId);

        DB::transaction(function () use ($item) {
            // Update ingredient costs
            foreach ($item->ingredients as $ingredient) {
                $unitCost = $ingredient->stock->avg_cost_per_gram ?? 0;

                $item->ingredients()->updateExistingPivot($ingredient->id, [
                    'unit_cost' => $unitCost,
                ]);
            }

            // Update box costs (if they have price history)
            foreach ($item->boxes as $box) {
                $latestPrice = $box->prices()->latest('valid_from')->first();

                if ($latestPrice) {
                    $item->boxes()->updateExistingPivot($box->id, [
                        'unit_cost' => $latestPrice->buy_price ?? 0,
                    ]);
                }
            }
        });

        return true;
    }

    /**
     * نامعتبر کردن هزینه‌های قبلی
     */
    public function invalidatePreviousCosts(int $itemId): int
    {
        return ItemCostHistory::where('item_id', $itemId)
            ->whereNull('valid_until')
            ->update(['valid_until' => now()]);
    }

    /**
     * محاسبه مجدد هزینه تمام محصولات
     */
    public function recalculateAllItems(bool $saveToHistory = true): array
    {
        $items = Item::with(['ingredients.stock', 'boxes'])->get();

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $costData = $this->calculateItemCost($item->id);

                if ($saveToHistory) {
                    $this->saveCostHistory($item->id, $costData, 'auto');
                }

                // Update item target cost
                $item->update([
                    'target_cost' => $costData['total_cost'],
                    'last_cost_calculated_at' => now(),
                ]);

                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'item_id' => $item->id,
                    'error' => $e->getMessage(),
                ];

                Log::error("Failed to recalculate cost for item {$item->id}: " . $e->getMessage());
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
        ];
    }

    /**
     * محاسبه هزینه دسته‌ای محصولات
     */
    public function bulkCalculateCosts(array $itemIds, bool $saveToHistory = true): array
    {
        $successCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($itemIds as $itemId) {
            try {
                $costData = $this->calculateItemCost($itemId);

                if ($saveToHistory) {
                    $this->saveCostHistory($itemId, $costData, 'auto');
                }

                $results[$itemId] = [
                    'success' => true,
                    'cost' => $costData['total_cost'],
                ];

                $successCount++;
            } catch (\Exception $e) {
                $results[$itemId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                $failedCount++;
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * تحلیل سودآوری محصول
     */
    public function analyzeProfitability(int $itemId): array
    {
        $item = Item::with(['ingredients.stock', 'boxes'])->findOrFail($itemId);
        $costData = $this->calculateItemCost($itemId);

        $sellPrice = $item->actual_sell_price ?? $item->target_sell_price;
        $cost = $costData['total_cost'];

        $isProfitable = $sellPrice > $cost;
        $profitMargin = $this->calculateProfitMargin($sellPrice, $cost);
        $netProfit = $sellPrice - $cost;

        return [
            'is_profitable' => $isProfitable,
            'profit_margin' => $profitMargin,
            'net_profit' => round($netProfit, 2),
            'sell_price' => $sellPrice,
            'total_cost' => $cost,
            'cost_breakdown' => $costData['breakdown'],
        ];
    }

    /**
     * مقایسه هزینه فعلی با گذشته
     */
    public function compareCosts(int $itemId): array
    {
        $current = $this->getCurrentCost($itemId);
        $previous = ItemCostHistory::where('item_id', $itemId)
            ->where('id', '!=', $current?->id)
            ->expired()
            ->latest('valid_from')
            ->first();

        if (!$current) {
            return [
                'current' => null,
                'previous' => null,
                'change_percent' => 0,
                'change_amount' => 0,
            ];
        }

        $currentCost = $current->total_cost ?? $current->total_cost_manual;
        $previousCost = $previous ? ($previous->total_cost ?? $previous->total_cost_manual) : 0;

        $changeAmount = $currentCost - $previousCost;
        $changePercent = $previousCost > 0
            ? (($changeAmount / $previousCost) * 100)
            : 0;

        return [
            'current' => round($currentCost, 2),
            'previous' => round($previousCost, 2),
            'change_percent' => round($changePercent, 2),
            'change_amount' => round($changeAmount, 2),
        ];
    }

    /**
     * پیش‌بینی هزینه با تغییر قیمت مواد اولیه
     */
    public function projectCostWithPriceChanges(int $itemId, array $priceChanges): array
    {
        $item = Item::with(['ingredients', 'boxes'])->findOrFail($itemId);
        $currentCost = $this->calculateItemCost($itemId);

        $projectedIngredientsCost = 0;

        foreach ($item->ingredients as $ingredient) {
            $actualGrams = $ingredient->pivot->actual_grams;
            $newPrice = $priceChanges[$ingredient->id] ?? $ingredient->pivot->unit_cost;
            $projectedIngredientsCost += $actualGrams * $newPrice;
        }

        $projectedTotalCost = $projectedIngredientsCost + $currentCost['boxes_cost'];

        return [
            'projected_cost' => round($projectedTotalCost, 2),
            'current_cost' => $currentCost['total_cost'],
            'difference' => round($projectedTotalCost - $currentCost['total_cost'], 2),
        ];
    }

    /**
     * تعیین قیمت بهینه
     */
    public function suggestOptimalPrice(int $itemId, float $targetProfitMargin): array
    {
        $costData = $this->calculateItemCost($itemId);
        $suggestedPrice = $this->calculateSuggestedPrice($costData['total_cost'], $targetProfitMargin);

        return [
            'suggested_price' => $suggestedPrice,
            'current_cost' => $costData['total_cost'],
            'profit_margin' => $targetProfitMargin,
            'expected_profit' => round($suggestedPrice - $costData['total_cost'], 2),
        ];
    }

    /**
     * دریافت گزارش هزینه‌ها
     */
    public function getCostReport(array $filters = []): array
    {
        $query = ItemCostHistory::with('item')
            ->current();

        if (isset($filters['item_ids'])) {
            $query->whereIn('item_id', $filters['item_ids']);
        }

        if (isset($filters['min_cost'])) {
            $query->whereRaw('(ingredients_cost + boxes_cost + overhead_cost) >= ?', [$filters['min_cost']]);
        }

        if (isset($filters['max_cost'])) {
            $query->whereRaw('(ingredients_cost + boxes_cost + overhead_cost) <= ?', [$filters['max_cost']]);
        }

        $costs = $query->get();

        return [
            'total_items' => $costs->count(),
            'total_value' => $costs->sum('total_cost_manual'),
            'average_cost' => $costs->avg('total_cost_manual'),
            'min_cost' => $costs->min('total_cost_manual'),
            'max_cost' => $costs->max('total_cost_manual'),
            'items' => $costs,
        ];
    }
}
