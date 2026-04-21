<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // 'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'grams' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000']
        ];
    }

    public function messages(): array
    {
        return [
            'ingredient_id.exists' => 'مواد مورد نظر وجود ندارد.',
            'grams.min' => 'مقدار رزرو باید بیشتر از صفر باشد.',
            'quantity.min' => 'تعداد باید حداقل 1 باشد.'
        ];
    }
}
