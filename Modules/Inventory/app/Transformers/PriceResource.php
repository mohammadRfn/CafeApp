<?php

namespace Modules\Inventory\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
        'ingredient' => [
            'id' => $this->ingredient_id,
            'name' => $this->whenLoaded('ingredient', fn () => $this->ingredient->ingredient_name ?? 'N/A'),
            'code' => $this->whenLoaded('ingredient', fn () => $this->ingredient->ingredient_code ?? 'N/A')
        ],
        'unit' => [
            'id' => $this->unit_id,
            'name' => $this->whenLoaded('unit', fn () => $this->unit->name ?? 'N/A')
        ],
        'box' => $this->whenLoaded('box', fn () => [
            'id' => $this->box_id,
            'name' => $this->whenLoaded('box', fn () => $this->box->name ?? 'N/A'),
        ]),
            'buy_price' => (float) $this->buy_price,
            'sell_price' => (float) $this->sell_price,
            'margin_percent' => $this->buy_price > 0 ?
                (($this->sell_price - $this->buy_price) / $this->buy_price) * 100 : 0,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
        ];
    }
}
