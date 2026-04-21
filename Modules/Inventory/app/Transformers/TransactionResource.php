<?php

namespace Modules\Inventory\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entityType = $this->entity_type ?? $request->entity_type;

        return [
            'id' => $this->id,
            'entity_type' => $entityType,
            'entity_id' => $this->ingredient_id ?? $this->box_id,
            'entity_name' => match($entityType) {
                'ingredient' => $this->ingredient_name,
                'box' => $this->entity_name,
                default => 'N/A'
            },

            'entity_code' => match($entityType) {
                'ingredient' => $this->ingredient_code,
                'box' => $this->entity_code,
                default => 'N/A'
            },
            'transaction_type' => $this->transaction_type,
            'input_quantity' => (float) $this->input_quantity,
            'effect' => (float) ($this->grams_effect ?? $this->quantity_effect),
            'effect_field' => $this->grams_effect ? 'grams_effect' : 'quantity_effect',
            'total_cost' => (float) ($this->total_cost ?? 0),
            'batch_number' => $this->batch_number,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'invoice_number' => $this->invoice_number,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at ? \Carbon\Carbon::parse($this->created_at)->format('Y-m-d H:i:s') : null,
            'created_by' => $this->created_by
        ];
    }
}
