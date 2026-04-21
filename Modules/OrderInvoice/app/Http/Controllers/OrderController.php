<?php

namespace Modules\OrderInvoice\app\Http\Controllers;

use Modules\OrderInvoice\app\Interfaces\OrderServiceInterface;
use Modules\OrderInvoice\app\Http\Requests\CreateOrderRequest;
use Modules\OrderInvoice\app\Http\Requests\AddOrderItemRequest;
use Modules\OrderInvoice\app\Http\Requests\ApplyDiscountRequest;
use Modules\OrderInvoice\app\Http\Requests\RefundOrderRequest;
use Modules\OrderInvoice\app\Transformers\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\OrderInvoice\app\Models\Order;

/**
 * Order Controller
 * 
 * مدیریت API سفارشات
 */
class OrderController
{
    public function __construct(
        protected OrderServiceInterface $orderService
    ) {}

    /**
     * لیست سفارشات
     * 
     * GET /api/v1/orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->input('per_page', 15), 100);
            $filters = $request->only(['status', 'created_by', 'start_date', 'end_date']);

            $orders = $this->orderService->list($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => OrderResource::collection($orders),
                'message' => 'لیست سفارشات با موفقیت دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست سفارشات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جزئیات سفارش
     * 
     * GET /api/v1/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getDetails($id);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'جزئیات سفارش دریافت شد',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش یافت نشد',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت جزئیات سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ایجاد سفارش
     * 
     * POST /api/v1/orders
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder($request->validated());

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'سفارش با موفقیت ایجاد شد',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * بروزرسانی سفارش
     * 
     * PUT/PATCH /api/v1/orders/{id}
     */
    public function update(int $id, CreateOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->updateOrder($id, $request->validated());

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'سفارش با موفقیت بروزرسانی شد',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش یافت نشد',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف سفارش
     * 
     * DELETE /api/v1/orders/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->orderService->deleteOrder($id);

            return response()->json([
                'success' => true,
                'message' => 'سفارش با موفقیت حذف شد',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'سفارش یافت نشد',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * افزودن آیتم به سفارش
     * 
     * POST /api/v1/orders/{id}/items
     */
    public function addItem(int $id, AddOrderItemRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $orderItem = $this->orderService->addItem(
                $id,
                $validated['item_id'],
                $validated['quantity'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $orderItem,
                'message' => 'آیتم با موفقیت اضافه شد',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در افزودن آیتم',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف آیتم از سفارش
     * 
     * DELETE /api/v1/orders/{id}/items/{itemId}
     */
    public function removeItem(int $id, int $itemId): JsonResponse
    {
        try {
            $this->orderService->removeItem($id, $itemId);

            return response()->json([
                'success' => true,
                'message' => 'آیتم با موفقیت حذف شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف آیتم',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * بروزرسانی تعداد آیتم
     * 
     * PATCH /api/v1/orders/{id}/items/{itemId}
     */
    public function updateItemQuantity(int $id, int $itemId, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'quantity' => 'required|integer|min:1|max:100',
            ]);

            $this->orderService->updateItemQuantity(
                $id,
                $itemId,
                $request->input('quantity')
            );

            $order = Order::with(['items.item', 'invoice', 'creator'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'تعداد آیتم بروزرسانی شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی تعداد',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * اعمال تخفیف
     * 
     * POST /api/v1/orders/{id}/discount
     */
    public function applyDiscount(int $id, ApplyDiscountRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->applyDiscount(
                $id,
                $request->input('discount_percent')
            );

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'تخفیف اعمال شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعمال تخفیف',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * اعمال مالیات
     * 
     * POST /api/v1/orders/{id}/tax
     */
    public function applyTax(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tax_percent' => 'required|numeric|min:0|max:100',
            ]);

            $order = $this->orderService->applyTax(
                $id,
                $request->input('tax_percent')
            );

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'مالیات اعمال شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعمال مالیات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تنظیم هزینه ارسال
     * 
     * POST /api/v1/orders/{id}/delivery-fee
     */
    public function setDeliveryFee(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'delivery_fee' => 'required|numeric|min:0',
            ]);

            $order = $this->orderService->setDeliveryFee(
                $id,
                $request->input('delivery_fee')
            );

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'هزینه ارسال تنظیم شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تنظیم هزینه ارسال',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تایید سفارش
     * 
     * POST /api/v1/orders/{id}/confirm
     */
    public function confirm(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->confirmOrder($id);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'سفارش تایید شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تایید سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * لغو سفارش
     * 
     * POST /api/v1/orders/{id}/cancel
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        try {
            $order = $this->orderService->cancelOrder(
                $id,
                $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'سفارش لغو شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در لغو سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تکمیل سفارش
     * 
     * POST /api/v1/orders/{id}/complete
     */
    public function complete(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->completeOrder($id);

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'سفارش تکمیل شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تکمیل سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * برگشت سفارش
     * 
     * POST /api/v1/orders/{id}/refund
     */
    public function refund(int $id, RefundOrderRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $order = $this->orderService->refundOrder(
                $id,
                $validated['refund_type'],
                $validated['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => new OrderResource($order),
                'message' => 'سفارش برگشت داده شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در برگشت سفارش',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * بررسی موجودی
     * 
     * GET /api/v1/orders/{id}/check-availability
     */
    public function checkAvailability(int $id): JsonResponse
    {
        try {
            $availability = $this->orderService->checkAvailability($id);

            return response()->json([
                'success' => true,
                'data' => $availability,
                'message' => $availability['available']
                    ? 'موجودی کافی است'
                    : 'موجودی کافی نیست',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در بررسی موجودی',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * آمار سفارشات
     * 
     * GET /api/v1/orders/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date']);
            $stats = $this->orderService->getStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'آمار سفارشات دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت آمار',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * سفارشات امروز
     * 
     * GET /api/v1/orders/today
     */
    public function today(): JsonResponse
    {
        try {
            $orders = $this->orderService->getTodayOrders();

            return response()->json([
                'success' => true,
                'data' => OrderResource::collection($orders),
                'message' => 'سفارشات امروز دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت سفارشات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
