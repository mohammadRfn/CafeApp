<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'refund_type' => ['required', 'string', 'in:consumed,returned'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'refund_type' => 'نوع برگشت',
            'reason' => 'دلیل برگشت',
        ];
    }

    public function messages(): array
    {
        return [
            'refund_type.required' => 'نوع برگشت الزامی است',
            'refund_type.in' => 'نوع برگشت نامعتبر است',
        ];
    }
}