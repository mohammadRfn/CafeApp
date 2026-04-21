<?php

namespace Modules\OrderInvoice\app\Repositories;

use Modules\OrderInvoice\app\Interfaces\OrderItemRepositoryInterface;
use Modules\OrderInvoice\app\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderItemRepository implements OrderItemRepositoryInterface
{
    protected int $cacheTTL = 1800;

    public function getOrderItems(int $orderId): Collection
    {
        $cacheKey = "order_items:order:{$orderId}";
        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($orderId) {
            return OrderItem::where('order_id', $orderId)->with('item')->orderBy('id')->get();
        });
    }

    public function findById(int $id): ?OrderItem
    {
        $cacheKey = "order_item:{$id}";
        return Cache::remember($cacheKey, $this->cacheTTL, function () use ($id) {
            return OrderItem::with('item')->find($id);
        });
    }

    public function create(array $data): OrderItem
    {
        $orderItem = OrderItem::create($data);
        $this->clearCache($orderItem->order_id, $orderItem->id);
        return $orderItem->fresh('item');
    }

    public function update(int $id, array $data): OrderItem
    {
        $orderItem = OrderItem::findOrFail($id);
        $orderItem->update($data);
        $this->clearCache($orderItem->order_id, $id);
        return $orderItem->fresh('item');
    }

    public function delete(int $id): bool
    {
        $orderItem = OrderItem::find($id);
        if (!$orderItem) return false;
        $orderId = $orderItem->order_id;
        $result = $orderItem->delete();
        $this->clearCache($orderId, $id);
        return $result;
    }

    public function updateQuantity(int $id, int $quantity): OrderItem
    {
        return DB::transaction(function () use ($id, $quantity) {
            $orderItem = OrderItem::lockForUpdate()->findOrFail($id);
            $orderItem->quantity = $quantity;
            $orderItem->calculateTotal();
            $orderItem->save();
            $this->clearCache($orderItem->order_id, $id);
            return $orderItem->fresh('item');
        });
    }

    public function itemExistsInOrder(int $orderId, int $itemId): bool
    {
        return OrderItem::where('order_id', $orderId)->where('item_id', $itemId)->exists();
    }

    public function getOrderItem(int $orderId, int $itemId): ?OrderItem
    {
        return OrderItem::where('order_id', $orderId)->where('item_id', $itemId)->with('item')->first();
    }

    protected function clearCache(int $orderId, int $itemId): void
    {
        Cache::forget("order_item:{$itemId}");
        Cache::forget("order_items:order:{$orderId}");
        Cache::forget("order:{$orderId}");
    }
}