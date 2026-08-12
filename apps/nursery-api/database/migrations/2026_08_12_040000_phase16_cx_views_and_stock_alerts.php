<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 — product view events (recently viewed + analytics signal).
 * Best-effort; never required for commerce success.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('platform', 40)->nullable();
            $table->timestamp('viewed_at')->useCurrent()->index();

            $table->index(['user_id', 'viewed_at']);
            $table->index(['guest_token', 'viewed_at']);
            $table->index(['product_id', 'viewed_at']);
        });

        Schema::create('stock_alert_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('status', 20)->default('active')->index(); // active|notified|cancelled
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alert_subscriptions');
        Schema::dropIfExists('product_views');
    }
};
