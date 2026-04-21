<?php

namespace Modules\Inventory\Http\Controllers;

use Modules\Inventory\Http\Requests\CreateTransactionRequest;
use Modules\Inventory\Http\Requests\RollbackTransactionRequest;
use Modules\Inventory\Contracts\TransactionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Inventory\app\Transformers\TransactionResource;
use Modules\Inventory\Contracts\InventoryTransactionServiceInterface;
use Modules\Inventory\Http\Requests\CreateBoxRequest;
use Modules\Inventory\Http\Requests\CreateBoxStockRequest;
use Modules\Inventory\Http\Requests\CreateProductRequest;
use Modules\Inventory\Http\Requests\CreateStockRequest;
use Modules\Inventory\Transformers\BoxResource;
use Modules\Inventory\Transformers\StockStatusResource;
use Modules\Inventory\Transformers\ProductResource;
use Symfony\Component\HttpFoundation\Response;

class InventoryTransactionController extends InventoryController
{
    public function __construct(
        private InventoryTransactionServiceInterface $transactionService
    ) {
    }

    /**
     * @throws ValidationException
     */
    public function listIngredients(Request $request): JsonResponse
    {
        try {
            $ingredients = $this->transactionService->getAllIngredients();

            $totalBuyValue = $ingredients->sum('total_buy_value');
            $totalSellValue = $ingredients->sum('total_sell_value');

            return new JsonResponse([
                'success' => true,
                'data' => $ingredients,
                'meta' => [
                    'total_items' => $ingredients->count(),
                    'total_buy_value' => round($totalBuyValue, 0),
                    'total_sell_value' => round($totalSellValue, 0),
                    'total_profit_potential' => round($totalSellValue - $totalBuyValue, 0),
                    'currency' => 'تومان'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to list ingredients: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'خطا در دریافت لیست مواد اولیه',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * @return JsonResponse
     */
    public function listBoxes(Request $request): JsonResponse
    {
        try {
            $boxes = $this->transactionService->getAllBoxes();

            $totalBuyValue = $boxes->sum('total_buy_value');
            $totalSellValue = $boxes->sum('total_sell_value');

            return new JsonResponse([
                'success' => true,
                'data' => $boxes,
                'meta' => [
                    'total_items' => $boxes->count(),
                    'total_quantity' => $boxes->sum('quantity'),
                    'total_buy_value' => round($totalBuyValue, 0),
                    'total_sell_value' => round($totalSellValue, 0),
                    'total_profit_potential' => round($totalSellValue - $totalBuyValue, 0),
                    'currency' => 'تومان'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to list boxes: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'خطا در دریافت لیست باکس‌ها',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function createTransaction(CreateTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->createTransaction($request->validated());
            return new JsonResponse([
                'success' => true,
                'data' => new TransactionResource($transaction),
                'message' => 'Transaction created successfully'
            ], Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            \Log::error('Transaction creation failed: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'Transaction processing failed'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function rollback(Request $request, string $entityType, int $transactionId): JsonResponse
    {

        $success = $this->transactionService->rollbackTransaction($entityType, $transactionId);

        return new JsonResponse([
            'success' => $success,
            'message' => $success ? 'Transaction rolled back successfully' : 'Rollback failed'
        ]);
    }


    public function recent(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 50);

        $transactions = $this->transactionService->getRecentTransactions($limit);

        return new JsonResponse([
            'success' => true,
            'data' => TransactionResource::collection($transactions),
            'meta' => [
                'total' => $transactions->count(),
                'limit' => $limit
            ]
        ]);
    }


    public function bulkTransactions(CreateTransactionRequest $request): JsonResponse
    {
        $transactions = $this->transactionService->bulkTransactions($request->validated());

        return new JsonResponse([
            'success' => true,
            'data' => TransactionResource::collection($transactions),
            'message' => 'Bulk transactions created'
        ], Response::HTTP_CREATED);
    }



    public function createProduct(CreateProductRequest $request): JsonResponse
    {
        try {
            $data = [
                'ingredient_name' => $request->input('name'),
                'ingredient_code' => $request->input('code'),
                'reorder_point' => $request->input('reorder_point', 0)
            ];

            $ingredient = $this->transactionService->createIngredient($data);

            return new JsonResponse([
                'success' => true,
                'data' => new ProductResource($ingredient),
                'message' => 'محصول با موفقیت ایجاد شد'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Product creation failed: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'ایجاد محصول ناموفق: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function createStock(CreateStockRequest $request): JsonResponse
    {
        try {
            $stock = $this->transactionService->initializeStock(
                $request->ingredient_id,
                $request->quantity_grams,
                $request->avg_cost_per_gram ?? 0
            );

            return new JsonResponse([
                'success' => true,
                'data' => $stock->load('ingredient')->toArray(),
                'message' => 'موجودی با موفقیت تنظیم شد'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {

            \Log::error('Stock init FAILED: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());

            return new JsonResponse([
                'success' => false,
                'message' => 'تنظیم موجودی ناموفق',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createBox(CreateBoxRequest $request): JsonResponse
    {
        try {
            $box = $this->transactionService->createBox($request->validated());

            return new JsonResponse([
                'success' => true,
                'data' => new BoxResource($box),
                'message' => 'باکس با موفقیت ایجاد شد'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Box creation failed: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'ایجاد باکس ناموفق'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createBoxStock(CreateBoxStockRequest $request): JsonResponse
    {
        try {
            $stock = $this->transactionService->initializeBoxStock(
                $request->box_id,
                $request->quantity,
                $request->avg_cost_per_unit ?? 0
            );

            return new JsonResponse([
                'success' => true,
                'data' => $stock->load('box')->toArray(),
                'message' => 'موجودی باکس تنظیم شد'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Box stock init FAILED: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => 'تنظیم موجودی باکس ناموفق'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



}
