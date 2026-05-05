<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportPageController extends Controller
{
    public function __invoke(Report $report): Response|RedirectResponse
    {
        $this->authorize('view', $report);

        if (! $report->isCompleted()) {
            return redirect()->route('dashboard')
                ->with('info', 'This report is not ready yet. Please check back shortly.');
        }

        return Inertia::render('Report/Show', [
            'reportId' => $report->id,
        ]);
    }
}
