<?php
namespace Modules\Inventory\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ingredient_id' => $this->ingredient_id,
            'ingredient_name' => $this->whenLoaded('ingredient', $this->ingredient->ingredient_name),
            'quantity_grams' => (float) $this->quantity_grams,
            'available_grams' => (float) $this->available_grams,
            'reserved_grams' => (float) $this->reserved_grams,
            'status' => $this->status ?? 'unknown',
            'reorder_point' => (float) ($this->ingredient->reorder_point ?? 0),
            'avg_cost_per_gram' => (float) ($this->avg_cost_per_gram ?? 0),
            'total_value' => (float) ($this->quantity_grams * $this->avg_cost_per_gram)
        ];
    }
}
