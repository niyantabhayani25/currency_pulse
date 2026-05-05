<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class CurrencySeeder extends Seeder
{
    // Symbols for the most common currencies — null for everything else
    private const SYMBOLS = [
        'AED' => 'د.إ', 'AUD' => 'A$',  'BRL' => 'R$',  'CAD' => 'C$',
        'CHF' => 'Fr',  'CNY' => '¥',   'CZK' => 'Kč',  'DKK' => 'kr',
        'EUR' => '€',   'GBP' => '£',   'HKD' => 'HK$', 'HUF' => 'Ft',
        'IDR' => 'Rp',  'ILS' => '₪',   'INR' => '₹',   'JPY' => '¥',
        'KRW' => '₩',   'MXN' => '$',   'MYR' => 'RM',  'NOK' => 'kr',
        'NZD' => 'NZ$', 'PHP' => '₱',   'PLN' => 'zł',  'SEK' => 'kr',
        'SGD' => 'S$',  'THB' => '฿',   'TRY' => '₺',   'USD' => '$',
        'ZAR' => 'R',
    ];

    // Static fallback used when Frankfurter is unreachable (offline dev, CI)
    private const FALLBACK = [
        'AED' => 'UAE Dirham',          'AUD' => 'Australian Dollar',
        'BRL' => 'Brazilian Real',      'CAD' => 'Canadian Dollar',
        'CHF' => 'Swiss Franc',         'CNY' => 'Chinese Yuan',
        'CZK' => 'Czech Koruna',        'DKK' => 'Danish Krone',
        'EUR' => 'Euro',                'GBP' => 'British Pound',
        'HKD' => 'Hong Kong Dollar',    'HUF' => 'Hungarian Forint',
        'IDR' => 'Indonesian Rupiah',   'ILS' => 'Israeli Shekel',
        'INR' => 'Indian Rupee',        'JPY' => 'Japanese Yen',
        'KRW' => 'South Korean Won',    'MXN' => 'Mexican Peso',
        'MYR' => 'Malaysian Ringgit',   'NOK' => 'Norwegian Krone',
        'NZD' => 'New Zealand Dollar',  'PHP' => 'Philippine Peso',
        'PLN' => 'Polish Zloty',        'SEK' => 'Swedish Krona',
        'SGD' => 'Singapore Dollar',    'THB' => 'Thai Baht',
        'TRY' => 'Turkish Lira',        'USD' => 'US Dollar',
        'ZAR' => 'South African Rand',
    ];

    public function run(): void
    {
        $currencies = $this->fetchFromFrankfurter() ?? self::FALLBACK;

        $rows = array_map(
            fn (string $code, string $name) => [
                'code'   => $code,
                'name'   => $name,
                'symbol' => self::SYMBOLS[$code] ?? null,
            ],
            array_keys($currencies),
            array_values($currencies)
        );

        Currency::upsert($rows, uniqueBy: ['code'], update: ['name', 'symbol']);

        $this->command->info(sprintf('Seeded %d currencies.', count($rows)));
    }

    /**
     * Fetch currency list from Frankfurter (free, no API key).
     * Returns null if unreachable so caller falls back to static list.
     *
     * @return array<string, string>|null
     */
    private function fetchFromFrankfurter(): ?array
    {
        try {
            $response = Http::timeout(5)->get('https://api.frankfurter.app/currencies');

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable) {
            // Network unavailable — fall through to static fallback
        }

        return null;
    }
}
