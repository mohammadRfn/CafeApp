<?php

namespace Modules\Inventory\Http\Controllers;

use Modules\Inventory\Http\Requests\SetNewPriceRequest;
use Modules\Inventory\Http\Requests\GetPriceSummaryRequest;
use Modules\Inventory\Contracts\PriceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\app\Transformers\PriceResource;
use Symfony\Component\HttpFoundation\Response;

class PriceController extends InventoryController
{
    public function __construct(
        private PriceServiceInterface $priceService
    ) {
    }

    public function current(int $ingredientId, int $unitId): JsonResponse
    {
        $price = $this->priceService->getCurrentPrice(
            $ingredientId,
            $unitId
        );

        if (!$price) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No active price found'
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'data' => new PriceResource($price)
        ]);
    }

    public function updatePrice(SetNewPriceRequest $request): JsonResponse
    {
        $price = $this->priceService->setNewPrice(
            $request->ingredient_id,
            $request->box_id,
            $request->unit_id,
            $request->buy_price,
            $request->sell_price
        );

        return new JsonResponse([
            'success' => true,
            'data' => new PriceResource($price),
            'message' => 'Price updated successfully'
        ], Response::HTTP_CREATED);
    }

    public function summary(int $ingredientId): JsonResponse
    {
        $summary = $this->priceService->getIngredientPricingSummary($ingredientId);

        $flattened = $summary->flatten();

        if ($flattened->isEmpty()) {
            return new JsonResponse([
                'success' => true,
                'data' => [],
                'message' => "هیچ واحد قیمتی فعالی برای ماده {$ingredientId} یافت نشد"
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'data' => PriceResource::collection($flattened)
        ]);
    }


    public function history(int $ingredientId, Request $request): JsonResponse
    {
        $ingredientId = (int) $ingredientId;

        if ($ingredientId <= 0) {
            return response()->json(['error' => 'شناسه نامعتبر'], 400);
        }
        $limit = request('limit', 10);
        $limit = min(max((int)$limit, 1), 50);

        $history = $this->priceService->getHistoricalPrices($ingredientId, $limit);


        return new JsonResponse([
            'success' => true,
            'data' => PriceResource::collection($history)
        ]);
    }


}
