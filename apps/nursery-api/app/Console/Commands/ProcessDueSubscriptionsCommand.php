<?php

namespace App\Console\Commands;

use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessDueSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:process-due {--limit=50 : Max subscriptions to process}';

    protected $description = 'Generate pay-per-cycle orders for due ACTIVE subscriptions (idempotent)';

    public function handle(SubscriptionService $subscriptions): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $result = $subscriptions->processDue($limit);
        $this->info(sprintf(
            'processed=%d skipped=%d failed=%d',
            $result['processed'],
            $result['skipped'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
