<?php

namespace Modules\OrderInvoice\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create Order Request
 * 
 * اعتبارسنجی ایجاد سفارش
 */
class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'notes' => 'یادداشت',
            'discount_percent' => 'درصد تخفیف',
            'tax_percent' => 'درصد مالیات',
            'delivery_fee' => 'هزینه ارسال',
             'items' => 'آیتم‌های سفارش',
            'items.*.item_id' => 'شناسه محصول',
            'items.*.quantity' => 'تعداد',
            'items.*.notes' => 'یادداشت آیتم',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'discount_percent.min' => 'درصد تخفیف نمی‌تواند منفی باشد',
            'discount_percent.max' => 'درصد تخفیف نمی‌تواند بیشتر از 100 باشد',
            'tax_percent.min' => 'درصد مالیات نمی‌تواند منفی باشد',
            'tax_percent.max' => 'درصد مالیات نمی‌تواند بیشتر از 100 باشد',
            'delivery_fee.min' => 'هزینه ارسال نمی‌تواند منفی باشد',
            'items.required' => 'حداقل یک آیتم برای سفارش الزامی است',
            'items.array' => 'فرمت آیتم‌ها صحیح نیست',
            'items.min' => 'حداقل یک آیتم برای سفارش الزامی است',
            'items.*.item_id.required' => 'شناسه محصول الزامی است',
            'items.*.item_id.exists' => 'محصول مورد نظر یافت نشد',
            'items.*.quantity.required' => 'تعداد الزامی است',
            'items.*.quantity.min' => 'تعداد باید حداقل 1 باشد',
            'items.*.quantity.max' => 'تعداد نمی‌تواند بیشتر از 100 باشد',
        ];
    }
}