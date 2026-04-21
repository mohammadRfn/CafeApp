<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Order Request
 */
class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => 'یادداشت',
            'discount_percent' => 'درصد تخفیف',
            'tax_percent' => 'درصد مالیات',
            'delivery_fee' => 'هزینه ارسال',
        ];
    }
}