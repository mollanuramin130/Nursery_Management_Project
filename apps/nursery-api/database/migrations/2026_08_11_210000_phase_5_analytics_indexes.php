<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'orders_created_at_status_index');
            $table->index(['created_at', 'payment_method'], 'orders_created_at_payment_method_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['product_id', 'order_id'], 'order_items_product_order_index');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'refunds_created_at_status_index');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'return_requests_created_at_status_index');
        });

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(['created_at', 'type'], 'stock_movements_created_at_type_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_created_at_status_index');
            $table->dropIndex('orders_created_at_payment_method_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_product_order_index');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex('refunds_created_at_status_index');
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex('return_requests_created_at_status_index');
        });

        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dropIndex('stock_movements_created_at_type_index');
            });
        }
    }
};
