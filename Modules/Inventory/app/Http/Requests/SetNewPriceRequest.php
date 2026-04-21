<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetNewPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'box_id' => ['nullable', 'integer', 'exists:boxes,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'buy_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'gt:buy_price']
        ];
    }
}
