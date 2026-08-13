<?php

namespace Tests\Support;

/**
 * Isolate payment tests from host .env Razorpay TEST keys.
 * Laravel env() reads putenv / $_ENV / $_SERVER — all three must be cleared.
 */
trait ClearsRazorpayEnv
{
    protected function clearRazorpayEnv(): void
    {
        foreach (['RAZORPAY_KEY', 'RAZORPAY_SECRET', 'RAZORPAY_WEBHOOK_SECRET'] as $name) {
            putenv($name.'=');
            $_ENV[$name] = '';
            $_SERVER[$name] = '';
        }
    }

    /**
     * Host .env presence (not process env — stub tests clear $_SERVER/$_ENV).
     * Never returns secret values to callers beyond empty/non-empty checks in tests.
     *
     * @return array{key:string,secret:string,webhook:string}
     */
    protected function razorpayEnvPresence(): array
    {
        $path = base_path('.env');
        $map = ['RAZORPAY_KEY' => '', 'RAZORPAY_SECRET' => '', 'RAZORPAY_WEBHOOK_SECRET' => ''];
        if (! is_file($path)) {
            return ['key' => '', 'secret' => '', 'webhook' => ''];
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            foreach (array_keys($map) as $name) {
                if (str_starts_with($line, $name.'=')) {
                    $map[$name] = trim(substr($line, strlen($name) + 1), " \t\"'");
                }
            }
        }

        return [
            'key' => $map['RAZORPAY_KEY'],
            'secret' => $map['RAZORPAY_SECRET'],
            'webhook' => $map['RAZORPAY_WEBHOOK_SECRET'],
        ];
    }

    protected function razorpayTestCredentialsConfigured(): bool
    {
        $p = $this->razorpayEnvPresence();

        return $p['key'] !== ''
            && $p['secret'] !== ''
            && $p['webhook'] !== ''
            && str_starts_with($p['key'], 'rzp_test_')
            && ! str_starts_with($p['key'], 'rzp_live_');
    }
}
