<?php

namespace Modules\OrderInvoice\app\Http\Controllers;

use Modules\OrderInvoice\app\Interfaces\InvoiceServiceInterface;
use Modules\OrderInvoice\app\Http\Requests\RecordPaymentRequest;
use Modules\OrderInvoice\app\Transformers\InvoiceResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Invoice Controller
 * 
 * مدیریت API فاکتورها
 */
class InvoiceController
{
    public function __construct(
        protected InvoiceServiceInterface $invoiceService
    ) {}

    /**
     * لیست فاکتورها
     * 
     * GET /api/v1/invoices
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->input('per_page', 15), 100);
            $filters = $request->only(['payment_status', 'payment_method', 'start_date', 'end_date']);

            // این از repository مستقیم میاد، چون service لیست نداره
            $invoices = app(\Modules\OrderInvoice\app\Interfaces\InvoiceRepositoryInterface::class)
                ->paginate($perPage, $filters);

            return response()->json([
                'success' => true,
                'data' => InvoiceResource::collection($invoices),
                'message' => 'لیست فاکتورها دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست فاکتورها',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * جزئیات فاکتور
     * 
     * GET /api/v1/invoices/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $invoice = app(\Modules\OrderInvoice\app\Interfaces\InvoiceRepositoryInterface::class)
                ->findById($id);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'فاکتور یافت نشد',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
                'message' => 'جزئیات فاکتور دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت جزئیات فاکتور',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تولید فاکتور از سفارش
     * 
     * POST /api/v1/invoices/generate
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
            ]);

            $invoice = $this->invoiceService->generateInvoice($request->input('order_id'));

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
                'message' => 'فاکتور با موفقیت ایجاد شد',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد فاکتور',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ثبت پرداخت
     * 
     * POST /api/v1/invoices/{id}/pay
     */
    public function pay(int $id, RecordPaymentRequest $request): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->recordPayment(
                $id,
                $request->input('payment_method')
            );

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
                'message' => 'پرداخت با موفقیت ثبت شد',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'فاکتور یافت نشد',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ثبت پرداخت',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * حذف فاکتور
     * 
     * DELETE /api/v1/invoices/{id}
     * 
     * ⚠️ این عملیات فاکتور را به صورت کامل حذف می‌کند
     * - فاکتورهای پرداخت شده را با احتیاط حذف کنید
     * - این عملیات غیرقابل بازگشت است
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->invoiceService->deleteInvoice($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_number' => $deleted['invoice_number'],
                    'payment_status' => $deleted['payment_status'],
                    'deleted_at' => now()->toDateTimeString(),
                ],
                'message' => 'فاکتور با موفقیت حذف شد',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'فاکتور یافت نشد',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف فاکتور',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * برگشت پرداخت
     * 
     * POST /api/v1/invoices/{id}/refund
     */
    public function refund(int $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->refundPayment($id);

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
                'message' => 'پرداخت برگشت داده شد',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'فاکتور یافت نشد',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در برگشت پرداخت',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * فاکتورهای پرداخت نشده
     * 
     * GET /api/v1/invoices/unpaid
     */
    public function unpaid(): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getUnpaidInvoices();

            return response()->json([
                'success' => true,
                'data' => InvoiceResource::collection($invoices),
                'message' => 'فاکتورهای پرداخت نشده دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت فاکتورها',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * درآمد روزانه
     * 
     * GET /api/v1/invoices/daily-revenue
     */
    public function dailyRevenue(Request $request): JsonResponse
    {
        try {
            $date = $request->input('date');
            $revenue = $this->invoiceService->getDailyRevenue($date);

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date ?? today()->toDateString(),
                    'revenue' => $revenue,
                ],
                'message' => 'درآمد روزانه دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در محاسبه درآمد',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * درآمد بازه زمانی
     * 
     * GET /api/v1/invoices/revenue
     */
    public function revenue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $revenue = $this->invoiceService->getRevenueBetween(
                $request->input('start_date'),
                $request->input('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'revenue' => $revenue,
                ],
                'message' => 'درآمد بازه زمانی دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در محاسبه درآمد',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * آمار فاکتورها
     * 
     * GET /api/v1/invoices/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date']);
            $stats = $this->invoiceService->getStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'آمار فاکتورها دریافت شد',
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
     * فاکتورهای امروز
     * 
     * GET /api/v1/invoices/today/list
     */
    public function today(): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getTodayInvoices();

            return response()->json([
                'success' => true,
                'data' => InvoiceResource::collection($invoices),
                'meta' => [
                    'date' => today()->toDateString(),
                    'count' => $invoices->count(),
                    'total_amount' => $invoices->sum('total_amount'),
                ],
                'message' => 'فاکتورهای امروز دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت فاکتورها',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * فاکتورهای پرداخت شده امروز
     * 
     * GET /api/v1/invoices/today/paid
     */
    public function todayPaid(): JsonResponse
    {
        try {
            $invoices = $this->invoiceService->getTodayPaidInvoices();

            return response()->json([
                'success' => true,
                'data' => InvoiceResource::collection($invoices),
                'meta' => [
                    'date' => today()->toDateString(),
                    'count' => $invoices->count(),
                    'total_revenue' => $invoices->sum('total_amount'),
                ],
                'message' => 'فاکتورهای پرداخت شده امروز دریافت شد',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت فاکتورها',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
