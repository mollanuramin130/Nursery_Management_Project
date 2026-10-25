<?php

namespace App\Console\Commands;

use Database\Seeders\NuvikozCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;

class SeedNuvikozCatalog extends Command
{
    use ConfirmableTrait;

    protected $signature = 'catalog:seed-nuvikoz {--dry-run : Validate inserts and roll back the transaction} {--force : Run in production}';
    protected $description = 'Add the Nuvikoz starter plants without replacing existing data or inventing stock/reviews';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        // Serialise repeated imports on this deployment, including preview runs.
        $result = Cache::lock('nuvikoz:catalog-seed', 120)->block(5,
            fn () => app(NuvikozCatalogSeeder::class)->import($dryRun));
        $this->info(($dryRun ? 'PREVIEW (rolled back): ' : 'IMPORTED: ')."{$result['created']} new plants; {$result['skipped']} existing plants skipped.");
        $this->line('New plants are published with zero stock and no customer ratings. Verify each price, add actual inventory and set stock status in Admin.');

        return self::SUCCESS;
    }
}
