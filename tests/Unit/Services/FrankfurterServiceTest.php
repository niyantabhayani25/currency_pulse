<?php

declare(strict_types=1);

use App\Services\FrankfurterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

function makeFrankfurterService(): FrankfurterService
{
    return new FrankfurterService(baseUrl: 'http://fake-frankfurter.test');
}

// ── getHistoricalRates ────────────────────────────────────────────────────────

it('normalises time-series response to date-keyed floats', function () {
    Http::fake([
        'fake-frankfurter.test/*' => Http::response([
            'rates' => [
                '2024-01-15' => ['EUR' => 1.0821],
                '2024-01-16' => ['EUR' => 1.0834],
            ],
        ]),
    ]);

    $rates = makeFrankfurterService()->getHistoricalRates('EUR', ['2024-01-15', '2024-01-16']);

    expect($rates)->toBe(['2024-01-15' => 1.0821, '2024-01-16' => 1.0834]);
});

it('null-fills dates not present in the API response (weekends/holidays)', function () {
    Http::fake([
        'fake-frankfurter.test/*' => Http::response([
            'rates' => ['2024-01-15' => ['EUR' => 1.0821]],
        ]),
    ]);

    $rates = makeFrankfurterService()->getHistoricalRates('EUR', ['2024-01-15', '2024-01-14']);

    expect($rates['2024-01-14'])->toBeNull();
});

it('returns null on a server error (transient failure)', function () {
    Http::fake([
        'fake-frankfurter.test/*' => Http::response([], 500),
    ]);

    $result = makeFrankfurterService()->getHistoricalRates('EUR', ['2024-01-15']);

    expect($result)->toBeNull();
});

it('returns null on 404 (unsupported currency)', function () {
    Http::fake([
        'fake-frankfurter.test/*' => Http::response([], 404),
    ]);

    $result = makeFrankfurterService()->getHistoricalRates('XYZ', ['2024-01-15']);

    expect($result)->toBeNull();
});

it('returns empty array when no dates are provided', function () {
    $result = makeFrankfurterService()->getHistoricalRates('EUR', []);

    expect($result)->toBe([]);
    Http::assertNothingSent();
});

// ── getSupportedCurrencies ────────────────────────────────────────────────────

it('returns an array of currency codes', function () {
    Http::fake([
        'fake-frankfurter.test/currencies' => Http::response(['EUR' => 'Euro', 'GBP' => 'British Pound']),
    ]);

    $codes = makeFrankfurterService()->getSupportedCurrencies();

    expect($codes)->toContain('EUR')->toContain('GBP');
});

it('returns empty array when /currencies call fails', function () {
    Http::fake([
        'fake-frankfurter.test/currencies' => Http::response([], 500),
    ]);

    expect(makeFrankfurterService()->getSupportedCurrencies())->toBe([]);
});

// ── supports ─────────────────────────────────────────────────────────────────

it('supports returns true for a known currency', function () {
    Http::fake([
        'fake-frankfurter.test/currencies' => Http::response(['EUR' => 'Euro']),
    ]);

    expect(makeFrankfurterService()->supports('EUR'))->toBeTrue();
});

it('supports returns false for an unknown currency', function () {
    Http::fake([
        'fake-frankfurter.test/currencies' => Http::response(['EUR' => 'Euro']),
    ]);

    expect(makeFrankfurterService()->supports('XYZ'))->toBeFalse();
});
