<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 40)->index();
            $table->char('currency', 3)->default('INR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->string('coupon_code', 40)->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('shipping_address_json');
            $table->json('billing_address_json')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('request_id', 60)->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('sku', 80);
            $table->string('name', 200);
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);
            $table->string('product_type', 40)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('request_id', 60)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('method', 40);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('INR');
            $table->string('status', 30);
            $table->string('idempotency_key', 80)->unique();
            $table->string('provider_order_id', 120)->nullable();
            $table->string('provider_payment_id', 120)->nullable()->index();
            $table->string('provider_signature', 255)->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response_json')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('carrier', 80)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->date('eta_date')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('description', 255)->nullable();
            $table->string('location', 160)->nullable();
            $table->timestamp('event_at');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('moderated_by')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'user_id']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80);
            $table->string('name', 160)->nullable();
            $table->string('channel', 40);
            $table->string('locale', 10)->default('en');
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code', 'channel', 'locale']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('title', 200);
            $table->text('body');
            $table->json('data_json')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('subject', 200);
            $table->string('status', 40)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_staff')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->string('data_type', 20);
            $table->json('applies_to_product_types')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->string('unit', 30)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->foreignId('attribute_definition_id')->constrained('attribute_definitions')->cascadeOnDelete();
            $table->string('value_string', 255)->nullable();
            $table->decimal('value_number', 18, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'variant_id', 'attribute_definition_id'], 'attribute_values_unique');
        });

        Schema::create('schema_field_maps', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 80);
            $table->string('logical_field', 80);
            $table->string('physical_table', 80);
            $table->string('physical_column', 80);
            $table->string('data_type', 30);
            $table->string('api_field', 80)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['entity', 'logical_field']);
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 60)->index();
            $table->string('method', 10);
            $table->string('path', 255)->index();
            $table->string('route_name', 120)->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('request_headers_json')->nullable();
            $table->json('request_body_json')->nullable();
            $table->json('response_body_json')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('correlation_id', 60)->nullable();
            $table->string('endpoint_code', 40)->nullable();
            $table->boolean('is_success')->default(false);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->softDeletes();
        });

        Schema::create('api_outbound_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 60)->nullable();
            $table->string('provider', 40);
            $table->string('operation', 80);
            $table->string('http_method', 10)->nullable();
            $table->string('url', 500)->nullable();
            $table->smallInteger('status_code')->nullable();
            $table->json('request_body_json')->nullable();
            $table->json('response_body_json')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->string('error_message', 255)->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_outbound_logs');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('schema_field_maps');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_definitions');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
