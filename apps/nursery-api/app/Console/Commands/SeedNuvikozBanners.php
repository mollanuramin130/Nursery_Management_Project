<?php

namespace App\Console\Commands;

use Database\Seeders\NuvikozBannerSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;

class SeedNuvikozBanners extends Command
{
    use ConfirmableTrait;

    protected $signature = 'banners:seed-nuvikoz {--dry-run : Validate and roll back banner inserts} {--force : Run in production}';
    protected $description = 'Add three Nuvikoz home banners without overwriting existing banners';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        $result = Cache::lock('nuvikoz:banner-seed', 120)->block(5,
            fn () => app(NuvikozBannerSeeder::class)->import($dryRun));
        $this->info(($dryRun ? 'PREVIEW (rolled back): ' : 'IMPORTED: ')."{$result['created']} new banners; {$result['skipped']} existing banners skipped.");

        return self::SUCCESS;
    }
}
