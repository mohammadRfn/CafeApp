<?php

namespace Modules\ItemMaker\app\Services;

use Modules\ItemMaker\app\Interfaces\ItemServiceInterface;
use Modules\ItemMaker\app\Interfaces\ItemRepositoryInterface;
use Modules\ItemMaker\app\Interfaces\CostCalculationServiceInterface;
use Modules\ItemMaker\app\Interfaces\RecipeValidationServiceInterface;
use Modules\ItemMaker\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Item Service
 *
 */
class ItemService implements ItemServiceInterface
{
    public function __construct(
        protected ItemRepositoryInterface $repository,
        protected CostCalculationServiceInterface $costService,
        protected RecipeValidationServiceInterface $validationService
    ) {
    }

    /**
     */
    public function list(array $filters = [], ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $with = ['ingredients', 'boxes', 'currentCost'];

        if ($perPage) {
            return $this->repository->paginate($perPage, $filters, $with);
        }

        return $this->repository->getAll($filters, $with);
    }

    /**
     */
    public function getDetails(int $id): Item
    {
        $item = $this->repository->findById($id, [
            'ingredients.stock',
            'boxes.stock',
            'currentCost',
            'creator',
            'updater',
        ]);

        if (!$item) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("محصول با شناسه {$id} یافت نشد");
        }

        return $item;
    }

    /**
     */
    public function getByCode(string $code): Item
    {
        $item = $this->repository->findByCode($code, [
            'ingredients.stock',
            'boxes.stock',
            'currentCost',
        ]);

        if (!$item) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("محصول با کد {$code} یافت نشد");
        }

        return $item;
    }

    /**
     */
    public function create(array $itemData, array $ingredients = [], array $boxes = []): Item
    {
        return DB::transaction(function () use ($itemData, $ingredients, $boxes) {
            $validation = $this->validate($itemData);
            if (!$validation['valid']) {
                throw ValidationException::withMessages($validation['errors']);
            }

            // $recipeValidation = $this->validationService->validateRecipe($ingredients, $boxes);
            // if (!$recipeValidation['valid']) {
            //     throw ValidationException::withMessages(['recipe' => $recipeValidation['errors']]);
            // }

            if (!empty($ingredients) || !empty($boxes)) {
                $recipeValidation = $this->validationService->validateRecipe($ingredients, $boxes);
                if (!$recipeValidation['valid']) {
                    throw ValidationException::withMessages(['recipe' => $recipeValidation['errors']]);
                }
            }
            $itemData['created_by'] = auth()->id();

            $item = $this->repository->create($itemData);

            if (!empty($ingredients)) {
                $this->repository->syncIngredients($item->id, $this->prepareIngredientsForSync($ingredients));
            }

            if (!empty($boxes)) {
                $this->repository->syncBoxes($item->id, $this->prepareBoxesForSync($boxes));
            }

            try {
                $costData = $this->costService->calculateItemCost($item->id);
                $this->costService->saveCostHistory($item->id, $costData, 'auto');

                $this->repository->update($item->id, [
                    'target_cost' => $costData['total_cost'],
                    'last_cost_calculated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to calculate cost for new item {$item->id}: " . $e->getMessage());
            }

            return $item->fresh(['ingredients', 'boxes', 'currentCost']);
        });
    }

    /**
     */
    public function update(int $id, array $itemData, ?array $ingredients = null, ?array $boxes = null): Item
    {
        return DB::transaction(function () use ($id, $itemData, $ingredients, $boxes) {
            $validation = $this->validate($itemData, $id);
            if (!$validation['valid']) {
                throw ValidationException::withMessages($validation['errors']);
            }

            $itemData['updated_by'] = auth()->id();

            $item = $this->repository->update($id, $itemData);

            $recipeChanged = false;

            if ($ingredients !== null) {
                $recipeValidation = $this->validationService->validateRecipe($ingredients, $boxes ?? []);
                if (!$recipeValidation['valid']) {
                    throw ValidationException::withMessages(['recipe' => $recipeValidation['errors']]);
                }

                $this->repository->syncIngredients($id, $this->prepareIngredientsForSync($ingredients));
                $recipeChanged = true;
            }

            if ($boxes !== null) {
                $this->repository->syncBoxes($id, $this->prepareBoxesForSync($boxes));
                $recipeChanged = true;
            }

            if ($recipeChanged) {
                try {
                    $costData = $this->costService->calculateItemCost($id);
                    $this->costService->saveCostHistory($id, $costData, 'auto');

                    $this->repository->update($id, [
                        'target_cost' => $costData['total_cost'],
                        'last_cost_calculated_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to recalculate cost for item {$id}: " . $e->getMessage());
                }
            }

            return $item->fresh(['ingredients', 'boxes', 'currentCost']);
        });
    }

    /**
     */
    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     */
    public function restore(int $id): bool
    {
        return $this->repository->restore($id);
    }

    /**
     */
    public function updateRecipe(int $itemId, array $ingredients): bool
    {
        return DB::transaction(function () use ($itemId, $ingredients) {
            $recipeValidation = $this->validationService->validateRecipe($ingredients, []);
            if (!$recipeValidation['valid']) {
                throw ValidationException::withMessages(['recipe' => $recipeValidation['errors']]);
            }

            $this->repository->syncIngredients($itemId, $this->prepareIngredientsForSync($ingredients));

            $costData = $this->costService->calculateItemCost($itemId);
            $this->costService->saveCostHistory($itemId, $costData, 'auto');

            $this->repository->update($itemId, [
                'target_cost' => $costData['total_cost'],
                'last_cost_calculated_at' => now(),
            ]);

            return true;
        });
    }

    /**
     */
    public function updatePackaging(int $itemId, array $boxes): bool
    {
        return DB::transaction(function () use ($itemId, $boxes) {
            $this->repository->syncBoxes($itemId, $this->prepareBoxesForSync($boxes));

            $costData = $this->costService->calculateItemCost($itemId);
            $this->costService->saveCostHistory($itemId, $costData, 'auto');

            $this->repository->update($itemId, [
                'target_cost' => $costData['total_cost'],
                'last_cost_calculated_at' => now(),
            ]);

            return true;
        });
    }

    /**
     */
    public function calculateCost(int $itemId, bool $saveToHistory = true): array
    {
        $costData = $this->costService->calculateItemCost($itemId);

        if ($saveToHistory) {
            $this->costService->saveCostHistory($itemId, $costData, 'auto');
        }

        $this->repository->update($itemId, [
            'target_cost' => $costData['total_cost'],
            'last_cost_calculated_at' => now(),
        ]);

        return $costData;
    }

    /**
     */
    public function checkAvailability(int $itemId, int $quantity = 1): array
    {
        return $this->validationService->checkFullAvailability($itemId, $quantity);
    }

    /**
     */
    public function toggleActive(int $itemId, bool $isActive): bool
    {
        return $this->repository->updateActiveStatus($itemId, $isActive);
    }

    /**
     */
    public function setSellPrice(int $itemId, float $price): bool
    {
        return $this->repository->updateSellPrice($itemId, $price);
    }

    /**
     */
    public function recordSale(int $itemId, int $quantity = 1): bool
    {
        return $this->repository->incrementDailySold($itemId, $quantity);
    }

    /**
     */
    public function cancelSale(int $itemId, int $quantity = 1): bool
    {
        return $this->repository->decrementDailySold($itemId, $quantity);
    }

    /**
     */
    public function resetDailyCounts(): int
    {
        return $this->repository->resetDailySoldCount();
    }

    /**
     */
    public function getActiveItems(?string $category = null): Collection
    {
        if ($category) {
            return $this->repository->getByCategory($category, ['ingredients', 'boxes']);
        }

        return $this->repository->getActive(['ingredients', 'boxes']);
    }

    /**
     */
    public function getFeaturedItems(): Collection
    {
        return $this->repository->getFeatured(['ingredients', 'boxes']);
    }

    /**
     */
    public function getAvailableItems(): Collection
    {
        return $this->repository->getAvailableToday(['ingredients', 'boxes']);
    }

    /**
     */
    public function search(string $query, array $filters = []): Collection
    {
        return $this->repository->search($query, $filters, ['ingredients', 'boxes']);
    }

    /**
     */
    public function getCategories(): Collection
    {
        return Item::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->where('is_active', true)
            ->orderBy('category')
            ->pluck('category');
    }

    /**
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     */
    public function duplicate(int $itemId, string $newCode, ?string $newName = null): Item
    {
        return DB::transaction(function () use ($itemId, $newCode, $newName) {
            $original = $this->getDetails($itemId);

            $newItemData = $original->only([
                'description', 'category', 'subcategory', 'preparation_time',
                'serving_size', 'serving_unit', 'requires_preparation', 'calories',
                'allergens', 'display_order',
            ]);

            $newItemData['code'] = $newCode;
            $newItemData['name'] = $newName ?? $original->name . ' (کپی)';
            $newItemData['is_active'] = false;

            // مواد اولیه - فرمت sync: key = ingredient_id
            $ingredients = [];
            foreach ($original->ingredients as $ingredient) {
                $ingredients[$ingredient->id] = [
                    'required_grams' => $ingredient->pivot->required_grams,
                    'waste_factor' => $ingredient->pivot->waste_factor,
                    'unit_cost' => $ingredient->pivot->unit_cost,
                    'is_optional' => $ingredient->pivot->is_optional,
                    'is_customizable' => $ingredient->pivot->is_customizable,
                    'preparation_note' => $ingredient->pivot->preparation_note,
                    'order' => $ingredient->pivot->order,
                ];
            }

            $boxes = [];
            foreach ($original->boxes as $box) {
                $boxes[$box->id] = [
                    'required_quantity' => $box->pivot->required_quantity,
                    'unit_cost' => $box->pivot->unit_cost,
                    'is_default_packaging' => $box->pivot->is_default_packaging,
                    'is_optional' => $box->pivot->is_optional,
                    'note' => $box->pivot->note,
                ];
            }

            // مستقیم create بدون recipe validation
            $itemData = $newItemData;
            $itemData['created_by'] = auth()->id();

            $item = $this->repository->create($itemData);

            // sync مستقیم بدون prepare (چون فرمت sync درسته)
            if (!empty($ingredients)) {
                $this->repository->syncIngredients($item->id, $ingredients);
            }

            if (!empty($boxes)) {
                $this->repository->syncBoxes($item->id, $boxes);
            }

            // cost calculation
            try {
                $costData = $this->costService->calculateItemCost($item->id);
                $this->costService->saveCostHistory($item->id, $costData, 'auto');
                $this->repository->update($item->id, [
                    'target_cost' => $costData['total_cost'],
                    'last_cost_calculated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to calculate cost for duplicated item {$item->id}: " . $e->getMessage());
            }

            return $item->fresh(['ingredients', 'boxes', 'currentCost']);
        });
    }


    /**
     */
    public function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = ['نام محصول الزامی است'];
        }

        if (empty($data['code'])) {
            $errors['code'] = ['کد محصول الزامی است'];
        } elseif ($this->repository->codeExists($data['code'], $excludeId)) {
            $errors['code'] = ['کد محصول تکراری است'];
        }

        if (isset($data['target_sell_price']) && $data['target_sell_price'] < 0) {
            $errors['target_sell_price'] = ['قیمت فروش نمی‌تواند منفی باشد'];
        }

        if (isset($data['preparation_time']) && $data['preparation_time'] < 0) {
            $errors['preparation_time'] = ['زمان آماده‌سازی نمی‌تواند منفی باشد'];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     */
    public function bulkToggleActive(array $itemIds, bool $isActive): int
    {
        $count = 0;
        foreach ($itemIds as $itemId) {
            if ($this->repository->updateActiveStatus($itemId, $isActive)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     */
    public function bulkDelete(array $itemIds): int
    {
        $count = 0;
        foreach ($itemIds as $itemId) {
            if ($this->repository->delete($itemId)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     */
    protected function prepareIngredientsForSync(array $ingredients): array
    {
        $prepared = [];

        foreach ($ingredients as $ingredient) {
            $ingredientId = $ingredient['ingredient_id'];
            unset($ingredient['ingredient_id']);

            $prepared[$ingredientId] = $ingredient;
        }

        return $prepared;
    }

    /**
     */
    protected function prepareBoxesForSync(array $boxes): array
    {
        $prepared = [];

        foreach ($boxes as $box) {
            $boxId = $box['box_id'];
            unset($box['box_id']);

            $prepared[$boxId] = $box;
        }

        return $prepared;
    }
}
