<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CurrencyProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyLayerService
{
    private const PROVIDER = 'CurrencyLayer';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * Return the full currency list keyed by code (e.g. ['USD' => 'United States Dollar']).
     * Hard failure (success:false from API) throws; transient failure returns [].
     */
    public function getCurrencyList(): array
    {
        return Cache::remember('currency_layer.list', now()->addHours(24), function () {
            $response = Http::timeout(10)->get("{$this->baseUrl}/list", [
                'access_key' => $this->apiKey,
            ]);

            if ($response->serverError() || $response->failed()) {
                Log::warning(self::PROVIDER . ' /list transient failure', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $data = $response->json();

            if (empty($data['success'])) {
                throw CurrencyProviderException::fromApiError(
                    self::PROVIDER,
                    $data['error']['info'] ?? 'Unknown error from /list'
                );
            }

            return $data['currencies'] ?? [];
        });
    }

    /**
     * Return live USD-based rates for the requested codes.
     * Keys are the target currency codes (e.g. ['EUR' => 1.08, 'GBP' => 0.79]).
     * Missing codes are null-filled. Transient failures return null; hard failures throw.
     */
    public function getLiveRates(array $codes): ?array
    {
        $cacheKey = 'currency_layer.live.' . md5(implode(',', $codes));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($codes) {
            $response = Http::timeout(10)->get("{$this->baseUrl}/live", [
                'access_key'  => $this->apiKey,
                'currencies'  => implode(',', $codes),
            ]);

            if ($response->serverError() || $response->failed()) {
                Log::warning(self::PROVIDER . ' /live transient failure', [
                    'status' => $response->status(),
                    'codes'  => $codes,
                ]);

                return null;
            }

            $data = $response->json();

            if (empty($data['success'])) {
                throw CurrencyProviderException::fromApiError(
                    self::PROVIDER,
                    $data['error']['info'] ?? 'Unknown error from /live'
                );
            }

            // CurrencyLayer returns rates prefixed with the source currency (e.g. 'USDEUR').
            // Normalise to bare currency codes.
            $raw = $data['quotes'] ?? [];
            $normalised = [];

            foreach ($codes as $code) {
                $key = 'USD' . strtoupper($code);
                $normalised[$code] = isset($raw[$key]) ? (float) $raw[$key] : null;
            }

            return $normalised;
        });
    }

    /**
     * Convenience wrapper — returns the live USD rate for a single currency.
     */
    public function getCurrentRate(string $currency): ?float
    {
        $rates = $this->getLiveRates([$currency]);

        return $rates[$currency] ?? null;
    }
}
