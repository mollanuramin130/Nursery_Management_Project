<?php

namespace App\Console\Commands;

use App\Shared\Support\ProductionReadinessChecker;
use Illuminate\Console\Command;

class ProductionReadinessCommand extends Command
{
    protected $signature = 'nursery:production-readiness
                            {--strict : Exit 1 when any P0 finding exists (use on production hosts)}';

    protected $description = 'QA-16: Evaluate production readiness (debug, Razorpay, CORS, JWT, HTTPS)';

    public function handle(ProductionReadinessChecker $checker): int
    {
        $env = (string) config('app.env');
        $findings = $checker->evaluate();

        if ($this->option('strict') && $env !== 'production') {
            $this->error('Strict production gate requires APP_ENV=production (current: '.$env.').');
            $this->line('Use without --strict on local/staging to inspect profile findings.');

            return self::FAILURE;
        }

        if ($findings === []) {
            $this->info('Production readiness: no P0 findings for current environment profile.');
            $this->line('env='.$env.' debug='.(config('app.debug') ? 'true' : 'false'));

            return self::SUCCESS;
        }

        $this->warn('Production readiness findings ('.count($findings).'):');
        foreach ($findings as $f) {
            $this->line(sprintf('[%s] %s — %s', $f['severity'], $f['code'], $f['message']));
        }

        if ($this->option('strict') && ! $checker->isReady()) {
            return self::FAILURE;
        }

        // Non-strict: always success so local/dev can inspect without failing CI smoke.
        return self::SUCCESS;
    }
}
