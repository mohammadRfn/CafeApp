<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BoxStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:1', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'مقدار رزرو الزامی است',
            'quantity.numeric' => 'مقدار باید عدد باشد',
            'quantity.min' => 'حداقل ۰.۰۰۱ قابل رزرو است',
            'quantity.max' => 'حداکثر ۹۹۹۹۹۹ قابل رزرو است',
        ];
    }

    public function quantity(): float
    {
        return (int) $this->quantity;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
