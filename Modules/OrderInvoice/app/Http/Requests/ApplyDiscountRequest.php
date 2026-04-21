<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'discount_percent' => 'درصد تخفیف',
        ];
    }

    public function messages(): array
    {
        return [
            'discount_percent.required' => 'درصد تخفیف الزامی است',
            'discount_percent.min' => 'درصد تخفیف نمی‌تواند منفی باشد',
            'discount_percent.max' => 'درصد تخفیف نمی‌تواند بیشتر از 100 باشد',
        ];
    }
}