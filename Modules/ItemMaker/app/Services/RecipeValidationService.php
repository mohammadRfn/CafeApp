<?php

namespace Modules\ItemMaker\app\Services;

use Modules\ItemMaker\app\Interfaces\RecipeValidationServiceInterface;
use Modules\ItemMaker\Models\Item;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Box;

/**
 * Recipe Validation Service
 *
 * سرویس اعتبارسنجی recipe و موجودی
 */
class RecipeValidationService implements RecipeValidationServiceInterface
{
    /**
     * اعتبارسنجی کامل recipe
     */
    public function validateRecipe(array $ingredients, array $boxes = []): array
    {
        $errors = [];
        $warnings = [];

        // بررسی حداقل ingredients
        $minCheck = $this->checkMinimumIngredients($ingredients);
        if (!$minCheck['valid']) {
            $errors[] = $minCheck['error'];
        }

        // بررسی duplicate ingredients
        $duplicateIngCheck = $this->checkDuplicateIngredients($ingredients);
        if ($duplicateIngCheck['has_duplicates']) {
            $errors[] = 'مواد اولیه تکراری: ' . implode(', ', $duplicateIngCheck['duplicates']);
        }

        // بررسی duplicate boxes
        $duplicateBoxCheck = $this->checkDuplicateBoxes($boxes);
        if ($duplicateBoxCheck['has_duplicates']) {
            $errors[] = 'بسته‌بندی تکراری: ' . implode(', ', $duplicateBoxCheck['duplicates']);
        }

        // اعتبارسنجی هر ingredient
        foreach ($ingredients as $ingredient) {
            $validation = $this->validateIngredientData($ingredient);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        // اعتبارسنجی هر box
        foreach ($boxes as $box) {
            $validation = $this->validateBoxData($box);
            if (!$validation['valid']) {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        // اعتبارسنجی total cost
        $costValidation = $this->validateTotalCost($ingredients, $boxes);
        if (!$costValidation['valid']) {
            $warnings = array_merge($warnings, $costValidation['warnings']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * بررسی موجودی مواد اولیه
     */
    public function checkIngredientsAvailability(int $itemId, int $quantity = 1): array
    {
        $item = Item::with('ingredients.stock')->findOrFail($itemId);

        return $item->checkIngredientsAvailability($quantity);
    }

    /**
     * بررسی موجودی بسته‌بندی
     */
    public function checkBoxesAvailability(int $itemId, int $quantity = 1): array
    {
        $item = Item::with('boxes.stock')->findOrFail($itemId);

        return $item->checkBoxesAvailability($quantity);
    }

    /**
     * بررسی کامل موجودی
     */
    public function checkFullAvailability(int $itemId, int $quantity = 1): array
    {
        $ingredientsCheck = $this->checkIngredientsAvailability($itemId, $quantity);
        $boxesCheck = $this->checkBoxesAvailability($itemId, $quantity);

        $available = $ingredientsCheck['available'] && $boxesCheck['available'];

        return [
            'available' => $available,
            'ingredients' => $ingredientsCheck,
            'boxes' => $boxesCheck,
        ];
    }

    /**
     * اعتبارسنجی مقادیر ingredient
     */
    public function validateIngredientData(array $ingredientData): array
    {
        $errors = [];

        // Required fields
        if (!isset($ingredientData['ingredient_id'])) {
            $errors[] = 'شناسه ماده اولیه الزامی است';
        }

        if (!isset($ingredientData['required_grams']) || $ingredientData['required_grams'] <= 0) {
            $errors[] = 'مقدار مورد نیاز باید بزرگتر از صفر باشد';
        }

        // Waste factor validation
        if (isset($ingredientData['waste_factor'])) {
            $wasteValidation = $this->validateWasteFactor($ingredientData['waste_factor']);
            if (!$wasteValidation['valid']) {
                $errors[] = $wasteValidation['error'];
            }
        }

        // Unit cost validation
        if (isset($ingredientData['unit_cost']) && $ingredientData['unit_cost'] < 0) {
            $errors[] = 'قیمت واحد نمی‌تواند منفی باشد';
        }

        // Ingredient existence validation
        if (isset($ingredientData['ingredient_id'])) {
            $ingredientValidation = $this->validateIngredientForRecipe($ingredientData['ingredient_id']);
            if (!$ingredientValidation['valid']) {
                $errors[] = $ingredientValidation['error'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * اعتبارسنجی مقادیر box
     */
    public function validateBoxData(array $boxData): array
    {
        $errors = [];

        // Required fields
        if (!isset($boxData['box_id'])) {
            $errors[] = 'شناسه بسته‌بندی الزامی است';
        }

        if (!isset($boxData['required_quantity']) || $boxData['required_quantity'] <= 0) {
            $errors[] = 'تعداد مورد نیاز باید بزرگتر از صفر باشد';
        }

        // Unit cost validation
        if (isset($boxData['unit_cost']) && $boxData['unit_cost'] < 0) {
            $errors[] = 'قیمت واحد نمی‌تواند منفی باشد';
        }

        // Box existence validation
        if (isset($boxData['box_id'])) {
            $boxValidation = $this->validateBoxForItem($boxData['box_id']);
            if (!$boxValidation['valid']) {
                $errors[] = $boxValidation['error'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * بررسی تداخل ingredients
     */
    public function checkDuplicateIngredients(array $ingredients): array
    {
        $ingredientIds = array_column($ingredients, 'ingredient_id');
        $duplicates = array_filter(array_count_values($ingredientIds), fn ($count) => $count > 1);

        return [
            'has_duplicates' => !empty($duplicates),
            'duplicates' => array_keys($duplicates),
        ];
    }

    /**
     * بررسی تداخل boxes
     */
    public function checkDuplicateBoxes(array $boxes): array
    {
        $boxIds = array_column($boxes, 'box_id');
        $duplicates = array_filter(array_count_values($boxIds), fn ($count) => $count > 1);

        return [
            'has_duplicates' => !empty($duplicates),
            'duplicates' => array_keys($duplicates),
        ];
    }

    /**
     * اعتبارسنجی waste_factor
     */
    public function validateWasteFactor(float $wasteFactor): array
    {
        if ($wasteFactor < 0) {
            return [
                'valid' => false,
                'error' => 'ضریب ضایعات نمی‌تواند منفی باشد',
            ];
        }

        if ($wasteFactor > 1) {
            return [
                'valid' => false,
                'error' => 'ضریب ضایعات نباید بیشتر از 100% (1.0) باشد',
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * بررسی حداقل مواد اولیه
     */
    public function checkMinimumIngredients(array $ingredients, int $minRequired = 1): array
    {
        if (empty($ingredients)) {
            return ['valid' => true, 'error' => null];
        }
        $count = count($ingredients);
        if ($count < $minRequired) {
            return [
                'valid' => false,
                'error' => "حداقل {$minRequired} ماده اولیه مورد نیاز است",
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * محاسبه و اعتبارسنجی total_cost
     */
    public function validateTotalCost(array $ingredients, array $boxes): array
    {
        $warnings = [];
        $totalCost = 0;

        // Calculate ingredients cost
        foreach ($ingredients as $ingredient) {
            $requiredGrams = $ingredient['required_grams'] ?? 0;
            $wasteFactor = $ingredient['waste_factor'] ?? 0;
            $actualGrams = $requiredGrams * (1 + $wasteFactor);
            $unitCost = $ingredient['unit_cost'] ?? 0;

            $totalCost += $actualGrams * $unitCost;
        }

        // Calculate boxes cost
        foreach ($boxes as $box) {
            $requiredQuantity = $box['required_quantity'] ?? 0;
            $unitCost = $box['unit_cost'] ?? 0;

            $totalCost += $requiredQuantity * $unitCost;
        }

        if ($totalCost <= 0) {
            $warnings[] = 'هزینه کل محصول صفر است. لطفاً قیمت‌های واحد را بررسی کنید';
        }

        return [
            'valid' => true,
            'total_cost' => round($totalCost, 2),
            'warnings' => $warnings,
        ];
    }

    /**
     * بررسی سازگاری قیمت‌ها
     */
    public function validatePricing(float $cost, ?float $sellPrice): array
    {
        if (!$sellPrice || $sellPrice <= 0) {
            return [
                'valid' => true,
                'warning' => 'قیمت فروش تعیین نشده است',
            ];
        }

        if ($sellPrice < $cost) {
            return [
                'valid' => false,
                'warning' => 'قیمت فروش کمتر از هزینه تمام شده است (ضرر‌ده)',
            ];
        }

        $profitMargin = (($sellPrice - $cost) / $sellPrice) * 100;

        if ($profitMargin < 10) {
            return [
                'valid' => true,
                'warning' => 'حاشیه سود کمتر از 10% است',
            ];
        }

        return ['valid' => true, 'warning' => null];
    }

    /**
     * اعتبارسنجی ingredient برای استفاده
     */
    public function validateIngredientForRecipe(int $ingredientId): array
    {
        $ingredient = Ingredient::with('stock')->find($ingredientId);

        if (!$ingredient) {
            return [
                'valid' => false,
                'error' => "ماده اولیه با شناسه {$ingredientId} یافت نشد",
                'ingredient' => null,
            ];
        }

        if (!$ingredient->is_active) {
            return [
                'valid' => false,
                'error' => "ماده اولیه '{$ingredient->ingredient_name}' غیرفعال است",
                'ingredient' => $ingredient,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'ingredient' => $ingredient,
        ];
    }

    /**
     * اعتبارسنجی box برای استفاده
     */
    public function validateBoxForItem(int $boxId): array
    {
        $box = Box::with('stock')->find($boxId);

        if (!$box) {
            return [
                'valid' => false,
                'error' => "بسته‌بندی با شناسه {$boxId} یافت نشد",
                'box' => null,
            ];
        }

        if (!$box->is_active) {
            return [
                'valid' => false,
                'error' => "بسته‌بندی '{$box->name}' غیرفعال است",
                'box' => $box,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'box' => $box,
        ];
    }

    /**
     * بررسی امکان تولید
     */
    public function calculateMaxProducibleQuantity(int $itemId, int $requestedQuantity): array
    {
        $item = Item::with(['ingredients.stock', 'boxes.stock'])->findOrFail($itemId);

        $maxQuantity = PHP_INT_MAX;
        $limitingFactor = null;

        // Check ingredients
        foreach ($item->ingredients as $ingredient) {
            $requiredGrams = $ingredient->pivot->actual_grams;
            $availableGrams = $ingredient->stock->available_grams ?? 0;

            if ($requiredGrams > 0) {
                $possibleQuantity = floor($availableGrams / $requiredGrams);

                if ($possibleQuantity < $maxQuantity) {
                    $maxQuantity = $possibleQuantity;
                    $limitingFactor = "ماده اولیه: {$ingredient->ingredient_name}";
                }
            }
        }

        // Check boxes
        foreach ($item->boxes as $box) {
            $requiredQuantity = $box->pivot->required_quantity;
            $availableQuantity = $box->stock->available_quantity ?? 0;

            if ($requiredQuantity > 0) {
                $possibleQuantity = floor($availableQuantity / $requiredQuantity);

                if ($possibleQuantity < $maxQuantity) {
                    $maxQuantity = $possibleQuantity;
                    $limitingFactor = "بسته‌بندی: {$box->name}";
                }
            }
        }

        $possible = $requestedQuantity <= $maxQuantity;

        return [
            'possible' => $possible,
            'max_quantity' => $maxQuantity === PHP_INT_MAX ? 0 : (int) $maxQuantity,
            'limiting_factor' => $limitingFactor,
        ];
    }

    /**
     * دریافت هشدارها برای recipe
     */
    public function getRecipeWarnings(Item $item): array
    {
        $warnings = [];

        // بررسی عدم وجود ingredients
        if ($item->ingredients->isEmpty()) {
            $warnings[] = 'هیچ ماده اولیه‌ای تعریف نشده است';
        }

        // بررسی ingredients با موجودی صفر
        foreach ($item->ingredients as $ingredient) {
            if (!$ingredient->stock || $ingredient->stock->quantity_grams <= 0) {
                $warnings[] = "موجودی ماده اولیه '{$ingredient->ingredient_name}' صفر است";
            }
        }

        // بررسی boxes با موجودی صفر
        foreach ($item->boxes as $box) {
            if (!$box->stock || $box->stock->quantity <= 0) {
                $warnings[] = "موجودی بسته‌بندی '{$box->name}' صفر است";
            }
        }

        // بررسی قیمت‌گذاری
        if ($item->target_cost > 0 && $item->actual_sell_price) {
            $pricingCheck = $this->validatePricing($item->target_cost, $item->actual_sell_price);
            if ($pricingCheck['warning']) {
                $warnings[] = $pricingCheck['warning'];
            }
        }

        return $warnings;
    }
}
