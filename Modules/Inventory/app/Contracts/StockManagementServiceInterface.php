<?php

namespace Modules\Inventory\Contracts;

use Modules\Inventory\Models\Box;
use Illuminate\Support\Collection;
use Modules\Inventory\Models\BoxStock;
use Modules\Inventory\Models\IngredientStock;

interface StockManagementServiceInterface
{
    public function reserveStock(int $ingredientId, float $grams): array;
    public function allocateForBoxProduction(Box $box, int $quantity = 1): array;
    public function releaseReservation(int $ingredientId, float $grams): bool;
    public function getStockStatus(int $ingredientId): ?IngredientStock;
    public function getBoxStockStatus(int $boxId): ?BoxStock;
    public function reserveBoxStock(int $boxId, float $quantity): array;
    public function releaseBoxReservation(int $boxId, float $quantity): bool;
}
