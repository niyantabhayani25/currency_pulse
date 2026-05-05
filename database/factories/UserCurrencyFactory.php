<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use App\Models\User;
use App\Models\UserCurrency;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserCurrencyFactory extends Factory
{
    protected $model = UserCurrency::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'currency_id' => Currency::factory(),
        ];
    }
}
