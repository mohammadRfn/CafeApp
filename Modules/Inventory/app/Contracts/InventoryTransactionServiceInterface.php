<?php

namespace Modules\Inventory\Contracts;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\Models\Box;
use Modules\Inventory\Models\BoxStock;
use Modules\Inventory\Models\BoxTransaction;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\IngredientStock;
use Modules\Inventory\Models\IngredientTransaction;

interface InventoryTransactionServiceInterface
{
    public function createTransaction(array $data): IngredientTransaction|BoxTransaction;

    /**
     * Create multiple transactions atomically
     */
    public function bulkTransactions(array $transactions): Collection;

    /**
     * Get recent transactions for ingredient
     */
    public function getRecentTransactions(int $limit = 50): Collection;


    /**
     * Rollback specific transaction (stock restoration)
     */
    public function rollbackTransaction(string $entityType, int $transactionId): bool;


    /**
     * Validate transaction data before processing
     */
    // public function validateTransactionData(array $data): array;
    /**
     * Validate transaction data before processing
     */
    public function createIngredient(array $data): Ingredient;
    /**
     * Validate transaction data before processing
     */
    public function initializeStock(int $ingredientId, float $quantityGrams, ?float $costPerGram = 0): IngredientStock;

    public function createBox(array $data): Box;

    public function initializeBoxStock(int $boxId, int $quantityUnits, float $costPerUnit = 0): BoxStock;

    public function getAllIngredients(): Collection;
    public function getAllBoxes(): Collection;

}
