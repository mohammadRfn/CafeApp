<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
         'ingredient_id' => 'required|exists:ingredients,id',
         'quantity_grams' => 'required|numeric|min:0.001|max:999999999',
         'avg_cost_per_gram' => 'nullable|numeric|min:0|max:999999'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
