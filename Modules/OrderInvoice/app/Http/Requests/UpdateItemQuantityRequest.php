<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Update Item Quantity Request
 */
class UpdateItemQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'quantity' => 'تعداد',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'تعداد الزامی است',
            'quantity.min' => 'تعداد حداقل باید 1 باشد',
            'quantity.max' => 'تعداد حداکثر می‌تواند 100 باشد',
        ];
    }
}