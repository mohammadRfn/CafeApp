<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Set Delivery Fee Request
 */
class SetDeliveryFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'delivery_fee' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'delivery_fee' => 'هزینه ارسال',
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_fee.required' => 'هزینه ارسال الزامی است',
            'delivery_fee.min' => 'هزینه ارسال نمی‌تواند منفی باشد',
        ];
    }
}