<?php

namespace App\Modules\Notification\Services;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\User;
use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * QA-22 — maps successful commerce events → customer + staff notifications.
 * Does not replace OrderStateMachine; callers invoke after DB commit paths.
 */
class OrderNotificationDispatcher
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Notify all active staff who hold any of the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function notifyStaff(
        array $permissions,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $idempotencySuffix = null,
    ): int {
        $permissionIds = Permission::query()->whereIn('slug', $permissions)->pluck('id');
        if ($permissionIds->isEmpty()) {
            return 0;
        }

        $userIds = DB::table('user_role')
            ->join('role_permission', 'role_permission.role_id', '=', 'user_role.role_id')
            ->join('users', 'users.id', '=', 'user_role.user_id')
            ->whereIn('role_permission.permission_id', $permissionIds)
            ->where('users.status', 'active')
            ->distinct()
            ->pluck('users.id');

        $sent = 0;
        foreach ($userIds as $userId) {
            $key = null;
            if ($idempotencySuffix !== null) {
                $key = strtolower($type).'|staff:'.$userId.'|'.$idempotencySuffix;
            }
            $n = $this->notifications->notify(
                (int) $userId,
                $type,
                $title,
                $body,
                $data,
                $key,
            );
            if ($n) {
                $sent++;
            }
        }

        return $sent;
    }

    public function customerDeepLinkData(Order $order, string $type): array
    {
        return [
            'type' => $type,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'route' => '/account/orders/'.$order->id,
            'audience' => 'customer',
        ];
    }

    public function adminDeepLinkData(Order $order, string $type): array
    {
        return [
            'type' => $type,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'route' => '/orders/'.$order->id,
            'audience' => 'admin',
        ];
    }

    public function notifyNewOrder(Order $order): void
    {
        $amount = number_format((float) $order->grand_total, 2);
        $data = array_merge($this->adminDeepLinkData($order, 'new_order'), [
            'order_total' => $amount,
            'payment_method' => $order->payment_method,
        ]);
        $this->notifyStaff(
            ['orders.view', 'notifications.view'],
            'new_order',
            'New Order Received',
            "Order {$order->order_number} has been placed for ₹{$amount}.",
            $data,
            'order:'.$order->id,
        );
    }

    public function notifyCustomerStatus(Order $order, string $toStatus): void
    {
        if (! $order->user_id) {
            return;
        }

        $map = [
            'CONFIRMED' => ['order_confirmed', 'Order Confirmed', "Your order {$order->order_number} has been confirmed."],
            'PROCESSING' => ['order_processing', 'Order Processing', "Your order {$order->order_number} is now being processed."],
            'PACKED' => ['order_packed', 'Order Packed', "Your order {$order->order_number} has been packed successfully."],
            'SHIPPED' => ['order_shipped', 'Order Shipped', "Your order {$order->order_number} has been shipped."],
            'OUT_FOR_DELIVERY' => ['order_out_for_delivery', 'Out for Delivery', "Your order {$order->order_number} is out for delivery."],
            'DELIVERED' => ['order_delivered', 'Order Delivered', "Your order {$order->order_number} has been delivered successfully."],
            'DELIVERY_FAILED' => ['delivery_failed', 'Delivery Update', "Delivery for order {$order->order_number} could not be completed."],
            'CANCELLED' => ['order_cancelled', 'Order Cancelled', "Your order {$order->order_number} has been cancelled."],
            'PAYMENT_FAILED' => ['payment_failed', 'Payment Failed', "Payment for order {$order->order_number} failed."],
        ];

        if (! isset($map[$toStatus])) {
            return;
        }

        [$type, $title, $body] = $map[$toStatus];
        $this->notifications->notify(
            $order->user_id,
            $type,
            $title,
            $body,
            array_merge($this->customerDeepLinkData($order, $type), [
                'status' => $toStatus,
            ]),
        );
    }

    public function notifyPaymentConfirmed(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }
        $type = 'payment_confirmed';
        $this->notifications->notify(
            $order->user_id,
            $type,
            'Payment Confirmed',
            "Your payment for order {$order->order_number} has been confirmed.",
            array_merge($this->customerDeepLinkData($order, $type), [
                'status' => 'PAID',
            ]),
        );

        $this->notifyStaff(
            ['orders.view', 'notifications.view'],
            'payment_confirmed_admin',
            'Payment Confirmed',
            "Payment confirmed for order {$order->order_number}.",
            $this->adminDeepLinkData($order, 'payment_confirmed'),
            'payment_order:'.$order->id,
        );
    }

    public function notifyCustomerCancelled(Order $order, bool $byCustomer = true): void
    {
        $this->notifyCustomerStatus($order, 'CANCELLED');
        if ($byCustomer) {
            $this->notifyStaff(
                ['orders.view', 'notifications.view'],
                'customer_cancelled',
                'Customer Cancelled Order',
                "Customer cancelled order {$order->order_number}.",
                $this->adminDeepLinkData($order, 'customer_cancelled'),
                'cancel_order:'.$order->id,
            );
        }
    }
}
