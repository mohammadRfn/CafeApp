<?php

namespace Modules\OrderInvoice\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OrderItem Resource
 */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'item_id' => $this->item_id,

            // From snapshot
            'item_name' => $this->item_name,
            'item_code' => $this->item_code,

            // Pricing
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_price' => (float) $this->total_price,

            // Notes
            'notes' => $this->notes,

            // Snapshot (optional, heavy)
            'recipe' => $this->when($request->input('include_recipe'), $this->recipe),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}