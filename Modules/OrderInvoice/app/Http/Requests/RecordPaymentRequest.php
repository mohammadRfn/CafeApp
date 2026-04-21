<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'in:cash,card,online'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_method' => 'روش پرداخت',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'روش پرداخت الزامی است',
            'payment_method.in' => 'روش پرداخت نامعتبر است',
        ];
    }
}