<?php

namespace Modules\OrderInvoice\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Inventory Usage Resource
 */
class InventoryUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            
            // Entity
            'entity_type' => $this->entity_type,
            'entity_type_label' => $this->entity_type_label,
            'entity_id' => $this->entity_id,
            
            // Entity details (if loaded)
            'entity' => $this->when($this->relationLoaded('ingredient') || $this->relationLoaded('box'), function() {
                if ($this->entity_type === 'ingredient' && $this->relationLoaded('ingredient')) {
                    return [
                        'id' => $this->ingredient->id,
                        'name' => $this->ingredient->ingredient_name,
                        'code' => $this->ingredient->ingredient_code,
                    ];
                }
                if ($this->entity_type === 'box' && $this->relationLoaded('box')) {
                    return [
                        'id' => $this->box->id,
                        'name' => $this->box->name,
                        'code' => $this->box->code,
                    ];
                }
                return null;
            }),
            
            // Usage
            'quantity_used' => (float) $this->quantity_used,
            'unit' => $this->unit,
            'usage_type' => $this->usage_type,
            'usage_type_label' => $this->usage_type_label,
            
            // Transaction
            'transaction_id' => $this->transaction_id,
            
            // Timestamp
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}