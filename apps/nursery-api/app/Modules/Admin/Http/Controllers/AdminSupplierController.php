<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminSupplierService;
use App\Modules\Auth\Models\User;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupplierController extends Controller
{
    public function __construct(private readonly AdminSupplierService $suppliers) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->suppliers->listSuppliers(), 'Suppliers retrieved successfully');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->suppliers->showSupplier($id), 'Supplier retrieved successfully');
    }

    public function upsertProduct(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'supplier_sku' => ['nullable', 'string', 'max:80'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'moq' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->upsertSupplierProduct($id, $validated, $user->id),
            'Supplier product saved',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:suppliers,code'],
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->createSupplier([
                ...$validated,
                'status' => $validated['status'] ?? 'active',
            ], $user->id),
            'Supplier created successfully',
            201,
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:40', 'unique:suppliers,code,'.$id],
            'name' => ['sometimes', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->updateSupplier($id, $validated, $user->id),
            'Supplier updated successfully',
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->suppliers->deleteSupplier($id, $user->id);

        return ApiResponse::success(null, 'Supplier deleted successfully');
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $result = $this->suppliers->listPurchaseOrders(
            $request->query('status'),
            (int) $request->query('per_page', 30),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Purchase orders retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function showPurchaseOrder(int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->suppliers->showPurchaseOrder($id),
            'Purchase order retrieved successfully',
        );
    }

    public function storePurchaseOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'po_number' => ['nullable', 'string', 'max:40', 'unique:purchase_orders,po_number'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'expected_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:draft,ordered,approved'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->createPurchaseOrder($validated, $user->id),
            'Purchase order created successfully',
            201,
        );
    }

    public function approvePurchaseOrder(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->approvePurchaseOrder($id, $user->id),
            'Purchase order approved',
        );
    }

    public function cancelPurchaseOrder(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->cancelPurchaseOrder($id, $user->id),
            'Purchase order cancelled',
        );
    }

    public function receivePurchaseOrder(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required_with:items', 'integer'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.damaged' => ['nullable', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->suppliers->receivePurchaseOrder($id, $validated['items'] ?? null ? $validated : null, $user->id),
            'Purchase order receipt recorded',
        );
    }
}
