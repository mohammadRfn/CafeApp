<?php

namespace Modules\ItemMaker\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item Box Resource
 * 
 * تبدیل Box در Item به JSON
 */
class ItemBoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'box_id' => $this->id,
            'box_name' => $this->name,
            'box_code' => $this->code,
            
            // Packaging details
            'required_quantity' => (int) $this->pivot->required_quantity,
            
            // Cost
            'unit_cost' => (float) $this->pivot->unit_cost,
            'total_cost' => (float) $this->pivot->total_cost,
            
            // Metadata
            'is_default_packaging' => (bool) $this->pivot->is_default_packaging,
            'is_optional' => (bool) $this->pivot->is_optional,
            'note' => $this->pivot->note,
            
            // Stock info (if loaded)
            'current_stock' => $this->when($this->relationLoaded('stock'), function () {
                return [
                    'quantity' => (int) $this->stock->quantity,
                    'available_quantity' => (int) $this->stock->available_quantity,
                    'reserved_quantity' => (int) $this->stock->reserved_quantity,
                ];
            }),
        ];
    }
}