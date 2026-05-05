<?php

declare(strict_types=1);

use App\Exceptions\CurrencyProviderException;
use App\Services\CurrencyLayerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

function makeLayerService(): CurrencyLayerService
{
    return new CurrencyLayerService(
        apiKey:  'test-key',
        baseUrl: 'http://fake-layer.test',
    );
}

// ── getLiveRates ──────────────────────────────────────────────────────────────

it('normalises USDEUR prefix to bare currency code keys', function () {
    Http::fake([
        'fake-layer.test/live*' => Http::response([
            'success' => true,
            'quotes'  => ['USDEUR' => 1.0821, 'USDGBP' => 0.7934],
        ]),
    ]);

    $rates = makeLayerService()->getLiveRates(['EUR', 'GBP']);

    expect($rates)->toBe(['EUR' => 1.0821, 'GBP' => 0.7934]);
});

it('null-fills codes missing from the API response', function () {
    Http::fake([
        'fake-layer.test/live*' => Http::response([
            'success' => true,
            'quotes'  => ['USDEUR' => 1.0821],
        ]),
    ]);

    $rates = makeLayerService()->getLiveRates(['EUR', 'GBP']);

    expect($rates['GBP'])->toBeNull();
});

it('returns null on a server error (transient failure)', function () {
    Http::fake([
        'fake-layer.test/live*' => Http::response([], 500),
    ]);

    $result = makeLayerService()->getLiveRates(['EUR']);

    expect($result)->toBeNull();
});

it('throws CurrencyProviderException when success is false', function () {
    Http::fake([
        'fake-layer.test/live*' => Http::response([
            'success' => false,
            'error'   => ['code' => 104, 'info' => 'API key invalid.'],
        ]),
    ]);

    expect(fn () => makeLayerService()->getLiveRates(['EUR']))
        ->toThrow(CurrencyProviderException::class);
});

// ── getCurrencyList ───────────────────────────────────────────────────────────

it('returns the currencies array from the API', function () {
    Http::fake([
        'fake-layer.test/list*' => Http::response([
            'success'    => true,
            'currencies' => ['EUR' => 'Euro', 'GBP' => 'British Pound'],
        ]),
    ]);

    $list = makeLayerService()->getCurrencyList();

    expect($list)->toBe(['EUR' => 'Euro', 'GBP' => 'British Pound']);
});

it('caches the currency list so the second call makes no HTTP request', function () {
    Http::fake([
        'fake-layer.test/list*' => Http::response([
            'success'    => true,
            'currencies' => ['EUR' => 'Euro'],
        ]),
    ]);

    makeLayerService()->getCurrencyList();
    makeLayerService()->getCurrencyList();

    Http::assertSentCount(1);
});

// ── getCurrentRate ────────────────────────────────────────────────────────────

it('getCurrentRate returns the float for a single currency', function () {
    Http::fake([
        'fake-layer.test/live*' => Http::response([
            'success' => true,
            'quotes'  => ['USDEUR' => 1.0821],
        ]),
    ]);

    $rate = makeLayerService()->getCurrentRate('EUR');

    expect($rate)->toBe(1.0821);
});
