<?php

namespace Modules\Inventory\Http\Controllers;

use Modules\Inventory\Http\Requests\StockRequest;
use Modules\Inventory\Contracts\StockManagementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Inventory\app\Transformers\StockStatusResource;
use Modules\Inventory\Http\Requests\BoxStockRequest;
use Modules\Inventory\Http\Requests\CreateBoxStockRequest;
use Modules\Inventory\Transformers\BoxStockStatusResource;
use Symfony\Component\HttpFoundation\Response;

class StockManagementController extends InventoryController
{
    public function __construct(
        private StockManagementServiceInterface $stockService
    ) {
    }

    public function reserve(int $ingredientId, Request $request): JsonResponse
    {
        $grams = $request->grams;

        $result = $this->stockService->reserveStock($ingredientId, $grams);

        $statusCode = $result['success'] ? Response::HTTP_OK : Response::HTTP_CONFLICT;

        return new JsonResponse([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] ? 'Stock reserved' : 'Insufficient stock'
        ], $statusCode);
    }


    public function allocate(int $boxId, StockRequest $request): JsonResponse
    {
        $box = \Modules\Inventory\Models\Box::findOrFail($boxId);

        $result = $this->stockService->allocateForBoxProduction($box, $request->quantity);


        $statusCode = $result['success'] ? Response::HTTP_OK : Response::HTTP_CONFLICT;

        return new JsonResponse([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] ? 'Production allocated' : 'Allocation failed'
        ], $statusCode);
    }

    public function release(int $ingredientId, StockRequest $request): JsonResponse
    {
        $success = $this->stockService->releaseReservation($ingredientId, $request->grams);

        return new JsonResponse([
            'success' => $success,
            'message' => $success ? 'Reservation released' : 'Release failed'
        ]);
    }

    public function status($ingredientId): JsonResponse
    {
        $stock = $this->stockService->getStockStatus($ingredientId);

        if (!$stock) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Stock record not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'data' => new StockStatusResource($stock)
        ]);
    }

    public function boxStatus(int $boxId): JsonResponse
    {
        $stock = $this->stockService->getBoxStockStatus($boxId);

        if (!$stock) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Box stock not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'success' => true,
            'data' => new BoxStockStatusResource($stock)
        ]);
    }

    public function reserveBox(int $boxId, BoxStockRequest $request): JsonResponse
    {
        $result = $this->stockService->reserveBoxStock($boxId, $request->quantity);

        $statusCode = $result['success'] ? Response::HTTP_OK : Response::HTTP_CONFLICT;

        return new JsonResponse([
            'success' => $result['success'],
            'data' => $result,
            'message' => $result['success'] ? 'Box reserved' : 'Insufficient box stock'
        ], $statusCode);
    }

    public function releaseBox(int $boxId, BoxStockRequest $request): JsonResponse
    {
        $success = $this->stockService->releaseBoxReservation($boxId, $request->quantity);

        return new JsonResponse([
            'success' => $success,
            'message' => $success ? 'Box reservation released' : 'Release failed'
        ]);
    }

}
