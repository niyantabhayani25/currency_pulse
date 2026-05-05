<?php

declare(strict_types=1);

use App\Enums\ReportRange;
use App\Models\Currency;
use App\Models\Report;
use App\Services\FrankfurterService;
use App\Services\HistoricalRateResolver;
use App\Services\SyntheticRateService;
use Mockery\MockInterface;

function makeReport(string $rangeValue = 'one_year', string $currencyCode = 'EUR'): Report
{
    $currency = new Currency();
    $currency->forceFill(['code' => $currencyCode, 'name' => 'Test Currency']);

    $report = new Report();
    $report->forceFill(['range' => $rangeValue, 'interval' => 'monthly']);
    $report->setRelation('currency', $currency);

    return $report;
}

it('uses frankfurter data and sets dataSource to frankfurter', function () {
    $dates = ReportRange::OneYear->datePoints();
    $fakeRates = array_fill_keys($dates, 1.08);

    $frankfurter = Mockery::mock(FrankfurterService::class, function (MockInterface $m) use ($fakeRates) {
        $m->shouldReceive('getHistoricalRates')->once()->andReturn($fakeRates);
    });

    $synthetic = Mockery::mock(SyntheticRateService::class, function (MockInterface $m) {
        $m->shouldNotReceive('getRates');
    });

    $resolver = new HistoricalRateResolver($frankfurter, $synthetic);
    $result   = $resolver->resolve(makeReport());

    expect($result->dataSource)->toBe('frankfurter')
        ->and($result->rates)->toBe($fakeRates);
});

it('falls back to synthetic when frankfurter returns null', function () {
    $dates      = ReportRange::OneYear->datePoints();
    $fakeRates  = array_fill_keys($dates, 0.95);

    $frankfurter = Mockery::mock(FrankfurterService::class, function (MockInterface $m) {
        $m->shouldReceive('getHistoricalRates')->once()->andReturn(null);
    });

    $synthetic = Mockery::mock(SyntheticRateService::class, function (MockInterface $m) use ($fakeRates) {
        $m->shouldReceive('getRates')->once()->andReturn($fakeRates);
    });

    $resolver = new HistoricalRateResolver($frankfurter, $synthetic);
    $result   = $resolver->resolve(makeReport());

    expect($result->dataSource)->toBe('synthetic')
        ->and($result->rates)->toBe($fakeRates);
});

it('passes the correct currency code and dates to frankfurter', function () {
    $report = makeReport('one_month', 'JPY');
    $expectedDates = ReportRange::OneMonth->datePoints();

    $frankfurter = Mockery::mock(FrankfurterService::class, function (MockInterface $m) use ($expectedDates) {
        $m->shouldReceive('getHistoricalRates')
            ->once()
            ->with('JPY', $expectedDates)
            ->andReturn(array_fill_keys($expectedDates, 140.0));
    });

    $synthetic = Mockery::mock(SyntheticRateService::class);

    $resolver = new HistoricalRateResolver($frankfurter, $synthetic);
    $resolver->resolve($report);
});
