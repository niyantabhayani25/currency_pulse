<?php

declare(strict_types=1);

use App\Enums\ReportRange;
use App\Enums\ReportStatus;
use App\Jobs\GenerateReportJob;
use App\Models\Report;
use App\Models\ReportResult;
use App\Services\HistoricalRateResolver;
use App\Services\ResolveResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeResolver(array $rates, string $dataSource = 'frankfurter'): HistoricalRateResolver
{
    $result   = new ResolveResult($rates, $dataSource);
    $resolver = Mockery::mock(HistoricalRateResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($result);

    return $resolver;
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('marks report as completed after successful resolution', function () {
    $report    = Report::factory()->forRange(ReportRange::OneMonth)->create();
    $fakeRates = array_fill_keys(ReportRange::OneMonth->datePoints(), 1.08);

    (new GenerateReportJob($report))->handle(fakeResolver($fakeRates));

    expect($report->fresh()->status)->toBe(ReportStatus::Completed);
});

it('sets completed_at after successful resolution', function () {
    $report    = Report::factory()->forRange(ReportRange::OneMonth)->create();
    $fakeRates = array_fill_keys(ReportRange::OneMonth->datePoints(), 1.08);

    (new GenerateReportJob($report))->handle(fakeResolver($fakeRates));

    expect($report->fresh()->completed_at)->not->toBeNull();
});

it('persists all rate rows to report_results', function () {
    $report    = Report::factory()->forRange(ReportRange::OneMonth)->create();
    $dates     = ReportRange::OneMonth->datePoints();
    $fakeRates = array_fill_keys($dates, 0.95);

    (new GenerateReportJob($report))->handle(fakeResolver($fakeRates));

    expect(ReportResult::where('report_id', $report->id)->count())->toBe(count($dates));
});

it('stores the data_source on the report', function () {
    $report    = Report::factory()->forRange(ReportRange::OneMonth)->create();
    $fakeRates = array_fill_keys(ReportRange::OneMonth->datePoints(), 1.08);

    (new GenerateReportJob($report))->handle(fakeResolver($fakeRates, 'synthetic'));

    expect($report->fresh()->data_source)->toBe('synthetic');
});

// ── Guard: already processing ─────────────────────────────────────────────────

it('returns early without calling resolver when report is already processing', function () {
    $report   = Report::factory()->processing()->create();
    $resolver = Mockery::mock(HistoricalRateResolver::class);
    $resolver->shouldNotReceive('resolve');

    (new GenerateReportJob($report))->handle($resolver);

    expect($report->fresh()->status)->toBe(ReportStatus::Processing);
});

// ── Failure handling ──────────────────────────────────────────────────────────

it('marks report as failed and stores the error message', function () {
    $report = Report::factory()->create();

    (new GenerateReportJob($report))->failed(new RuntimeException('API down'));

    expect($report->fresh()->status)->toBe(ReportStatus::Failed)
        ->and($report->fresh()->error_message)->toBe('API down');
});
