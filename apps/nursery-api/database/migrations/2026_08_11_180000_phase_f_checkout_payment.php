<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->string('coupon_code', 40);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->timestamps();
                $table->unique('order_id');
                $table->index(['coupon_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('payments')) {
            return;
        }

        $dupes = DB::table('payments')
            ->select('provider_payment_id')
            ->whereNotNull('provider_payment_id')
            ->where('provider_payment_id', '!=', '')
            ->groupBy('provider_payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('provider_payment_id');

        foreach ($dupes as $pid) {
            $ids = DB::table('payments')
                ->where('provider_payment_id', $pid)
                ->orderBy('id')
                ->pluck('id');
            $ids->shift();
            foreach ($ids as $id) {
                DB::table('payments')
                    ->where('id', $id)
                    ->update(['provider_payment_id' => $pid.'_dup_'.$id]);
            }
        }

        $driver = Schema::getConnection()->getDriverName();
        $indexExists = false;
        if ($driver === 'mysql') {
            $indexExists = collect(DB::select(
                'SHOW INDEX FROM payments WHERE Key_name = ?',
                ['payments_provider_payment_id_unique'],
            ))->isNotEmpty();
        } else {
            // SQLite / others: inspect via schema manager listing.
            foreach (Schema::getIndexes('payments') as $index) {
                if (($index['name'] ?? '') === 'payments_provider_payment_id_unique') {
                    $indexExists = true;
                    break;
                }
            }
        }

        if (! $indexExists) {
            // MySQL allows multiple NULLs in a UNIQUE column.
            Schema::table('payments', function (Blueprint $table) {
                $table->unique('provider_payment_id', 'payments_provider_payment_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');

        if (! Schema::hasTable('payments')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $indexExists = false;
        if ($driver === 'mysql') {
            $indexExists = collect(DB::select(
                'SHOW INDEX FROM payments WHERE Key_name = ?',
                ['payments_provider_payment_id_unique'],
            ))->isNotEmpty();
        } else {
            foreach (Schema::getIndexes('payments') as $index) {
                if (($index['name'] ?? '') === 'payments_provider_payment_id_unique') {
                    $indexExists = true;
                    break;
                }
            }
        }

        if ($indexExists) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique('payments_provider_payment_id_unique');
            });
        }
    }
};
