<?php

namespace Modules\ItemMaker\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item Ingredient Resource
 *
 * تبدیل Ingredient در Recipe به JSON
 */
class ItemIngredientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'ingredient_id' => $this->id,
            'ingredient_name' => $this->ingredient_name,
            'ingredient_code' => $this->ingredient_code,

            // Recipe details
            'required_grams' => (float) $this->pivot->required_grams,
            'waste_factor' => (float) $this->pivot->waste_factor,
            'actual_grams' => (float) $this->pivot->actual_grams,

            // Cost
            'unit_cost' => (float) $this->pivot->unit_cost,
            'total_cost' => (float) $this->pivot->total_cost,

            // Metadata
            'is_optional' => (bool) $this->pivot->is_optional,
            'is_customizable' => (bool) $this->pivot->is_customizable,
            'preparation_note' => $this->pivot->preparation_note,
            'order' => (int) $this->pivot->order,

            // Stock info (if loaded)
            'current_stock' => $this->when($this->relationLoaded('stock'), function () {
                $stock = $this->stock;
                if ($stock && $stock->isNotEmpty()) {
                    $stock = $stock->first();
                }
                return $stock ? [
                    'quantity_grams' => (float) $stock->quantity_grams,
                    'available_grams' => (float) $stock->available_grams,
                    'reserved_grams' => (float) $stock->reserved_grams,
                ] : null;
            }),
        ];
    }
}
