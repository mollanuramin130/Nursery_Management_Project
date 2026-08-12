<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Services\FulfillmentService;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFulfillmentController extends Controller
{
    public function __construct(private readonly FulfillmentService $fulfillment) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->fulfillment->dashboard(), 'Fulfillment dashboard retrieved');
    }

    public function queue(Request $request, string $queue): JsonResponse
    {
        $result = $this->fulfillment->queue(
            $queue,
            $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null,
            $request->query('q'),
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Fulfillment queue retrieved', 200, [
            'pagination' => $result['pagination'],
            'pick_exceptions' => $result['pick_exceptions'] ?? null,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success($this->fulfillment->showOrder($id), 'Fulfillment order retrieved');
    }

    public function startPicking(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->startPicking($id, $user->id),
            'Picking started',
        );
    }

    public function updatePick(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.picked' => ['required', 'integer', 'min:0'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->updatePickedQuantities($id, $validated['items'], $user->id),
            'Pick quantities updated',
        );
    }

    public function pickException(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'order_item_id' => ['required', 'integer'],
            'expected' => ['required', 'integer', 'min:1'],
            'actual' => ['required', 'integer', 'min:0'],
            'note' => ['required', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->recordPickException(
                $id,
                (int) $validated['order_item_id'],
                (int) $validated['expected'],
                (int) $validated['actual'],
                $validated['note'],
                $user->id,
            ),
            'Pick exception recorded',
        );
    }

    public function completePicking(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->completePicking($id, $user->id),
            'Picking completed',
        );
    }

    public function pack(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'package_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->pack($id, $validated, $user->id),
            'Order packed',
        );
    }

    public function createShipment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'eta_date' => ['nullable', 'date'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $shipment = $this->fulfillment->createShipment($id, $validated, $user->id);
        $replay = (bool) ($shipment['idempotent_replay'] ?? false);

        return ApiResponse::success(
            $shipment,
            $replay ? 'Shipment already exists' : 'Shipment created successfully',
            $replay ? 200 : 201,
        );
    }

    public function outForDelivery(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->markOutForDelivery($id, $user->id),
            'Marked out for delivery',
        );
    }

    public function deliver(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->markDelivered($id, $user->id),
            'Marked delivered',
        );
    }

    public function failDelivery(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', FulfillmentService::FAILURE_REASONS)],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->markDeliveryFailed($id, $validated['reason'], $validated['note'] ?? null, $user->id),
            'Delivery failure recorded',
        );
    }

    public function retryDelivery(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->retryDelivery($id, $user->id),
            'Delivery retry started',
        );
    }

    public function shipments(Request $request): JsonResponse
    {
        $result = $this->fulfillment->listShipments(
            $request->query('status'),
            $request->query('carrier'),
            $request->query('q'),
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Shipments retrieved', 200, [
            'pagination' => $result['pagination'],
        ]);
    }

    public function showShipment(int $id): JsonResponse
    {
        return ApiResponse::success($this->fulfillment->showShipment($id), 'Shipment retrieved');
    }

    public function addTracking(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'event_at' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->addTrackingEvent($id, $validated, $user->id),
            'Tracking event added',
        );
    }

    public function exceptions(Request $request): JsonResponse
    {
        $result = $this->fulfillment->listExceptions(
            (int) $request->query('per_page', 20),
            (int) $request->query('page', 1),
        );

        return ApiResponse::success($result['data'], 'Exceptions retrieved', 200, [
            'pagination' => $result['pagination'],
            'pick_exceptions' => $result['pick_exceptions'] ?? [],
        ]);
    }

    public function resolveException(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'exception_id' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success(
            $this->fulfillment->resolveException($id, $validated['exception_id'], $validated['note'] ?? null, $user->id),
            'Exception resolved',
        );
    }
}
