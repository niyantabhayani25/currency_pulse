<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * List the authenticated user's reports, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = $request->user()
            ->reports()
            ->with('currency:id,code,name')
            ->latest()
            ->get([
                'id', 'currency_id', 'range', 'interval',
                'status', 'data_source', 'created_at', 'completed_at',
            ])
            ->map(fn (Report $r) => [
                'id'           => $r->id,
                'currency'     => $r->currency,
                'range'        => $r->range->value,
                'range_label'  => $r->range_label,
                'interval'     => $r->interval->value,
                'status'       => $r->status->value,
                'data_source'  => $r->data_source,
                'created_at'   => $r->created_at,
                'completed_at' => $r->completed_at,
            ]);

        return response()->json(['reports' => $reports]);
    }

    /**
     * Queue a new report request.
     * Interval is derived from the range — clients only submit currency_id + range.
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $range  = \App\Enums\ReportRange::from($request->validated('range'));

        $report = $request->user()->reports()->create([
            'currency_id' => $request->validated('currency_id'),
            'range'       => $range,
            'interval'    => $range->allowedInterval(),
            'status'      => ReportStatus::Pending,
        ]);

        $report->load('currency:id,code,name');

        return response()->json([
            'report' => [
                'id'          => $report->id,
                'currency'    => $report->currency,
                'range'       => $report->range->value,
                'range_label' => $report->range_label,
                'interval'    => $report->interval->value,
                'status'      => $report->status->value,
                'created_at'  => $report->created_at,
            ],
        ], 201);
    }

    /**
     * Return a single report with its results (policy: owner only).
     */
    public function show(Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $report->load(['currency:id,code,name', 'results']);

        return response()->json([
            'report' => [
                'id'           => $report->id,
                'currency'     => $report->currency,
                'range'        => $report->range->value,
                'range_label'  => $report->range_label,
                'interval'     => $report->interval->value,
                'status'       => $report->status->value,
                'data_source'  => $report->data_source,
                'completed_at' => $report->completed_at,
                'results'      => $report->results->map(fn ($r) => [
                    'date' => $r->date->toDateString(),
                    'rate' => $r->rate,
                ]),
            ],
        ]);
    }

    /**
     * Delete a report (policy: owner only).
     */
    public function destroy(Report $report): JsonResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json(null, 204);
    }
}
