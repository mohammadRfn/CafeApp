<?php

namespace Modules\Inventory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoxStockStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'box_id' => $this->box_id,
            'box_name' => $this->box->name ?? 'نامشخص',
            'box_code' => $this->box->code ?? 'N/A',
            'total_quantity' => (float) $this->quantity,
            'reserved_quantity' => (float) $this->reserved_quantity,
            'available_quantity' => (float) ($this->quantity - $this->reserved_quantity),
            'avg_cost_per_unit' => (float) optional($this->box)->target_sell_price ?? 0,
            'total_value' => (float) ($this->quantity * (optional($this->box)->target_sell_price ?? 0)),
            'last_updated' => $this->updated_at ?: 'N/A',
        ];
    }
}
