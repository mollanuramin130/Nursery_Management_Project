<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side catalog search logging for Admin analytics.
 * Does not store passwords/tokens. Query truncated; optional user_id only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_events', function (Blueprint $table) {
            $table->id();
            $table->string('query', 200);
            $table->string('normalized_query', 200)->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform', 40)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['normalized_query', 'created_at']);
            $table->index(['results_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_events');
    }
};
