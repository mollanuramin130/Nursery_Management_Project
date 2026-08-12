<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['product_id', 'status', 'id'], 'reviews_product_status_id_index');
            $table->index(['user_id', 'id'], 'reviews_user_id_index');
            $table->index(['status', 'created_at'], 'reviews_status_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_product_status_id_index');
            $table->dropIndex('reviews_user_id_index');
            $table->dropIndex('reviews_status_created_at_index');
        });
    }
};
