<?php

namespace Modules\OrderInvoice\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice Resource
 */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'invoice_number' => $this->invoice_number,

            // Pricing
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'delivery_fee' => (float) $this->delivery_fee,
            'total_amount' => (float) $this->total_amount,

            // Payment
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'payment_status' => $this->payment_status,
            'payment_status_label' => $this->payment_status_label,

            // Computed
            'is_paid' => (bool) $this->is_paid,
            'is_refunded' => (bool) $this->is_refunded,

            // Notes
            'notes' => $this->notes,

            // Relationships
            'order' => new OrderResource($this->whenLoaded('order')),
            'creator' => $this->whenLoaded('creator', fn() => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),

            // Timestamps
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}