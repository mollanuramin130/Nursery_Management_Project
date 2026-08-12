<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->timestamp('decided_at')->nullable()->after('notes');
            $table->unsignedBigInteger('decided_by')->nullable()->after('decided_at');
            $table->timestamp('received_at')->nullable()->after('decided_by');
            $table->timestamp('completed_at')->nullable()->after('received_at');
            $table->index(['status', 'created_at'], 'return_requests_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropIndex('return_requests_status_created_idx');
            $table->dropColumn(['decided_at', 'decided_by', 'received_at', 'completed_at']);
        });
    }
};
