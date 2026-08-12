<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 18 — inventory ledger before/after, warehouse transfers, supplier-product links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'qty_before')) {
                $table->integer('qty_before')->nullable()->after('qty_delta');
            }
            if (! Schema::hasColumn('stock_movements', 'qty_after')) {
                $table->integer('qty_after')->nullable()->after('qty_before');
            }
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 40)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index(); // draft|in_transit|completed|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['stock_transfer_id', 'product_id']);
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('supplier_sku', 80)->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedInteger('moq')->nullable(); // minimum order quantity
            $table->string('status', 20)->default('active')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');

        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'qty_after')) {
                $table->dropColumn('qty_after');
            }
            if (Schema::hasColumn('stock_movements', 'qty_before')) {
                $table->dropColumn('qty_before');
            }
        });
    }
};
