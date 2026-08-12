<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('frequency', 20)->index(); // WEEKLY|BIWEEKLY|MONTHLY|QUARTERLY|YEARLY
            $table->unsignedInteger('quantity_default')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('draft')->index(); // draft|active|archived
            $table->unsignedInteger('max_cycles')->nullable();
            $table->string('description', 1000)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_number', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('frequency', 20);
            $table->decimal('unit_price', 12, 2); // locked at create
            $table->char('currency', 3)->default('INR');
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->json('shipping_address_json');
            $table->json('billing_address_json')->nullable();
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->string('payment_method', 40)->default('razorpay');
            $table->unsignedInteger('cycle_count')->default(0);
            $table->timestamp('next_billing_at')->nullable()->index();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->unsignedInteger('failed_payment_count')->default(0);
            $table->unsignedInteger('max_failed_payments')->default(3);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'next_billing_at']);
        });

        Schema::create('subscription_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->timestamp('scheduled_at');
            $table->string('status', 30)->index();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'cycle_number']);
            $table->unique('order_id');
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('event_type', 40)->index();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subscription_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->after('warehouse_id');
            $table->unsignedBigInteger('subscription_cycle_id')->nullable()->after('subscription_id');
            $table->index('subscription_id');
            $table->index('subscription_cycle_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['subscription_id']);
            $table->dropIndex(['subscription_cycle_id']);
            $table->dropColumn(['subscription_id', 'subscription_cycle_id']);
        });
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscription_cycles');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
