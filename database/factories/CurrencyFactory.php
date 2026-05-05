<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    private static array $currencies = [
        ['code' => 'EUR', 'name' => 'Euro',           'symbol' => '€'],
        ['code' => 'GBP', 'name' => 'British Pound',  'symbol' => '£'],
        ['code' => 'JPY', 'name' => 'Japanese Yen',   'symbol' => '¥'],
        ['code' => 'INR', 'name' => 'Indian Rupee',   'symbol' => '₹'],
        ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
        ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
        ['code' => 'CHF', 'name' => 'Swiss Franc',    'symbol' => 'Fr'],
    ];

    public function definition(): array
    {
        static $index = 0;
        $currency = self::$currencies[$index % count(self::$currencies)];
        $index++;

        return [
            'code'   => $currency['code'],
            'name'   => $currency['name'],
            'symbol' => $currency['symbol'],
        ];
    }

    public function eur(): static
    {
        return $this->state(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);
    }
}
