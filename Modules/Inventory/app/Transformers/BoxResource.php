<?php

namespace Modules\Inventory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'total_weight_grams' => (float) $this->total_weight_grams,
            'target_sell_price' => (float) $this->target_sell_price,
            'is_active' => (bool) $this->is_active,
            'stock_quantity' => $this->stock?->quantity ?? 0,
            'stock_reserved' => $this->stock?->reserved_quantity ?? 0,
            // 'available_stock' => ($this->stock?->quantity ?? 0) - ($this->stock?->reserved_quantity ?? 0),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }
}
