<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 20 — additive delivery ops on existing shipments (no parallel deliveries table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'assigned_driver_user_id')) {
                $table->foreignId('assigned_driver_user_id')
                    ->nullable()
                    ->after('warehouse_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('assigned_driver_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'assigned_driver_user_id')) {
                $table->dropConstrainedForeignId('assigned_driver_user_id');
            }
        });
    }
};
