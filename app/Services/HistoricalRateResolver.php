<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Report;

class HistoricalRateResolver
{
    public function __construct(
        private readonly FrankfurterService   $frankfurter,
        private readonly SyntheticRateService $synthetic,
    ) {}

    /**
     * Resolve historical rates for a report.
     * Tries Frankfurter first; falls back to synthetic if Frankfurter returns null
     * (unsupported currency or transient API failure). Always returns a result.
     */
    public function resolve(Report $report): ResolveResult
    {
        $dates    = $report->range->datePoints();
        $currency = $report->currency->code;

        $frankfurterRates = $this->frankfurter->getHistoricalRates($currency, $dates);

        if ($frankfurterRates !== null) {
            return new ResolveResult(
                rates:      $frankfurterRates,
                dataSource: 'frankfurter',
            );
        }

        return new ResolveResult(
            rates:      $this->synthetic->getRates($currency, $dates),
            dataSource: 'synthetic',
        );
    }
}
