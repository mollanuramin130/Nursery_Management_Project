<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // MySQL allows multiple NULLs under UNIQUE — tracking numbers must be unique when set.
            $table->unique('tracking_number', 'shipments_tracking_number_unique');
            $table->index(['status', 'created_at'], 'shipments_status_created_idx');
            $table->index('carrier', 'shipments_carrier_idx');
        });

        Schema::table('shipment_events', function (Blueprint $table) {
            $table->index(['shipment_id', 'event_at'], 'shipment_events_shipment_event_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropIndex('shipment_events_shipment_event_at_idx');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique('shipments_tracking_number_unique');
            $table->dropIndex('shipments_status_created_idx');
            $table->dropIndex('shipments_carrier_idx');
        });
    }
};
