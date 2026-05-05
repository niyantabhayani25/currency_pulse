<?php

declare(strict_types=1);

namespace App\Services;

class SyntheticRateService
{
    private const EPOCH       = '2020-01-01';
    private const DAILY_SWING = 0.02;   // ±1% drift per day
    private const RATE_MIN    = 0.01;
    private const RATE_MAX    = 1000.0;

    /**
     * Generate deterministic synthetic USD-based rates for every requested date.
     * Returns ['2024-01-15' => 0.923, ...] — always float, never null.
     *
     * Determinism guarantee: same currency + date always yields the same float,
     * regardless of call order or how many other dates are in the array.
     */
    public function getRates(string $currency, array $dates): array
    {
        if (empty($dates)) {
            return [];
        }

        $epoch  = new \DateTimeImmutable(self::EPOCH);
        $anchor = $this->anchorRate($currency);

        // Walk from epoch to the latest requested date once, accumulating drift.
        // This is O(days-from-epoch) instead of O(dates × days-from-epoch).
        $sorted = $dates;
        sort($sorted);

        $maxDate   = new \DateTimeImmutable($sorted[array_key_last($sorted)]);
        $totalDays = (int) $epoch->diff($maxDate)->days;

        $lookup          = array_flip($dates); // O(1) membership test
        $result          = [];
        $cumulativeDrift = 0.0;
        $cursor          = $epoch;

        for ($day = 0; $day <= $totalDays; $day++) {
            $dateStr = $cursor->format('Y-m-d');

            // Deterministic per-day drift: hash of "{currency}_{date}".
            $seed            = abs(crc32("{$currency}_{$dateStr}"));
            $drift           = (($seed % 1000) / 1000 - 0.5) * self::DAILY_SWING;
            $cumulativeDrift += $drift;

            if (isset($lookup[$dateStr])) {
                $rate            = $anchor * (1.0 + $cumulativeDrift);
                $result[$dateStr] = max(self::RATE_MIN, min(self::RATE_MAX, $rate));
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $result;
    }

    /**
     * Stable per-currency base rate in [0.5, 1.5], derived from the currency code alone.
     */
    private function anchorRate(string $currency): float
    {
        $seed = abs(crc32(strtolower($currency)));

        return 0.5 + ($seed % 10_000) / 10_000;
    }
}
