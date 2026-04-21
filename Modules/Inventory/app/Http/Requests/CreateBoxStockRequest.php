<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBoxStockRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'box_id' => 'required|exists:boxes,id',
            'quantity' => 'required|integer|min:1|max:999999',
            'avg_cost_per_unit' => 'nullable|numeric|min:0'
        ];
    }

    public function messages(): array
    {
        return [
            'box_id.exists' => 'باکس وجود ندارد',
            'quantity.required' => 'مقدار موجودی الزامی است',
            'quantity.min' => 'مقدار موجودی باید مثبت باشد'
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
