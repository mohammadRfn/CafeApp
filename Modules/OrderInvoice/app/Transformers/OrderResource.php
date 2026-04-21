<?php

namespace Modules\OrderInvoice\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order Resource
 * 
 * تبدیل Order Model به JSON Response
 */
class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Pricing
            'subtotal' => (float) $this->subtotal,
            'discount_percent' => $this->discount_percent ? (float) $this->discount_percent : null,
            'discount_amount' => (float) $this->discount_amount,
            'tax_percent' => $this->tax_percent ? (float) $this->tax_percent : null,
            'tax_amount' => (float) $this->tax_amount,
            'delivery_fee' => (float) $this->delivery_fee,
            'total_amount' => (float) $this->total_amount,

            // Metadata
            'notes' => $this->notes,
            'refund_type' => $this->refund_type,
            'refund_reason' => $this->refund_reason,

            // Counts
            'items_count' => $this->whenLoaded('items', fn() => $this->items->count()),
            'total_quantity' => $this->whenLoaded('items', fn() => $this->items->sum('quantity')),

            // Relationships
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            // Computed
            'is_editable' => (bool) $this->is_editable,
            'is_confirmable' => (bool) $this->is_confirmable,
            'is_cancellable' => (bool) $this->is_cancellable,
            'is_refundable' => (bool) $this->is_refundable,

            // Timestamps
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}