<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum ReportRange: string
{
    case OneYear   = 'one_year';
    case SixMonths = 'six_months';
    case OneMonth  = 'one_month';

    public function allowedInterval(): ReportInterval
    {
        return match ($this) {
            self::OneYear   => ReportInterval::Monthly,
            self::SixMonths => ReportInterval::Weekly,
            self::OneMonth  => ReportInterval::Daily,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OneYear   => 'One Year · Monthly',
            self::SixMonths => 'Six Months · Weekly',
            self::OneMonth  => 'One Month · Daily',
        };
    }

    /**
     * Returns Y-m-d date strings in ascending order.
     * OneYear:   12 dates — 1st of each month going back 12 months
     * SixMonths: 26 dates — each Monday going back 26 weeks
     * OneMonth:  30 dates — each calendar day going back 30 days
     *
     * @return string[]
     */
    public function datePoints(): array
    {
        $today = CarbonImmutable::today();

        return match ($this) {
            self::OneYear => array_map(
                fn (int $i) => $today->startOfMonth()->subMonths($i)->format('Y-m-d'),
                range(11, 0)
            ),
            self::SixMonths => array_map(
                fn (int $i) => $today->startOfWeek()->subWeeks($i)->format('Y-m-d'),
                range(25, 0)
            ),
            self::OneMonth => array_map(
                fn (int $i) => $today->subDays($i)->format('Y-m-d'),
                range(29, 0)
            ),
        };
    }

    /**
     * Structured array consumed by DashboardController and the Vue rangePairs prop.
     *
     * @return array<int, array{range: string, interval: string, label: string}>
     */
    public static function rangePairs(): array
    {
        return array_map(
            fn (self $case) => [
                'range'    => $case->value,
                'interval' => $case->allowedInterval()->value,
                'label'    => $case->label(),
            ],
            self::cases()
        );
    }
}
