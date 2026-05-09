<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Contracts\InventoryTransactionServiceInterface;
use Modules\Inventory\Models\IngredientTransaction;
use Modules\Inventory\Models\IngredientStock;
use Modules\Inventory\Jobs\UpdateStockAnalytics;
use Modules\Inventory\Jobs\CheckExpiredBatches;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Inventory\Models\Box;
use Modules\Inventory\Models\BoxStock;
use Modules\Inventory\Models\BoxTransaction;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\PriceHistory;

class InventoryTransactionService implements InventoryTransactionServiceInterface
{
    public function getAllIngredients(): Collection
    {
        return Cache::remember('ingredients:list:all', 3, function () {
            return Ingredient::with(['stock'])
                ->where('is_active', true)
                ->get()
                ->map(function ($ingredient) {
                    $stock = $ingredient->stock;
                    $price = $this->getIngredientLatestPrice($ingredient->id);

                    return [
                        'id' => $ingredient->id,
                        'name' => $ingredient->ingredient_name,
                        'code' => $ingredient->ingredient_code,
                        'quantity_grams' => $stock ? round($stock->quantity_grams, 2) : 0,
                        'available_grams' => $stock ? round($stock->available_grams, 2) : 0,
                        'reserved_grams' => $stock ? round($stock->reserved_grams, 2) : 0,
                        'buy_price_per_gram' => $price['buy_price'],
                        'sell_price_per_gram' => $price['sell_price'],
                        'total_buy_value' => round(($stock ? $stock->quantity_grams : 0) * $price['buy_price'], 0),
                        'total_sell_value' => round(($stock ? $stock->quantity_grams : 0) * $price['sell_price'], 0),
                        'profit_potential' => round(
                            (($stock ? $stock->quantity_grams : 0) * $price['sell_price']) -
                                (($stock ? $stock->quantity_grams : 0) * $price['buy_price']),
                            0
                        ),
                    ];
                })
                ->sortByDesc('quantity_grams')
                ->values();
        });
    }

    /**
     */
    public function getAllBoxes(): Collection
    {
        return Cache::remember('boxes:list:all', 3, function () {
            return Box::with(['stock'])
                ->where('is_active', true)
                ->get()
                ->map(function ($box) {
                    $stock = $box->stock;
                    $price = $this->getBoxLatestPrice($box->id);

                    $quantity = $stock ? $stock->quantity : 0;
                    $reserved = $stock ? $stock->reserved_quantity : 0;

                    return [
                        'id' => $box->id,
                        'name' => $box->name,
                        'code' => $box->code,
                        'quantity' => $quantity,
                        'reserved_quantity' => $reserved,
                        'available_quantity' => max(0, $quantity - $reserved),
                        'buy_price_per_unit' => $price['buy_price'],
                        'sell_price_per_unit' => $price['sell_price'],
                        'total_buy_value' => round($quantity * $price['buy_price'], 0),
                        'total_sell_value' => round($quantity * $price['sell_price'], 0),
                        'target_sell_price' => round($box->target_sell_price, 0),
                        'profit_potential' => round(
                            ($quantity * $price['sell_price']) - ($quantity * $price['buy_price']),
                            0
                        ),
                    ];
                })
                ->sortByDesc('quantity')
                ->values();
        });
    }
    private function getIngredientLatestPrice(int $ingredientId): array
    {
        $price = PriceHistory::where('ingredient_id', $ingredientId)
            ->where('unit_id', 21) // واحد گرم
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->orderBy('valid_from', 'desc')
            ->first();

        return [
            'buy_price' => $price ? round($price->buy_price, 2) : 0,
            'sell_price' => $price ? round($price->sell_price, 2) : 0,
        ];
    }

    /**
     */
    private function getBoxLatestPrice(int $boxId): array
    {
        $price = PriceHistory::where('box_id', $boxId)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->orderBy('valid_from', 'desc')
            ->first();

        return [
            'buy_price' => $price ? round($price->buy_price, 0) : 0,
            'sell_price' => $price ? round($price->sell_price, 0) : 0,
        ];
    }

    public function getRecentTransactions(int $limit = 50): Collection
    {
        return Cache::remember("transactions:recent:{$limit}", 1800, function () use ($limit) {
            $ingredientTx = IngredientTransaction::with(['ingredient', 'unit'])
                ->selectRaw('*, "ingredient" as entity_type, "ingredient" as source_type')
                ->latest('created_at')
                ->limit($limit)
                ->get();

            $boxTx = BoxTransaction::with(['box'])
                ->selectRaw('*, "box" as entity_type, "box" as source_type')
                ->latest('created_at')
                ->limit($limit)
                ->get();

            return $ingredientTx->concat($boxTx)
                ->sortByDesc('created_at')
                ->take($limit)
                ->values();
        });
    }

    public function rollbackTransaction(string $entityType, int $transactionId): bool
    {
        return DB::transaction(function () use ($entityType, $transactionId) {
            $transaction = match ($entityType) {
                'ingredient' => IngredientTransaction::lockForUpdate()->find($transactionId),
                'box' => BoxTransaction::lockForUpdate()->find($transactionId),
                default => throw new \Exception('Invalid entity type: ' . $entityType)
            };

            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }

            $oppositeEffect = match ($entityType) {
                'ingredient' => -$transaction->grams_effect,
                'box' => -$transaction->quantity_effect,
                default => 0
            };

            match ($entityType) {
                'ingredient' => IngredientStock::where('ingredient_id', $transaction->ingredient_id)
                    ->update([
                        'quantity_grams' => DB::raw("quantity_grams + {$oppositeEffect}"),
                        'available_grams' => DB::raw("GREATEST(0, (quantity_grams + {$oppositeEffect}) - reserved_grams)")
                    ]),
                'box' => BoxStock::where('box_id', $transaction->box_id)
                    ->update([
                        'quantity' => DB::raw("quantity + {$oppositeEffect}"),
                        // 'available_quantity' => DB::raw("GREATEST(0, (quantity + {$oppositeEffect}) - reserved_quantity)")
                    ])
            };

            $transaction->delete();

            Cache::forget('transactions:all');
            Cache::forget('stock:all');
            Cache::forget('ingredients:all');
            Cache::forget('boxes:all');

            return true;
        });
    }


    private function validateTransactionData(array $data): array
    {
        $rules = [
            'entity_type' => 'required|in:ingredient,box',
            'entity_id' => 'required|integer|min:1',
            'transaction_type' => 'required|in:purchase,usage,adjustment,waste,expiry,reserve,release',
            'input_quantity' => 'required|numeric|min:0.001',
            'total_cost' => 'nullable|numeric|min:0',
            'batch_number' => 'nullable|string|max:100',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'created_by' => 'nullable|exists:users,id'
        ];

        if ($data['entity_type'] === 'ingredient') {
            $rules['grams_effect'] = 'required|numeric|between:-999999,999999';
        } elseif ($data['entity_type'] === 'box') {
            $rules['quantity_effect'] = 'required|numeric|between:-999999,999999';
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    public function createTransaction(array $data): IngredientTransaction|BoxTransaction
    {
        $validatedData = $this->validateTransactionData($data);
        ['entity_type' => $entityType, 'entity_id' => $entityId] = $validatedData;

        $entityInfo = $this->getEntityInfo($entityType, $entityId);

        if ($entityType === 'ingredient') {
            $validatedData['ingredient_name'] = $entityInfo['name'];
            $validatedData['ingredient_code'] = $entityInfo['code'] ?? 'N/A';
        } else {
            $validatedData['entity_name'] = $entityInfo['name'];
            $validatedData['entity_code'] = $entityInfo['code'] ?? 'N/A';
        }

        return RateLimiter::attempt('inventory-tx-' . Auth::id(), 100, function () use ($validatedData, $entityType, $entityId) {
            return DB::transaction(function () use ($validatedData, $entityType, $entityId) {
                $stock = match ($entityType) {
                    'ingredient' => $this->handleIngredientStock($entityId, $validatedData),
                    'box' => $this->handleBoxStock($entityId, $validatedData),
                    default => throw new \Exception('Invalid entity_type: ' . $entityType)
                };

                $transaction = $this->createTransactionRecord($validatedData, $entityType, $entityId);
                $this->dispatchJobs($validatedData, $entityType, $entityId);
                $this->flushRelevantCache($entityType, $entityId);
                return match ($entityType) {
                    'ingredient' => $transaction->load('ingredient'),
                    'box' => $transaction->load('box'),
                    default => $transaction
                };
            });
        });
    }

    private function getEntityInfo(string $entityType, int $entityId): array
    {
        return match ($entityType) {
            'ingredient' => Ingredient::select('ingredient_name as name', 'ingredient_code as code')
                ->findOrFail($entityId)
                ->only(['name', 'code']),

            'box' => Box::select('name', 'code')
                ->findOrFail($entityId)
                ->only(['name', 'code']),

            default => throw new \Exception('Unknown entity type')
        };
    }
    private function dispatchJobs(array $data, string $entityType, int $entityId): void
    {
        if ($entityType === 'ingredient') {
            UpdateStockAnalytics::dispatch($entityId);
            if ($data['transaction_type'] === 'purchase') {
                CheckExpiredBatches::dispatch($entityId);
            }
        }
    }


    private function handleIngredientStock(int $ingredientId, array $data): IngredientStock
    {
        $stock = IngredientStock::lockForUpdate()
            ->where('ingredient_id', $ingredientId)
            ->firstOrFail();

        $newQuantity = $stock->quantity_grams + $data['grams_effect'];

        if ($newQuantity < 0 && $data['transaction_type'] !== 'adjustment') {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient stock: only ' . $stock->available_grams . 'g available'
            ]);
        }

        $stock->update([
            'quantity_grams' => max(0, $newQuantity),
            'available_grams' => max(0, $newQuantity - $stock->reserved_grams),
        ]);

        return $stock;
    }

    private function handleBoxStock(int $boxId, array $data): BoxStock
    {
        $stock = BoxStock::lockForUpdate()
            ->where('box_id', $boxId)
            ->firstOrFail();

        $effectField = $data['quantity_effect'] ?? $data['grams_effect'] ?? 0;
        $newQuantity = $stock->quantity + $effectField;

        if ($newQuantity < 0 && $data['transaction_type'] !== 'adjustment') {
            throw ValidationException::withMessages([
                'quantity' => 'Insufficient box stock: only ' . $stock->available_quantity . ' units available'
            ]);
        }

        $stock->update([
            'quantity' => max(0, $newQuantity),
            'available_quantity' => max(0, $newQuantity - $stock->reserved_quantity)
        ]);

        return $stock;
    }


    private function createTransactionRecord(array $data, string $entityType, int $entityId): Model
    {
        $transactionData = array_merge($data, [
            $entityType === 'ingredient' ? 'ingredient_id' : 'box_id' => $entityId,
            'entity_type' => $entityType
        ]);

        if ($entityType === 'ingredient') {
            unset($transactionData['entity_name'], $transactionData['entity_code']);
        }

        return match ($entityType) {
            'ingredient' => IngredientTransaction::create($transactionData),
            'box' => BoxTransaction::create($transactionData),
            default => throw new \Exception('Cannot create transaction for: ' . $entityType)
        };
    }

    private function flushRelevantCache(string $entityType, int $entityId): void
    {
        try {

            Cache::forget("{$entityType}:{$entityId}");
            Cache::forget('stock:all');
            Cache::forget('ingredients:all');
            Cache::forget('analytics:all');
            Cache::forget('transactions:all');
            Cache::forget('boxes:all');
            Cache::forget('box_stock:all');
        } catch (\Exception $e) {
            \Log::warning('Cache flush failed: ' . $e->getMessage());
        }
    }


    public function bulkTransactions(array $transactions): Collection
    {
        $validatedTransactions = collect($transactions)->map(
            fn($data) =>
            $this->validateTransactionData($data)
        );

        return DB::transaction(function () use ($validatedTransactions) {
            return $validatedTransactions->map(
                fn($data) =>
                $this->createTransaction($data->toArray())
            );
        });
    }

    public function createUsageTransaction(array $data, string $status = 'committed'): IngredientTransaction
    {
        $data['status'] = $status;
        $data['transaction_type'] = 'usage';
        return $this->createTransaction($data);
    }

    public function commitPendingTransactions(string $referenceType, int $referenceId): bool
    {
        return DB::transaction(function () use ($referenceType, $referenceId) {
            $pendingTxs = IngredientTransaction::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->get();

            foreach ($pendingTxs as $tx) {
                IngredientStock::where('ingredient_id', $tx->ingredient_id)
                    ->decrement('quantity_grams', abs($tx->grams_effect));
                $tx->update(['status' => 'committed']);
            }

            Cache::forget('ingredients:list:all');
            Cache::forget('stock:all');
            Cache::forget('analytics:all');
            return true;
        });
    }

    public function createIngredient(array $data): Ingredient
    {
        return DB::transaction(function () use ($data) {
            $ingredient = \Modules\Inventory\Models\Ingredient::create([
                'ingredient_name' => $data['ingredient_name'],
                'ingredient_code' => $data['ingredient_code'],
                'is_active' => true,
                'reorder_point' => $data['reorder_point'] ?? 0
            ]);

            Cache::forget('ingredients:list:all');
            Cache::forget('ingredients:all');

            return $ingredient->load('units');
        });
    }

    public function initializeStock(int $ingredientId, float $quantityGrams, ?float $costPerGram = 0): IngredientStock
    {
        return DB::transaction(function () use ($ingredientId, $quantityGrams, $costPerGram) {
            $stock = IngredientStock::updateOrCreate(
                ['ingredient_id' => $ingredientId],
                [
                    'quantity_grams' => $quantityGrams,
                    'available_grams' => $quantityGrams,
                    'reserved_grams' => 0,
                    'avg_cost_per_gram' => $costPerGram,
                    'last_updated' => now(),
                ]
            );

            Cache::forget('stock:all');
            Cache::forget('ingredients:list:all');

            return $stock->load('ingredient');
        });
    }


    public function createBox(array $data): Box
    {
        return DB::transaction(function () use ($data) {
            $box = Box::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'total_weight_grams' => $data['total_weight_grams'] ?? 0,
                    'target_sell_price' => $data['target_sell_price'] ?? 0,
                    'is_active' => true,
                ]
            );
            $this->flushBoxCache();
            return $box->fresh();
        });
    }

    public function initializeBoxStock(int $boxId, int $quantityUnits, float $costPerUnit = 0): BoxStock
    {
        return DB::transaction(function () use ($boxId, $quantityUnits, $costPerUnit) {
            $stock = BoxStock::updateOrCreate(
                ['box_id' => $boxId],
                [
                    'quantity' => $quantityUnits,
                    'reserved_quantity' => 0,
                    'updated_at' => now()
                ]
            );

            $this->flushBoxCache();
            return $stock->load('box');
        });
    }

    private function calculateNewAvgCost(IngredientStock $stock, array $data): float
    {
        if ($data['transaction_type'] !== 'purchase' || !isset($data['total_cost'])) {
            return $stock->avg_cost_per_gram ?? 0;
        }

        $incomingCost = $data['total_cost'];
        $totalQuantity = $stock->quantity_grams + abs($data['grams_effect']);

        return $totalQuantity > 0 ? $incomingCost / $totalQuantity : ($stock->avg_cost_per_gram ?? 0);
    }
    private function flushBoxCache(): void
    {
        try {
            if (in_array(config('cache.default'), ['redis', 'memcached'])) {
                Cache::tags(['boxes', 'box_stock'])->flush();
            } else {
                Cache::forget('boxes:all');
                Cache::forget('box_stock:all');
                Cache::forget('inventory:all');
            }
        } catch (\Exception $e) {
            \Log::warning('Cache flush failed: ' . $e->getMessage());
        }
    }
}
