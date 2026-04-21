<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Contracts\ReportingServiceInterface;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\IngredientTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService implements ReportingServiceInterface
{
    public function getLowStockIngredients(int $limit = 10): Collection
    {
        return Cache::remember('reports:low-stock', 300, function () use ($limit) {
            return Ingredient::active()
                ->lowStock()
                ->with(['stock', 'category'])
                ->limit($limit)
                ->get();
        });
    }

    public function getInventoryValues(): array
    {
        return Cache::remember('reports:inventory-values', 600, function () {
            return [
                'purchase_value' => $this->getPurchaseInventoryValue(),
                'sales_value' => $this->getSalesInventoryValue()
            ];
        });
    }

    private function getPurchaseInventoryValue(): float
    {
        return DB::table('ingredient_stock as s')
            ->join('price_history as p', function ($join) {
                $join->on('s.ingredient_id', '=', 'p.ingredient_id')
                     ->whereNull('p.valid_until')
                     ->where('p.unit_id', 21);
            })
            ->whereNotNull('p.buy_price') 
            ->sum(DB::raw('COALESCE(s.quantity_grams * p.buy_price, 0)'));
    }

    private function getSalesInventoryValue(): float
    {
        return DB::table('ingredient_stock as s')
            ->join('price_history as p', function ($join) {
                $join->on('s.ingredient_id', '=', 'p.ingredient_id')
                     ->whereNull('p.valid_until')
                     ->where('p.unit_id', 21);
            })
            ->whereNotNull('p.sell_price') 
            ->sum(DB::raw('COALESCE(s.quantity_grams * p.sell_price, 0)'));
    }



    public function getStockMovementReport(int $days = 30): Collection
    {
        return Cache::remember("reports:movement:{$days}", 1800, function () use ($days) {
            return IngredientTransaction::where('created_at', '>=', now()->subDays($days))
                ->selectRaw('
                    ingredient_id, 
                    SUM(CASE WHEN grams_effect > 0 THEN grams_effect ELSE 0 END) as total_in,
                    SUM(CASE WHEN grams_effect < 0 THEN ABS(grams_effect) ELSE 0 END) as total_out,
                    COUNT(*) as transactions
                ')
                ->groupBy('ingredient_id')
                ->with('ingredient')
                ->get();
        });
    }

    public function getAbcAnalysis(): Collection
    {
        return Cache::remember('reports:abc-analysis', 3600, function () {
            return Ingredient::with('stock')
                ->get()
                ->map(function ($ingredient) {
                    $value = ($ingredient->stock->quantity_grams ?? 0) * ($ingredient->stock->avg_cost_per_gram ?? 0);
                    return [
                        'ingredient' => $ingredient,
                        'value' => $value,
                        'abc_class' => $ingredient->abc_class
                    ];
                })
                ->sortByDesc('value')
                ->values();
        });
    }
}
