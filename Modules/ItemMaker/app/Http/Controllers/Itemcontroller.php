<?php

namespace Modules\ItemMaker\Http\Controllers;

use Modules\ItemMaker\app\Interfaces\ItemServiceInterface;
use Modules\ItemMaker\Http\Requests\CreateItemRequest;
use Modules\ItemMaker\Http\Requests\UpdateItemRequest;
use Modules\ItemMaker\Http\Requests\CheckAvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\ItemMaker\app\Transformers\ItemResource;
use Modules\ItemMaker\Models\Item;
use Symfony\Component\HttpFoundation\Response;

/**
 * Item Controller
 *
 */
class ItemController
{
    public function __construct(
        protected ItemServiceInterface $itemService
    ) {
    }

    /**
     * لیست محصولات
     *
     * GET /api/v1/items
     *

     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 15);
            $perPage = min(max($perPage, 1), 100);
    
            $filters = $request->only([
                'is_active',
                'is_featured',
                'category',
                'subcategory',
                'available_today',
            ]);

            if ($request->filled('search')) {
                $items = $this->itemService->search($request->input('search'), $filters);
                return ItemResource::collection($items);
            }

            $items = $this->itemService->list($filters, $perPage);

            return ItemResource::collection($items);

        } catch (\Exception $e) {
            \Log::error('Failed to list items: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت لیست محصولات',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * GET /api/v1/items/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $item = $this->itemService->getDetails($id);

            return response()->json([
                'success' => true,
                'data' => new ItemResource($item),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'محصول یافت نشد',
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            \Log::error("Failed to show item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت اطلاعات محصول',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * POST /api/v1/items
     */
    public function store(CreateItemRequest $request): JsonResponse
    {
        try {
            $item = $this->itemService->create(
                $request->except(['ingredients', 'boxes']),
                $request->input('ingredients', []),
                $request->input('boxes', [])
            );
            return response()->json([
            'success' => true,
            'data' => [
            'item' => new ItemResource($item->fresh()),
            'message' => 'محصول با موفقیت ایجاد شد'
            ],
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطای اعتبارسنجی',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            \Log::error('Failed to create item: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در ایجاد محصول',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * PUT/PATCH /api/v1/items/{id}
     */
    public function update(UpdateItemRequest $request, int $id): JsonResponse
    {
        try {
            $item = $this->itemService->update(
                $id,
                $request->except(['ingredients', 'boxes']),
                $request->has('ingredients') ? $request->input('ingredients') : null,
                $request->has('boxes') ? $request->input('boxes') : null
            );

            return response()->json([
                'success' => true,
                'data' => new ItemResource($item),
                'message' => 'محصول با موفقیت بروزرسانی شد',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'محصول یافت نشد',
            ], Response::HTTP_NOT_FOUND);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطای اعتبارسنجی',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            \Log::error("Failed to update item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در بروزرسانی محصول',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * DELETE /api/v1/items/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->itemService->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'محصول با موفقیت حذف شد',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'محصول یافت نشد',
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            \Log::error("Failed to delete item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف محصول',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * POST /api/v1/items/{id}/restore
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $this->itemService->restore($id);

            return response()->json([
                'success' => true,
                'message' => 'محصول با موفقیت بازگردانی شد',
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to restore item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در بازگردانی محصول',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * POST /api/v1/items/{id}/check-availability
     */
    public function checkAvailability(CheckAvailabilityRequest $request, int $id): JsonResponse
    {
        try {
            $availability = $this->itemService->checkAvailability(
                $id,
                $request->integer('quantity', 1)
            );

            return response()->json([
                'success' => true,
                'data' => $availability,
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to check availability for item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در بررسی موجودی',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * POST /api/v1/items/{id}/recalculate-cost
     */
    public function recalculateCost(int $id): JsonResponse
    {
        try {
            $costData = $this->itemService->calculateCost($id, true);

            return response()->json([
                'success' => true,
                'data' => $costData,
                'message' => 'هزینه محاسبه و ذخیره شد',
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to recalculate cost for item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در محاسبه هزینه',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * PATCH /api/v1/items/{id}/toggle-active
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        try {
            $isActive = $request->boolean('is_active');

            $this->itemService->toggleActive($id, $isActive);

            return response()->json([
                'success' => true,
                'message' => $isActive ? 'محصول فعال شد' : 'محصول غیرفعال شد',
            ]);

        } catch (\Exception $e) {
            \Log::error("Failed to toggle active for item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در تغییر وضعیت',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     *
     * GET /api/v1/items/active
     */
    public function active(Request $request): AnonymousResourceCollection
    {
        $category = $request->input('category');
        $items = $this->itemService->getActiveItems($category);

        return ItemResource::collection($items);
    }

    /**
     *
     * GET /api/v1/items/featured
     */
    public function featured(): AnonymousResourceCollection
    {
        $items = $this->itemService->getFeaturedItems();

        return ItemResource::collection($items);
    }

    /**
     *
     * GET /api/v1/items/available
     */
    public function available(): AnonymousResourceCollection
    {
        $items = $this->itemService->getAvailableItems();

        return ItemResource::collection($items);
    }

    /**
     *
     * GET /api/v1/items/categories
     */
    public function categories(): JsonResponse
    {
        $categories = $this->itemService->getCategories();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     *
     * GET /api/v1/items/statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->itemService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     *
     * POST /api/v1/items/{id}/duplicate
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'code' => "required|string|max:50|unique:items,code,{$id}",
                'name' => 'nullable|string|max:100',
            ]);

            $item = $this->itemService->duplicate(
                $id,
                $request->input('code'),
                $request->input('name')
            );

            return response()->json([
                'success' => true,
                'data' => new ItemResource($item),
                'message' => 'محصول با موفقیت کپی شد',
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error("Failed to duplicate item {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در کپی کردن محصول',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
