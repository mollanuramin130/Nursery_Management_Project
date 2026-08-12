<?php

namespace App\Modules\Admin\Support;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Inclusive calendar-day range in app timezone (Asia/Kolkata by default).
 * Interval semantics: [startOfDay(from), endOfDay(to)] inclusive.
 */
final class AnalyticsDateRange
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $preset,
    ) {}

    public static function fromRequest(?string $from, ?string $to, ?string $preset = null): self
    {
        $tz = config('app.timezone', 'Asia/Kolkata');
        $now = Carbon::now($tz);
        $preset = $preset ?: (($from || $to) ? 'custom' : 'last_30_days');

        [$start, $end] = match ($preset) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay(),
            ],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfDay()],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            'this_year' => [$now->copy()->startOfYear()->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [
                $from
                    ? Carbon::parse($from, $tz)->startOfDay()
                    : $now->copy()->subDays(29)->startOfDay(),
                $to
                    ? Carbon::parse($to, $tz)->endOfDay()
                    : $now->copy()->endOfDay(),
            ],
            default => throw new InvalidArgumentException('Invalid analytics date preset'),
        };

        if ($start->gt($end)) {
            throw new InvalidArgumentException('from must be on or before to');
        }

        // Cap range to 366 days to protect analytics queries.
        if ($start->diffInDays($end) > 366) {
            throw new InvalidArgumentException('Date range cannot exceed 366 days');
        }

        return new self($start, $end, $preset);
    }

    public function previous(): self
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;
        $prevEnd = $this->from->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return new self($prevStart, $prevEnd, 'previous_period');
    }

    /**
     * @return array{from: string, to: string, preset: string, timezone: string}
     */
    public function meta(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'preset' => $this->preset,
            'timezone' => (string) config('app.timezone', 'Asia/Kolkata'),
            'interval' => 'inclusive_calendar_days',
        ];
    }

    public function granularity(): string
    {
        $days = (int) $this->from->diffInDays($this->to) + 1;
        if ($days <= 31) {
            return 'day';
        }
        if ($days <= 120) {
            return 'week';
        }

        return 'month';
    }
}
