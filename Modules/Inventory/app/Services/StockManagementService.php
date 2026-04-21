<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Contracts\StockManagementServiceInterface;
use Modules\Inventory\Models\IngredientStock;
use Modules\Inventory\Models\Box;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\BoxStock;

class StockManagementService implements StockManagementServiceInterface
{
    public function reserveStock(int $ingredientId, float $grams): array
    {
        return DB::transaction(function () use ($ingredientId, $grams) {
            $stock = IngredientStock::lockForUpdate()
                ->where('ingredient_id', $ingredientId)
                ->firstOrFail();

            $available = $stock->quantity_grams - $stock->reserved_grams;
            if ($available < $grams) {
                return [
                    'success' => false,
                    'available' => (float) $available,
                    'required' => $grams,
                    'shortfall' => $grams - $available
                ];
            }

            $newReserved = $stock->reserved_grams + $grams;

            $stock->update([
                'reserved_grams' => $newReserved,
                'available_grams' => $stock->quantity_grams - $newReserved,  
            ]);

            $stock->refresh();

            Cache::forget("stock:{$ingredientId}");
            Cache::forget('stock:all');
            Cache::forget('ingredients:all');

            return ['success' => true, 'reserved' => (float) $stock->reserved_grams];
        });
    }


    public function allocateForBoxProduction(Box $box, int $quantity = 1): array
    {
        return DB::transaction(function () use ($box, $quantity) {
            $results = [];

            foreach ($box->ingredients as $ingredient) {
                $requiredGrams = $ingredient->pivot->required_quantity *
                               (1 + $ingredient->pivot->waste_factor) * $quantity;

                $result = $this->reserveStock($ingredient->id, $requiredGrams);
                $results[$ingredient->id] = $result;

                if (!$result['success']) {
                    $this->releaseAllReservations(array_keys($results));
                    return $results;
                }
            }

            return ['success' => true, 'details' => $results];
        });
    }

    public function releaseReservation(int $ingredientId, float $grams): bool
    {
        return DB::transaction(function () use ($ingredientId, $grams) {
            $stock = IngredientStock::lockForUpdate()
                ->where('ingredient_id', $ingredientId)
                ->first();

            if (!$stock || $stock->reserved_grams < $grams) {
                return false;
            }

            $stock->update([
                'reserved_grams' => max(0, $stock->reserved_grams - $grams),
                'available_grams' => $stock->quantity_grams - max(0, $stock->reserved_grams - $grams),
            ]);

            Cache::forget("stock:{$ingredientId}");
            Cache::forget('stock:all');
            Cache::forget('ingredients:all');
            return true;
        });
    }


    public function getStockStatus(int $ingredientId): ?IngredientStock
    {
        return IngredientStock::where('ingredient_id', $ingredientId)
        ->with('ingredient')
        ->first();
    }

    private function releaseAllReservations(array $ingredientIds): void
    {
        foreach ($ingredientIds as $ingredientId) {
            $this->releaseReservation($ingredientId, 999999);
        }
    }

    public function getBoxStockStatus(int $boxId): ?BoxStock
    {
        return BoxStock::where('box_id', $boxId)->first();
    }

    public function reserveBoxStock(int $boxId, float $quantity): array
    {
        return DB::transaction(function () use ($boxId, $quantity) {
            $stock = BoxStock::lockForUpdate()->where('box_id', $boxId)->first();

            if (!$stock || $stock->quantity < $quantity) {
                return ['success' => false, 'available' => $stock->quantity ?? 0];
            }

            $stock->update([
                'quantity' => $stock->quantity - $quantity,
                'reserved_quantity' => $stock->reserved_quantity + $quantity
            ]);

            Cache::forget("box_stock_{$boxId}");
            Cache::forget("box_stock_all");

            return ['success' => true, 'reserved' => $quantity];
        });
    }

    public function releaseBoxReservation(int $boxId, float $quantity): bool
    {
        $stock = BoxStock::where('box_id', $boxId)->first();

        if (!$stock) {
            return false;
        }

        $stock->update([
            'quantity' => $stock->quantity + $quantity,
            'reserved_quantity' => max(0, $stock->reserved_quantity - $quantity)
        ]);

        Cache::forget("box_stock_{$boxId}");
        Cache::forget("box_stock_all");

        return true;
    }

}
