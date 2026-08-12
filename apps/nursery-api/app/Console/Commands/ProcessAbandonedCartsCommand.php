<?php

namespace App\Console\Commands;

use App\Modules\Marketing\Services\MarketingAutomationService;
use Illuminate\Console\Command;

class ProcessAbandonedCartsCommand extends Command
{
    protected $signature = 'marketing:process-abandoned-carts {--limit=100}';

    protected $description = 'Send abandoned-cart recovery messages for eligible carts';

    public function handle(MarketingAutomationService $marketing): int
    {
        $result = $marketing->processAbandonedCarts((int) $this->option('limit'));
        $this->info(json_encode($result));

        return self::SUCCESS;
    }
}
