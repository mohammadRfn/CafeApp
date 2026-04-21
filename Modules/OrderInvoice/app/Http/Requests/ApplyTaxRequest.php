<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Apply Tax Request
 */
class ApplyTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tax_percent' => 'درصد مالیات',
        ];
    }

    public function messages(): array
    {
        return [
            'tax_percent.required' => 'درصد مالیات الزامی است',
            'tax_percent.min' => 'درصد مالیات نمی‌تواند منفی باشد',
            'tax_percent.max' => 'درصد مالیات نمی‌تواند بیشتر از 100 باشد',
        ];
    }
}