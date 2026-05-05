<?php

declare(strict_types=1);

use App\Enums\ReportRange;
use App\Enums\ReportStatus;
use App\Models\Currency;
use App\Models\Report;
use App\Models\ReportResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Unauthenticated ───────────────────────────────────────────────────────────

it('returns 401 for unauthenticated index request', function () {
    $this->getJson('/api/reports')->assertUnauthorized();
});

// ── GET /api/reports ──────────────────────────────────────────────────────────

it('index returns only the authenticated users reports', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    Report::factory()->for($user)->create();
    Report::factory()->for($other)->create();

    $response = $this->actingAs($user)->getJson('/api/reports')->assertOk();

    expect($response->json('reports'))->toHaveCount(1);
});

it('index returns reports in descending order', function () {
    $user = User::factory()->create();
    Report::factory()->for($user)->count(3)->create();

    $reports = $this->actingAs($user)
        ->getJson('/api/reports')
        ->assertOk()
        ->json('reports');

    $ids = array_column($reports, 'id');
    $sorted = $ids;
    rsort($sorted);
    expect($ids)->toBe($sorted);
});

// ── POST /api/reports ─────────────────────────────────────────────────────────

it('store creates a pending report and returns 201', function () {
    $user     = User::factory()->create();
    $currency = Currency::factory()->eur()->create();

    $this->actingAs($user)
        ->postJson('/api/reports', [
            'currency_id' => $currency->id,
            'range'       => ReportRange::OneMonth->value,
        ])
        ->assertCreated()
        ->assertJsonPath('report.status', ReportStatus::Pending->value);

    expect(Report::where('user_id', $user->id)->count())->toBe(1);
});

it('store sets the interval derived from the range', function () {
    $user     = User::factory()->create();
    $currency = Currency::factory()->eur()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/reports', [
            'currency_id' => $currency->id,
            'range'       => ReportRange::OneYear->value,
        ])
        ->assertCreated();

    expect($response->json('report.interval'))->toBe(ReportRange::OneYear->allowedInterval()->value);
});

it('store rejects an invalid range value', function () {
    $user     = User::factory()->create();
    $currency = Currency::factory()->eur()->create();

    $this->actingAs($user)
        ->postJson('/api/reports', [
            'currency_id' => $currency->id,
            'range'       => 'not_a_valid_range',
        ])
        ->assertUnprocessable();
});

it('store rejects a non-existent currency_id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/reports', [
            'currency_id' => 999999,
            'range'       => ReportRange::OneMonth->value,
        ])
        ->assertUnprocessable();
});

// ── GET /api/reports/{report} ─────────────────────────────────────────────────

it('show returns the report with a results array', function () {
    $user   = User::factory()->create();
    $report = Report::factory()->for($user)->completed()->create();
    ReportResult::insert([
        ['report_id' => $report->id, 'date' => '2024-01-01', 'rate' => 1.08],
        ['report_id' => $report->id, 'date' => '2024-02-01', 'rate' => 1.09],
    ]);

    $this->actingAs($user)
        ->getJson("/api/reports/{$report->id}")
        ->assertOk()
        ->assertJsonStructure(['report' => ['id', 'currency', 'results']]);
});

it('show returns 403 when accessing another users report', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $report = Report::factory()->for($other)->completed()->create();

    $this->actingAs($user)
        ->getJson("/api/reports/{$report->id}")
        ->assertForbidden();
});

// ── DELETE /api/reports/{report} ──────────────────────────────────────────────

it('destroy deletes the report and returns 204', function () {
    $user   = User::factory()->create();
    $report = Report::factory()->for($user)->create();

    $this->actingAs($user)
        ->deleteJson("/api/reports/{$report->id}")
        ->assertNoContent();

    expect(Report::find($report->id))->toBeNull();
});

it('destroy returns 403 when deleting another users report', function () {
    $user   = User::factory()->create();
    $other  = User::factory()->create();
    $report = Report::factory()->for($other)->create();

    $this->actingAs($user)
        ->deleteJson("/api/reports/{$report->id}")
        ->assertForbidden();
});
