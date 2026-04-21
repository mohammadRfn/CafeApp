<?php

namespace Modules\Inventory\Http\Controllers;

use Modules\Inventory\Http\Requests\ReportingRequest;
use Modules\Inventory\Contracts\ReportingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Inventory\app\Transformers\ReportingResource;

class ReportingController extends InventoryController
{
    public function __construct(
        private ReportingServiceInterface $reportingService
    ) {
    }

    public function lowStock(ReportingRequest $request): AnonymousResourceCollection
    {
        $ingredients = $this->reportingService->getLowStockIngredients(
            $request->integer('limit', 10)
        );

        return ReportingResource::collection($ingredients);
    }

    public function inventoryValue(): JsonResponse
    {
        $values = $this->reportingService->getInventoryValues();

        return new JsonResponse([
            'success' => true,
            'data' => [
                'purchase_value' => round($values['purchase_value'], 0),
                'sales_value' => round($values['sales_value'], 0),
                'currency' => 'تومان',
                'profit_potential' => round($values['sales_value'] - $values['purchase_value'], 0)
            ]
        ]);
    }


    public function stockMovement(ReportingRequest $request): JsonResponse
    {
        $report = $this->reportingService->getStockMovementReport(
            $request->integer('days', 30)
        );

        return new JsonResponse([
            'success' => true,
            'data' => $report,
            'period_days' => $request->days ?? 30
        ]);
    }

    public function abcAnalysis(): JsonResponse
    {
        $analysis = $this->reportingService->getAbcAnalysis();

        return new JsonResponse([
            'success' => true,
            'data' => $analysis
        ]);
    }
}
