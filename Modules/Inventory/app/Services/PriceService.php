<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Contracts\PriceServiceInterface;
use Modules\Inventory\Models\PriceHistory;
use Modules\Inventory\Models\IngredientUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PriceService implements PriceServiceInterface
{
    public function getCurrentPrice(int $ingredientId, int $unitId)
    {
        return Cache::remember("price:{$ingredientId}:{$unitId}", 1800, function () use ($ingredientId, $unitId) {
            return PriceHistory::with(['ingredient', 'unit'])
                ->where('ingredient_id', $ingredientId)
                ->where('unit_id', $unitId)
                ->where('valid_from', '<=', Carbon::today())
                ->where(function ($query) {
                    $query->whereNull('valid_until')
                          ->orWhere('valid_until', '>=', Carbon::today());
                })
                ->orderBy('valid_from', 'desc')
                ->first();
        });
    }

    public function setNewPrice(?int $ingredientId = null, ?int $boxId = null, int $unitId, float $buyPrice, float $sellPrice): PriceHistory
    {
        return DB::transaction(function () use ($ingredientId, $boxId, $unitId, $buyPrice, $sellPrice) {
            $previousQuery = PriceHistory::whereNull('valid_until')
                ->where('unit_id', $unitId);

            if ($ingredientId !== null) {
                $previousQuery->where('ingredient_id', $ingredientId);
            }
            if ($boxId !== null) {
                $previousQuery->where('box_id', $boxId);
            }

            $previousQuery->update(['valid_until' => now()]);

            $priceData = [
                'unit_id' => $unitId,
                'buy_price' => $buyPrice,
                'sell_price' => $sellPrice,
                'valid_from' => Carbon::now()
            ];

            if ($ingredientId !== null) {
                $priceData['ingredient_id'] = $ingredientId;
            }
            if ($boxId !== null) {
                $priceData['box_id'] = $boxId;
            }

            $price = PriceHistory::create($priceData);
            $price->load(['ingredient', 'unit', 'box']);

            if ($ingredientId !== null) {
                Cache::forget("prices:{$ingredientId}");
            }
            if ($boxId !== null) {
                Cache::forget("prices:box:{$boxId}");
            }
            Cache::forget('prices:all');
            Cache::forget('ingredients:all');

            return $price;
        });
    }



    public function getIngredientPricingSummary(int $ingredientId): Collection
    {
        return Cache::remember("price-summary:{$ingredientId}", 3600, function () use ($ingredientId) {
            $units = IngredientUnit::where('ingredient_id', $ingredientId)->pluck('unit_id');
            \Log::info("Ingredient {$ingredientId} units: " . $units->toJson());

            if ($units->isEmpty()) {
                \Log::warning("No units found for ingredient {$ingredientId}");
                return collect();
            }

            $prices = PriceHistory::with(['ingredient', 'unit'])
                ->whereIn('unit_id', $units)
                ->where('ingredient_id', $ingredientId)
                ->where(function ($query) {
                    $query->whereNull('valid_until')  
                          ->orWhere('valid_until', '>=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('valid_from')
                          ->orWhereDate('valid_from', '<=', now());
                })
                ->orderBy('valid_from', 'desc')  
                ->get()
                ->groupBy('unit_id')
                ->map(function ($group) {        
                    return $group->first();
                });

            \Log::info("Summary for ingredient {$ingredientId}: " . $prices->count() . " units");
            return $prices;
        });
    }


    public function getHistoricalPrices(int $ingredientId, int $limit = 10): Collection
    {
        return Cache::remember("price-history:{$ingredientId}:{$limit}", 3600, function () use ($ingredientId, $limit) {
            return PriceHistory::with(['ingredient', 'unit', 'box'])
                ->where('ingredient_id', $ingredientId)
                ->orderBy('valid_from', 'desc')
                ->limit($limit)
                ->get();
        });
    }

}
