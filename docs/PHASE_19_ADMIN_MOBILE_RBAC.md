# PHASE 19 — Admin Mobile RBAC

Backend remains authoritative. UI uses permission slugs from `/auth/me`.

## Staff gate

Login succeeds only if user matches Admin Web staff rules (`super_admin`, `admin`, managers, etc.) — not customer-only.

## Feature gates (examples)

| Feature | Permissions (any) |
|---------|-------------------|
| Orders tab | `orders.view` |
| Status update | `orders.update_status` |
| Inventory tab | `inventory.view` |
| Adjust | `inventory.adjust` |
| POs | `purchase_orders.view` / `inventory.adjust` |
| Receive | `inventory.adjust` / `purchase_orders.manage` / `inventory.receive` |
| Suppliers | `suppliers.view` / `inventory.adjust` |
| Notifications | `notifications.view` |

Hidden buttons are UX only — API returns 403 if unauthorized.
