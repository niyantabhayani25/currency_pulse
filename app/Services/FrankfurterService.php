<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrankfurterService
{
    private const PROVIDER = 'Frankfurter';

    public function __construct(
        private readonly string $baseUrl,
    ) {}

    /**
     * Fetch historical USD-based rates for a single currency on the given dates.
     * Returns an array keyed by date string; value is float or null (weekend/holiday).
     * Returns null on transient failure so the caller can fall back to synthetic data.
     */
    public function getHistoricalRates(string $currency, array $dates): ?array
    {
        if (empty($dates)) {
            return [];
        }

        $sorted = $dates;
        sort($sorted);
        $start = $sorted[0];
        $end   = $sorted[array_key_last($sorted)];

        // Extend fetch window 7 days back so prior business-day rates are available
        // when the requested range starts on a weekend or ECB holiday.
        $fetchStart = (new \DateTimeImmutable($start))->modify('-7 days')->format('Y-m-d');

        $cacheKey = "frankfurter.rates.{$currency}.{$fetchStart}.{$end}";

        $fetched = Cache::remember($cacheKey, now()->addHour(), function () use ($currency, $fetchStart, $end) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/{$fetchStart}..{$end}", [
                    'from' => 'USD',
                    'to'   => $currency,
                ]);
            } catch (ConnectionException) {
                return null;
            }

            if ($response->notFound()) {
                return null;
            }

            if ($response->serverError() || $response->failed()) {
                Log::warning(self::PROVIDER . ' historical rates transient failure', [
                    'currency'   => $currency,
                    'fetchStart' => $fetchStart,
                    'end'        => $end,
                    'status'     => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            $normalised = [];
            foreach ($data['rates'] ?? [] as $date => $pair) {
                $normalised[$date] = isset($pair[$currency]) ? (float) $pair[$currency] : null;
            }

            return $normalised;
        });

        if ($fetched === null) {
            return null;
        }

        // Frankfurter only publishes on business days. For weekends / ECB holidays,
        // use the nearest prior business-day rate. If the range starts on a non-business
        // day (no prior exists yet), fall forward to the next available rate instead.
        ksort($fetched);
        $available = array_filter($fetched, fn ($r) => $r !== null);

        $result = [];
        foreach ($dates as $date) {
            if (isset($fetched[$date]) && $fetched[$date] !== null) {
                $result[$date] = $fetched[$date];
                continue;
            }

            $nearest = null;
            foreach ($available as $d => $r) {
                if ($d <= $date) {
                    $nearest = $r;
                } else {
                    break;
                }
            }

            if ($nearest === null) {
                foreach ($available as $d => $r) {
                    if ($d >= $date) {
                        $nearest = $r;
                        break;
                    }
                }
            }

            $result[$date] = $nearest;
        }

        return $result;
    }

    /**
     * Return the latest USD-based rates for the given target currencies.
     * Response is keyed by currency code (e.g. ['EUR' => 0.923, 'GBP' => 0.783]).
     * Missing codes are null-filled. Returns null on transient failure.
     */
    public function getLatestRates(array $codes): ?array
    {
        if (empty($codes)) {
            return [];
        }

        $cacheKey = 'frankfurter.latest.' . md5(implode(',', $codes));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($codes) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/latest", [
                    'from' => 'USD',
                    'to'   => implode(',', $codes),
                ]);
            } catch (ConnectionException) {
                return null;
            }

            if ($response->serverError() || $response->failed()) {
                Log::warning(self::PROVIDER . ' /latest transient failure', [
                    'status' => $response->status(),
                    'codes'  => $codes,
                ]);

                return null;
            }

            $raw = $response->json()['rates'] ?? [];

            $result = [];
            foreach ($codes as $code) {
                $result[$code] = isset($raw[$code]) ? (float) $raw[$code] : null;
            }

            return $result;
        });
    }

    /**
     * Return all currency codes that Frankfurter supports.
     * Returns [] on failure so supports() safely returns false.
     */
    public function getSupportedCurrencies(): array
    {
        return Cache::remember('frankfurter.currencies', now()->addHours(24), function () {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/currencies");
            } catch (ConnectionException) {
                return [];
            }

            if ($response->failed()) {
                Log::warning(self::PROVIDER . ' /currencies fetch failed', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            return array_keys($response->json() ?? []);
        });
    }

    /**
     * Check whether Frankfurter covers the given currency code.
     */
    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->getSupportedCurrencies(), true);
    }
}
