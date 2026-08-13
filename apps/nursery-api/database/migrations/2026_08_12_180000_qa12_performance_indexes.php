<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QA-12 — Additive indexes for reserve/release lookup and payment dashboards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(
                ['reference_type', 'reference_id', 'type', 'product_id'],
                'stock_movements_ref_lookup_index'
            );
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'payments_created_at_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_ref_lookup_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_created_at_status_index');
        });
    }
};
