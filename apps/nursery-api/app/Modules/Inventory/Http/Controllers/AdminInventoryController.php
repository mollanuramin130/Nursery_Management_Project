<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockTransferService;
use App\Shared\Support\ApiResponse;
use App\Shared\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminInventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly StockTransferService $transfers,
    ) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->inventory->dashboard(), 'Inventory dashboard retrieved successfully');
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->inventory->list(
            $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            is_string($request->query('q')) && $request->query('q') !== '' ? (string) $request->query('q') : null,
            $request->filled('low_stock') ? $request->boolean('low_stock') : null,
            is_string($request->query('status')) && $request->query('status') !== '' ? strtoupper((string) $request->query('status')) : null,
            min(100, max(1, (int) $request->query('per_page', 50))),
            max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success($result['data'], 'Inventory retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            $this->inventory->show($id),
            'Inventory item retrieved successfully',
        );
    }

    public function reorderSuggestions(): JsonResponse
    {
        return ApiResponse::success(
            $this->inventory->reorderSuggestions(),
            'Reorder suggestions retrieved successfully',
        );
    }

    public function deadStock(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->inventory->deadStock(
                max(1, (int) $request->query('days', 90)),
                min(200, max(1, (int) $request->query('limit', 50))),
            ),
            'Dead stock report retrieved successfully',
        );
    }

    public function movements(Request $request): JsonResponse
    {
        $result = $this->inventory->movements(
            $request->filled('product_id') ? (int) $request->query('product_id') : null,
            $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            min(100, max(1, (int) $request->query('per_page', 30))),
            max(1, (int) $request->query('page', 1)),
            is_string($request->query('type')) && $request->query('type') !== '' ? (string) $request->query('type') : null,
            $request->filled('actor_user_id') ? (int) $request->query('actor_user_id') : null,
            is_string($request->query('from')) ? (string) $request->query('from') : null,
            is_string($request->query('to')) ? (string) $request->query('to') : null,
        );

        return ApiResponse::success($result['data'], 'Stock movements retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'adjustment' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:40'],
            'reference_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! empty($validated['idempotency_key'])) {
            $validated['reference_type'] = $validated['reference_type'] ?? 'idempotency';
            $validated['reference_id'] = $validated['reference_id'] ?? crc32($validated['idempotency_key']);
        }

        $result = $this->inventory->adjust($validated, $user->id);
        AuditLogger::log('inventory.adjust', 'inventory', $validated['product_id'], null, $result, $user->id);

        return ApiResponse::success($result, 'Inventory adjusted');
    }

    public function reconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer'],
            'physical_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->inventory->reconcile($validated, $user->id),
            'Inventory reconciled successfully',
        );
    }

    public function updateThreshold(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->inventory->updateThreshold($id, (int) $validated['low_stock_threshold'], $user->id),
            'Reorder level updated',
        );
    }

    public function transfers(Request $request): JsonResponse
    {
        $result = $this->transfers->list(
            is_string($request->query('status')) && $request->query('status') !== '' ? (string) $request->query('status') : null,
            min(100, max(1, (int) $request->query('per_page', 30))),
            max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success($result['data'], 'Transfers retrieved successfully', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function transferShow(int $id): JsonResponse
    {
        return ApiResponse::success($this->transfers->show($id), 'Transfer retrieved successfully');
    }

    public function transferStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => ['required', 'integer'],
            'to_warehouse_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->transfers->create($validated, $user->id),
            'Transfer created successfully',
            201,
        );
    }

    public function transferShip(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->transfers->ship($id, $user->id), 'Transfer marked in transit');
    }

    public function transferComplete(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->transfers->complete($id, $user->id), 'Transfer completed');
    }

    public function transferCancel(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success($this->transfers->cancel($id, $user->id), 'Transfer cancelled');
    }
}
