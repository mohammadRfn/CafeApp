<?php

namespace Modules\ItemMaker\app\Repositories;

use Modules\ItemMaker\app\Interfaces\ItemRepositoryInterface;
use Modules\ItemMaker\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Item Repository
 * 
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * مدت زمان کش (ثانیه)
     */
    protected int $cacheTTL = 1800; // 30 minutes

    /**
     */
    public function getAll(array $filters = [], array $with = []): Collection
    {
        $query = Item::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     */
    public function paginate(int $perPage = 15, array $filters = [], array $with = []): LengthAwarePaginator
    {
        $query = Item::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     */
    public function findById(int $id, array $with = []): ?Item
    {
        $cacheKey = "item:{$id}:" . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($id, $with) {
            $query = Item::query();

            if (!empty($with)) {
                $query->with($with);
            }

            return $query->find($id);
        });
    }

    /**
     */
    public function findByCode(string $code, array $with = []): ?Item
    {
        $cacheKey = "item:code:{$code}:" . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($code, $with) {
            $query = Item::query();

            if (!empty($with)) {
                $query->with($with);
            }

            return $query->where('code', $code)->first();
        });
    }

    /**
     */
    public function create(array $data): Item
    {
        $item = Item::create($data);
        
        $this->clearCache();
        
        return $item->fresh();
    }

    /**
     */
    public function update(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);
        $item->update($data);
        
        $this->clearItemCache($id);
        
        return $item->fresh();
    }

    /**
     */
    public function delete(int $id): bool
    {
        $item = Item::findOrFail($id);
        $result = $item->delete();
        
        $this->clearItemCache($id);
        
        return $result;
    }

    /**
     */
    public function restore(int $id): bool
    {
        $item = Item::withTrashed()->findOrFail($id);
        $result = $item->restore();
        
        $this->clearItemCache($id);
        
        return $result;
    }

    /**
     */
    public function forceDelete(int $id): bool
    {
        $item = Item::withTrashed()->findOrFail($id);
        $result = $item->forceDelete();
        
        $this->clearItemCache($id);
        
        return $result;
    }

    /**
     */
    public function getActive(array $with = []): Collection
    {
        $cacheKey = 'items:active:' . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($with) {
            return Item::active()
                ->when(!empty($with), fn($q) => $q->with($with))
                ->ordered()
                ->get();
        });
    }

    /**
     */
    public function getFeatured(array $with = []): Collection
    {
        $cacheKey = 'items:featured:' . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($with) {
            return Item::active()
                ->featured()
                ->when(!empty($with), fn($q) => $q->with($with))
                ->ordered()
                ->get();
        });
    }

    /**
     */
    public function getByCategory(string $category, array $with = []): Collection
    {
        $cacheKey = "items:category:{$category}:" . md5(json_encode($with));

        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($category, $with) {
            return Item::active()
                ->byCategory($category)
                ->when(!empty($with), fn($q) => $q->with($with))
                ->ordered()
                ->get();
        });
    }

    /**
     */
    public function search(string $search, array $filters = [], array $with = []): Collection
    {
        $query = Item::query();

        if (!empty($with)) {
            $query->with($with);
        }

        $query->search($search);
        
        $this->applyFilters($query, $filters);

        return $query->ordered()->get();
    }

    /**
     */
    public function getAvailableToday(array $with = []): Collection
    {
        return Item::active()
            ->availableToday()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->ordered()
            ->get();
    }

    /**
     */
    public function getSoldOut(array $with = []): Collection
    {
        return Item::active()
            ->soldOut()
            ->when(!empty($with), fn($q) => $q->with($with))
            ->ordered()
            ->get();
    }

    /**
     */
    public function attachIngredient(int $itemId, int $ingredientId, array $pivotData): bool
    {
        $item = Item::findOrFail($itemId);
        $item->ingredients()->attach($ingredientId, $pivotData);
        
        $this->clearItemCache($itemId);
        
        return true;
    }

    /**
     */
    public function detachIngredient(int $itemId, int $ingredientId): bool
    {
        $item = Item::findOrFail($itemId);
        $item->ingredients()->detach($ingredientId);
        
        $this->clearItemCache($itemId);
        
        return true;
    }

    /**
     */
    public function updateIngredient(int $itemId, int $ingredientId, array $pivotData): bool
    {
        $item = Item::findOrFail($itemId);
        $item->ingredients()->updateExistingPivot($ingredientId, $pivotData);
        
        $this->clearItemCache($itemId);
        
        return true;
    }

    /**
     */
    public function syncIngredients(int $itemId, array $ingredients): array
    {
        $item = Item::with('ingredients.currentPrice')->findOrFail($itemId);
    
        $ingredientsWithCosts = [];
    
        foreach ($ingredients as $ingredientId => $data) {
        if (!isset($data['unit_cost'])) {
            $ingredient = \Modules\Inventory\Models\Ingredient::with('currentPrice')
                ->findOrFail($ingredientId);
            $data['unit_cost'] = $ingredient->getLatestPricePerGram();
        }
        
        $requiredGrams = $data['required_grams'];
        $wasteFactor = $data['waste_factor'] ?? 0;
        $actualGrams = $requiredGrams * (1 + $wasteFactor);
        
        $data['total_cost'] = $actualGrams * $data['unit_cost'];
        
        $ingredientsWithCosts[$ingredientId] = $data;
        }
    
        $result = $item->ingredients()->sync($ingredientsWithCosts);
    
        $this->clearItemCache($itemId);
    
        return $result;
    }

    /**
     */
    public function attachBox(int $itemId, int $boxId, array $pivotData): bool
    {
        $item = Item::findOrFail($itemId);
        $item->boxes()->attach($boxId, $pivotData);
        
        $this->clearItemCache($itemId);
        
        return true;
    }

    /**
     */
    public function detachBox(int $itemId, int $boxId): bool
    {
        $item = Item::findOrFail($itemId);
        $item->boxes()->detach($boxId);
        
        $this->clearItemCache($itemId);
        
        return true;
    }

    /**
     */
    public function updateBox(int $itemId, int $boxId, array $pivotData): bool
    {
        $item = Item::findOrFail($itemId);
        $item->boxes()->updateExistingPivot($boxId, $pivotData);
        
        $this->clearItemCache($itemId);
        
        return true;
    }

    /**
     */
    public function syncBoxes(int $itemId, array $boxes): array
    {
        $item = Item::with('boxes.currentPrice')->findOrFail($itemId);
    
        $boxesWithCosts = [];
    
        foreach ($boxes as $boxId => $data) {
        if (!isset($data['unit_cost'])) {
            $box = \Modules\Inventory\Models\Box::with('currentPrice')
                ->findOrFail($boxId);
            $data['unit_cost'] = $box->getLatestUnitPrice();
        }
        
        $requiredQuantity = $data['required_quantity'];
        $data['total_cost'] = $requiredQuantity * $data['unit_cost'];
        
        $boxesWithCosts[$boxId] = $data;
        }
    
        $result = $item->boxes()->sync($boxesWithCosts);
    
        $this->clearItemCache($itemId);
    
        return $result;
    }

    /**
     */
    public function incrementDailySold(int $itemId, int $quantity = 1): bool
    {
        $item = Item::findOrFail($itemId);
        $result = $item->incrementDailySold($quantity);
        
        $this->clearItemCache($itemId);
        
        return $result;
    }

    /**
     */
    public function decrementDailySold(int $itemId, int $quantity = 1): bool
    {
        $item = Item::findOrFail($itemId);
        $result = $item->decrementDailySold($quantity);
        
        $this->clearItemCache($itemId);
        
        return $result;
    }

    /**
     */
    public function resetDailySoldCount(?int $itemId = null): int
    {
        if ($itemId) {
            $item = Item::findOrFail($itemId);
            $item->resetDailySoldCount();
            $this->clearItemCache($itemId);
            return 1;
        }

        $count = Item::withDailyLimit()->update(['daily_sold_count' => 0]);
        $this->clearCache();
        
        return $count;
    }

    /**
     */
    public function updateActiveStatus(int $itemId, bool $isActive): bool
    {
        $item = Item::findOrFail($itemId);
        $result = $item->update(['is_active' => $isActive]);
        
        $this->clearItemCache($itemId);
        
        return $result;
    }

    /**
     */
    public function updateSellPrice(int $itemId, float $price): bool
    {
        $item = Item::findOrFail($itemId);
        $result = $item->update(['actual_sell_price' => $price]);
        
        $this->clearItemCache($itemId);
        
        return $result;
    }

    /**
     */
    public function getStatistics(): array
    {
        return Cache::remember('items:statistics', 600, function () {
            return [
                'total' => Item::count(),
                'active' => Item::active()->count(),
                'inactive' => Item::inactive()->count(),
                'featured' => Item::featured()->count(),
                'with_daily_limit' => Item::withDailyLimit()->count(),
                'sold_out_today' => Item::soldOut()->count(),
                'categories' => Item::select('category')
                    ->distinct()
                    ->whereNotNull('category')
                    ->pluck('category')
                    ->count(),
            ];
        });
    }

    /**
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = Item::where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     */
    protected function applyFilters($query, array $filters): void
    {
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['subcategory'])) {
            $query->where('subcategory', $filters['subcategory']);
        }

        if (isset($filters['requires_preparation'])) {
            $query->where('requires_preparation', $filters['requires_preparation']);
        }

        if (isset($filters['available_today']) && $filters['available_today']) {
            $query->availableToday();
        }

        if (isset($filters['min_price'])) {
            $query->where('actual_sell_price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('actual_sell_price', '<=', $filters['max_price']);
        }
    }

    /**
     */
    protected function clearItemCache(int $itemId): void
    {
        Cache::forget("item:{$itemId}");
        $this->clearCache();
    }

    /**
     */
    protected function clearCache(): void
    {
        Cache::forget('items:active');
        Cache::forget('items:featured');
        Cache::forget('items:statistics');
        
        // Clear category caches (if needed, can be more specific)
        // This is a simple approach, for production consider cache tags
    }
}
