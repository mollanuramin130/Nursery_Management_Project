<?php

namespace App\Console\Commands;

use App\Modules\Marketing\Services\CustomerSegmentService;
use App\Modules\Marketing\Services\MarketingAutomationService;
use Illuminate\Console\Command;

class MarketingSeedCommand extends Command
{
    protected $signature = 'marketing:seed';

    protected $description = 'Seed default CRM segments and marketing automations';

    public function handle(CustomerSegmentService $segments, MarketingAutomationService $automations): int
    {
        $segments->seedDefaults();
        $automations->seedDefaults();
        $this->info('Marketing defaults seeded.');

        return self::SUCCESS;
    }
}
