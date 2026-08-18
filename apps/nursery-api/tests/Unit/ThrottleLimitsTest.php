<?php

namespace Tests\Unit;

use App\Shared\Support\ThrottleLimits;
use Tests\TestCase;

class ThrottleLimitsTest extends TestCase
{
    public function test_env_override_wins_when_positive(): void
    {
        config(['throttling.api_per_minute' => 77]);
        $this->assertSame(77, ThrottleLimits::apiPerMinute());
    }

    public function test_zero_override_uses_testing_default(): void
    {
        config(['throttling.api_per_minute' => 0]);
        $this->assertSame(10000, ThrottleLimits::apiPerMinute());
        $this->assertSame(120, ThrottleLimits::loginPerMinute());
    }
}
