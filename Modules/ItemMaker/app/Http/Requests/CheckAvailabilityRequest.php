<?php

namespace Modules\ItemMaker\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Check Availability Request
 * 
 * درخواست بررسی موجودی برای تولید
 */
class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'quantity' => 'تعداد',
        ];
    }
}