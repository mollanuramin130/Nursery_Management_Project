<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 17 — CRM segments, marketing automations, deliveries, attribution links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('criteria_json');
            $table->boolean('is_system')->default(false);
            $table->string('status', 20)->default('active')->index(); // active|archived
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_automations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 160);
            $table->string('type', 40)->index(); // abandoned_cart|welcome|post_purchase|reactivation|manual_blast
            $table->string('status', 20)->default('draft')->index(); // draft|active|paused|archived
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->json('channels_json')->nullable(); // ["in_app","email","push"]
            $table->json('config_json')->nullable();
            $table->string('title_template', 200)->nullable();
            $table->text('body_template')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('marketing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->nullable()->constrained('marketing_automations')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20)->default('in_app');
            $table->string('status', 20)->default('queued')->index(); // queued|sent|failed|skipped
            $table->string('idempotency_key', 190)->unique();
            $table->unsignedBigInteger('notification_id')->nullable()->index();
            $table->string('skip_reason', 120)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['automation_id', 'user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('coupon_code')
                    ->constrained('campaigns')->nullOnDelete();
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('status')
                    ->constrained('campaigns')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
        });
        Schema::dropIfExists('marketing_deliveries');
        Schema::dropIfExists('marketing_automations');
        Schema::dropIfExists('customer_segments');
    }
};
