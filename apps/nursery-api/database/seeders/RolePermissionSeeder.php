<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\Permission;
use App\Modules\Auth\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Customer', 'slug' => 'customer'],
            ['name' => 'Super Admin', 'slug' => 'super_admin'],
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Nursery Manager', 'slug' => 'nursery_manager'],
            ['name' => 'Inventory Manager', 'slug' => 'inventory_manager'],
            ['name' => 'Sales Manager', 'slug' => 'sales_manager'],
            ['name' => 'Order Manager', 'slug' => 'order_manager'],
            ['name' => 'Delivery Manager', 'slug' => 'delivery_manager'],
            ['name' => 'Customer Support', 'slug' => 'customer_support'],
            ['name' => 'Content Manager', 'slug' => 'content_manager'],
            ['name' => 'Supplier', 'slug' => 'supplier'],
            ['name' => 'Accountant', 'slug' => 'accountant'],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(
                ['slug' => $role['slug']],
                ['name' => $role['name']],
            );
        }

        $permissions = [
            'products.read',
            'products.write',
            'products.publish',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.receive',
            'warehouses.view',
            'warehouses.manage',
            'suppliers.view',
            'suppliers.manage',
            'purchase_orders.view',
            'purchase_orders.manage',
            'orders.view',
            'orders.update_status',
            'orders.cancel',
            'fulfillment.view',
            'fulfillment.pick',
            'fulfillment.pack',
            'fulfillment.ship',
            'fulfillment.manage',
            'returns.view',
            'returns.approve',
            'returns.reject',
            'returns.inspect',
            'returns.manage',
            'payments.refund',
            'campaigns.manage',
            'users.manage',
            'customers.view',
            'customers.segment',
            'marketing.view',
            'marketing.manage',
            'marketing.launch',
            'reports.view',
            'reports.export',
            'reviews.view',
            'reviews.moderate',
            'loyalty.view',
            'loyalty.adjust',
            'subscriptions.view',
            'subscriptions.manage',
            'notifications.view',
            'notifications.send',
            'notifications.manage_templates',
        ];

        foreach ($permissions as $slug) {
            Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => str_replace('.', ' ', ucwords($slug, '.'))],
            );
        }

        $admin = Role::query()->where('slug', 'admin')->first();
        $super = Role::query()->where('slug', 'super_admin')->first();
        $allPermissionIds = Permission::query()->pluck('id');

        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($allPermissionIds);
        }
        if ($super) {
            $super->permissions()->syncWithoutDetaching($allPermissionIds);
        }

        Role::query()->where('slug', 'inventory_manager')->first()
            ?->permissions()
            ->syncWithoutDetaching(
                Permission::query()->whereIn('slug', [
                    'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.receive',
                    'warehouses.view', 'warehouses.manage',
                    'suppliers.view', 'suppliers.manage',
                    'purchase_orders.view', 'purchase_orders.manage',
                    'products.read',
                ])->pluck('id')
            );

        Role::query()->where('slug', 'order_manager')->first()
            ?->permissions()
            ->syncWithoutDetaching(
                Permission::query()->whereIn('slug', [
                    'orders.view', 'orders.update_status', 'orders.cancel',
                    'fulfillment.view', 'fulfillment.pick', 'fulfillment.pack', 'fulfillment.ship', 'fulfillment.manage',
                    'returns.view', 'returns.approve', 'returns.reject', 'returns.inspect', 'returns.manage',
                    'payments.refund',
                    'subscriptions.view', 'subscriptions.manage',
                ])->pluck('id')
            );

        Role::query()->where('slug', 'delivery_manager')->first()
            ?->permissions()
            ->syncWithoutDetaching(
                Permission::query()->whereIn('slug', [
                    'orders.view',
                    'fulfillment.view', 'fulfillment.ship', 'fulfillment.manage',
                ])->pluck('id')
            );

        Role::query()->where('slug', 'content_manager')->first()
            ?->permissions()
            ->syncWithoutDetaching(
                Permission::query()->whereIn('slug', [
                    'products.read', 'products.write',
                    'reviews.view', 'reviews.moderate',
                    'campaigns.manage',
                    'marketing.view', 'marketing.manage', 'marketing.launch',
                    'customers.view', 'customers.segment',
                ])->pluck('id')
            );

        Role::query()->where('slug', 'customer_support')->first()
            ?->permissions()
            ->syncWithoutDetaching(
                Permission::query()->whereIn('slug', [
                    'orders.view',
                    'customers.view',
                    'reviews.view', 'reviews.moderate',
                    'loyalty.view', 'loyalty.adjust',
                    'subscriptions.view', 'subscriptions.manage',
                    'notifications.view',
                    'returns.view',
                ])->pluck('id')
            );
    }
}
