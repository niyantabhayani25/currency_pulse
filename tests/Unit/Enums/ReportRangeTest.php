<?php

declare(strict_types=1);

use App\Enums\ReportInterval;
use App\Enums\ReportRange;

it('allows monthly interval for one year range', function () {
    expect(ReportRange::OneYear->allowedInterval())->toBe(ReportInterval::Monthly);
});

it('allows weekly interval for six months range', function () {
    expect(ReportRange::SixMonths->allowedInterval())->toBe(ReportInterval::Weekly);
});

it('allows daily interval for one month range', function () {
    expect(ReportRange::OneMonth->allowedInterval())->toBe(ReportInterval::Daily);
});

it('returns descriptive labels', function () {
    expect(ReportRange::OneYear->label())->toContain('Year')
        ->and(ReportRange::SixMonths->label())->toContain('Month')
        ->and(ReportRange::OneMonth->label())->toContain('Month');
});

it('returns 12 date points for one year', function () {
    expect(ReportRange::OneYear->datePoints())->toHaveCount(12);
});

it('returns 26 date points for six months', function () {
    expect(ReportRange::SixMonths->datePoints())->toHaveCount(26);
});

it('returns 30 date points for one month', function () {
    expect(ReportRange::OneMonth->datePoints())->toHaveCount(30);
});

it('returns date points in ascending order', function () {
    foreach (ReportRange::cases() as $range) {
        $points = $range->datePoints();
        $sorted = $points;
        sort($sorted);
        expect($points)->toBe($sorted);
    }
});

it('returns valid Y-m-d date strings', function () {
    foreach (ReportRange::cases() as $range) {
        foreach ($range->datePoints() as $date) {
            expect($date)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
            expect(\DateTime::createFromFormat('Y-m-d', $date))->not->toBeFalse();
        }
    }
});

it('returns all three ranges from rangePairs', function () {
    $pairs = ReportRange::rangePairs();
    expect($pairs)->toHaveCount(3);
});

it('rangePairs contain required keys', function () {
    foreach (ReportRange::rangePairs() as $pair) {
        expect($pair)->toHaveKeys(['range', 'interval', 'label']);
    }
});

it('rangePairs intervals match allowedInterval for each range', function () {
    foreach (ReportRange::rangePairs() as $pair) {
        $range = ReportRange::from($pair['range']);
        expect($pair['interval'])->toBe($range->allowedInterval()->value);
    }
});
