<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('category', 20)->default('transactional')->after('type');
            $table->string('idempotency_key', 160)->nullable()->after('meta');
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
            $table->index(['user_id', 'created_at'], 'notifications_user_created_index');
            $table->unique('idempotency_key', 'notifications_idempotency_key_unique');
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 30)->default('pending');
            $table->string('provider', 40)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'channel'], 'notification_deliveries_unique_channel');
            $table->index(['status', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_idempotency_key_unique');
            $table->dropIndex('notifications_user_read_created_index');
            $table->dropIndex('notifications_user_created_index');
            $table->dropColumn(['category', 'idempotency_key']);
        });
    }
};
