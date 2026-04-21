<?php
namespace Modules\Inventory\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->resource->ingredient_name,
            'stock_status' => $this->stock->status ?? 'unknown',
            'available_grams' => (float) ($this->stock->available_grams ?? 0),
            'min_stock' => (float) $this->min_stock,
            'reorder_point' => (float) $this->reorder_point,
            'category' => $this->category?->name
        ];
    }
}
