<?php

namespace Modules\ItemMaker\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Item Cost History Resource
 * 
 * تبدیل Cost History به JSON
 */
class ItemCostHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            
            // Cost breakdown
            'ingredients_cost' => (float) $this->ingredients_cost,
            'boxes_cost' => (float) $this->boxes_cost,
            'overhead_cost' => (float) $this->overhead_cost,
            'total_cost' => (float) ($this->total_cost ?? $this->total_cost_manual),
            
            // Pricing
            'suggested_sell_price' => $this->suggested_sell_price ? (float) $this->suggested_sell_price : null,
            'profit_margin' => $this->profit_margin ? (float) $this->profit_margin : null,
            
            // Metadata
            'calculation_method' => $this->calculation_method,
            'breakdown_details' => $this->breakdown_details,
            'notes' => $this->notes,
            
            // Validity
            'valid_from' => $this->valid_from->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'is_current' => (bool) $this->is_current,
            
            // Audit
            'calculated_by' => $this->calculated_by,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}