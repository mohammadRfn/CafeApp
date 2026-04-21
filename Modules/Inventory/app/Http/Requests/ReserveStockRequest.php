<?php
namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'grams' => ['required', 'numeric', 'min:0.001'],
            'quantity' => ['nullable', 'integer', 'min:1']
        ];
    }
}
