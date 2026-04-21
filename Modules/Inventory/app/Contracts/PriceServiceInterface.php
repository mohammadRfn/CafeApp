<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\Models\PriceHistory;

interface PriceServiceInterface
{
    public function getCurrentPrice(int $ingredientId, int $unitId);
    public function setNewPrice(?int $ingredientId = null, ?int $boxId = null, int $unitId, float $buyPrice, float $sellPrice): PriceHistory;
    public function getIngredientPricingSummary(int $ingredientId): \Illuminate\Support\Collection;
    public function getHistoricalPrices(int $ingredientId, int $limit = 10): \Illuminate\Support\Collection;
}
