<?php

namespace Modules\ItemMaker\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Update Item Request
 * 
 */
class UpdateItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     */
    public function rules(): array
    {
        $itemId = $this->route('id'); 

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('items', 'code')->ignore($itemId)],
            'description' => ['nullable', 'string', 'max:5000'],
            
            'category' => ['nullable', 'string', 'max:50'],
            'subcategory' => ['nullable', 'string', 'max:50'],
            
            'target_sell_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'actual_sell_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            
            'preparation_time' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'serving_size' => ['nullable', 'numeric', 'min:0'],
            'serving_unit' => ['nullable', 'string', 'max:20'],
            
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'requires_preparation' => ['nullable', 'boolean'],
            
            'daily_stock_limit' => ['nullable', 'integer', 'min:0'],
            
            'calories' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', 'max:50'],
            
            'image_url' => ['nullable', 'string', 'max:500', 'url'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            
            // ═══════════════════════════════════════════════════════
            // ═══════════════════════════════════════════════════════
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'ingredients.*.required_grams' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'ingredients.*.waste_factor' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'ingredients.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.is_optional' => ['nullable', 'boolean'],
            'ingredients.*.is_customizable' => ['nullable', 'boolean'],
            'ingredients.*.preparation_note' => ['nullable', 'string', 'max:255'],
            'ingredients.*.order' => ['nullable', 'integer', 'min:0'],
            
            // ═══════════════════════════════════════════════════════
            // ═══════════════════════════════════════════════════════
            'boxes' => ['nullable', 'array'],
            'boxes.*.box_id' => ['required', 'integer', 'exists:boxes,id'],
            'boxes.*.required_quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'boxes.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'boxes.*.is_default_packaging' => ['nullable', 'boolean'],
            'boxes.*.is_optional' => ['nullable', 'boolean'],
            'boxes.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     */
    public function attributes(): array
    {
        return [
            'name' => 'نام محصول',
            'code' => 'کد محصول',
            'description' => 'توضیحات',
            'category' => 'دسته‌بندی',
            'subcategory' => 'زیردسته',
            'target_sell_price' => 'قیمت فروش هدف',
            'actual_sell_price' => 'قیمت فروش واقعی',
            'preparation_time' => 'زمان آماده‌سازی',
            'serving_size' => 'سایز سرو',
            'serving_unit' => 'واحد سرو',
            'is_active' => 'وضعیت فعال',
            'is_featured' => 'محصول ویژه',
            'requires_preparation' => 'نیاز به آماده‌سازی',
            'daily_stock_limit' => 'محدودیت روزانه',
            'calories' => 'کالری',
            'allergens' => 'آلرژن‌ها',
            'image_url' => 'آدرس تصویر',
            'display_order' => 'ترتیب نمایش',
            'ingredients' => 'مواد اولیه',
            'boxes' => 'بسته‌بندی',
        ];
    }

    /**
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'این کد محصول قبلاً استفاده شده است',
            'ingredients.*.ingredient_id.exists' => 'ماده اولیه انتخاب شده معتبر نیست',
            'boxes.*.box_id.exists' => 'بسته‌بندی انتخاب شده معتبر نیست',
        ];
    }

    /**
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('ingredients')) {
            $ingredients = $this->input('ingredients', []);
            foreach ($ingredients as $key => $ingredient) {
                if (!isset($ingredient['waste_factor'])) {
                    $ingredients[$key]['waste_factor'] = 0;
                }
                if (!isset($ingredient['order'])) {
                    $ingredients[$key]['order'] = $key;
                }
            }
            $this->merge(['ingredients' => $ingredients]);
        }

        if ($this->has('boxes')) {
            $boxes = $this->input('boxes', []);
            foreach ($boxes as $key => $box) {
                if (!isset($box['is_optional'])) {
                    $boxes[$key]['is_optional'] = false;
                }
            }
            $this->merge(['boxes' => $boxes]);
        }
    }
}