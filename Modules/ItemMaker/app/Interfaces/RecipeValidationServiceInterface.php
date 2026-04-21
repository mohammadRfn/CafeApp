<?php

namespace Modules\ItemMaker\app\Interfaces;

use Modules\ItemMaker\Models\Item;

/**
 * Recipe Validation Service Interface
 * 
 * قرارداد سرویس اعتبارسنجی recipe محصولات
 */
interface RecipeValidationServiceInterface
{
    /**
     * اعتبارسنجی کامل recipe
     * 
     * @param array $ingredients
     * @param array $boxes
     * @return array ['valid' => bool, 'errors' => array, 'warnings' => array]
     */
    public function validateRecipe(array $ingredients, array $boxes = []): array;

    /**
     * بررسی موجودی مواد اولیه
     * 
     * @param int $itemId
     * @param int $quantity
     * @return array ['available' => bool, 'shortages' => array]
     */
    public function checkIngredientsAvailability(int $itemId, int $quantity = 1): array;

    /**
     * بررسی موجودی بسته‌بندی
     * 
     * @param int $itemId
     * @param int $quantity
     * @return array ['available' => bool, 'shortages' => array]
     */
    public function checkBoxesAvailability(int $itemId, int $quantity = 1): array;

    /**
     * بررسی کامل موجودی (ingredients + boxes)
     * 
     * @param int $itemId
     * @param int $quantity
     * @return array ['available' => bool, 'ingredients' => array, 'boxes' => array]
     */
    public function checkFullAvailability(int $itemId, int $quantity = 1): array;

    /**
     * اعتبارسنجی مقادیر ingredient
     * 
     * @param array $ingredientData
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateIngredientData(array $ingredientData): array;

    /**
     * اعتبارسنجی مقادیر box
     * 
     * @param array $boxData
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateBoxData(array $boxData): array;

    /**
     * بررسی تداخل ingredients (duplicate check)
     * 
     * @param array $ingredients
     * @return array ['has_duplicates' => bool, 'duplicates' => array]
     */
    public function checkDuplicateIngredients(array $ingredients): array;

    /**
     * بررسی تداخل boxes (duplicate check)
     * 
     * @param array $boxes
     * @return array ['has_duplicates' => bool, 'duplicates' => array]
     */
    public function checkDuplicateBoxes(array $boxes): array;

    /**
     * اعتبارسنجی waste_factor
     * 
     * @param float $wasteFactor
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateWasteFactor(float $wasteFactor): array;

    /**
     * بررسی حداقل مواد اولیه برای recipe
     * 
     * @param array $ingredients
     * @param int $minRequired
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function checkMinimumIngredients(array $ingredients, int $minRequired = 1): array;

    /**
     * محاسبه و اعتبارسنجی total_cost
     * 
     * @param array $ingredients
     * @param array $boxes
     * @return array ['valid' => bool, 'total_cost' => float, 'warnings' => array]
     */
    public function validateTotalCost(array $ingredients, array $boxes): array;

    /**
     * بررسی سازگاری قیمت‌ها
     * 
     * @param float $cost
     * @param float|null $sellPrice
     * @return array ['valid' => bool, 'warning' => string|null]
     */
    public function validatePricing(float $cost, ?float $sellPrice): array;

    /**
     * اعتبارسنجی ingredient برای استفاده در recipe
     * 
     * @param int $ingredientId
     * @return array ['valid' => bool, 'error' => string|null, 'ingredient' => object|null]
     */
    public function validateIngredientForRecipe(int $ingredientId): array;

    /**
     * اعتبارسنجی box برای استفاده در item
     * 
     * @param int $boxId
     * @return array ['valid' => bool, 'error' => string|null, 'box' => object|null]
     */
    public function validateBoxForItem(int $boxId): array;

    /**
     * بررسی امکان تولید با موجودی فعلی
     * 
     * @param int $itemId
     * @param int $requestedQuantity
     * @return array ['possible' => bool, 'max_quantity' => int, 'limiting_factor' => string|null]
     */
    public function calculateMaxProducibleQuantity(int $itemId, int $requestedQuantity): array;

    /**
     * دریافت هشدارها برای recipe
     * 
     * @param Item $item
     * @return array
     */
    public function getRecipeWarnings(Item $item): array;
}
