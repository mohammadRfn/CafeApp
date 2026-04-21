<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Add Order Item Request
 * 
 * اعتبارسنجی افزودن آیتم به سفارش
 */
class AddOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'item_id' => 'محصول',
            'quantity' => 'تعداد',
            'notes' => 'یادداشت',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => 'انتخاب محصول الزامی است',
            'item_id.exists' => 'محصول انتخابی معتبر نیست',
            'quantity.required' => 'تعداد الزامی است',
            'quantity.min' => 'تعداد حداقل باید 1 باشد',
            'quantity.max' => 'تعداد حداکثر می‌تواند 100 باشد',
        ];
    }
}