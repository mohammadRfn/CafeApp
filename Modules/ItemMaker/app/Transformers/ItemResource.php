<?php

namespace Modules\ItemMaker\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item Resource
 *
 * تبدیل Item Model به JSON Response
 */
class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $finalPrice = $this->actual_sell_price ?? $this->target_sell_price ?? 0;
        $targetCost = $this->target_cost ?? 0;

        $profitMargin = ($finalPrice > 0 && $targetCost > 0)
            ? round((($finalPrice - $targetCost) / $finalPrice) * 100, 2)
            : 0;

        $isProfitable = $targetCost > 0 && $finalPrice > $targetCost;
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,

            'category' => $this->category,
            'subcategory' => $this->subcategory,

            'target_cost' => (float) $this->target_cost,
            'target_sell_price' => (float) $this->target_sell_price,
            'actual_sell_price' => $this->actual_sell_price ? (float) $this->actual_sell_price : null,
            'final_sell_price' => (float) $this->final_sell_price,

            'profit_margin' => (float) $profitMargin,
            'is_profitable' => (bool) $isProfitable,

            'preparation_time' => (int) $this->preparation_time,
            'serving_size' => $this->serving_size ? (float) $this->serving_size : null,
            'serving_unit' => $this->serving_unit,

            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'requires_preparation' => (bool) $this->requires_preparation,
            'can_be_ordered' => (bool) $this->can_be_ordered,

            'daily_stock_limit' => $this->daily_stock_limit,
            'daily_sold_count' => (int) $this->daily_sold_count,
            'remaining_today' => $this->daily_stock_limit
                ? max(0, $this->daily_stock_limit - $this->daily_sold_count)
                : null,

            'calories' => $this->calories,
            'allergens' => $this->allergens,

            'image_url' => $this->image_url,
            'display_order' => (int) $this->display_order,

            'ingredients_count' => $this->whenLoaded('ingredients', fn () => $this->ingredients->count()),
            'boxes_count' => $this->whenLoaded('boxes', fn () => $this->boxes->count()),

            // 'current_cost' => new ItemCostHistoryResource($this->whenLoaded('currentCost')),
            'current_cost' => $this->whenLoaded('currentCost', function () {
                return $this->currentCost->isNotEmpty()
                    ? new ItemCostHistoryResource($this->currentCost->first())
                    : null;
            }),

            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'last_cost_calculated_at' => $this->last_cost_calculated_at?->toIso8601String(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
