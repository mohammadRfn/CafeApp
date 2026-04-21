<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBoxRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:boxes,code',
            'total_weight_grams' => 'nullable|numeric|min:0|max:999999',
            'target_sell_price' => 'nullable|numeric|min:0|max:99999999'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'نام باکس الزامی است',
            'code.unique' => 'کد باکس تکراری است',
            'code.required' => 'کد باکس الزامی است'
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
