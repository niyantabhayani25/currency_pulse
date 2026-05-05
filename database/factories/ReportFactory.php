<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportRange;
use App\Enums\ReportStatus;
use App\Models\Currency;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $range = $this->faker->randomElement(ReportRange::cases());

        return [
            'user_id'     => User::factory(),
            'currency_id' => Currency::factory(),
            'range'       => $range->value,
            'interval'    => $range->allowedInterval()->value,
            'status'      => ReportStatus::Pending->value,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'       => ReportStatus::Completed->value,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status'        => ReportStatus::Failed->value,
            'error_message' => 'CurrencyLayer API error.',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => ReportStatus::Processing->value,
        ]);
    }

    public function forRange(ReportRange $range): static
    {
        return $this->state(fn () => [
            'range'    => $range->value,
            'interval' => $range->allowedInterval()->value,
        ]);
    }
}
