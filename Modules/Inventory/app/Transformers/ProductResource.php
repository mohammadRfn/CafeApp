<?php

namespace Modules\Inventory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingredient_name' => $this->ingredient_name,
            'ingredient_code' => $this->ingredient_code,
            'is_active' => (bool) $this->is_active,
            'reorder_point' => (float) ($this->reorder_point ?? 0),
            'created_at' => $this->created_at?->toDateTimeString(),
            'has_stock' => $this->whenLoaded('stock', $this->relationLoaded('stock'))
        ];
    }
}
