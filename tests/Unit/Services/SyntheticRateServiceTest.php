<?php

declare(strict_types=1);

use App\Services\SyntheticRateService;

$service = new SyntheticRateService();

it('returns a float for every requested date', function () use ($service) {
    $dates = ['2024-01-15', '2024-03-01', '2024-06-20'];
    $rates = $service->getRates('EUR', $dates);

    foreach ($dates as $date) {
        expect($rates)->toHaveKey($date);
        expect($rates[$date])->toBeFloat();
    }
});

it('is deterministic — same currency and date always produces the same rate', function () use ($service) {
    $first  = $service->getRates('JPY', ['2024-01-15']);
    $second = $service->getRates('JPY', ['2024-01-15']);

    expect($first['2024-01-15'])->toBe($second['2024-01-15']);
});

it('produces different rates for different currencies on the same date', function () use ($service) {
    $eur = $service->getRates('EUR', ['2024-01-15'])['2024-01-15'];
    $jpy = $service->getRates('JPY', ['2024-01-15'])['2024-01-15'];

    expect($eur)->not->toBe($jpy);
});

it('clamps rates to the configured minimum', function () use ($service) {
    foreach (['EUR', 'USD', 'JPY', 'XYZ', 'AAA'] as $code) {
        $rates = $service->getRates($code, ['2023-01-01', '2024-06-15', '2025-03-20']);
        foreach ($rates as $rate) {
            expect($rate)->toBeGreaterThanOrEqual(0.01);
            expect($rate)->toBeLessThanOrEqual(1000.0);
        }
    }
});

it('returns an empty array for empty date input', function () use ($service) {
    expect($service->getRates('EUR', []))->toBe([]);
});

it('result contains exactly the requested dates', function () use ($service) {
    $dates = ['2024-02-01', '2024-03-15', '2024-04-20'];
    $rates = $service->getRates('GBP', $dates);

    expect(array_keys($rates))->toBe($dates);
});
